<?php namespace Uralmedias\Linker\Layout;


use ArrayIterator, Generator, Traversable, IteratorAggregate,
    DOMCharacterData, DOMAttr, DOMElement;


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
class NodeAggregator implements IteratorAggregate
{

    private array $cache;
    private Traversable $items;


    public function __construct (Traversable $items)
    {
        $this->items = $items;
    }


    public function __toString(): string
    {
        $result = '';
        foreach ($this->items() as $n) {
            $result .= $n->ownerDocument->saveHtml($n);
        }

        return $result;
    }


    public function getIterator(): Generator
    {
        foreach ($this->items() as $n) {
            yield new NodeAggregator(new ArrayIterator([$n]));
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
            foreach ($this->items() as $n) {
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
        foreach ($this->items() as $n) {
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
    public function classes (?array $updates = [], callable $method = NULL): array
    {
        $result = NULL;

        if ($updates === NULL) {
            foreach ($this->items() as $n) {
                if (is_a($n, DOMElement::class) and $n->hasAttribute('class')) {

                    if ($result === NULL) {
                        $result =  preg_split('/\s+/', ($n->getAttribute('class') ?? ''));
                    }

                    $n->setAttribute('class', '');
                }
            }
        } elseif (!empty($updates) and ($updates = is_array($updates) ? $updates : [$updates])) {
            foreach ($this->items() as $n) {
                if (is_a($n, DOMElement::class)) {

                    $classes = preg_split('/\s+/', ($n->getAttribute('class') ?? ''));

                    if ($result === null) {
                        $result = $classes;
                    }

                    if ($method === NULL) {

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
                            $c = $method($search, $replacement, $c);
                        }
                    }

                    $n->setAttribute('class', implode(' ', array_filter($classes)));
                }
            }
        }

        if ($result === NULL) {
            foreach ($this->items() as $n) {
                if (is_a($n, DOMElement::class)) {

                   $result = preg_split('/\s+/', ($n->getAttribute('class') ?? ''));
                   break;
                }
            }
        }

        return $result ?: [];
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
        $result = NULL;

        if ($updates === NULL) {
            foreach ($this->items() as $n) {
                if (is_a($n, DOMElement::class)) {

                    if ($result === NULL) {

                        $result = [];
                        foreach ($n->attributes as $a) {
                            $result[$a->name] = $a->value;
                        }
                    }

                    foreach ($n->attributes as $a) {
                        if ($remove) {
                            $n->removeAttribute($a->name);
                        } else {
                            $n->setAttribute($a->name, NULL);
                        }
                    }
                }
            }
        } elseif (!empty($updates)) {
            foreach ($this->items() as $n) {
                if (is_a($n, DOMElement::class)) {

                    if ($result === NULL) {

                        $result = [];
                        foreach ($n->attributes as $a) {
                            $result[$a->name] = $a->value;
                        }
                    }

                    foreach ($updates as $uName => $uParams) {

                        $match = preg_match('/^\/.*\/[gmixsuUAJD]*$/', $uName) ? 'preg_match': 'fnmatch';
                        if (!$n->hasAttribute($uName) and preg_match('/^[\w:_-]+$/', $uName)) {
                            $n->setAttribute($uName, '');
                        }

                        foreach ($n->attributes as $attr) {
                            if ($match($uName, $attr->name)) {
                                if (is_array($uParams)) {

                                    $value = $attr->value ?: '';
                                    $uParams[0] = $uParams[0] ?? $value;
                                    $uParams[1] = $uParams[1] ?? $uParams[0];
                                    $uParams[2] = $uParams[2] ?? 'str_replace';

                                    $value = $uParams[2]($uParams[0], $uParams[1], $value);

                                } else {
                                    $value = strval($uParams);
                                }

                                if (!empty($value)) {
                                    $value = htmlentities(html_entity_decode($value));
                                }

                                if (empty($value) and $remove) {
                                    $n->removeAttribute($attr->name);
                                } else {
                                    $attr->value = $value;
                                }
                            }
                        }
                    }
                }
            }
        }

        if ($result === NULL) {
            foreach ($this->items() as $n) {
                if (is_a($n, DOMElement::class)) {

                    $result = [];
                    foreach ($n->attributes as $a) {
                        $result[$a->name] = $a->value;
                    }

                    break;
                }
            }
        }

        return $result ?: [];
    }


    /**
     *
     *
     */
    public function url ($attributes = NULL, $updates = '', bool $remove = TRUE): array
    {
        $result = NULL;

        // Нахождение узлов, к которым будет применяться метод //
        $targets = [];
        $attributes = is_array($attributes) ? $attributes : [$attributes];
        $attributes = array_unique(array_filter($attributes));
        if (empty($attributes)) {
            $targets = $this->items();
        } else {
            foreach ($attributes as $aName) {

                $match = preg_match('/^\/.*\/[gmixsuUAJD]*$/', $aName) ? 'preg_match': 'fnmatch';
                foreach ($this->items() as $n) {
                    if (is_a($n, DOMElement::class)) {
                        foreach ($n->attributes as $a) {
                            if ($match($aName, $a->name)) {
                                array_push($targets, $a);
                            }
                        }
                    }
                }
            }
        }

        // Разбирает URL на компоненты
        $parseUrl = function ($url): array {

            if (is_string($url)) {
                if ($url = parse_url($url)) {

                    $query = [];
                    parse_str($url['query'], $query);
                    if ($query) {
                        $url['query'] = $query;
                    }
                }
            }

            return is_array($url) ? $url : [];
        };

        // Собирает URL из компонентов
        $buildUrl = function ($url): string {

            if (is_array($url)) {

                $scheme   = !empty($url['scheme']) ? $url['scheme'] . '://' : '';
                $host     = !empty($url['host']) ? $url['host'] : '';
                $port     = !empty($url['port']) ? ':' . $url['port'] : '';
                $user     = !empty($url['user']) ? $url['user'] : '';
                $pass     = !empty($url['pass']) ? ':' . $url['pass']  : '';
                $pass     = ($user || $pass) ? "$pass@" : '';
                $path     = !empty($url['path']) ? $url['path'] : '';
                $query    = !empty($url['query']) ? '?' . $url['query'] : '';
                $fragment = !empty($url['fragment']) ? '#' . $url['fragment'] : '';

                $url = "$scheme$user$pass$host$port$path$query$fragment";
            }

            return is_string($url) ? $url : '';
        };

        if ($updates === NULL) {
            foreach ($targets as $node) {

                if ($result === NULL) {
                    $result = $parseUrl($node->value);
                }

                if ($remove) {
                    $node->parentNode->removeChild($node);
                } else {
                    $node->value = NULL;
                }
            }
        } elseif (!empty($updates)) {

            $updates = is_string($updates) ?
                $parseUrl($updates):
                (is_array($updates)? $updates : []);

            foreach ($targets as $node) {

                $source = $parseUrl($node->value);

                if (($result === NULL) and isset($targets[0])) {
                    $result = $source;
                }

                foreach ($updates as $uName => $uParams) {
                    if (is_array($uParams)) {

                        $source[$uName] = $source[$uName] ?? '';
                        $uParams[0] = $uParams[0] ?? $source[$uName];
                        $uParams[1] = $uParams[1] ?? $uParams[0];
                        $uParams[2] = $uParams[2] ?? 'str_replace';

                        $source[$uName] = $uParams[2]($uParams[0], $uParams[1], $source[$uName]);

                    } else {
                        $source[$uName] = strval($uParams);
                    }
                }

                $node->value = $buildUrl($source);

            }

        }

        if (($result === NULL) and isset($targets[0])) {
            $result = $parseUrl($targets[0]->value);
        }

        return $result ?: [];
    }


    protected function items (): array
    {
        if (!isset($this->cache)) {
            $this->cache = [...$this->items];
        }
        return $this->cache;
    }

}