<?php namespace Uralmedias\Linker\Tests;


use Uralmedias\Linker\Layout\NodeAggregator;
use Uralmedias\Linker\Tests\AbstractTestCase;


class NodeAggregatorTest extends AbstractTestCase
{

    public function testName ()
    {
        $properties = new NodeAggregator($this->exampleXPath->query('//*[@class="b_heading__title"]'));

        $this->assertNoRegression($properties->name(), 'get.txt');
        $properties->name('div');
        $this->assertNoRegression($this->exampleDocument->saveHTML(), 'set.html');
    }


    public function testValue ()
    {
        $properties = new NodeAggregator($this->exampleXPath->query('//*[@class="b_heading__title"]'));

        $this->assertNoRegression($properties->value(), 'get.txt');
        $properties->value('New heading');
        $this->assertNoRegression($this->exampleDocument->saveHTML(), 'set.html');
    }


    public function testStyle ()
    {
        $properties1 = new NodeAggregator($this->exampleXPath->query('//ul'));
        $properties2 = new NodeAggregator($this->exampleXPath->query('//ol'));

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
        $properties1 = new NodeAggregator($this->exampleXPath->query('//*[@class="b_heading__title"]'));
        $properties2 = new NodeAggregator($this->exampleXPath->query('//*[@class="b_list__item"]'));

        $test = [
            $properties1->classes(),
            $properties2->classes()
        ];
        $this->assertNoRegression(var_export($test, TRUE), 'get.txt');

        //TODO: Протестировать все аргументы
    }


    public function testAttributes ()
    {
        $properties = new NodeAggregator($this->exampleXPath->query('//*[@class="b_heading__title"]'));

        $properties->attributes(['data-test' => '12345 number']);
        $properties->attributes(['data-*' => ['/\d+/', 'test', 'preg_replace']]);
        $properties->attributes(['/data[-]test/' => ['number', 'attribute']]);
        $this->assertNoRegression($this->exampleDocument->saveHTML(), 'set.html');

        $read = $properties->attributes();
        $this->assertNoRegression(var_export($read, true), 'get.txt');

        $read['data-test']->value('test data aggregator');
        $this->assertNoRegression($this->exampleDocument->saveHTML(), 'set_node.html');

        $read['data-test']->value('test transition');
        $another = new NodeAggregator($this->exampleXPath->query('//*[@class="b_list__item"]'));
        $another->attributes($read);
        $this->assertNoRegression($this->exampleDocument->saveHTML(), 'set_transition.html');

        foreach ($read as $name => $value) {
            $read[$name] = (string) $value;
        }
        $this->assertNoRegression(var_export($read, true), 'get_converted.txt');
    }
}