<?php namespace Uralmedias\Linker\Tests;


use Uralmedias\Linker\Layout\NodeRegrouping;
use Uralmedias\Linker\Tests\AbstractTestCase;


class NodeRegroupingTest extends AbstractTestCase
{


    public function testBefore ()
    {
        $source = [
            $this->exampleDocument->createElement('div', 'Lorem ipsum #1'),
            $this->exampleDocument->createElement('div', 'Lorem ipsum #2')
        ];
        $regrouping = new NodeRegrouping($this->exampleXPath, ...$source);
        $regrouping->before('ul', 'ol');
        $this->assertNoRegression($this->exampleDocument->saveHTML(), 'result.html');
    }


    public function testAfter ()
    {
        $source = [
            $this->exampleDocument->createElement('div', 'Lorem ipsum #1'),
            $this->exampleDocument->createElement('div', 'Lorem ipsum #2')
        ];
        $regrouping = new NodeRegrouping($this->exampleXPath, ...$source);
        $regrouping->after('ul', 'ol');
        $this->assertNoRegression($this->exampleDocument->saveHTML(), 'result.html');
    }


    public function testUp ()
    {
        $source = [
            $this->exampleDocument->createElement('li', 'Lorem ipsum #1'),
            $this->exampleDocument->createElement('li', 'Lorem ipsum #2')
        ];
        $regrouping = new NodeRegrouping($this->exampleXPath, ...$source);
        $regrouping->up('ul', 'ol');
        $this->assertNoRegression($this->exampleDocument->saveHTML(), 'result.html');
    }


    public function testDown ()
    {
        $source = [
            $this->exampleDocument->createElement('li', 'Lorem ipsum #1'),
            $this->exampleDocument->createElement('li', 'Lorem ipsum #2')
        ];
        $regrouping = new NodeRegrouping($this->exampleXPath, ...$source);
        $regrouping->down('ul', 'ol');
        $this->assertNoRegression($this->exampleDocument->saveHTML(), 'result.html');
    }


    public function testInto ()
    {
        $source = [
            $this->exampleDocument->createElement('li', 'Lorem ipsum #1'),
            $this->exampleDocument->createElement('li', 'Lorem ipsum #2')
        ];
        $regrouping = new NodeRegrouping($this->exampleXPath, ...$source);
        $regrouping->into('ul', 'ol');
        $this->assertNoRegression($this->exampleDocument->saveHTML(), 'result.html');
    }


    public function testTo ()
    {
        $source = [
            $this->exampleDocument->createElement('div', 'Lorem ipsum #1'),
            $this->exampleDocument->createElement('div', 'Lorem ipsum #2')
        ];
        $regrouping = new NodeRegrouping($this->exampleXPath, ...$source);
        $regrouping->to('ul', 'ol');
        $this->assertNoRegression($this->exampleDocument->saveHTML(), 'result.html');
    }

}