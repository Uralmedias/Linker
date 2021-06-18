<?php namespace Uralmedias\Linker\Layout;


use Uralmedias\Linker\Layout;
use Uralmedias\Linker\Select;
use Uralmedias\Linker\Layout\NodeRegrouping;
use Uralmedias\Linker\Layout\NodeProperties;
use DOMDocument, DOMXPath, Traversable;


/**
 * Фрагмент шаблона разметки.
 */
class LayoutFragment
{

    private DOMDocument $document;
    private DOMXPath $xpath;


    public function __construct(DOMDocument $document)
    {
        $this->document = $document;
        $this->xpath = new DOMXPath($document);
    }


    public function __clone ()
    {
        $this->document = clone $this->document;
        $this->xpath = new DOMXPath($this->document);
    }


    public function __toString(): string
    {
        return html_entity_decode($this->document->saveHTML(), ENT_HTML5);
    }


    /**
     * Минимизирует, убирая лишние данные.
     */
    public function minimize (bool $comments = FALSE, bool $scripts = FALSE)
    {
        $q = $scripts ?
            '//text()[not(parent::pre)]':
            '//text()[not(parent::script) and not(parent::style) and not(parent::pre)]';

        $nodes = $this->query($q);
        foreach ($nodes as $n) {
            $n->textContent = preg_replace('/\s+/', ' ', $n->textContent);
        }

        if ($comments) {
            $nodes = $this->query('//comment()');
            foreach ($nodes as $n) {
                $n->parentNode->removeChild($n);
            }
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

        return Layout::fromNodes(...$nodes);
    }


    /**
     * Создаёт экземпляр, копируя часть существующего.
     */
    public function copy (...$selectors): self
    {
        return static::fromNodes($this->query(...$selectors));
    }


    public function move (...$selectors): NodeRegrouping
    {
        return $this->put($this->cut(...$selectors));
    }


    /**
     * Расширяет текущий экземпляр контентом другого.
     */
    public function put (self ...$LayoutFragments): NodeRegrouping
    {
        $nodes = [];
        foreach ($LayoutFragments as $f) {
            $f->query();
            foreach ($f->document->childNodes as $n) {
                $nodes[] = $n;
            }
        }

        return new NodeRegrouping ($this->xpath, ...$nodes);
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

        return new NodeRegrouping ($this->xpath, ...$nodes);
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

        return new NodeRegrouping ($this->xpath, ...$nodes);
    }


    /**
     * Позволяет читать и изменять атрибуты элементов внутри экземпляра.
     */
    public function nodes (...$selectors): NodeProperties
    {
        return new NodeProperties (...$this->query(...$selectors));
    }


    /**
     * Возвращает список ссылок на ресурсы из экземляра.
     */
    public function assets (array $updates = [], bool $assumeRE = FALSE): array
    {
        $walkAssets = function (string $xpath) use ($updates, $assumeRE) {

            $result = [];
            $nodes = $this->query($xpath);
            $search = array_keys($updates);
            $replacement = array_values($updates);

            foreach ($nodes as $n) {

                $n->value = $assumeRE ?
                    preg_replace ($search, $replacement, $n->value):
                    str_replace ($search, $replacement, $n->value);

                $result[] = $n->value;
            }

            return $result;
        };

        return array_unique(array_merge(
            $walkAssets('//@*[name() = "src"]'),
            $walkAssets('//@*[name() = "href"]'),
            $walkAssets('//@*[name() = "xlink:href"]')
        ));
    }


    private function query (...$selectors): Traversable
    {
        foreach ($selectors as $s) {
            foreach ($this->xpath->query(Select::auto($s)) as $node) {
                yield $node;
            }
        }
    }

}