<?php namespace Uralmedias\Linker;


/**
 * Агрегирует общие паттерны работы с данными, позволяя
 * сохранять целостность архитектуры и давать пользователям
 * возможность использовать те же алгоритмы, что используются
 * классами библиотеки.
 */
abstract class Generic
{

    /**
     * Обрабатывает строку $source, выводя $words слов, но не более $chars
     * символов. Если $words или $chars меньше нуля, то они отсчитываются с конца,
     * а если равны нулю, то не учитываются. Аргумент $breakable позволяет разрезать
     * слова при ограничении по символам, а если текст был обрезан, обрезанные части
     * заменяется на $delimiter, который не учитывается при вычислении лимитов.
     */
    public static function text (?string $source, string $delimiter = NULL,
        int $words = 0, int $chars = 0, bool $breakable = FALSE): string
    {
        $result = $source ? strval($source) : '';

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
    public static function value (?string $value, $update): ?string
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