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
        $document = new DOMDocument();
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
        return new LayoutFragment (clone $document);
    }


    /**
     * Создать фрагмент из строки, содержащей разметку.
     */
    public static function fromHTML (string $contents, string $encoding = "UTF-8"): LayoutFragment
    {
        $contents = mb_convert_encoding($contents, 'HTML-ENTITIES', $encoding);
        $cacheKey = md5($contents);

        if (!isset(self::$htmlCache[$cacheKey])) {

            libxml_use_internal_errors(true);
            $document = DOMDocument::loadHTML($contents) ?: new DOMDocument();
            libxml_clear_errors();
            self::$htmlCache[$cacheKey] = $document;
        }

        return new LayoutFragment (self::$htmlCache[$cacheKey]);
    }


    /**
     * Создать фрагмент из локального файла или URL.
     */
    public static function fromFile (string $filename, string $encoding = "UTF-8"): LayoutFragment
    {
        if (($localPath = realpath($filename))) {

            $cacheKey = md5("[$encoding]".$filename);

            if (!isset(self::$fileCache[$cacheKey]) or (self::$fileCache[$cacheKey]['time'] !== fileatime($filename))) {

                $contents = file_get_contents($filename);
                $contents = mb_convert_encoding($contents, 'HTML-ENTITIES', $encoding);
                libxml_use_internal_errors(true);
                $document = DOMDocument::loadHTML($contents) ?: new DOMDocument();
                libxml_clear_errors();
                self::$fileCache[$cacheKey] = [
                    'time' => fileatime($filename),
                    'data' => $document
                ];
            }

            return new LayoutFragment (self::$fileCache[$cacheKey]['data']);
        }

        $contents = file_get_contents($filename);
        $contents = mb_convert_encoding($contents, 'HTML-ENTITIES', $encoding);
        libxml_use_internal_errors(true);
        $document = DOMDocument::loadHTML($contents) ?: new DOMDocument();
        libxml_clear_errors();
        return new LayoutFragment ($document);
    }


    /**
     * Создать фрагмент из буфера вывода при выполнении функции.
     */
    public static function fromOutput (callable $process, string $encoding = 'UTF-8'): LayoutFragment
    {
        ob_start();
        call_user_func($process);
		$contents = ob_get_contents();
        ob_end_clean();

        $contents = mb_convert_encoding($contents, 'HTML-ENTITIES', $encoding);
        libxml_use_internal_errors(true);
        $document = DOMDocument::loadHTML($contents) ?: new DOMDocument();
        libxml_clear_errors();
        return new LayoutFragment ($document);
    }

}