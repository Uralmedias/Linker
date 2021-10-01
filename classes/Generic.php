<?php namespace Uralmedias\Linker;


use Symfony\Component\CssSelector\CssSelectorConverter;
use Exception, DOMXPath, DOMDocument;


/**
 * Служит пространством имён общих и часто используемых методов.
 */
abstract class Generic
{

    private static CssSelectorConverter $converter;
    private static DOMXPath $tester;
    private static array $selectCache = [];


    /**
     * Конвертирует ```$selectors``` в запрос XPath следующим образом:
     * ```exp1,[exp2, exp3]``` будет преобразовано в ```/exp1|/exp2/exp3```,
     * что примерно означает "узлы exp1 ИЛИ узлы exp3, находящиеся
     * внутри узлов exp2". В данном случае ```exp1```, ```exp2``` и ```exp3``` -
     * это селектор CSS, выражение XPath или целочисленный индекс. В случае,
     * когда индекс меньше нуля, отсчет будет идти с конца.
     */
    public static function select (...$selectors): string
    {
        $cacheKey = static::identify($selectors);

        if (!array_key_exists($cacheKey, self::$selectCache)) {
            $query = [];
            foreach ($selectors as $p) {

                $steps = is_array($p) ? $p : [$p];
                $subquery = '';
                foreach ($steps as $s) {
                    if (is_integer($s)) {
                        $subquery .= '/*'.(($s < 0) ? '[last()'.$s.']': '['.($s + 1).']');
                    } else {
                        try {

                            self::$converter ??= new CssSelectorConverter();
                            $subquery .= self::$converter->toXPath($s, '//');

                        } catch (Exception $e) {

                            self::$tester ??= new DOMXPath(new DOMDocument);
                            self::$tester->query($s);
                            $subquery .= $s;
                        }
                    }
                }

                $query[] = $subquery;
            }

            self::$selectCache[$cacheKey] = implode('|', $query);
        }

        return self::$selectCache[$cacheKey];
    }


    /**
     * Метод формирует текст, сначала захватывая ```$words``` слов из ```$source``` с соседними небуквенными
     * символами, затем оставляет ```$chars``` символов. Если ```$words``` или ```$chars``` меньше
     * нуля, то отсчёт ведётся с конца, а равные нулю значения не учитываются. Аргумент ```$breakable```
     * указывает: можно ли разрывать слова при ограничении по символам. Если нельзя, то вместе со
     * словом в результат попадают соседние небуквенные символы, кроме пробелов. Аргумент ```$delimiter```
     * содержит начало и (или) окончание обрезанной строки, в подсчёте лимитов не используется.
     */
    public static function text (?string $source, string $delimiter = NULL,
        int $words = 0, int $chars = 0, bool $breakable = FALSE): string
    {

        // Экспериментально выяснил, что переворот строки работает в десятки раз эффективнее,
        // чем использование специального регулярного выражения для поиска с конца

        $result = $source ? strval($source) : '';

        $delimiter = strval($delimiter);
        $croppedFront = FALSE;
        $croppedBack = FALSE;

        if ($words !== 0) {

            $count = ($words < 0) ? -$words : $words;
            $result = ($words < 0) ? implode('', array_reverse(preg_split('//u', $result))) : $result;
            $re = "/^(?:[^\w]*\w+[^\w]*){{$count}}/su";

            $matches = [];
            preg_match($re, $result, $matches);
            if (!empty($matches[0])) {
                $croppedFront |= $words < 0;
                $croppedBack |= $words > 0;
                $result = $matches[0];
            }

            $result = ($words < 0) ? implode('', array_reverse(preg_split('//u', $result))) : $result;
        }

        if ($chars !== 0) {

            $count = ($chars < 0) ? -$chars : $chars;
            $result = ($chars < 0) ? implode('', array_reverse(preg_split('//u', $result))) : $result;
            $re = $breakable ? "/^.{{$count}}/su" : "/^.{{$count}}\w*[^\w]?/su";

            $matches = [];
            preg_match($re, $result, $matches);
            if (!empty($matches[0])) {
                $croppedFront |= $chars < 0;
                $croppedBack |= $chars > 0;
                $result = $matches[0];
            }

            $result = ($chars < 0) ? implode('', array_reverse(preg_split('//u', $result))) : $result;
        }

        $result = $croppedFront ? $delimiter.ltrim($result) : $result;
        $result = $croppedBack ? rtrim($result).$delimiter : $result;

        return $result;
    }


    /**
     * Возвращает измененное значение ```$value```. Изменение происходит путём
     * интерпритации ```$params``` одним из следующих способов:
     * ```php
     * Generic::value('foo') === Generic::value('foo', []) === 'foo';
     * Generic::value('foo', 'bar') === Generic::value('foo', ['bar']) === 'bar';
     * Generic::value('foo', ['f', 'm']) === 'moo';
     * Generic::value('foo', ['/f/', 'm', 'preg_replace']) === 'moo';
     * ```
     */
    public static function value (?string $value, $params = []): ?string
    {
        $params = is_array($params) ? $params : [$params];

        switch (count($params)) {

            case 0: return $value;
            case 1: return isset($params[0]) ? strval($params[0]) : NULL;
            case 2: return str_replace($params[0], $params[1], $value);
            case 3: return $params[2]($params[0], $params[1], $value);
        }

        throw new Exception ();
    }


    /**
     * Создаёт уникальный хэш для ```$value```. Хэши для скалярных
     * значений или массивов, состоящих только из скалярных значений
     * могут быть использованы повторно между запусками.
     */
    public static function identify ($value): string
    {
        if (is_array($value)) {

            $result = '';
            foreach ($value as $v) {
                $result .= static::identify($v);
            }
            return md5($result);

        } elseif (is_object($value)) {
            return spl_object_hash($value);
        } elseif (is_callable($value)) {
            return spl_object_hash((object) $value);
        }

        return md5(serialize($value));
    }


    /**
     * Возвращает функтор, который проверяет аргумент на соответствие выражению ```$pattern```. Тип
     * выражения определяется автоматически, возможные варианты: "регулярное выражение", "шаблон
     * поиска" или "точное совпадение". В последнем случае функтор может быть преобразован в
     * непустую строку, которая будет содержать выражение. Используется приемущественно для
     * фильтрации ключей. Возможность превращения в строку используется для создания отсутствующих
     * ключей (возможна только когда в качестве выражения выступает точный идентификатор).
     */
    public static function matcher (string $pattern): ?callable
    {
        return new class ($pattern) {

            private $identifier = NULL;
            private $comparator = NULL;

            public function __construct (string $pattern) {

                $simple = function (string $value) use ($pattern): bool {
                    return strtolower($pattern) === strtolower($value);
                };

                $regexp = function (string $value) use ($pattern): bool {
                    return preg_match($pattern, $value);
                };

                $wildcard = function (string $value) use ($pattern): bool {
                    return fnmatch(strtolower($pattern), strtolower($value));
                };

                static $regexpPattern = '/^\/.*\/[gmixsuUAJD]*$/';
                static $simplePattern = '/^[\w\s_-]+$/';

                $this->comparator = preg_match($regexpPattern, $pattern) ? $regexp : $wildcard;
                $this->comparator = preg_match($simplePattern, $pattern) ? $simple : $this->comparator;
                $this->identifier = ($this->comparator === $simple) ? $pattern : NULL;
            }

            public function __invoke (string $value): bool {
                return ($this->comparator)($value);
            }

            public function __toString(): string {
                return $this->identifier ?? '';
            }
        };
    }

}