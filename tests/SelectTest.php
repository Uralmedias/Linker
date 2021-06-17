<?php namespace Uralmedias\Linker;


use Uralmedias\Linker\Select;
use PHPUnit\Framework\TestCase;
use Symfony\Component\CssSelector\CssSelectorConverter;


final class SelectTest extends TestCase
{

    public function testPositiveAt (): void
    {
        $this->expectOutputString('/*[42]');
        echo Select::at(41);
    }


    public function testNegativeAt (): void
    {
        $this->expectOutputString('/*[last()-42]');
        echo Select::at(-42);
    }


    public function testCss (): void
    {
        $this->expectOutputString('/*[last()-42]');
        echo Select::at(-42);
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
            Select::auto(41).
            Select::auto(-42).
            Select::auto('//body/div[@class="myclass"]').
            Select::auto('body.class1.class2#id')
        );
    }

}