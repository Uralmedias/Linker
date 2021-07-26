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

    private array $imports = [];


    public function __construct (array $targets, array $sources)
    {
        foreach ($targets as $t) {

            $items = [];
            foreach ($sources as $s) {
                if (is_a($s, DOMNode::class)) {
                    if ($s->ownerDocument->isSameNode($t->document)) {
                        array_push($items, $s->cloneNode(TRUE));
                    } else {
                        array_push($items, $t->document->importNode($s, TRUE));
                    }
                }
            }

            array_push($this->imports, [$t, $items]);
        }
    }


    private function targets (...$selectors): Generator
    {
        foreach ($selectors as $s) {

            $xpath = Select::auto($s);
            foreach ($this->imports as $i) {
                foreach ($i[0]->query($xpath) as $anchor) {

                    yield (object) [
                        'anchor' => $anchor,
                        'items' => $i[1]
                    ];
                }
            }
        }
    }


    /**
     * Вставить копию перед каждым узлом, выбранным с помощью $selectors
     */
    public function before (...$selectors): NodeProperties
    {
        $nodes = [];
        foreach ($this->targets(...$selectors) as $target) {

            $a = $target->anchor;
            foreach ($target->items as $i) {

                $node = $a->parentNode->insertBefore($i->cloneNode(true), $a);
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
        foreach ($this->targets(...$selectors) as $target) {

            $a = $target->anchor;
            $first = null;

            foreach ($target->items as $i) {
                $node = $a->parentNode->insertBefore($i->cloneNode(true), $a);
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
        foreach ($this->targets(...$selectors) as $target) {

            $a = $target->anchor;
            $first = $a->firstChild;

            foreach ($target->items as $i) {

                $node = $first ?
                    $a->insertBefore($i->cloneNode(TRUE), $first):
                    $a->appenChild($i->cloneNode(TRUE));

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
        foreach ($this->targets(...$selectors) as $target) {

            $a = $target->anchor;
            foreach ($target->items as $i) {

                $node = $a->appendChild($i->cloneNode(true));
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
        foreach ($this->targets(...$selectors) as $target) {

            $a = $target->anchor;

            while ($a->hasChildNodes()) {
                $a->removeChild($a->firstChild);
            }

            foreach ($target->items as $i) {
                $node = $a->appendChild($i->cloneNode(true));
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
        foreach ($this->targets(...$selectors) as $target) {

            $a = $target->anchor;

            foreach ($target->items as $i) {
                $node = $a->parentNode->insertBefore($i->cloneNode(TRUE), $a);
                array_push($nodes, $node);
            }

            $a->parentNode->removeChild($a);
        }

        return new NodeProperties(...$nodes);
    }

}