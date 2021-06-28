<?php namespace Uralmedias\Linker;


use Uralmedias\Linker\Layout\LayoutFragment;
use DOMDocument, DOMNode;


/**
 * **Загружает фрагменты разметки**
 *
 * Фасад, который служит точкой входа во все операции с исходным кодом. Созданные
 * фрагменты не привязаны к данным, из которых они создаются, чтобы обеспечить
 * целостность и ожидаемое поведение во время обработки.
 */
abstract class Layout
{

    private static array $htmlCache = [];
    private static array $fileCache = [];


    /**
     * Создать фрагмент из набора узлов DOM.
     */
    public static function fromNodes (DOMNode ...$nodes): LayoutFragment
    {
        $document = new DOMDocument("1.0", "utf-8");
        foreach ($nodes as $n) {
            $document->appendChild($document->importNode($n, TRUE));
        }

        return new LayoutFragment ($document);
    }


    /**
     * Создать фрагмент из документа DOM.
     */
    public static function fromDocument (DOMDocument $document): LayoutFragment
    {
        return new LayoutFragment ($document);
    }


    /**
     * Создать фрагмент из строки, содержащей разметку.
     */
    public static function fromHTML (string $contents): LayoutFragment
    {
        if ($cacheKey = md5($contents)) {
            if (!isset(self::$htmlCache[$cacheKey])) {

                $document = new DOMDocument("1.0", "utf-8");
                $document->loadHTML($contents);
                self::$htmlCache[$cacheKey] = $document;
            }

            return new LayoutFragment (self::$htmlCache[$cacheKey]);
        }

        $document = new DOMDocument("1.0", "utf-8");
        $document->loadHTML($contents);
        return new LayoutFragment ($document);
    }


    /**
     * Создать фрагмент из локального файла или URL.
     */
    public static function fromFile (string $filename): LayoutFragment
    {
        if ($cacheKey = realpath($filename)) {
            if (!isset(self::$fileCache[$cacheKey]) or (self::$fileCache[$cacheKey]['time'] !== fileatime($filename))) {

                $document = new DOMDocument("1.0", "utf-8");
                $document->loadHTMLFile($filename);
                self::$fileCache[$cacheKey] = [
                    'time' => fileatime($filename),
                    'data' => $document
                ];
            }

            return new LayoutFragment (clone self::$fileCache[$cacheKey]['data']);
        }

        $document = new DOMDocument("1.0", "utf-8");
        $document->loadHTMLFile($filename);
        return new LayoutFragment ($document);
    }


    /**
     * Создать фрагмент из буфера вывода при выполнении функции.
     */
    public static function fromOutput (callable $process): LayoutFragment
    {
        $document = new DOMDocument("1.0", "utf-8");
        ob_start();

        call_user_func($process);
        $fragment = static::fromHTML(ob_get_contents());
        ob_end_clean();

        return $fragment;
    }

}