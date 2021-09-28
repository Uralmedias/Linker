<?php namespace Uralmedias\Linker\Tests;


use Uralmedias\Linker\Layout;
use Uralmedias\Linker\Tests\AbstractTestCase;


class LayoutTest extends AbstractTestCase
{

    public function testFromNodes ()
    {
        $this->startTimer();
        $nodes = $this->exampleXPath->query('/*');
        $fragment = Layout::fromNodes(...$nodes);
        $this->assertNoRegression($fragment, 'result.html');
        $this->stopTimer();
    }


    public function testFromDocument ()
    {
        $this->startTimer();
        $fragment = Layout::fromDocument($this->exampleDocument);
        $this->assertNoRegression($fragment, 'result.html');
        $this->stopTimer();
    }


    public function testFromFile ()
    {
        $this->startTimer();
        $fragment = Layout::fromFile($this->exampleFileName);
        $this->assertNoRegression($fragment, 'result.html');
        $this->stopTimer();
    }


    public function testFromHTML ()
    {
        $this->startTimer();
        $contents = $this->exampleHTML;
        $fragment = Layout::fromHTML($contents);
        $this->assertNoRegression($fragment, 'result.html');
        $this->stopTimer();
    }


    public function testFromOutput ()
    {
        $this->startTimer();
        $contents = $this->exampleHTML;
        $fragment = Layout::fromOutput(function () use ($contents) {
            echo $contents;
        });
        $this->assertNoRegression($fragment, 'result.html');
        $this->stopTimer();
    }

    public function testAuto ()
    {
        $this->startTimer();
        $this->assertNoRegression(Layout::select(-1), 'negative.txt');
        $this->assertNoRegression(Layout::select(1), 'positive.txt');
        $this->assertNoRegression(Layout::select(0), 'zero.txt');
        $this->assertNoRegression(Layout::select('tag'), 'tag.txt');
        $this->assertNoRegression(Layout::select('#id'), 'id.txt');
        $this->assertNoRegression(Layout::select('.class'), 'class.txt');
        $this->assertNoRegression(Layout::select('parent>children'), 'children.txt');
        $this->assertNoRegression(Layout::select('.multi.class'), 'multiclass.txt');
        $this->assertNoRegression(Layout::select('*[attribute=value]'), 'attribute.txt');
        $this->stopTimer();
    }

    public function testAt ()
    {
        $this->startTimer();
        $this->assertNoRegression(Layout::at(-1), 'negative.txt');
        $this->assertNoRegression(Layout::at(1), 'positive.txt');
        $this->assertNoRegression(Layout::at(0), 'zero.txt');
        $this->stopTimer();
    }


    public function testCss ()
    {
        $this->startTimer();
        $this->assertNoRegression(Layout::css('tag'), 'tag.txt');
        $this->assertNoRegression(Layout::css('#id'), 'id.txt');
        $this->assertNoRegression(Layout::css('.class'), 'class.txt');
        $this->assertNoRegression(Layout::css('parent>children'), 'children.txt');
        $this->assertNoRegression(Layout::css('.multi.class'), 'multiclass.txt');
        $this->assertNoRegression(Layout::css('*[attribute=value]'), 'attribute.txt');
        $this->stopTimer();
    }

}