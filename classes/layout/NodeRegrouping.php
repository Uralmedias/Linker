<?php namespace Uralmedias\Linker\Layout;


use Uralmedias\Linker\Select;
use Uralmedias\Linker\Layout\NodeProperties;
use DOMNode, DOMXPath;


/**
 * Класс позволяет выбирать места размещения перед вставкой узлов в DOM.
 *
 * Может использоваться самостоятельно вместе с PHP DOM, но спроектирован
 * как вспомогательный внутренний класс для LayoutFragment для оборачивания
 * нативных методов DOM.
 *
 * **Избегайте длительного хранения экземпляров, т.к. ссылки на узлы могут портится.**
 */
class NodeRegrouping
{

    public static bool $lazyImport = TRUE;
    public static bool $asumeStatic = TRUE;

    private DOMXpath $target;
    private array $source = [];
    private bool $imported = FALSE;


    public function __construct(DOMXPath $target, DOMNode ...$source)
    {
        $this->target = $target;
        $this->source = array_filter(array_values($source));

        if (!static::$lazyImport) {
            $this->touch();
        }
    }


    /**
     * Импортирует узлы из документа-источника в приемник.
     */
    public function fetch ()
    {
        if (!$this->imported) {
            $doc = $this->target->document;
            foreach ($this->source as &$s) {
                $s = $doc->importNode($s, true);
            }
            $this->imported = static::$asumeStatic;
        }
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
        $this->fetch();

        $nodes = [];
        foreach ($selectors as $s) {
            $list = $this->target->query(Select::auto($s));
            foreach ($list as $l) {
                foreach ($this->source as $s) {
                    $nodes[] = $proc($l, $s);
                }
            }
        }

        return new NodeProperties (...$nodes);
    }

}