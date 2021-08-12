<?php namespace Uralmedias\Linker\Tests;

use ArrayIterator, DOMXPath;
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
        $this->exampleDocument = clone $this->exampleDocument;
        $this->exampleXPath = new DOMXPath($this->exampleDocument);
        $regrouping = new NodeRegrouping(new ArrayIterator([$this->exampleXPath]), new ArrayIterator($source));
        $regrouping->before('ul', 'ol');
        $this->assertNoRegression($this->exampleDocument->saveHTML(), 'result.html');
    }


    public function testAfter ()
    {
        $source = [
            $this->exampleDocument->createElement('div', 'Lorem ipsum #1'),
            $this->exampleDocument->createElement('div', 'Lorem ipsum #2')
        ];
        $regrouping = new NodeRegrouping(new ArrayIterator([$this->exampleXPath]), new ArrayIterator($source));
        $regrouping->after('ul', 'ol');
        $this->assertNoRegression($this->exampleDocument->saveHTML(), 'result.html');
    }


    public function testUp ()
    {
        $source = [
            $this->exampleDocument->createElement('li', 'Lorem ipsum #1'),
            $this->exampleDocument->createElement('li', 'Lorem ipsum #2')
        ];
        $regrouping = new NodeRegrouping(new ArrayIterator([$this->exampleXPath]), new ArrayIterator($source));
        $regrouping->up('ul', 'ol');
        $this->assertNoRegression($this->exampleDocument->saveHTML(), 'result.html');
    }


    public function testDown ()
    {
        $source = [
            $this->exampleDocument->createElement('li', 'Lorem ipsum #1'),
            $this->exampleDocument->createElement('li', 'Lorem ipsum #2')
        ];
        $regrouping = new NodeRegrouping(new ArrayIterator([$this->exampleXPath]), new ArrayIterator($source));
        $regrouping->down('ul', 'ol');
        $this->assertNoRegression($this->exampleDocument->saveHTML(), 'result.html');
    }


    public function testInto ()
    {
        $source = [
            $this->exampleDocument->createElement('li', 'Lorem ipsum #1'),
            $this->exampleDocument->createElement('li', 'Lorem ipsum #2')
        ];
        $regrouping = new NodeRegrouping(new ArrayIterator([$this->exampleXPath]), new ArrayIterator($source));
        $regrouping->into('ul', 'ol');
        $this->assertNoRegression($this->exampleDocument->saveHTML(), 'result.html');
    }


    public function testTo ()
    {
        $source = [
            $this->exampleDocument->createElement('div', 'Lorem ipsum #1'),
            $this->exampleDocument->createElement('div', 'Lorem ipsum #2')
        ];
        $regrouping = new NodeRegrouping(new ArrayIterator([$this->exampleXPath]), new ArrayIterator($source));
        $regrouping->to('ul', 'ol');
        $this->assertNoRegression($this->exampleDocument->saveHTML(), 'result.html');
    }

}