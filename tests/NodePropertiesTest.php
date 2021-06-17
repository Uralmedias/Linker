<?php namespace Uralmedias\Linker;


use DOMDocument;
use DOMXPath;
use Uralmedias\Linker\Layout\NodeProperties;
use PHPUnit\Framework\TestCase;


final class NodePropertiesTest extends TestCase
{

    public function testCanAccessToClassesAndAttributes (): void
    {
        $domX = new DOMDocument();
        $domY = new DOMDocument();

        $domX->loadHTMLFile(__DIR__.'/nodepropertiestest/original.html');
        $domY->loadHTMLFile(__DIR__.'/nodepropertiestest/modified.html');

        $xpath = new DOMXPath($domX);
        $NodeProperties = new NodeProperties(...$xpath->query('//*[@class="test0 test1"]'));

        $NodeProperties->classes(['test1' => 'test2']);
        $NodeProperties->attributes(['data-test1' => NULL, 'data-test2' => 'test2']);

        $this->expectOutputString($domY->saveHTML());
        echo $domX->saveHTML();
    }

}