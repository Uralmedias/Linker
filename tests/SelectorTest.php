<?php namespace Uralmedias\Linker;


use Uralmedias\Linker\Selector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\CssSelector\CssSelectorConverter;


final class SelectorTest extends TestCase
{

    public function testDefaults (): void
    {
        $this->expectOutputString('//*');
        echo Selector::query();
    }


    public function testPositiveIndex (): void
    {
        $this->expectOutputString('/*[42]');
        echo Selector::index(41);
    }


    public function testNegativeIndex (): void
    {
        $this->expectOutputString('/*[last()-42]');
        echo Selector::index(-42);
    }


    public function testCssToXpath (): void
    {
        $this->expectOutputString('/*[last()-42]');
        echo Selector::index(-42);
    }


    public function testJustXpath (): void
    {
        $this->expectOutputString('//body/div[@class="myclass"]');
        echo Selector::xpath('//body/div[@class="myclass"]');
    }


    public function testCustomValue (): void
    {
        $this->expectOutputString(
            '/*[42]'.
            '/*[last()-42]'.
            '//body/div[@class="myclass"]'.
            (new CssSelectorConverter())->toXPath('body.class1.class2#id')
        );

        echo(
            Selector::query(41).
            Selector::query(-42).
            Selector::query('//body/div[@class="myclass"]').
            Selector::query('body.class1.class2#id')
        );
    }

}