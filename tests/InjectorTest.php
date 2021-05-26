<?php namespace Uralmedias\Linker;


use DOMDocument;
use DOMXPath;
use Uralmedias\Linker\Injector;
use PHPUnit\Framework\TestCase;


final class InjectorTest extends TestCase
{

    public function testCanInjectAndReplaceNodes (): void
    {
        $domX = new DOMDocument();
        $domY = new DOMDocument();

        $domX->loadHTMLFile(__DIR__.'/injectortest/original.html');
        $domY->loadHTMLFile(__DIR__.'/injectortest/modified.html');

        $xpath = new DOMXPath($domX);
        $injector = new Injector($xpath, ...$xpath->query('//*[@class="source"]'));
        $injector->up('.target0');
        $injector->down('.target0');
        $injector->before('.target1');
        $injector->after('.target1');
        $injector->into('.target2');
        $injector->to('.target3');

        $this->expectOutputString($domY->saveHTML());
        echo $domX->saveHTML();
    }

}