<?php namespace Uralmedias\Linker;


use Symfony\Component\CssSelector\CssSelectorConverter;
use Exception;


/**
 * *Транслирует селекторы в XPath*
 *
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
    public static function auto ($value): string
    {
        $cacheKey = 'auto'.$value;

        if (!array_key_exists($cacheKey, self::$cache)) {

            if (is_int($value)) {
                self::$cache[$cacheKey] = static::at($value);
            } else {

                try {
                    self::$cache[$cacheKey] = static::css($value);
                } catch (Exception $e) {
                    self::$cache[$cacheKey] = $value;
                }
            }
        }

        return self::$cache[$cacheKey];
    }


    /**
     * Целочисленный индекс. Положительный начинается с 0
     * и указывает позицию с начала, отрицательный начинается
     * с -1 и указывает позицию с конца.
     */
    public static function at (int $position): string
    {
        $cacheKey = 'at'.$position;

        if (!array_key_exists($cacheKey, self::$cache)) {

            self::$cache[$cacheKey] = ($position < 0) ?
                self::$cache[$cacheKey] = '/*[last()'.$position.']':
                self::$cache[$cacheKey] = '/*['.($position + 1).']';
        }

        return self::$cache[$cacheKey];
    }


    /**
     * Селектор CSS (будет преобразован в XPath).
     */
    public static function css (string $selector): string
    {
        $cacheKey = 'css'.$selector;

        if (!array_key_exists($cacheKey, self::$cache)) {

            self::$converter = self::$converter ?? new CssSelectorConverter();
            self::$cache[$cacheKey] = self::$converter->toXPath($selector, '//');
        }

        return self::$cache[$cacheKey];
    }


    // TODO: Документировать и тестировать
    public static function path (string ...$steps): string
    {
        $result = '';
        foreach ($steps as $s) {
            $result .= static::auto($s);
        }

        self::$cache['auto'.$result] = $result;
        return $result;
    }

}