<?php namespace Uralmedias\Linker\Layout;

use ArrayObject;
use Uralmedias\Linker\Generic;
use Uralmedias\Linker\Layout\DataAggregator;
use Generator, ArrayIterator, DOMElement;


/**
 * **Оператор доступа к свойствам узлов**
 *
 * Позволяет изменять код разметки меняя имена и значения,
 * атрибуты элементов и другие характеристики узлов. Также
 * позволяет получать эти значения.
 *
 * Экземпляр возвращается методами `nodes` класса `LayoutFragment`,
 * и методами `before`, `after`, `up`, `down`, `into`, `to` класса
 * `NodeRelocator`.
 *
 * *Длительное хранение экземпляра может привести к неожиданным
 * последствиям из-за непостоянства ссылок в PHP DOM.*
 */
class NodeAggregator extends DataAggregator
{

    public function __toString(): string
    {
        $result = '';
        foreach ($this->GetNodes() as $n) {
            $result .= $n->ownerDocument->saveHtml($n);
        }

        return $result;
    }


    public function getIterator(): Generator
    {
        foreach ($this->GetNodes() as $n) {
            yield new NodeAggregator(new ArrayIterator([$n]));
        }
    }


    /**
     * **Inline-стили элемента**
     *
     * Возвращает преобразованное в ассоциативный массив значение
     * атрибута "style". $updates - принимает новое значение в том
     * же виде. Новое значение, если не равно NULL заменяет текущее.
     * Для возврата используется первый подходящий узел, для
     * установки - все узлы в выборке.
     *
     * *Парсер работает очень неаккуратно из-за сложности разбора этого
     * атрибута. Возможно, в будущем получится реализовать это лучше*
     */
    public function styles (array $updates = NULL): array
    {
        $parseStyle = function (DOMElement $e) {

            $rules = explode(';', $e->getAttribute('style') ?? '');
            $styles = [];

            foreach ($rules as $r) {
                $r = explode (':', $r);
                if (count($r) == 2) {
                    $styles[trim($r[0])] = trim($r[1]);
                }
            }

            return $styles;
        };

        // стили могут быть только у элементов
        if ($updates !== NULL) {
            foreach ($this->GetNodes() as $n) {
                if (is_a($n, DOMElement::class)) {

                    $currentStyle = $parseStyle($n);

                    foreach ($updates as $uName => $uValue) {
                        if ($uValue === NULL) {
                            unset($currentStyle[$uName]);
                        } else {
                            $currentStyle[$uName] = $uValue;
                        }
                    }

                    $style = '';
                    foreach ($currentStyle as $sName => $sValue) {
                        $style .= "$sName: $sValue;";
                    }
                    $n->setAttribute('style', $style);
                }
            }
        }

        // возврат значения
        foreach ($this->GetNodes() as $n) {
            if (is_a($n, DOMElement::class)) {
                return $parseStyle($n);
            }
        }

        return [];
    }


    /**
     * **CSS-классы элементов**
     *
     * Доступ к атрибуту class элемента. Возвращает массив CSS-классов.
     * Чтобы изменить значения, необходимо передать первым аргументом массив,
     * при этом поведение вариируется:
     * - По умолчанию: ключи соответствуют удаляемым классам, значения - добавляемым;
     * - `$doReplacing = TRUE`: ключи соответствуют шаблонам поиска, значения - подстановкам;
     * - `$assumeRE = TRUE`: то, что и предыдущий, но шаблоны поиска - регулярные выражения.
     *
     * *При использовании регулярных выражений можно использовать групировки и подстановки.*
     */
    public function classes ($updates = [], bool $remove = TRUE): array
    {
        $result =[];
        if (is_string($updates)) {
            $updates = preg_split('/\s+/', $updates);
        }

        foreach ($this->GetNodes(DOMElement::class) as $n) {

            $classes = preg_split('/\s+/', ($n->getAttribute('class') ?? ''));
            array_push($result, ...$classes);

            if ($updates === null) {
                if ($remove) {
                    $n->removeAttribute('class');
                } else {
                    $n->setAttribute('class', NULL);
                }
            } elseif (!empty($updates)) {
                foreach ($updates as $uKey => $uData) {

                    if (is_string($uKey)) {

                        $match = Generic::matcher($uKey);
                        foreach ($classes as &$cName) {
                            if ($match($cName)) {
                                $cName = Generic::value($cName, $uData);
                            }
                        }

                    } else {
                        $classes[] = strval($uData);
                    }
                }
            }

            $n->setAttribute('class', implode(' ', array_unique(array_filter($classes))));
        }

        return array_unique(array_filter($result));
    }


    /**
     *
     *
     */
    public function attributes (?array $updates = [], bool $removable = TRUE): array
    {
        $result = [];

        foreach ($this->GetNodes(DOMElement::class) as $n) {

            foreach ($n->attributes as $a) {

                $result[$a->name] = $result[$a->name] ?? [];
                $result[$a->name][] = $a;
            }

            if ($updates === NULL) {
                foreach ($n->attributes as $a) {
                    if ($removable) {
                        $n->removeAttribute($a->name);
                    } else {
                        $n->setAttribute($a->name, NULL);
                    }
                }
            } else {
                foreach ($updates as $uName => $uData) {

                    if (preg_match('/^[\w\s_-]+$/', $uName) and !$n->hasAttribute($uName)) {
                        $n->setAttribute($uName, NULL);
                    }

                    $match = Generic::matcher($uName);
                    $matched = [];
                    foreach ($n->attributes as $attr) {
                        if ($match($attr->name)) {
                            $matched[] = $attr;
                        }
                    }

                    $aggregator = new DataAggregator(new ArrayObject($matched));
                    $aggregator->value($uData, TRUE, $removable);
                }
            }
        }

        foreach ($result as $key => $items) {
            $result[$key] = new DataAggregator (new ArrayIterator($items));
        }
        return $result ?: [];
    }

}