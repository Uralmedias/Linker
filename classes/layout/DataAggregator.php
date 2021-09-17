<?php namespace Uralmedias\Linker\Layout;


use ArrayIterator, Generator, Traversable, IteratorAggregate, DOMCharacterData, DOMNode, DOMAttr, DOMElement;


class DataAggregator implements IteratorAggregate
{

    private array $cache;
    private Traversable $items;


    public function __construct (Traversable $items)
    {
        $this->items = $items;
    }


    public function __toString(): string
    {
        foreach ($this->items() as $n) {
            if (is_a($n, DOMNode::class)) {
                return $n->value ?: '';
            }
        }

        return '';
    }


    public function getIterator(): Generator
    {
        foreach ($this->items() as $n) {
            yield new DataAggregator(new ArrayIterator([$n]));
        }
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
            foreach ($this->items() as $n) {

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
        foreach ($this->items() as $n) {
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
            foreach ($this->items() as $n) {

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
        foreach ($this->items() as $n) {
            $result .= $n->textContent;
        }
        return $result;
    }


    protected function items (): array
    {
        if (!isset($this->cache)) {
            $this->cache = [...$this->items];
        }
        return $this->cache;
    }

}