<?php namespace Uralmedias\Linker\Tests;


use PHPUnit\Framework\TestCase;
use DOMDocument, DOMXPath;


/**
 * **Абстрактный тесткейс**
 *
 * Помогает тестировать модули библиотеки.
 */
abstract class AbstractTest extends TestCase
{

    private static DOMDocument $sourceDocument;
    private static string $sourceString = <<<HTML
    <!DOCTYPE html>
    <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta http-equiv="X-UA-Compatible" content="IE=edge">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Document</title>
            <style>
                body {
                    padding: 20px;
                }
                .heading {
                    margin-bottom: 15px;
                }
            </style>
            <script>
                alert('Hello world!');
                console.log('Hello world');
            </script>
        </head>
        <body>

            <!-- Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod
            tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam,
            quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo
            consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse
            cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non
            proident, sunt in culpa qui officia deserunt mollit anim id est laborum. -->

            <div class="b_heading">
                <h1 class="b_heading__title">Heading #1</h1>
                <h2 class="b_heading__title">Heading #2</h2>
                <h3 class="b_heading__title">Heading #3</h3>
                <h4 class="b_heading__title">Heading #4</h4>
                <h5 class="b_heading__title">Heading #5</h5>
                <h6 class="b_heading__title">Heading #6</h6>
            </div>
            <nav>
                <ul class="b_list" style="border: 1px dotted gray; padding: 5px;">
                    <li id="item1" class="b_list__item">Item #1</li>
                    <li id="item2" class="b_list__item">Item #2</li>
                    <li id="item3" class="b_list__item">Item #3</li>
                </ul>
                <ol class="b_list" style="border: 1px dashed green; 5px;">
                    <li id="item4" class="b_list__item">Item #4</li>
                    <li id="item5" class="b_list__item">Item #5</li>
                    <li id="item6" class="b_list__item">Item #6</li>
                </ol>
            </nav>
            <main class="b_main">
                <img src="http://www.google.com/images/branding/googlelogo/2x/googlelogo_color_92x30dp.png" alt="">
                <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod
                tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam,
                quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo
                consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse
                cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non
                proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>
                <a href="https://google.com/">Google</a>
            </main>
        </body>
    </html>
    HTML;


    protected DOMDocument $document;
    protected DOMXPath $xpath;
    protected string $source;


    /**
     * Устанавливает тестовые данные перед запуском каждого теста.
     */
    public function setUp (): void
    {
        $this->source = self::$sourceString;

        if (!isset(self::$sourceDocument)) {
            self::$sourceDocument = new DOMDocument;
            libxml_use_internal_errors(true);
            self::$sourceDocument->loadHTML($this->source);
            libxml_clear_errors();
        }

        $this->document = clone self::$sourceDocument;
        $this->xpath = new DOMXPath ($this->document);
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
        $dir = __DIR__.DIRECTORY_SEPARATOR.array_pop($class);
        $subdir = $dir.DIRECTORY_SEPARATOR.$this->getName();
        $filename = $subdir.DIRECTORY_SEPARATOR.$filename;

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

}