<?php namespace Uralmedias\Linker;


use DOMDocument;
use DOMXPath;
use Uralmedias\Linker\Accessor;
use PHPUnit\Framework\TestCase;


final class AccessorTest extends TestCase
{

    public function testCanAccessToClassesAndAttributes (): void
    {
        $domX = new DOMDocument();
        $domY = new DOMDocument();

        $domX->loadHTMLFile(__DIR__.'/accessortest/original.html');
        $domY->loadHTMLFile(__DIR__.'/accessortest/modified.html');

        $xpath = new DOMXPath($domX);
        $accessor = new Accessor(...$xpath->query('//*[@class="test0 test1"]'));

        $accessor->classes(['test1' => 'test2']);
        $accessor->attributes(['data-test1' => NULL, 'data-test2' => 'test2']);

        $this->expectOutputString($domY->saveHTML());
        echo $domX->saveHTML();
    }

}