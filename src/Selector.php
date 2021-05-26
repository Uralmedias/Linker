<?php namespace Uralmedias\Linker;


use Symfony\Component\CssSelector\CssSelectorConverter;
use Exception;


/**
 * Помогает интерпретировать различные способы указания узлов.
 */
class Selector
{

    static private array $cache = [];
    static private CssSelectorConverter $converter;

    private string $xpath;


    private function __construct () {}


    public function __toString(): string
    {
        return $this->xpath;
    }


    /**
     * Определяет тип селектора автоматически на основе типа
     * и структуры аргумента.
     */
    static public function fromValue ($value = NULL)
    {
        switch (TRUE) {
            case is_a($value, self::class): return $value;
            case $value === NULL: return static::fromXPath('//*');
            case is_int($value): return static::fromIndex($value);
        }

        try {
            return static::fromCss($value);
        } catch (Exception $e) {}

        return static::fromXPath($value);
    }


    /**
     * Создаёт селектор из целочисленного индекса. Положительные
     * индексы идут сначала и начинаются с 0, отрицательные идут
     * с конца и начинаются с единицы.
     */
    static public function fromIndex (int $index)
    {
        if (!array_key_exists($index, self::$cache)) {

            self::$cache[$index] = ($index < 0) ?
                self::$cache[$index] = static::fromXPath('/*[last()'.$index.']'):
                self::$cache[$index] = static::fromXPath('/*['.($index + 1).']');
        }
        return self::$cache[$index];
    }


    /**
     * Преобразует селектор CSS в аналогичный запрос XPath.
     *
     * **Будьте аккуратны - поддерживаются не все селекторы**
     */
    static public function fromCss (string $css)
    {
        if (!array_key_exists($css, self::$cache)) {

            self::$converter = self::$converter ?? new CssSelectorConverter();
            self::$cache[$css] = static::fromXPath(self::$converter->toXPath($css));
        }
        return self::$cache[$css];
    }


    /**
     * Просто использует указанный XPath для выбора узлов.
     */
    static public function fromXPath (string $xpath)
    {
        $selector = new Selector ();
        $selector->xpath = $xpath;
        return $selector;
    }

}