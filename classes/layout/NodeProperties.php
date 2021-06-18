<?php namespace Uralmedias\Linker\Layout;


use DOMNode, DOMCharacterData, DOMAttr, DOMElement;


/**
 * **Интерфейс для свойств и атрибутов узлов**
 *
 * Создаётся на основе ссылок на узлы и даёт доступ к чтению и редактированию
 * их свойств и атрибутов. Некоторые действия могут приводить к порче старых
 * ссылок, поэтому длительное хранение объектов нежелательно.
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
     * Для элемента, коментария или текстового узла это - его текст,
     * для атрибута - это выражение после "=". Для задания нового
     * значения нужно передать аргумент.
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

        foreach ($this->nodes as $n) {
            return $n->textContent;
        }
        return "";
    }


    /**
     * **Inline-стили элемента**
     *
     * Возвращает массив, ключами которого служат имена атрибутов стиля,
     * а значениями - их значения. Заполненный аргумент заменяет текущее
     * значение. Парсер очень неаккуратный, нужно пользоваться осторожно.
     */
    public function styles (array $updates = NULL): array
    {
        $parseStyle = function (DOMElement $e) {

            $rules = explode(';', $e->getAttribute('style') ?? '');
            $styles = [];
            foreach ($rules as $r) {
                $r = explode (':', $r);
                if (count($r) == 2) {
                    $style[$r[0]] = $r[1];
                }
            }

            return $styles;
        };

        // стили могут быть только у элементов
        if ($updates !== NULL) {
            foreach ($this->nodes as $n) {
                if (is_a($n, DOMElement::class)) {

                    $styles = $parseStyle($n);
                    foreach ($updates as $uName => $uValue) {
                        $styles[$uName] = $uValue;
                    }
                }
            }
        }

        // возврат значения
        if ($updates !== NULL) {
            foreach ($this->nodes as $n) {
                if (is_a($n, DOMElement::class)) {

                    return $parseStyle($n);
                }
            }
        }
    }


    /**
     * **CSS-классы элементов**
     *
     * Доступ к атрибуту class элемента. Возвращает массив CSS-классов.
     * Чтобы изменить значения, необходимо передать первым аргументом массив,
     * при этом поведение вариируется:
     * - *По умолчанию*: ключи соответствуют удаляемым классам, значения - добавляемым;
     * - *$doReplacing = TRUE*: ключи соответствуют шаблонам поиска, значения - подстановкам;
     * - *$assumeRE = TRUE*: то, что и предыдущий, но шаблоны поиска - регулярные выражения.
     *
     * При использовании регулярных выражений можно использовать групировки и подстановки.
     */
    public function classes (array $updates = NULL, bool $doReplacing = FALSE, bool $assumeRE = FALSE): array
    {
        // классы могут быть только у элементов
        if ($updates !== NULL) {
            foreach ($this->nodes as $n) {
                if (is_a($n, DOMElement::class)) {

                    $classes = preg_split('/\s+/', ($n->getAttribute('class') ?? ''));
                    if (!$doReplacing) {

                        $classes = array_diff($classes, array_keys($updates));
                        foreach ($updates as $u) {
                            if ($u !== NULL) {
                                array_push($classes, $u);
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
     * Доступ к атрибутам элементов. Возвращает атрибуты в виде массива ключ => значение,
     * принимает новое значение в таком же виде. При отсустствии ключа в аргументе,
     * значение атрибута остаётся не тронутым. Если значение === NULL, атрибут удаляется,
     * кроме случаев, когда $nullValues = TRUE. В этом случае устанавливается NULL.
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