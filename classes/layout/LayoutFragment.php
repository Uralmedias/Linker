<?php namespace Uralmedias\Linker\Layout;


use Uralmedias\Linker\Layout;
use Uralmedias\Linker\Select;
use Uralmedias\Linker\Layout\NodeRegrouping;
use Uralmedias\Linker\Layout\NodeProperties;
use DOMDocument, DOMXPath, Traversable;


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
        return html_entity_decode($this->document->saveHTML(), ENT_HTML5);
    }


    /**
     * Минимизирует, убирая лишние данные.
     */
    public function minimize (bool $comments = FALSE, bool $scripts = FALSE): void
    {
        $q = $scripts ?
            '//text()[not(parent::pre)]':
            '//text()[not(parent::script) and not(parent::style) and not(parent::pre)]';

        foreach ($this->query($q) as $node) {
            $node->textContent = preg_replace('/\s+/', ' ', $node->textContent);
        }

        if ($comments) {
            foreach ($this->query('//comment()') as $node) {
                $node->parentNode->removeChild($node);
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
        return Layout::fromNodes(...$this->query(...$selectors));
    }


    public function move (...$selectors): NodeRegrouping
    {
        return $this->put($this->cut(...$selectors));
    }


    /**
     * Расширяет текущий экземпляр контентом другого.
     */
    public function put (self ...$fragments): NodeRegrouping
    {
        $nodes = [];
        foreach ($fragments as $f) {
            foreach ($f->document->childNodes as $node) {
                $nodes[] = $node;
            }
        }

        return new NodeRegrouping ($this->document, ...$nodes);
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

        return new NodeRegrouping ($this->document, ...$nodes);
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

        return new NodeRegrouping ($this->document, ...$nodes);
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
            $search = array_keys($updates);
            $replacement = array_values($updates);

            foreach ($this->query($xpath) as $node) {

                $node->value = $assumeRE ?
                    preg_replace ($search, $replacement, $node->value):
                    str_replace ($search, $replacement, $node->value);

                $result[] = $node->value;
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
        $nodes = $this->query(...$selectors);

        $result = [];
        while ($nodeX = array_shift($nodes) and ($nodeY = array_pop($nodes))) {

            $parentX = $nodeX->parentNode;
            $parentY = $nodeY->parentNode;
            $nodeZ = $nodeY->nextSibling;

            $result[] = $parentX->insertBefore($nodeY, $nodeX);
            $result[] = $parentY->insertBefore($nodeX, $nodeZ);
        }

        return new NodeProperties (...$result);
    }


    /**
     * Меняет узлы между собой в случайном порядке.
     */
    public function randomize (...$selectors): NodeProperties
    {
        $nodes = $this->query(...$selectors);
        shuffle($nodes);

        $result = [];
        while ($nodeX = array_shift($nodes) and ($nodeY = array_shift($nodes))) {

            $parentX = $nodeX->parentNode;
            $parentY = $nodeY->parentNode;
            $nodeZ = $nodeY->nextSibling;

            $result[] = $parentX->insertBefore($nodeY, $nodeX);
            $result[] = $parentY->insertBefore($nodeX, $nodeZ);
        }

        return new NodeProperties (...$result);
    }


    /**
     * Пересчитывает узлы, выбранные селекторами
     */
    public function count (...$selectors): int
    {
        return count($this->query(...$selectors));
    }


    private function query (...$selectors): array
    {
        $result = [];
        foreach ($selectors as $s) {
            foreach ($this->xpath->query(Select::auto($s)) as $node) {
                array_push($result, $node);
            }
        }
        return $result;
    }

}