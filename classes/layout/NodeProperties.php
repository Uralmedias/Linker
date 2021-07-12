<?php namespace Uralmedias\Linker\Layout;


use DOMNode, DOMCharacterData, DOMAttr, DOMElement;


/**
 * **Оператор доступа к свойствам узлов**
 *
 * Позволяет изменять код разметки меняя имена и значения,
 * атрибуты элементов и другие характеристики узлов. Также
 * позволяет получать эти значения.
 *
 * Экземпляр возвращается методами `nodes` класса `LayoutFragment`,
 * и методами `before`, `after`, `up`, `down`, `into`, `to` класса
 * `NodeRegrouping`.
 *
 * *Длительное хранение экземпляра может привести к неожиданным
 * последствиям из-за непостоянства ссылок в PHP DOM.*
 */
class NodeProperties
{

    private array $nodes = [];


    public function __construct(DOMNode ...$nodes)
    {
        $this->nodes = $nodes;
    }


    /**
     * **Имя узла**
     *
     * Для элемента это - тэг, для атрибута это - токен перед "=",
     * узлы других типов не могут иметь имя, в этом случае
     * значение не устанавливается, а возвращается пустая строка.
     * Для установки используются все узлы в выборке, для возврата -
     * первый подходящий узел.
     */
    public function name (string $update = NULL): string
    {
        // Обновление имени узла - нетривиальная операция. Дело в том,
        // что текущая реализация не позволяет сделать это напрямую.
        // Единственный способ - создать новый узел с новым именем.
        // В следующих реализациях должны были исправить, но пока
        // можно пользоваться только этим.
        if ($update !== NULL) {
            foreach ($this->nodes as &$n) {

                if (is_a($n, DOMElement::class)) {

                    $new = $n->ownerDocument->createElement($update);
                    while ($n->hasAttributes()) {
                        $new->setAttributeNode($n->attributes->item(0));
                    }
                    while ($n->hasChildNodes()) {
                        $new->appendChild($n->firstChild);
                    }
                    $n->parentNode->replaceChild($new, $n);
                    $n = $new;

                } elseif (is_a($n, DOMAttr::class)) {

                    $new = $n->ownerDocument->createAttribute($update);
                    $new->value = $n->value;
                    $element = $n->parentNode;
                    $element->removeAttributeNode($n);
                    $element->setAttributeNode($new);
                    $n = $new;

                }
            }
        }

        // возврат значения
        foreach ($this->nodes as $n) {
            if (is_a($n, DOMElement::class) or is_a($n, DOMAttr::class)) {
                return $n->nodeName;
            }
        }
        return "";
    }


    /**
     * **Значение узла**
     *
     * Для элемента, коментария или текстового узла это - его текстовое
     * значение, для атрибута - это выражение после "=". Если аргумент
     * не равен NULL, он заменяет текущее значение. Для установки
     * используются все узлы в выборке, для возврата - конкатенированное
     * значение всех подходящих узлов.
     */
    public function value (string $update = NULL): string
    {
        if ($update !== NULL) {
            foreach ($this->nodes as $n) {

                if (is_a($n, DOMElement::class)) {

                    foreach ($n->childNodes as $cn) {
                        $n->removeChild($cn);
                    }
                    $n->appendChild($n->ownerDocument->createTextNode($update));

                } elseif (is_a($n, DOMAttr::class)) {

                    $n->value = $update;

                } elseif (is_a($n, DOMCharacterData::class)) {

                    $n->data = $update;

                }
            }
        }

        $result = "";
        foreach ($this->nodes as $n) {
            $result .= $n->textContent;
        }
        return $result;
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
            foreach ($this->nodes as $n) {
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
        foreach ($this->nodes as $n) {
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
    public function classes (array $updates = NULL, ?bool $assumeRE = NULL): array
    {
        // классы могут быть только у элементов
        if ($updates !== NULL) {
            foreach ($this->nodes as $n) {
                if (is_a($n, DOMElement::class)) {

                    $classes = preg_split('/\s+/', ($n->getAttribute('class') ?? ''));
                    if ($assumeRE === NULL) {

                        foreach ($updates as $uKey => $uValue) {
                            if (is_string($uKey)) {
                                if (($i = array_search($uKey, $classes)) !== FALSE) {
                                    $classes[$i] = $uValue;
                                }
                            } else {
                                array_push($classes, $uValue);
                            }
                        }

                    } else {

                        $search = array_keys($updates);
                        $replacement = array_values($updates);

                        foreach ($classes as &$c) {
                            $c = $assumeRE ?
                                preg_replace ($search, $replacement, $c):
                                str_replace ($search, $replacement, $c);
                        }
                    }
                    $n->setAttribute('class', implode(' ', array_filter($classes)));
                }
            }
        }

        // возврат значения
        foreach ($this->nodes as $n) {
            if (is_a($n, DOMElement::class)) {
                return preg_split('/\s+/', ($n->getAttribute('class') ?? ''));
            }
        }
        return [];
    }


    /**
     * **Атрибуты элементов**
     *
     * Доступ к атрибутам элементов. Возвращает атрибуты в виде массива ключ-значение,
     * принимает новые значения в таком же формате. При отсустствии ключа в аргументе,
     * значение атрибута остаётся не тронутым. Если значение равно `NULL`, атрибут удаляется,
     * кроме случаев, когда `$nullValues = TRUE`. В этом случае устанавливается `NULL`.
     */
    public function attributes (array $updates = NULL, bool $nullValues = FALSE): array
    {
        // атрибуты могут быть только у элементов
        if ($updates !== NULL) {
            foreach ($this->nodes as $n) {
                if (is_a($n, DOMElement::class)) {

                    foreach ($updates as $uName => $uValue) {
                        if (($uValue === NULL) and !$nullValues) {
                            $n->removeAttribute($uName);
                        } else {
                            $n->setAttribute($uName, $uValue);
                        }
                    }
                }
            }
        }

        // возврат значения
        foreach ($this->nodes as $n) {
            if (is_a($n, DOMElement::class)) {

                $result = [];
                $length = $n->attributes->length;
                for ($i = 0; $i < $length; ++$i) {
                    $item = $n->attributes->item($i);
                    $result[$item->name] = $item->value;
                }

                return $result;
            }
        }
        return [];
    }

}