<?php namespace Uralmedias\Linker;


use Uralmedias\Linker\Selector;
use DOMNode, DOMXPath;


/**
 * Класс позволяет выбирать места размещения перед вставкой узлов в DOM.
 *
 * Может использоваться самостоятельно вместе с PHP DOM, но спроектирован
 * как вспомогательный внутренний класс для Fragment для оборачивания
 * нативных методов DOM.
 *
 * **Избегайте длительного хранения экземпляров, т.к. ссылки на узлы могут портится.**
 */
class Injector
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
    public function before (...$selectors)
    {
        return $this->inject($selectors, function ($anchor, $source)
        {
            if ($parent = $anchor->parentNode) {
                $parent->insertBefore($source->cloneNode(true), $anchor);
            }
        });
    }


    /**
     * Вставляет контент сразу после селектора.
     */
    public function after (...$selectors)
    {
        return $this->inject($selectors, function ($anchor, $source)
        {
            if ($parent = $anchor->parentNode) {
                $parent->insertBefore($anchor, $parent->insertBefore($source->cloneNode(true), $anchor));
            }
        });
    }


    /**
     * Вставляет контент на место первого потомка селектора.
     */
    public function up (...$selectors)
    {
        return $this->inject($selectors, function ($anchor, $source)
        {
            if ($first = $anchor->firstChild) {
                $anchor->insertBefore($source->cloneNode(true), $first);
            } else {
                $anchor->appendChild($source->cloneNode(true));
            }
        });
    }


    /**
     * Вставляет контент на место последнего потомка селектора.
     */
    public function down (...$selectors)
    {
        return $this->inject($selectors, function ($anchor, $source)
        {
            $anchor->appendChild($source->cloneNode(true));
        });
    }


    /**
     * Заменяет контентом содержимое селектора.
     */
    public function into (...$selectors)
    {
        return $this->inject($selectors, function ($anchor, $source)
        {
            foreach ($anchor->childNodes as $c) {
                $anchor->removeChild($c);
            }
            $anchor->appendChild($source->cloneNode(true));
        });
    }


    /**
     * Заменяет селектор контентом.
     */
    public function to (...$selectors)
    {
        return $this->inject($selectors, function ($anchor, $source)
        {
            if ($parent = $anchor->parentNode) {
                $parent->insertBefore($source->cloneNode(true), $anchor);
                $parent->removeChild($anchor);
            }
        });
    }


    private function inject (array $selectors, callable $proc)
    {
        $this->fetch();
        foreach ($selectors as $s) {
            $list = $this->target->query(Selector::query($s));
            foreach ($list as $l) {
                foreach ($this->source as $s) {
                    $proc($l, $s);
                }
            }
        }
    }

}