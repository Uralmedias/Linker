<?php namespace Uralmedias\Linker\Layout;


use Uralmedias\Linker\Select;
use Uralmedias\Linker\Layout\NodeProperties;
use DOMDocument, DOMNode, DOMXPath;


/**
 * **Оператор вставки узлов**.
 *
 * Позволяет расширять код разметки, вставляя в него новые
 * узлы. Методы позволяют выбрать конкретное место вставки.
 * - Опорные узлы (узлы целевого фрагмента) определяются
 *   во время вызова метода.
 * - Исходные узлы определяются в момент создания экземпляра
 *
 * Экземпляр возвращается методами `put`, `write`, `annotate`
 * класса `LayoutFragment`.
 *
 * *Длительное хранение экземпляра может привести к неожиданным
 * последствиям из-за непостоянства ссылок в PHP DOM.*
 */
class NodeRegrouping
{

    private DOMXpath $xpath;
    private DOMDocument $document;
    private array $source;


    public function __construct(DOMDocument $document, DOMNode ...$source)
    {
        $this->document = $document;
        $this->xpath = new DOMXPath($document);

        $this->source = [];
        foreach ($source as $s) {
            array_push($this->source, $document->importNode($s, TRUE));
        }
    }


    /**
     * Вставить копию перед каждым узлом, выбранным с помощью $selectors
     */
    public function before (...$selectors): NodeProperties
    {
        return $this->inject($selectors, function ($anchor, $source)
        {
            return $anchor->parentNode->insertBefore($source->cloneNode(true), $anchor);
        });
    }


    /**
     * Вставить копию после каждого узла, выбранного с помощью $selectors
     */
    public function after (...$selectors): NodeProperties
    {
        return $this->inject($selectors, function ($anchor, $source)
        {
            $source = $anchor->parentNode->insertBefore($source->cloneNode(true), $anchor);
            $anchor->parentNode->insertBefore($anchor, $source);
            return $source;
        });
    }


    /**
     * Вставить копию в начало каждого узла, выбранного с помощью $selectors
     */
    public function up (...$selectors): NodeProperties
    {
        return $this->inject($selectors, function ($anchor, $source)
        {
            if ($first = $anchor->firstChild) {
                return $anchor->insertBefore($source->cloneNode(true), $first);
            } else {
                return $anchor->appendChild($source->cloneNode(true));
            }
        });
    }


    /**
     * Вставить копию в конец каждого узла, выбранного с помощью $selectors
     */
    public function down (...$selectors): NodeProperties
    {
        return $this->inject($selectors, function ($anchor, $source)
        {
            return $anchor->appendChild($source->cloneNode(true));
        });
    }


    /**
     * Заменить копией содержимое узлов, выбранных с помощью $selectors
     */
    public function into (...$selectors): NodeProperties
    {
        return $this->inject($selectors, function ($anchor, $source)
        {
            foreach ($anchor->childNodes as $c) {
                $anchor->removeChild($c);
            }
            return $anchor->appendChild($source->cloneNode(true));
        });
    }


    /**
     * Заменить копией узлы, выбранные с помощью $selectors
     */
    public function to (...$selectors): NodeProperties
    {
        return $this->inject($selectors, function ($anchor, $source)
        {
            $source = $anchor->parentNode->insertBefore($source->cloneNode(true), $anchor);
            $anchor->parentNode->removeChild($anchor);
            return $source;
        });
    }


    private function inject (array $selectors, callable $proc)
    {
        $nodes = [];
        foreach ($selectors as $s) {
            $list = $this->xpath->query(Select::auto($s));
            foreach ($list as $l) {
                foreach ($this->source as $s) {
                    $nodes[] = $proc($l, $s);
                }
            }
        }

        return new NodeProperties (...$nodes);
    }

}