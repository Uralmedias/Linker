<?php namespace Uralmedias\Linker\Tests;


use Uralmedias\Linker\Layout\NodeProperties;
use Uralmedias\Linker\Tests\AbstractTestCase;


class NodePropertiesTest extends AbstractTestCase
{

    public function testName ()
    {
        $properties = new NodeProperties(...$this->exampleXPath->query('//*[@class="b_heading__title"]'));

        $this->assertNoRegression($properties->name(), 'get.txt');
        $properties->name('div');
        $this->assertNoRegression($this->exampleDocument->saveHTML(), 'set.html');
    }


    public function testValue ()
    {
        $properties = new NodeProperties(...$this->exampleXPath->query('//*[@class="b_heading__title"]'));

        $this->assertNoRegression($properties->value(), 'get.txt');
        $properties->value('New heading');
        $this->assertNoRegression($this->exampleDocument->saveHTML(), 'set.html');
    }


    public function testStyle ()
    {
        $properties1 = new NodeProperties(...$this->exampleXPath->query('//ul'));
        $properties2 = new NodeProperties(...$this->exampleXPath->query('//ol'));

        $test = [
            $properties1->styles(),
            $properties2->styles()
        ];
        $this->assertNoRegression(var_export($test, TRUE), 'get.txt');

        $properties1->styles(['background' => 'silver']);
        $properties2->styles(['background' => 'lightblue']);

        $this->assertNoRegression($this->exampleDocument->saveHTML(), 'set.html');
    }


    public function testClasses ()
    {
        $properties1 = new NodeProperties(...$this->exampleXPath->query('//*[@class="b_heading__title"]'));
        $properties2 = new NodeProperties(...$this->exampleXPath->query('//*[@class="b_list__item"]'));

        $test = [
            $properties1->classes(),
            $properties2->classes()
        ];
        $this->assertNoRegression(var_export($test, TRUE), 'get.txt');

        //TODO: Протестировать все аргументы
    }


    public function testAttributes ()
    {
        $properties = new NodeProperties(...$this->exampleXPath->query('//*[@class="b_heading__title"]'));

        $properties->attributes(['data-test' => 'test attribute']);
        $this->assertNoRegression($this->exampleDocument->saveHTML(), 'set.html');

        //TODO: Протестировать все аргументы
    }
}