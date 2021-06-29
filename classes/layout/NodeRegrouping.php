<?php namespace Uralmedias\Linker\Layout;


use Uralmedias\Linker\Select;
use Uralmedias\Linker\Layout\NodeProperties;
use DOMNode, DOMXPath, Generator;


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

    private DOMXpath $target;
    private array $nodes = [];


    public function __construct (DOMXPath $target, DOMNode ...$nodes)
    {
        $this->target = $target;

        $document = $target->document;
        foreach ($nodes as $n) {
            if ($n->ownerDocument->isSameNode($document)) {
                array_push($this->nodes, $n->cloneNode(TRUE));
            } else {
                array_push($this->nodes, $document->importNode($n, TRUE));
            }
        }
    }


    /**
     * Вставить копию перед каждым узлом, выбранным с помощью $selectors
     */
    public function before (...$selectors): NodeProperties
    {
        $nodes = [];
        foreach ($this->anchors(...$selectors) as $a) {
            foreach ($this->nodes as $n) {

                $node = $a->parentNode->insertBefore($n->cloneNode(TRUE), $a);
                array_push($nodes, $node);
            }
        }

        return new NodeProperties(...$nodes);
    }


    /**
     * Вставить копию после каждого узла, выбранного с помощью $selectors
     */
    public function after (...$selectors): NodeProperties
    {
        $nodes = [];
        foreach ($this->anchors(...$selectors) as $a) {

            $first = NULL;
            foreach ($this->nodes as $n) {

                $node = $a->parentNode->insertBefore($n->cloneNode(TRUE), $a);
                array_push($nodes, $node);
                $first = $first ?? $node;
            }

            if ($first) {
                $first->parentNode->insertBefore($a, $first);
            }
        }

        return new NodeProperties(...$nodes);
    }


    /**
     * Вставить копию в начало каждого узла, выбранного с помощью $selectors
     */
    public function up (...$selectors): NodeProperties
    {
        $nodes = [];
        foreach ($this->anchors(...$selectors) as $a) {

            $first = $a->firstChild;
            foreach ($this->nodes as $n) {

                $node = $first ?
                    $a->insertBefore($n->cloneNode(TRUE), $first):
                    $a->appenChild($n->cloneNode(TRUE));

                array_push($nodes, $node);
            }
        }

        return new NodeProperties(...$nodes);
    }


    /**
     * Вставить копию в конец каждого узла, выбранного с помощью $selectors
     */
    public function down (...$selectors): NodeProperties
    {
        $nodes = [];
        foreach ($this->anchors(...$selectors) as $a) {
            foreach ($this->nodes as $n) {

                $node = $a->appendChild($n->cloneNode(TRUE));
                array_push($nodes, $node);
            }
        }

        return new NodeProperties(...$nodes);
    }


    /**
     * Заменить копией содержимое узлов, выбранных с помощью $selectors
     */
    public function into (...$selectors): NodeProperties
    {
        $nodes = [];
        foreach ($this->anchors(...$selectors) as $a) {

            while ($a->hasChildNodes()) {
                $a->removeChild($a->firstChild);
            }
            foreach ($this->nodes as $n) {

                $node = $a->appendChild($n->cloneNode(TRUE));
                array_push($nodes, $node);
            }
        }

        return new NodeProperties(...$nodes);
    }


    /**
     * Заменить копией узлы, выбранные с помощью $selectors
     */
    public function to (...$selectors): NodeProperties
    {
        $nodes = [];
        foreach ($this->anchors(...$selectors) as $a) {
            foreach ($this->nodes as $n) {

                $node = $a->parentNode->insertBefore($n->cloneNode(TRUE), $a);
                array_push($nodes, $node);
            }

            $a->parentNode->removeChild($a);
        }

        return new NodeProperties(...$nodes);
    }


    private function anchors (...$selectors): Generator
    {
        foreach ($selectors as $p) {
            foreach ($this->target->query(Select::auto($p)) as $node) {
                yield $node;
            }
        }
    }

}