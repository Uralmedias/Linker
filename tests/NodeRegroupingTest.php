<?php namespace Uralmedias\Linker;


use DOMDocument;
use DOMXPath;
use Uralmedias\Linker\Layout\NodeRegrouping;
use PHPUnit\Framework\TestCase;


final class NodeRegroupingTest extends TestCase
{

    public function testCanInjectAndReplaceNodes (): void
    {
        $domX = new DOMDocument();
        $domY = new DOMDocument();

        $domX->loadHTMLFile(__DIR__.'/noderegroupingtest/original.html');
        $domY->loadHTMLFile(__DIR__.'/noderegroupingtest/modified.html');

        $xpath = new DOMXPath($domX);
        $NodeRegrouping = new NodeRegrouping($xpath, ...$xpath->query('//*[@class="source"]'));
        $NodeRegrouping->up('.target0');
        $NodeRegrouping->down('.target0');
        $NodeRegrouping->before('.target1');
        $NodeRegrouping->after('.target1');
        $NodeRegrouping->into('.target2');
        $NodeRegrouping->to('.target3');

        $this->expectOutputString($domY->saveHTML());
        echo $domX->saveHTML();
    }

}