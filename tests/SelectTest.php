<?php namespace Uralmedias\Linker\Tests;


use Uralmedias\Linker\Select;
use Uralmedias\Linker\Tests\AbstractTestCase;


class SelectTest extends AbstractTestCase
{

    public function testAuto ()
    {
        $this->assertNoRegression(Select::auto(-1), 'negative.txt');
        $this->assertNoRegression(Select::auto(1), 'positive.txt');
        $this->assertNoRegression(Select::auto(0), 'zero.txt');
        $this->assertNoRegression(Select::auto('tag'), 'tag.txt');
        $this->assertNoRegression(Select::auto('#id'), 'id.txt');
        $this->assertNoRegression(Select::auto('.class'), 'class.txt');
        $this->assertNoRegression(Select::auto('parent>children'), 'children.txt');
        $this->assertNoRegression(Select::auto('.multi.class'), 'multiclass.txt');
        $this->assertNoRegression(Select::auto('*[attribute=value]'), 'attribute.txt');
    }


    public function testAt ()
    {
        $this->assertNoRegression(Select::at(-1), 'negative.txt');
        $this->assertNoRegression(Select::at(1), 'positive.txt');
        $this->assertNoRegression(Select::at(0), 'zero.txt');
    }


    public function testCss ()
    {
        $this->assertNoRegression(Select::css('tag'), 'tag.txt');
        $this->assertNoRegression(Select::css('#id'), 'id.txt');
        $this->assertNoRegression(Select::css('.class'), 'class.txt');
        $this->assertNoRegression(Select::css('parent>children'), 'children.txt');
        $this->assertNoRegression(Select::css('.multi.class'), 'multiclass.txt');
        $this->assertNoRegression(Select::css('*[attribute=value]'), 'attribute.txt');
    }

}