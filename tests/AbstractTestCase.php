<?php namespace Uralmedias\Linker\Tests;


use PHPUnit\Framework\TestCase;
use DOMDocument, DOMXPath;


/**
 * **Абстрактный тесткейс**
 *
 * Помогает тестировать модули библиотеки.
 */
abstract class AbstractTestCase extends TestCase
{

    protected DOMDocument $exampleDocument;
    protected DOMXPath $exampleXPath;
    protected string $exampleHTML;
    protected string $exampleFileName;


    /**
     * Устанавливает тестовые данные перед запуском каждого теста.
     */
    public function setUp (): void
    {
        $this->exampleFileName = __DIR__.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'example.html';
        $this->exampleHTML = file_get_contents($this->exampleFileName);
        $this->exampleDocument = new DOMDocument("1.0", "utf-8");
        libxml_use_internal_errors(true);
        $this->exampleDocument->loadHTML($this->exampleHTML);
        libxml_clear_errors();
        $this->exampleXPath = new DOMXPath($this->exampleDocument);
    }


    /**
     * **Регрессивный тест**
     *
     * Тест успешен, если нет сохраненных значений или если
     * результаты прошлого теста идентичны текущим.
     */
    protected function assertNoRegression (string $data, string $filename)
    {
        $class = explode('\\', static::class);
        $cache = __DIR__.DIRECTORY_SEPARATOR.'cache';
        $dir = $cache.DIRECTORY_SEPARATOR.array_pop($class);
        $subdir = $dir.DIRECTORY_SEPARATOR.$this->getName();
        $filename = $subdir.DIRECTORY_SEPARATOR.$filename;

        if (!file_exists($cache)) {
            mkdir($dir);
        }
        if (!file_exists($dir)) {
            mkdir($dir);
        }
        if (!file_exists($subdir)) {
            mkdir($subdir);
        }

        if (file_exists($filename)) {

            $lastResult = file_get_contents($filename);
            $this->assertSame($data, $lastResult);
            return;

        }

        file_put_contents($filename, $data);
        $this->assertTrue(TRUE);
    }

    private $timer;

    public function startTimer ()
    {
        $this->timer =  microtime(true);
    }

    public function stopTimer ()
    {
        echo ' • '.(microtime(true) - $this->timer)."\n";
    }

}