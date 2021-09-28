<?php namespace Uralmedias\Linker\Tests;

use ArrayIterator, DOMXPath;
use Uralmedias\Linker\Layout\NodeRelocator;
use Uralmedias\Linker\Tests\AbstractTestCase;


class NodeRelocatorTest extends AbstractTestCase
{


    public function testBefore ()
    {
        $this->startTimer();
        $source = [
            $this->exampleDocument->createElement('div', 'Lorem ipsum #1'),
            $this->exampleDocument->createElement('div', 'Lorem ipsum #2')
        ];
        $this->exampleDocument = clone $this->exampleDocument;
        $this->exampleXPath = new DOMXPath($this->exampleDocument);
        $regrouping = new NodeRelocator(new ArrayIterator([$this->exampleXPath]), new ArrayIterator($source));
        $regrouping->before('ul', 'ol');
        $this->assertNoRegression($this->exampleDocument->saveHTML(), 'result.html');
        $this->stopTimer();
    }


    public function testAfter ()
    {
        $this->startTimer();
        $source = [
            $this->exampleDocument->createElement('div', 'Lorem ipsum #1'),
            $this->exampleDocument->createElement('div', 'Lorem ipsum #2')
        ];
        $regrouping = new NodeRelocator(new ArrayIterator([$this->exampleXPath]), new ArrayIterator($source));
        $regrouping->after('ul', 'ol');
        $this->assertNoRegression($this->exampleDocument->saveHTML(), 'result.html');
        $this->stopTimer();
    }


    public function testUp ()
    {
        $this->startTimer();
        $source = [
            $this->exampleDocument->createElement('li', 'Lorem ipsum #1'),
            $this->exampleDocument->createElement('li', 'Lorem ipsum #2')
        ];
        $regrouping = new NodeRelocator(new ArrayIterator([$this->exampleXPath]), new ArrayIterator($source));
        $regrouping->up('ul', 'ol');
        $this->assertNoRegression($this->exampleDocument->saveHTML(), 'result.html');
        $this->stopTimer();
    }


    public function testDown ()
    {
        $this->startTimer();
        $source = [
            $this->exampleDocument->createElement('li', 'Lorem ipsum #1'),
            $this->exampleDocument->createElement('li', 'Lorem ipsum #2')
        ];
        $regrouping = new NodeRelocator(new ArrayIterator([$this->exampleXPath]), new ArrayIterator($source));
        $regrouping->down('ul', 'ol');
        $this->assertNoRegression($this->exampleDocument->saveHTML(), 'result.html');
        $this->stopTimer();
    }


    public function testInto ()
    {
        $this->startTimer();
        $source = [
            $this->exampleDocument->createElement('li', 'Lorem ipsum #1'),
            $this->exampleDocument->createElement('li', 'Lorem ipsum #2')
        ];
        $regrouping = new NodeRelocator(new ArrayIterator([$this->exampleXPath]), new ArrayIterator($source));
        $regrouping->into('ul', 'ol');
        $this->assertNoRegression($this->exampleDocument->saveHTML(), 'result.html');
        $this->stopTimer();
    }


    public function testTo ()
    {
        $this->startTimer();
        $source = [
            $this->exampleDocument->createElement('div', 'Lorem ipsum #1'),
            $this->exampleDocument->createElement('div', 'Lorem ipsum #2')
        ];
        $regrouping = new NodeRelocator(new ArrayIterator([$this->exampleXPath]), new ArrayIterator($source));
        $regrouping->to('ul', 'ol');
        $this->assertNoRegression($this->exampleDocument->saveHTML(), 'result.html');
        $this->stopTimer();
    }

}