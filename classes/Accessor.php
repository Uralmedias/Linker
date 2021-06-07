<?php namespace Uralmedias\Linker;


use DOMNode, DOMCharacterData, DOMAttr, DOMElement;


/**
 * Класс даёт доступ к управлению основными параметрами узлов.
 *
 * Может использоваться самостоятельно вместе с PHP DOM, но спроектирован
 * как вспомогательный внутренний класс для Fragment и Injector для
 * оборачивания нативных методов DOM.
 *
 * **Избегайте длительного хранения экземпляров, т.к. ссылки на узлы могут портится.**
 */
class Accessor
{

    private array $nodes = [];


    public function __construct(DOMNode ...$nodes)
    {
        $this->nodes = array_filter(array_values($nodes));
    }


    /**
     * Управляет именами узлов.
     *
     * Метод возвращает строковое имя первого узла, являющегося атрибутом
     * или элементом или пустую строку. Принимает новое имя в виде строки
     * и устанавливает его для всех элементов и атрибутов в выборке.
     *
     * **Из-за сложностей, связанных с реализацией, ранее полученные
     * ссылки на измененные данным методом узлы станут недействительными,
     * будьте аккуратнее.**
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
     * Управляет тестовым значением узла.
     *
     * Метод возвращает текстовое значение узла или пустую строку.
     * Устанавливает текстовое значение узла из аргумента.
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
     * Управление inline-стилями элементов.
     *
     * Метод возвращает ассоциативный массив CSS-правил или пустой массив.
     * Принимает ассоциативный массив, ключи которого соответствуют именам правил,
     * а значения - их значениям. Правила, значения котрых установлены в NULL,
     * будут удалены, остальные - обновлены. Не упомянутые правила не изменяются.
     *
     * **Иногда метод может портить данные из-за сложности парсинга правил**.
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
     * Управление CSS-классами элементов.
     *
     * Метод возвращает CSS-классы первого элемента в значениях простого массива или пустой
     * массив. Принимает ассоциативный массив, ключи и изначения которых соответствуют классам.
     * CSS-классы, совпадающие со строковыми ключами - удаляются, совпадающие со значениями -
     * добавляются. Другими словами, аргумент ['test1' => 'test2'] заменит класс test1 на test2.
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
     * Управление атрибутами элементов.
     *
     * Метод возвращает ассоциативный массив с атрибутами первого элемента или пустой
     * массив. Принимает ассоциативный массив с ключами и значениями, соответствующими
     * именам и значениям атрибутов. Если значение равно NULL, то атрибут с именем,
     * равным ключу, удаляется. Не указанные в аргументе атрибуты остаются без изменений.
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