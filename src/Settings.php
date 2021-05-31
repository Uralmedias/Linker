<?php namespace Uralmedias\Linker;


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

    /** Путь на диске, где лежат исходники верстки */
    static public string $assetsPath = './';

    /** Путь на диске, где происходит выполнение скриптов */
    static public string $publicPath = './';


    /**
     * Создает или обновляет ссылки на ресурсы в публичной директории.
     *
     * Принимает список путей файлов или каталогов, на котории нужно
     * создать ссылки. Относительные пути считаются от $assetsPath, а
     * ссылки создаются в $publicPath.
     */
    static public function assets (string ...$paths)
    {
        $cwd = getcwd();
        $assets = realpath(self::$assetsPath);
        $public = realpath(self::$publicPath);
        $links = [];

        if ($assets !== $public) {

            chdir($assets);
            foreach($paths as $p) {
                if ($path = realpath($p)) {
                    $links[$p] = $path;
                }
            }

            chdir($public);
            foreach ($links as $lLink => $lTarget) {

                if (file_exists($lLink) and is_link($lLink)) {
                    unlink($lLink);
                }
                symlink($lTarget, $lLink);
            }

            chdir($cwd);
        }
    }

}