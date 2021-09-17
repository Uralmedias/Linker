<?php namespace Uralmedias\Linker\Layout;


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

                        $match = static::GetMatcher($uKey);
                        foreach ($classes as &$cName) {
                            if ($match($cName)) {
                                $cName = static::UpdateValue($cName, $uData);
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
     * **Атрибуты элементов**
     *
     * Доступ к атрибутам элементов. Возвращает атрибуты в виде массива ключ-значение,
     * принимает новые значения в таком же формате. При отсустствии ключа в аргументе,
     * значение атрибута остаётся не тронутым. Если значение равно `NULL`, атрибут удаляется,
     * кроме случаев, когда `$nullValues = TRUE`. В этом случае устанавливается `NULL`.
     */
    public function attributes (?array $updates = [], bool $remove = TRUE): array
    {
        $result = [];

        foreach ($this->GetNodes(DOMElement::class) as $n) {

            foreach ($n->attributes as $a) {

                $result[$a->name] = $result[$a->name] ?? [];
                $result[$a->name][] = $a;
            }

            if ($updates === NULL) {
                foreach ($n->attributes as $a) {
                    if ($remove) {
                        $n->removeAttribute($a->name);
                    } else {
                        $n->setAttribute($a->name, NULL);
                    }
                }
            } elseif (!empty($updates)) {
                foreach ($updates as $uName => $uData) {

                    if (preg_match('/^[\w\s_-]+$/', $uName) and !$n->hasAttribute($uName)) {
                        $n->setAttribute($uName, NULL);
                    }

                    $match = static::GetMatcher($uName);
                    foreach ($n->attributes as $attr) {
                        if ($match($attr->name)) {

                            $value = static::UpdateValue($attr->value, $uData);

                            if (!empty($value)) {
                                $value = htmlentities(html_entity_decode($value));
                            }

                            if (($value === NULL) and $remove) {
                                $n->removeAttribute($attr->name);
                            } else {
                                $attr->value = $value;
                            }
                        }
                    }
                }
            }
        }

        foreach ($result as $key => $items) {
            $result[$key] = new DataAggregator (new ArrayIterator($items));
        }
        return $result ?: [];
    }

}