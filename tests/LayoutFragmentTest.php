<?php namespace Uralmedias\Linker;


use Uralmedias\Linker\Layout;
use Uralmedias\Linker\Layout\LayoutFragment;
use PHPUnit\Framework\TestCase;
use DOMDocument;


final class LayoutFragmentTest extends TestCase
{

    public function testCanBeCreatedFromSupportedSources (): void
    {
        $this->assertInstanceOf(LayoutFragment::class, Layout::fromFile(__DIR__.'/layoutfragmenttest/original.html'));
        $this->assertInstanceOf(LayoutFragment::class, Layout::fromHTML('<html><div></div></html>'));
        $this->assertInstanceOf(LayoutFragment::class, Layout::fromNodes((new DOMDocument())->createElement('div')));
        $this->assertInstanceOf(LayoutFragment::class, Layout::fromOutput(function (){
            echo '<html><div></div></html>';
        }));
    }


    public function testCanOptimizeWhiteSpaces (): void
    {
        $x = Layout::fromFile(__DIR__.'/layoutfragmenttest/original.html');
        $y = Layout::fromFile(__DIR__.'/layoutfragmenttest/optimized.html');

        $this->expectOutputString($y);
        $x->minimize();
        echo $x;
    }

}