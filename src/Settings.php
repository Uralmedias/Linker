<?php namespace Uralmedias\Linker;


use ReflectionClass;


/**
 * Позволяет выполнять тонкую настройку во время выполнения.
 */
abstract class Settings
{

    /** Влияет на работу Fragment, определяет момент разбора строки */
    static public bool $lazyParsing = TRUE;

    /** Влияет на работу Fragment, определяет момент загрузки файла */
    static public bool $lazyLoading = TRUE;

    /** Влияет на работу Fragment и Injector, определяет момент импорта узлов */
    static public bool $lazyImport = TRUE;

    /** Влияет на работу Fragment, определяет момент вызова процедуры для генерации буфера */
    static public bool $lazyExecution = TRUE;

    /** Влияет на работу Fragment и Injector, определяет можно ли кэшировать данные */
    static public bool $asumeStatic = TRUE;

    /** Влияет на работу Fragment, определяет можно ли кэшировать результат вызова функции */
    static public bool $asumeIdempotence = TRUE;

    /** Путь загрузки исходных файлов верстки */
    static public string $assetsPath = './';

    /** Базовый путь в корне сервера, откуда будут загружаться ресурсы */
    static public string $publicPath = './';


    /**
     * Создает или обновляет ссылки на ресурсы в публичной директории.
     *
     * Принимает список путей файлов или каталогов, на котории нужно
     * создать ссылки. Относительные пути считаются от $assetsPath, а
     * ссылки создаются в $publicPath.
     */
    static public function assets (string ...$paths): array
    {
        $assets = realpath(self::$assetsPath);
        $public = realpath(self::$publicPath);

        if ($assets !== $public) {

            $cwd = getcwd();
            $links = [];
            $result = [];

            // получить абсолютные пути для источников
            chdir($assets);
            foreach($paths as $p) {
                if ($path = realpath($p)) {
                    $links[$p] = $path;
                }
            }

            chdir($public);

            // удалить все старые ссылки
            $linkKeys = array_keys($links);
            foreach ($linkKeys as $lk) {
                if (is_link($lk)) {
                    unlink($lk);
                }
            }

            // удалить пустые каталоги
            foreach ($linkKeys as $lk) {
                rmdir(dirname($lk));
            }

            // создать новые ссылки
            foreach ($links as $lLink => $lTarget) {

                mkdir(dirname($lLink), 0777, true);
                symlink($lTarget, $lLink);
                $result[$lTarget] = realpath($lLink);
            }

            // вернуться в рабочую директорию
            chdir($cwd);
            return $result;
        }

        return $paths;
    }


    /**
     * Позволяет сохранять и загружать состояние настроек.
     */
    static public function current (object $updates = NULL): object
    {
        $state = (new ReflectionClass(static::class))->getStaticProperties();

        if ($updates !== NULL) {
            $keys = array_keys($state);
            foreach ($keys as $k) {
                static::$$k = $state[$k] = $updates->$k;
            }
        }

        return (object) $state;
    }

}