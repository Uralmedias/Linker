<?php namespace Uralmedias\Linker;


use Symfony\Component\CssSelector\CssSelectorConverter;
use Exception;


/**
 * Помогает интерпретировать различные способы указания узлов.
 */
abstract class Selector
{

    private static array $cache = [];
    private static CssSelectorConverter $converter;


    /**
     * Определяет тип селектора автоматически на основе типа
     * и структуры аргумента.
     */
    public static function query ($value = NULL): string
    {
        switch (TRUE) {
            case $value === NULL: return '//*';
            case is_int($value): return static::index($value);
        }

        try {
            return static::css($value);
        } catch (Exception $e) {}

        return static::xpath($value);
    }


    /**
     * Создаёт селектор из целочисленного индекса. Положительные
     * индексы идут сначала и начинаются с 0, отрицательные идут
     * с конца и начинаются с единицы.
     */
    public static function index (int $index): string
    {
        if (!array_key_exists($index, self::$cache)) {

            self::$cache[$index] = ($index < 0) ?
                self::$cache[$index] = '/*[last()'.$index.']':
                self::$cache[$index] = '/*['.($index + 1).']';
        }
        return self::$cache[$index];
    }


    /**
     * Преобразует селектор CSS в аналогичный запрос XPath.
     *
     * **Будьте аккуратны - поддерживаются не все селекторы**
     */
    public static function css (string $css): string
    {
        if (!array_key_exists($css, self::$cache)) {

            self::$converter = self::$converter ?? new CssSelectorConverter();
            self::$cache[$css] = self::$converter->toXPath($css);
        }
        return self::$cache[$css];
    }


    /**
     * Просто использует указанный XPath для выбора узлов.
     */
    public static function xpath (string $xpath): string
    {
        return $xpath;
    }

}