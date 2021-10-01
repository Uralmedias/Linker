<?php namespace Uralmedias\Linker\Layout;


use Uralmedias\Linker\Generic;
use Uralmedias\Linker\Layout\DataAggregator;
use Generator, DOMElement;


/**
 * То же, что и DataAggregator, но дополнительно позволяет
 * управлять структурой и дочерними узлами.
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
            yield new NodeAggregator([$n]);
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
    public function classes ($updates = [], bool $removable = TRUE): array
    {
        $result =[];
        if (is_string($updates)) {
            $updates = preg_split('/\s+/', $updates);
        }

        foreach ($this->GetNodes(DOMElement::class) as $n) {

            $classes = preg_split('/\s+/', ($n->getAttribute('class') ?? ''));
            array_push($result, ...$classes);

            if ($updates === null) {
                if ($removable) {
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
     * Создаёт ассоциативный массив, где ключи - это имена, а значения - объекты ```DataAggregator```,
     * с узлами атрибутов. В результат попадают те атрибуты, имена которых совпадают с шаблонами
     * в ключах ```$query``` или в значениях индексированных элементов. Шаблоны ключей подаются в
     * формате для ```Generic::matcher```. Элементы с ключами содержат данные для обновления
     * в формате для ```DataAggregator::value```, например:\
     * ```['data-*']``` - вернуть все атрибуты, которые начинаются с "data-"\
     * ```['data-*' => 'test']``` - как предыдущее, но еще установит значение в "test"\
     * ```['data-*' => 'test', 'src']``` - как предыдущее, но дополнительно вернуть атрибуты "src"\
     * Аргумент ```$nullable``` позволяет использовать значение ```NULL```, а ```$removable``` -
     * удалять обнудённые узлы.
     */
    public function attributes ($params = ['*'], bool $manage = TRUE): array
    {
        $params = is_array($params) ? $params : [$params];
        $result = [];

        foreach ($params as $qLeft => $qRight) {

            if (is_string($qLeft)) {
                $match = Generic::matcher($qLeft);
            } else {
                $match = Generic::matcher(strval($qRight));
            }

            $targets = [];
            foreach ($this->GetNodes(DOMElement::class) as $n) {

                if ($name = (string) $match) {

                    if (!$n->hasAttribute($name) and $manage) {
                        $n->setAttribute($name, NULL);
                    }

                    if ($a = $n->getAttributeNode($name)) {
                        $targets[] = $a;
                        $result[$name] ??= [];
                        $result[$name][$a->getNodePath()] = $a;
                    }

                } else {

                    foreach ($n->attributes as $a) {
                        if ($match($a->name)) {

                            $targets[] = $a;
                            $result[$a->name] ??= [];
                            $result[$a->name][$a->getNodePath()] = $a;
                        }
                    }
                }
            }

            if (is_string($qLeft) and !empty($targets)) {

                $aggregator = new DataAggregator($targets);
                $aggregator->value($qRight, $manage);
            }
        }

        foreach ($result as $rName => $rNodes) {
            ksort($rNodes);
            $result[$rName] = new DataAggregator ($rNodes);
        }

        return $result;
    }

}