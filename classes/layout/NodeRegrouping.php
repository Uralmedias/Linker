<?php namespace Uralmedias\Linker\Layout;


use Uralmedias\Linker\Select;
use Uralmedias\Linker\Layout\NodeProperties;
use DOMNode, DOMXPath;


/**
 * **Интерфейс для перемещения узлов**.
 * Создаётся в процессе редактирования структуры как завершающий этап
 * вставки. Позволяет выбрать место размещения новых узлов. Длительное
 * хранение экземпляров не желательно, так как используемые ссылки на
 * узлы могут портится как самим экземпляром, так и внешними факторами.
 */
class NodeRegrouping
{

    private DOMXpath $xpath;
    private DOMDocument $document;


    public function __construct(DOMDocument $document, DOMNode ...$source)
    {
        $this->document = $document;
        $this->xpath = new DOMXPath();
    }


    /**
     * Вставляет контент сразу перед селектором.
     */
    public function before (...$selectors): NodeProperties
    {
        return $this->inject($selectors, function ($anchor, $source)
        {
            return $anchor->parentNode->insertBefore($source->cloneNode(true), $anchor);
        });
    }


    /**
     * Вставляет контент сразу после селектора.
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
     * Вставляет контент на место первого потомка селектора.
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
     * Вставляет контент на место последнего потомка селектора.
     */
    public function down (...$selectors): NodeProperties
    {
        return $this->inject($selectors, function ($anchor, $source)
        {
            return $anchor->appendChild($source->cloneNode(true));
        });
    }


    /**
     * Заменяет контентом содержимое селектора.
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
     * Заменяет селектор контентом.
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