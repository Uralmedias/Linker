<?php namespace Uralmedias\Linker\Layout;


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
     * согласно правилам метода DataAggregator::UpdateValue. Если обновление и чтение
     * происходят одновременно, возвращается старое значение. Аргумент $nullable
     * позволяет использовать NULL в качетве $update. Узлы, имя которых
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

            $value = static::UpdateValue($value, $update);

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
     * согласно правилам метода DataAggregator::UpdateValue. Если обновление и чтение
     * происходят одновременно, возвращается старое значение. Аргумент $nullable позволяет
     * использовать NULL в качетве $update, аргумент $removable указывает на то, что узлы,
     * значение которых не установлено после обновления должны быть удалены. Значения узлов -
     * значения атрибутов и текст внутри элементов.
     */
    public function value ($update = NULL, bool $nullable = FALSE, bool $removable = FALSE): ?string
    {
        $result = NULL;

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

            $result = $result ?? $value;
            if (($result !== null) and !$nullable and ($update === null)) {
                break;
            }

            $value = static::UpdateValue($value, $update);

            if (($value === NULL) and $removable) {

                if (is_a($n, DOMAttr::class)) {
                    $n->ownerElement->removeAttribute($n->name);
                } else {
                    $n->parentNode->removeChild($n);
                }

            } else {

                if (is_a($n, DOMAttr::class)) {
                    $n->value = $value;
                } elseif (is_a($n, DOMCharacterData::class)) {
                    $n->data = $value;
                } elseif (is_a($n, DOMElement::class)) {

                    foreach ($n->childNodes as $child) {
                        $n->removeChild($child);
                    }

                    $n->appendChild($n->ownerDocument->createTextNode($value));
                }
            }
        }

        return $result;
    }


    /**
     * Объединяет значения узлов в текст, выводя $words слов, но не более $chars
     * символов. Отдельные значения скрепляются с помощью $separator. Если $words
     * или $chars меньше нуля, то они отсчитываются с конца, а если равны нулю, то
     * не учитываются. Аргумент $breakable позволяет разрезать слова при ограничении
     * по символам, а если текст был обрезан, обрезанные части заменяется на $delimiter,
     * который учитывается при вычислении лимитов, а $separator - учитывается.
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

        $result = implode($separator, $raw);
        $delimiter = is_string($delimiter) ? $delimiter : strval($delimiter);
        $cropped = FALSE;

        if ($words !== 0) {

            $re = $words > 0 ?
                "/^(?:[^\w]*\w+){$words}/":
                "/(?:\w+[^\w]*){-$words}$/";

            $matches = [];
            if (preg_match($re, $result)) {

                $cropped = TRUE;
                $result = $matches[0];
            }
        }

        if ($chars !== 0) {

            $re = $chars > 0 ?
                ($breakable ? "/^.{$chars}/" : "/^.{$chars}\w*/"):
                ($breakable ? "/.{-$chars}$/" : "/\w*.{-$chars}$/");

        }

        return $cropped ? $result.$delimiter : $result;
    }


    /**
     * Возвращает измененное значение $value. Характер изменений описывается
     * параметром $update, который может быть массивом или значением другого
     * типа. В случае другого типа возвращается NULL или строковое представление
     * $update. В массиве играют роль первые три значения: шаблон поиска,
     * шаблон замены и функция. Функция по умолчанию - str_replace, второй
     * параметр по умолчанию равен первому, а первый - самому значению.
     */
    protected static function UpdateValue (?string $value, $update): ?string
    {
        $value = $value ?: '';

        if (is_array($update)) {

            $update[0] = $update[0] ?? $value;
            $update[1] = $update[1] ?? $update[0];
            $update[2] = $update[2] ?? 'str_replace';

            return $update[2]($update[0], $update[1], $value);
        }

        return $update === NULL ? NULL : strval($update);
    }


    /**
     * Возвращает функцию, которая проверяет аргумент на соответствие выражению $pattern. Тип
     * выражения определяется автоматически, возможные варианты: "регулярное выражение", "шаблон
     * поиска" или "точное совпадение". Используется приемущественно для поиска ключей.
     */
    protected static function GetMatcher (string $pattern, $caseSensitivity = FALSE): callable
    {
        $simple = function (string $value) use ($pattern, $caseSensitivity): bool {
            return $caseSensitivity?
                $pattern === $value:
                strtolower($pattern) === strtolower($value);
        };

        $regexp = function (string $value) use ($pattern, $caseSensitivity): bool {
            return preg_match($pattern, $value);
        };

        $wildcard = function (string $value) use ($pattern, $caseSensitivity): bool {
            return fnmatch($pattern, $value);
        };

        $result = preg_match('/^\/.*\/[gmixsuUAJD]*$/', $pattern) ? $regexp : $wildcard;
        $result = preg_match('/^[\w\s_-]+$/', $pattern) ? $simple : $result;

        return $result;
    }


    /**
     * Возвращает массив узлов DOM, на которые указвает текущий объект, и
     * каждый из которых принадлежит хотябы к одному из классов, перечисленных
     * в $classNames. Если не указать ни одного класса, возвращаются сразу все узлы.
     */
    protected function GetNodes (string ...$classNemes): array
    {
        if (!isset($this->cache)) {
            $this->cache = [...$this->items];
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