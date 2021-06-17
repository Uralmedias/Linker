<?php namespace Uralmedias\Linker;


use Symfony\Component\CssSelector\CssSelectorConverter;
use Exception;


/**
 * Транслирует селекторы в XPath.
 * Фасад, который позволяет использовать разные способы указания
 * узлов разметки. Более лаконичные селекторы CSS вместо более мощных
 * запросов XPath. Так же поддерживаются целочисленные индексы.
 */
abstract class Select
{

    private static array $cache = [];
    private static CssSelectorConverter $converter;


    /**
     * Автоматически определеить тип селектора.
     */
    public static function auto ($pattern): string
    {
        if (is_int($pattern)) {
            return static::at($pattern);
        }

        try {
            return static::css($pattern);
        } catch (Exception $e) {}

        return $pattern;
    }


    /**
     * Целочисленный индекс. Положительный начинается с 0
     * и указывает позицию с начала, отрицательный начинается
     * с -1 и указывает позицию с конца.
     */
    public static function at (int $index): string
    {
        if (!array_key_exists($index, self::$cache)) {

            self::$cache[$index] = ($index < 0) ?
                self::$cache[$index] = '/*[last()'.$index.']':
                self::$cache[$index] = '/*['.($index + 1).']';
        }
        return self::$cache[$index];
    }


    /**
     * Селектор CSS (будет преобразован в XPath).
     */
    public static function css (string $css): string
    {
        if (!array_key_exists($css, self::$cache)) {

            self::$converter = self::$converter ?? new CssSelectorConverter();
            self::$cache[$css] = self::$converter->toXPath($css);
        }
        return self::$cache[$css];
    }

}