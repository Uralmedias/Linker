<?php namespace Uralmedias\Linker;


use DOMDocument;
use Uralmedias\Linker\Fragment;
use PHPUnit\Framework\TestCase;


final class FragmentTest extends TestCase
{

    public function testCanBeCreatedFromSupportedSources (): void
    {
        $this->assertInstanceOf(Fragment::class, Fragment::fromFile(__DIR__.'/fragmenttest/original.html'));
        $this->assertInstanceOf(Fragment::class,Fragment::fromString('<html><div></div></html>'));
        $this->assertInstanceOf(Fragment::class, Fragment::fromNodes([(new DOMDocument())->createElement('div')]));
        $this->assertInstanceOf(Fragment::class, Fragment::fromBuffer(function (){
            echo '<html><div></div></html>';
        }));
    }


    public function testCanOptimizeWhiteSpaces (): void
    {
        $x = Fragment::fromFile(__DIR__.'/fragmenttest/original.html');
        $y = Fragment::fromFile(__DIR__.'/fragmenttest/optimized.html');

        $this->expectOutputString($y);
        $x->minimize();
        echo $x;
    }

}