<?php namespace Uralmedias\Linker;


abstract class Settings
{

    static public bool $lazyParsing = TRUE;
    static public bool $lazyLoading = TRUE;
    static public bool $lazyImport = TRUE;
    static public bool $lazyExecution = TRUE;
    static public bool $asumeStatic = TRUE;
    static public bool $asumeIdempotence = TRUE;


    static public function array (array $updates = NULL): array
    {
        if ($updates !== NULL) {
            foreach ($updates as $uName => $uValue) {
                static::$$uName = $uValue;
            }
        }

        return [

            'lazyParsing' => static::$lazyParsing,
            'lazyLoading' => static::$lazyLoading,
            'lazyImport' => static::$lazyImport,
            'lazyExecution' => static::$lazyExecution,
            'asumeStatic' => static::$asumeStatic,
            'asumeIdempotence' => static::$asumeIdempotence
        ];
    }

}