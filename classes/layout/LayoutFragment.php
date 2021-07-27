<?php namespace Uralmedias\Linker\Layout;


use Uralmedias\Linker\Layout;
use Uralmedias\Linker\Select;
use Uralmedias\Linker\Layout\NodeRegrouping;
use Uralmedias\Linker\Layout\NodeProperties;
use WeakReference, Generator, DOMDocument, DOMXPath;


/**
 * **Фрагмент кода разметки**
 *
 * Хранит структуру файла или участка кода разметки, которую можно
 * использовать для извлечения значений и формирования вывода.
 *
 * Экземпляр возвращается методами `fromNodes`, `fromDocument`,
 * `fromHTML`, `fromFile`, `fromOutput` фасада `Layout`.
 */
class LayoutFragment
{

    private DOMDocument $document;
    private DOMXPath $xpath;
    private array $queryCache = [];
    private ?string $stringCache = NULL;


    /**
     * Использует копию экземпляра DOMDocument.
     */
    public function __construct (DOMDocument $document)
    {
        $this->document = clone $document;
        $this->xpath = new DOMXPath($this->document);
    }


    /**
     * Копирует данные вместе с экземпляром.
     */
    public function __clone ()
    {
        $this->document = clone $this->document;
        $this->xpath = new DOMXPath($this->document);
    }


    /**
     * Выводит контент.
     */
    public function __toString(): string
    {
        if ($this->stringCache === NULL) {
            $this->stringCache = html_entity_decode($this->document->saveHTML(), ENT_HTML5);
        }

        return $this->stringCache;
    }


    /**
     * Минимизирует, убирая лишние данные.
     */
    public function minimize (bool $comments = FALSE, bool $scripts = FALSE): void
    {
        $q = $scripts ?
            '//text()[not(parent::pre)]':
            '//text()[not(parent::script) and not(parent::style) and not(parent::pre)]';

        $clean = FALSE;

        foreach ($this->query($q) as $node) {
            $node->textContent = preg_replace('/\s+/', ' ', $node->textContent);
            $clean = TRUE;
        }

        if ($comments) {
            foreach ($this->query('//comment()') as $node) {
                $node->parentNode->removeChild($node);
                $clean = TRUE;
            }
        }

        if ($clean) {
			 $this->clean();
		}
    }


    // TODO: Написать документацию
    // TODO: Написать тест
    public function split (...$selectors): Generator
    {
        foreach ($this->query(...$selectors) as $node) {
            yield Layout::fromNodes($node);
        }
    }


    /**
     * Создаёт новый экземпляр, вырезая часть существующего.
     */
    public function cut (...$selectors): self
    {
        $nodes = [];
        foreach ($this->query(...$selectors) as $node) {

            $nodes[] = $node;
            $node->parentNode->removeChild($node);
        }

        if (!empty($nodes)) {
			 $this->clean();
		}

        return Layout::fromNodes(...$nodes);
    }


    /**
     * Создаёт экземпляр, копируя часть существующего.
     */
    public function copy (...$selectors): self
    {
        return Layout::fromNodes(...$this->query(...$selectors));
    }


    public function move (...$selectors): NodeRegrouping
    {
        return $this->pull($this->cut(...$selectors));
    }


    /**
     * Расширяет текущий экземпляр контентом другого.
     */
    public function pull (self ...$sources): NodeRegrouping
    {
        $nodes = [];
        foreach ($sources as $s) {
            foreach ($s->document->childNodes as $node) {
                array_push($nodes, $node);
            }
        }

        if (!empty($nodes)) {
			 $this->clean();
		}

        return new NodeRegrouping ([$this->xpath], $nodes);
    }


    public function push (self ...$targets): NodeRegrouping
    {
        $xpaths = [];
        foreach ($targets as $t) {
            array_push($xpaths, $t->xpath);
        }

        return new NodeRegrouping ($xpaths, [...$this->document->childNodes]);
    }


    /**
     * Втавляет текстовый узел.
     */
    public function write (string ...$strings): NodeRegrouping
    {
        $nodes = [];
        foreach ($strings as $s) {
            $nodes[] = $this->document->createTextNode($s);
        }

        if (!empty($nodes)) {
			 $this->clean();
		}

        return new NodeRegrouping ([$this->xpath], $nodes);
    }


    /**
     * Вставляет коментарий.
     */
    public function annotate (string ...$comments): NodeRegrouping
    {
        $nodes = [];
        foreach ($comments as $c) {
            $nodes[] = $this->document->createComment($c);
        }

        if (!empty($nodes)) {
			 $this->clean();
		}

        return new NodeRegrouping ([$this->xpath], $nodes);
    }


    /**
     * Позволяет читать и изменять атрибуты элементов внутри экземпляра.
     */
    public function nodes (...$selectors): NodeProperties
    {
        $nodes = [...$this->query(...$selectors)];

        if (!empty($nodes)) {
			 $this->clean();
		}

        return new NodeProperties (...$nodes);
    }


    /**
     * Возвращает список ссылок на ресурсы из экземляра.
     */
    public function assets (array $updates = [], bool $assumeRE = FALSE): array
    {
        $walkAssets = function (string $xpath) use ($updates, $assumeRE) {

            $result = [];
            $search = array_keys($updates);
            $replacement = array_values($updates);

            $nodes = $this->query($xpath);

            foreach ($nodes as $n) {

                $n->value = $assumeRE ?
                    preg_replace ($search, $replacement, $n->value):
                    str_replace ($search, $replacement, $n->value);

                array_push($result, $n->value);
            }

		    if (!empty($nodes)) {
				 $this->clean();
			}

            return $result;
        };

        return array_unique(array_merge(
            $walkAssets('//@*[name() = "src"]'),
            $walkAssets('//@*[name() = "href"]'),
            $walkAssets('//@*[name() = "xlink:href"]')
        ));
    }


    /**
     * Переворачивает порядок следования узлов.
     */
    public function reverse (...$selectors): NodeProperties
    {
        $nodes = [...$this->query(...$selectors)];

        $result = [];
        while ($nodeX = array_shift($nodes) and ($nodeY = array_pop($nodes))) {

            $parentX = $nodeX->parentNode;
            $parentY = $nodeY->parentNode;
            $nodeZ = $nodeY->nextSibling;

            $result[] = $parentX->insertBefore($nodeY, $nodeX);
            $result[] = $parentY->insertBefore($nodeX, $nodeZ);
        }

        if (!empty($nodes)) {
			 $this->clean();
		}

        return new NodeProperties (...$result);
    }


    /**
     * Меняет узлы между собой в случайном порядке.
     */
    public function randomize (...$selectors): NodeProperties
    {
        $nodes = [...$this->query(...$selectors)];
        shuffle($nodes);

        $result = [];
        while ($nodeX = array_shift($nodes) and ($nodeY = array_shift($nodes))) {

            $parentX = $nodeX->parentNode;
            $parentY = $nodeY->parentNode;
            $nodeZ = $nodeY->nextSibling;

            $result[] = $parentX->insertBefore($nodeY, $nodeX);
            $result[] = $parentY->insertBefore($nodeX, $nodeZ);
        }

        $this->clean();
        return new NodeProperties (...$result);
    }


    /**
     * Пересчитывает узлы, выбранные селекторами
     */
    public function count (...$selectors): int
    {
        $result = 0;
        foreach ($selectors as $s) {
            if ($list = $this->xpath->query(Select::auto($s))) {
                $result += $list->length;
            }
        }
        return $result;
    }


    private function clean (): void
    {
        $this->queryCache = [];
        $this->stringCache = NULL;
    }


    private function query (...$selectors): Generator
    {
        foreach ($selectors as $s) {

            $request = Select::auto($s);
            if (array_key_exists($request, $this->queryCache)) {

                foreach ($this->queryCache[$request] as $node) {
                    yield $node;
                }

            } else {

                $cacheItems = [];
                foreach ($this->xpath->query($request) as $node) {
                    array_push($cacheItems, $node);
                    yield $node;
                }
                $this->queryCache[$request] = $cacheItems;
            }
        }
    }

}