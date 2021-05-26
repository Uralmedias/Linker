<?php namespace Uralmedias\Linker;


use Uralmedias\Linker\Selector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\CssSelector\CssSelectorConverter;


final class SelectorTest extends TestCase
{

    public function testDefaults (): void
    {
        $this->expectOutputString('//*');
        echo Selector::fromValue();
    }


    public function testPositiveIndex (): void
    {
        $this->expectOutputString('/*[42]');
        echo Selector::fromIndex(41);
    }


    public function testNegativeIndex (): void
    {
        $this->expectOutputString('/*[last()-42]');
        echo Selector::fromIndex(-42);
    }


    public function testCssToXpath (): void
    {
        $this->expectOutputString('/*[last()-42]');
        echo Selector::fromIndex(-42);
    }


    public function testJustXpath (): void
    {
        $this->expectOutputString('//body/div[@class="myclass"]');
        echo Selector::fromXPath('//body/div[@class="myclass"]');
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
            Selector::fromValue(41).
            Selector::fromValue(-42).
            Selector::fromValue('//body/div[@class="myclass"]').
            Selector::fromValue('body.class1.class2#id')
        );
    }

}