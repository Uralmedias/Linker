<?php namespace Uralmedias\Linker\Tests;


use Uralmedias\Linker\Generic;
use Uralmedias\Linker\Tests\AbstractTestCase;


class GenericTest extends AbstractTestCase
{

    public function testSelect()
    {
        // echo Generic::select('ul', 'ol');
        // die;

        $this->assertNoRegression(Generic::select(-1), 'negative.txt');
        $this->assertNoRegression(Generic::select(1), 'positive.txt');
        $this->assertNoRegression(Generic::select(0), 'zero.txt');
        $this->assertNoRegression(Generic::select('tag'), 'tag.txt');
        $this->assertNoRegression(Generic::select('#id'), 'id.txt');
        $this->assertNoRegression(Generic::select('.class'), 'class.txt');
        $this->assertNoRegression(Generic::select('parent>children'), 'children.txt');
        $this->assertNoRegression(Generic::select('.multi.class'), 'multiclass.txt');
        $this->assertNoRegression(Generic::select('*[attribute=value]'), 'attribute.txt');
    }


    public function testValue()
    {

        $value = 'testing';
        $d1 = NULL;
        $d2 = [];
        $d3 = 'test';
        $d4 = ['test'];
        $d5 = ['ing', 'er'];
        $d6 = ['/e/', 'o', 'preg_replace'];

        $this->assertSame(Generic::value($value, $d1), NULL);
        $this->assertSame(Generic::value($value, $d2), 'testing');
        $this->assertSame(Generic::value($value, $d3), 'test');
        $this->assertSame(Generic::value($value, $d4), 'test');
        $this->assertSame(Generic::value($value, $d5), 'tester');
        $this->assertSame(Generic::value($value, $d6), 'tosting');

    }


    public function testMatcher()
    {

        $m1 = Generic::matcher('data-foo');
        $m2 = Generic::matcher('data-*');
        $m3 = Generic::matcher('/data-.*/');

        $this->assertTrue($m1('data-foo'));
        $this->assertTrue((string) $m1 === 'data-foo');
        $this->assertTrue($m2('data-foo') and $m2('data-bar'));
        $this->assertTrue($m3('data-foo') and $m3('data-bar'));

    }


    public function testText ()
    {

        $data = 'Lorem Ipsum - это текст-"рыба", часто используемый в печати и вэб-дизайне. '
            .'Lorem Ipsum является стандартной "рыбой" для текстов на латинице с начала XVI века. '
            .'В то время некий безымянный печатник создал большую коллекцию размеров и форм шрифтов, '
            .'используя Lorem Ipsum для распечатки образцов. Lorem Ipsum не только успешно пережил без '
            .'заметных изменений пять веков, но и перешагнул в электронный дизайн. Его популяризации в '
            .'новое время послужили публикация листов Letraset с образцами Lorem Ipsum в 60-х годах и, в '
            .'более недавнее время, программы электронной вёрстки типа Aldus PageMaker, в шаблонах '
            .'которых используется Lorem Ipsum.';

        $this->assertNoRegression(Generic::text($data, '...', 5, 20, FALSE), '5b_20b_false.txt');
        $this->assertNoRegression(Generic::text($data, '...', -5, 20, FALSE), '5e_20b_false.txt');
        $this->assertNoRegression(Generic::text($data, '...', 5, -20, FALSE), '5b_20e_false.txt');
        $this->assertNoRegression(Generic::text($data, '...', -5, -20, FALSE), '5e_20e_false.txt');
        $this->assertNoRegression(Generic::text($data, '...', 5, 20, TRUE), '5b_20b_true.txt');
        $this->assertNoRegression(Generic::text($data, '...', -5, 20, TRUE), '5e_20b_true.txt');
        $this->assertNoRegression(Generic::text($data, '...', 5, -20, TRUE), '5b_20e_true.txt');
        $this->assertNoRegression(Generic::text($data, '...', -5, -20, TRUE), '5e_20e_true.txt');

    }

}