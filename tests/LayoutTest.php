<?php namespace Uralmedias\Linker\Tests;


use Uralmedias\Linker\Layout;
use Uralmedias\Linker\Tests\AbstractTestCase;


class LayoutTest extends AbstractTestCase
{

    public function testFromNodes ()
    {
        $nodes = $this->exampleXPath->query('/*');
        $fragment = Layout::fromNodes(...$nodes);
        $this->assertNoRegression($fragment, 'result.html');
    }


    public function testFromDocument ()
    {
        $fragment = Layout::fromDocument($this->exampleDocument);
        $this->assertNoRegression($fragment, 'result.html');
    }


    public function testFromFile ()
    {
        $fragment = Layout::fromFile($this->exampleFileName);
        $this->assertNoRegression($fragment, 'result.html');
    }


    public function testFromHTML ()
    {
        $contents = $this->exampleHTML;
        $fragment = Layout::fromHTML($contents);
        $this->assertNoRegression($fragment, 'result.html');
    }


    public function testFromOutput ()
    {
        $contents = $this->exampleHTML;
        $fragment = Layout::fromOutput(function () use ($contents) {
            echo $contents;
        });
        $this->assertNoRegression($fragment, 'result.html');
    }

}