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
    private static DOMDocument $template;


    /**
     * Создать фрагмент из набора узлов DOM.
     */
    public static function fromNodes (DOMNode ...$nodes): LayoutFragment
    {
        $document = static::NewDocument();
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
    public static function fromHTML ($contents, string $encoding = "UTF-8"): LayoutFragment
    {
        $contents = strval($contents) ?: '';
        $document = static::NewDocument($contents, $encoding);
        return new LayoutFragment($document);
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
                $document = static::NewDocument($contents, $encoding);
                self::$fileCache[$cacheKey] = [
                    'time' => fileatime($filename),
                    'data' => $document
                ];
            }

            return new LayoutFragment (clone self::$fileCache[$cacheKey]['data']);
        }

        $contents = file_get_contents($filename);
        $document = static::NewDocument($contents, $encoding);
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

        $document = static::NewDocument($contents, $encoding);
        return new LayoutFragment ($document);
    }


    private static function NewDocument (string $contents = NULL, string $encoding = 'UTF-8')
    {
		if (!isset(self::$template)) {

			self::$template = new DOMDocument;

		    self::$template->formatOutput = FALSE;
		    self::$template->preserveWhiteSpace = TRUE;
		    self::$template->validateOnParse = FALSE;
		    self::$template->strictErrorChecking = FALSE;
		    self::$template->recover = FALSE;
		    self::$template->resolveExternals = FALSE;
		    self::$template->substituteEntities = FALSE;
		}

        if (!empty($contents)) {

            $contents = mb_convert_encoding($contents, 'HTML-ENTITIES', $encoding);
            $cacheKey = Generic::identify($contents);

            if (!array_key_exists($cacheKey, self::$htmlCache)) {

                $document = clone self::$template;
                libxml_use_internal_errors(true);
                $document->loadHTML($contents, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                libxml_clear_errors();
                self::$htmlCache[$cacheKey] = $document;
            }

            return clone self::$htmlCache[$cacheKey];
        }

        return clone self::$template;
    }

}