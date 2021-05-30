<?php namespace Uralmedias\Linker;


abstract class Settings
{

    static public bool $lazyParsing = TRUE;
    static public bool $lazyLoading = TRUE;
    static public bool $lazyImport = TRUE;
    static public bool $lazyExecution = TRUE;
    static public bool $asumeStatic = TRUE;
    static public bool $asumeIdempotence = TRUE;
    static public string $sourcePath = '.';
    static public string $publicPath = '.';


    static public function assets (string ...$paths)
    {
        $cwd = getcwd();
        $source = realpath(self::$sourcePath);
        $public = realpath(self::$publicPath);
        $links = [];

        if ($source != $target) {

            chdir($source);
            foreach($paths as $p) {
                if ($path = realpath($p)) {
                    $links[$p] = $path;
                }
            }

            chdir($target);
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