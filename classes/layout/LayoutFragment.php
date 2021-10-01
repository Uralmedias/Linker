<?php namespace Uralmedias\Linker\Layout;


use Uralmedias\Linker\Layout;
use Uralmedias\Linker\Generic;
use Uralmedias\Linker\Layout\NodeRelocator;
use Uralmedias\Linker\Layout\NodeAggregator;
use Generator, DOMDocument, DOMXPath, DOMNode;

/**
 * **Фрагмент кода разметки**
 *
 * Хранит структуру файла или участка кода разметки, которую можно
 * использовать для извлечения значений и формирования вывода.
 *
 * Экземпляр возвращается методами `fromNodes`, `fromDocument`,
 * `fromHTML`, `fromFile`, `fromOutput` фасада `Layout`.
 */
class LayoutFragment extends NodeAggregator
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
        $this->document = $document;
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
            $this->stringCache = html_entity_decode($this->document->saveHTML());
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

        foreach ($this->QueryNodes($q) as $node) {
            $node->textContent = preg_replace('/\s+/', ' ', $node->textContent);
            $clean = TRUE;
        }

        if ($comments) {
            foreach ($this->QueryNodes('//comment()') as $node) {
                $node->parentNode->removeChild($node);
                $clean = TRUE;
            }
        }

        if ($clean) {
			$this->ClearCache();
		}
    }


    /**
     * Создаёт новый экземпляр, вырезая часть существующего.
     */
    public function cut (...$selectors): self
    {
        $nodes = [];
        foreach ($this->QueryNodes(...$selectors) as $node) {

            $nodes[] = $node;
            $node->parentNode->removeChild($node);
        }

        if (!empty($nodes)) {
			$this->ClearCache();
		}

        return Layout::fromNodes(...$nodes);
    }


    /**
     * Создаёт экземпляр, копируя часть существующего.
     */
    public function copy (...$selectors): self
    {
        return Layout::fromNodes(...$this->QueryNodes(...$selectors));
    }


    public function move (...$selectors): NodeRelocator
    {
        $nodes = [];
        foreach ($this->QueryNodes(...$selectors) as $node) {

            $nodes[] = $node;
            $node->parentNode->removeChild($node);
        }

        if (!empty($nodes)) {
			$this->ClearCache();
		}

        return new NodeRelocator ([$this->xpath], $nodes);
    }


    /**
     * Расширяет текущий экземпляр контентом другого.
     */
    public function pull (...$sources): NodeRelocator
    {
        $nodes = [];
        foreach ($sources as $s) {

            if (is_callable($s)) {
                $s = Layout::fromOutput($s);
            } elseif (is_a($s, DOMNode::class)) {
                $s = Layout::fromNodes($s);
            } elseif (is_a($s, DOMDocument::class)) {
                $s = Layout::fromDocument($s);
            } elseif (!is_a($s, NodeAggregator::class)) {
                $s = Layout::fromHTML(strval($s));
            }

            foreach ($s->GetNodes() as $node) {
                array_push($nodes, $node);
            }
        }

        if (!empty($nodes)) {
			$this->ClearCache();
		}

        return new NodeRelocator ([$this->xpath], $nodes);
    }


    public function push (...$targets): NodeRelocator
    {
        $xpaths = [];
        foreach ($targets as $t) {
            if (is_a($t, self::class)) {
                array_push($xpaths, $t->xpath);
            } elseif (is_a($t, DOMXpath::class)) {
                array_push($xpaths, $t);
            } elseif (is_a($t, DOMDocument::class)) {
                array_push($xpaths, new DOMXPath($t));
            }
        }

        return new NodeRelocator ($xpaths, $this->GetNodes());
    }


    /**
     * Втавляет текстовый узел.
     */
    public function write (...$strings): NodeRelocator
    {
        $nodes = [];
        foreach ($strings as $s) {
            $s = strval($s) ?: '';
            $nodes[] = $this->document->createTextNode($s);
        }

        if (!empty($nodes)) {
			$this->ClearCache();
		}

        return new NodeRelocator ([$this->xpath], $nodes);
    }


    /**
     * Вставляет коментарий.
     */
    public function annotate (...$comments): NodeRelocator
    {
        $nodes = [];
        foreach ($comments as $c) {
            $c = strval($c) ?: '';
            $nodes[] = $this->document->createComment($c);
        }

        if (!empty($nodes)) {
			$this->ClearCache();
		}

        return new NodeRelocator ([$this->xpath], $nodes);
    }


    /**
     * Позволяет читать и изменять атрибуты элементов внутри экземпляра.
     */
    public function nodes (...$selectors): NodeAggregator
    {
       $this->ClearCache();
        return new NodeAggregator (iterator_to_array($this->QueryNodes(...$selectors)));
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

            $nodes = $this->QueryNodes($xpath);

            foreach ($nodes as $n) {

                $n->value = $assumeRE ?
                    preg_replace ($search, $replacement, $n->value):
                    str_replace ($search, $replacement, $n->value);

                array_push($result, $n->value);
            }

		    if (!empty($nodes)) {
				$this->ClearCache();
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
    public function reverse (...$selectors): NodeAggregator
    {
        $nodes = [...$this->QueryNodes(...$selectors)];

        $result = [];
        while ($nodeX = array_shift($nodes) and ($nodeY = array_pop($nodes))) {

            $parentX = $nodeX->parentNode;
            $parentY = $nodeY->parentNode;
            $nodeZ = $nodeY->nextSibling;

            $result[] = $parentX->insertBefore($nodeY, $nodeX);
            $result[] = $parentY->insertBefore($nodeX, $nodeZ);
        }

        if (!empty($nodes)) {
			$this->ClearCache();
		}

        return new NodeAggregator ($result);
    }


    /**
     * Меняет узлы между собой в случайном порядке.
     */
    public function randomize (...$selectors): NodeAggregator
    {
        $nodes = [...$this->QueryNodes(...$selectors)];
        shuffle($nodes);

        $result = [];
        while ($nodeX = array_shift($nodes) and ($nodeY = array_shift($nodes))) {

            $parentX = $nodeX->parentNode;
            $parentY = $nodeY->parentNode;
            $nodeZ = $nodeY->nextSibling;

            $result[] = $parentX->insertBefore($nodeY, $nodeX);
            $result[] = $parentY->insertBefore($nodeX, $nodeZ);
        }

        $this->ClearCache();
        return new NodeAggregator ($result);
    }


    /**
     * Пересчитывает узлы, выбранные селекторами
     */
    public function count (...$selectors): int
    {
        if (empty($selectors)) {
            return $this->document->childNodes->length;
        }

        $result = 0;
        $request = Generic::select($selectors);
        if (!array_key_exists($request, $this->queryCache)) {
            $this->queryCache[$request] = $this->xpath->query($request);
        }
        $result += $this->queryCache[$request]->length;

        return $result;
    }


    protected function ClearCache (): void
    {
        $this->queryCache = [];
        $this->stringCache = NULL;
    }


    /**
     * Возвращает массив узлов DOM, на которые указвает текущий объект, и
     * каждый из которых принадлежит хотябы к одному из классов, перечисленных
     * в ```$classNames```. Если не указать ни одного класса, возвращаются сразу все узлы.
     */
    protected function GetNodes (string ...$classNames): array
    {
        return iterator_to_array($this->document->childNodes, FALSE);
    }


    protected function QueryNodes (...$selectors): Generator
    {
        if (empty($selectors)) {

            foreach ($this->document->childNodes as $node) {
                yield $node;
            }

        } else {

            $query = Generic::select(...$selectors);
            if (!array_key_exists($query, $this->queryCache)) {
                $this->queryCache[$query] = $this->xpath->query($query);
            }

            foreach ($this->queryCache[$query] as $node) {
                yield $node;
            }
        }
    }

}
