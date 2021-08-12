<?php namespace Uralmedias\Linker\Layout;


use Uralmedias\Linker\Select;
use Uralmedias\Linker\Layout\NodeAggregator;
use ArrayIterator, DOMNode, DOMXPath, Generator;


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

                if ($s->ownerDocument->isSameNode($t->document)) {
                    $stash = $s->cloneNode(TRUE);
                } else {
                    $stash = $t->document->importNode($s, TRUE);
                }

				if (is_a($stash, DOMNode::class)) {
					array_push($items, $stash);
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
    public function before (...$selectors): NodeAggregator
    {
        $nodes = [];
        foreach ($this->targets(...$selectors) as $target) {

            $a = $target->anchor;
            foreach ($target->items as $i) {

				if (!$i) continue;
                $node = $a->parentNode->insertBefore($i->cloneNode(true), $a);
                array_push($nodes, $node);
            }
        }

        return new NodeAggregator(new ArrayIterator($nodes));
    }


    /**
     * Вставить копию после каждого узла, выбранного с помощью $selectors
     */
    public function after (...$selectors): NodeAggregator
    {
        $nodes = [];
        foreach ($this->targets(...$selectors) as $target) {

            $a = $target->anchor;
            $first = null;

            foreach ($target->items as $i) {

				//if (!$i) continue;
                $node = $a->parentNode->insertBefore($i->cloneNode(true), $a);
                array_push($nodes, $node);
                $first = $first ?? $node;
            }

            if ($first) {
                $first->parentNode->insertBefore($a, $first);
            }
        }

        return new NodeAggregator(new ArrayIterator($nodes));
    }


    /**
     * Вставить копию в начало каждого узла, выбранного с помощью $selectors
     */
    public function up (...$selectors): NodeAggregator
    {
        $nodes = [];
        foreach ($this->targets(...$selectors) as $target) {

            $a = $target->anchor;
            $first = $a->firstChild;

            foreach ($target->items as $i) {

				if (!$i) continue;
                $node = $first ?
                    $a->insertBefore($i->cloneNode(TRUE), $first):
                    $a->appenChild($i->cloneNode(TRUE));

                array_push($nodes, $node);
            }
        }

        return new NodeAggregator(new ArrayIterator($nodes));
    }


    /**
     * Вставить копию в конец каждого узла, выбранного с помощью $selectors
     */
    public function down (...$selectors): NodeAggregator
    {
        $nodes = [];
        foreach ($this->targets(...$selectors) as $target) {

            $a = $target->anchor;
            foreach ($target->items as $i) {

				if (!$i) continue;
                $node = $a->appendChild($i->cloneNode(true));
                array_push($nodes, $node);
            }
        }

        return new NodeAggregator(new ArrayIterator($nodes));
    }


    /**
     * Заменить копией содержимое узлов, выбранных с помощью $selectors
     */
    public function into (...$selectors): NodeAggregator
    {
        $nodes = [];
        foreach ($this->targets(...$selectors) as $target) {

            $a = $target->anchor;

            while ($a->hasChildNodes()) {
                $a->removeChild($a->firstChild);
            }

            foreach ($target->items as $i) {

				if (!$i) continue;
                $node = $a->appendChild($i->cloneNode(true));
                array_push($nodes, $node);
            }
        }

        return new NodeAggregator(new ArrayIterator($nodes));
    }


    /**
     * Заменить копией узлы, выбранные с помощью $selectors
     */
    public function to (...$selectors): NodeAggregator
    {
        $nodes = [];
        foreach ($this->targets(...$selectors) as $target) {

            $a = $target->anchor;

            foreach ($target->items as $i) {

				if (!$i) continue;
                $node = $a->parentNode->insertBefore($i->cloneNode(TRUE), $a);
                array_push($nodes, $node);
            }

            $a->parentNode->removeChild($a);
        }

        return new NodeAggregator(new ArrayIterator($nodes));
    }

}