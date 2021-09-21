<?php namespace Uralmedias\Linker\Layout;


use Uralmedias\Linker\Generic;
use ArrayIterator, Generator, Traversable, IteratorAggregate, DOMCharacterData, DOMNode, DOMAttr, DOMElement;


/**
 * Позволяет работать со значениями и именами узлов, выполнять
 * массовые обновления, переименования и другие операции,
 * доступные для большинства типов узлов DOM, исключаяя
 * операции, связанное с обновлением структуры.
 */
class DataAggregator implements IteratorAggregate
{

    private array $cache;
    private Traversable $items;


    /**
     * Создаёт объект из итерируемого источника узлов DOM.
     */
    public function __construct (Traversable $items)
    {
        $this->items = $items;
    }


    /**
     * Позволяет объекту преобразовываться в строковое представление,
     * предоставляя первое установленное значение узла.
     */
    public function __toString(): string
    {
        foreach ($this->GetNodes() as $n) {
            if (is_a($n, DOMNode::class)) {
                return $n->value ?: '';
            }
        }

        return '';
    }


    /**
     * Позволяет обращаться к узлам по отдельности, применять оператор
     * foreach и другие операции для итерируемых объектов.
     */
    public function getIterator(): Generator
    {
        foreach ($this->GetNodes() as $n) {
            yield new DataAggregator(new ArrayIterator([$n]));
        }
    }


    /**
     * Возвращает первое установленное имя узла или изменяет имена всех узлов
     * согласно правилам метода ```Generic::value```. Если обновление и чтение
     * происходят одновременно, возвращается старое значение. Аргумент ```$nullable```
     * позволяет использовать ```NULL``` в качетве ```$update```. Узлы, имя которых
     * становится пустым после обновления удаляются. Имена узлов - это имена
     * атрибутов и тэги.
     */
    public function name ($update = NULL, bool $nullable = FALSE): ?string
    {
        $result = NULL;

        foreach ($this->GetNodes() as $n) {

            if (is_a($n, DOMAttr::class) or is_a($n,DOMElement::class)) {
                $value = $n->nodeName;
            } else {
                continue;
            }

            $result = $result ?? $value;
            if (($result !== NULL) and !$nullable and ($update === NULL)) {
                break;
            }

            $value = Generic::value($value, $update);

            if (is_a($n, DOMElement::class)) {
                if (empty($value)) {
                    $n->parentNode->removeChild($n);
                } else {

                    $new = $n->ownerDocument->createElement($update);
                    while ($n->hasAttributes()) {
                        $new->setAttributeNode($n->attributes->item(0));
                    }
                    while ($n->hasChildNodes()) {
                        $new->appendChild($n->firstChild);
                    }
                    $n->parentNode->replaceChild($new, $n);
                    $n = $new;
                }
            } elseif (is_a($n, DOMAttr::class)) {
                if (empty($value)) {
                    $n->ownerElement->removeAttribute($n->name);
                } else {
                    $n->parentNode->removeChild($n);
                    $new = $n->ownerDocument->createAttribute($update);
                    $new->value = $n->value;
                    $element = $n->parentNode;
                    $element->removeAttributeNode($n);
                    $element->setAttributeNode($new);
                    $n = $new;
                }
            }
        }

        return $result;
    }


    /**
     * Возвращает первое установленное значение узла или обновляет значения всех узлов
     * согласно правилам метода ```Generic::value```. Если обновление и чтение
     * происходят одновременно, возвращается новое значение. Аргумент ```$nullable``` позволяет
     * использовать ```NULL``` в операциях, аргумент ```$removable``` указывает на то, что узлы,
     * значение которых не установлено после обновления должны быть удалены. Значения узлов -
     * значения атрибутов и текст внутри элементов. Примеры аргумента ```$update```:\
     * ```'bar'``` - установит значение в "bar"\
     * ```['foo', 'bar']``` - заменит вхождения "foo" на вхождения "bar" с помощью ```str_replace```\
     * ```['foo', 'bar', 'preg_replace']``` - выполнит то же самое с помощью ```preg_replace```\
     * Аргумент ```$nullable``` позволяет использовать NULL для установки значения,
     * аргумент ```$removable``` позволяет удалять обнулённые узлы.
     */
    public function value ($query = NULL, bool $nullable = FALSE, bool $removable = FALSE): ?string
    {
        $result = NULL;

        foreach ($this->GetNodes() as $n) {

            if (is_a($n, DOMAttr::class)) {
                $old = $n->value;
            } elseif (is_a($n, DOMCharacterData::class)) {
                $old = $n->data;
            } elseif (is_a($n, DOMElement::class)) {
                $old = $n->textContent;
            } else {
                continue;
            }

            $new = Generic::value($old, $query);
            if (($new === $old) or (($new === NULL) and !$nullable)) {

                $result ??= $old;
                continue;
            }

            if (($new === NULL) and $removable) {

                if (is_a($n, DOMAttr::class)) {
                    $n->ownerElement->removeAttribute($n->name);
                } else {
                    $n->parentNode->removeChild($n);
                }

            } else {

                if (is_a($n, DOMAttr::class)) {
                    $n->value = $new;
                } elseif (is_a($n, DOMCharacterData::class)) {
                    $n->data = $new;
                } elseif (is_a($n, DOMElement::class)) {

                    foreach ($n->childNodes as $child) {
                        $n->removeChild($child);
                    }

                    if ($new) {
                        $n->appendChild($n->ownerDocument->createTextNode($new));
                    }
                }

                $result ??= $new;
            }
        }

        return $result;
    }


    /**
     * Объединяет значения узлов в текст, скрепляя отдельные значения с
     * помощью ```$separator```. Далее полученный текст обрабатывается по правилам
     * ```Generic::text```. ```$separator``` учитывается при подсчёте лимитов.
     */
    public function text ($separator = NULL, string $delimiter = NULL,
        int $words = 0, int $chars = 0, bool $breakable = FALSE): string
    {
        $result = "";
        if (!is_array($separator)) {
            $separator = strval($separator);
        }

        $raw = [];
        foreach ($this->GetNodes() as $n) {

            if (is_a($n, DOMAttr::class)) {
                $value = $n->value;
            } elseif (is_a($n, DOMCharacterData::class)) {
                $value = $n->data;
            } elseif (is_a($n, DOMElement::class)) {
                $value = $n->textContent;
            } else {
                continue;
            }

            if (!empty($value)) {
                $raw[] = $value;
            }
        }

        return Generic::text(implode($separator, $raw), $delimiter, $words, $chars, $breakable);
    }


    /**
     * Возвращает массив узлов DOM, на которые указвает текущий объект, и
     * каждый из которых принадлежит хотябы к одному из классов, перечисленных
     * в ```$classNames```. Если не указать ни одного класса, возвращаются сразу все узлы.
     */
    protected function GetNodes (string ...$classNemes): array
    {
        if (!isset($this->cache)) {
            $this->cache = [...array_values(iterator_to_array($this->items))];
        }

        $result = [];
        if (empty($classNemes)) {
            $result = $this->cache;
        } else {

            foreach ($this->GetNodes() as $n) {
                foreach ($classNemes as $class) {
                    if (is_a($n, $class)) {

                        $result[] = $n;
                        break;
                    }
                }
            }
        }

        return $result;
    }

}