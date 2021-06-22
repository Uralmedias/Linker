<?php namespace Uralmedias\Linker\Tests;


use Uralmedias\Linker\Layout\LayoutFragment;
use Uralmedias\Linker\Tests\AbstractTest;


class LayoutFragmentTest extends AbstractTest
{

    public function testMinimize ()
    {
        $fragment1 = new LayoutFragment ($this->document);
        $fragment2 = new LayoutFragment ($this->document);
        $fragment3 = new LayoutFragment ($this->document);
        $fragment4 = new LayoutFragment ($this->document);
        $fragment1->minimize(FALSE, FALSE);
        $fragment2->minimize(TRUE, FALSE);
        $fragment3->minimize(FALSE, TRUE);
        $fragment4->minimize(TRUE, TRUE);

        $this->assertNoRegression($fragment1, 'default.html');
        $this->assertNoRegression($fragment2, 'comments.html');
        $this->assertNoRegression($fragment3, 'scripts.html');
        $this->assertNoRegression($fragment4, 'scripts-comments.html');
    }


    public function testCut ()
    {
        $donnor = new LayoutFragment ($this->document);
        $piece = $donnor->cut('ul.b_list');

        $this->assertNoRegression($donnor, 'donnor.html');
        $this->assertNoRegression($piece, 'piece.html');
    }


    public function testCopy ()
    {
        $donnor = new LayoutFragment ($this->document);
        $piece = $donnor->copy('ul.b_list');

        $this->assertNoRegression($donnor, 'donnor.html');
        $this->assertNoRegression($piece, 'piece.html');
    }


    public function testMove ()
    {
        $fragment = new LayoutFragment ($this->document);
        $fragment->move('ul.b_list .b_list__item')->up('ol.b_list');

        $this->assertNoRegression($fragment, 'result.html');
    }


    public function testPut ()
    {
        $fragment = new LayoutFragment ($this->document);
        $piece1 = $fragment->copy('ul.b_list .b_list__item');
        $piece2 = $fragment->copy('ol.b_list .b_list__item');
        $fragment->put($piece1)->up('ol.b_list');
        $fragment->put($piece2)->down('ul.b_list');

        $this->assertNoRegression($fragment, 'result.html');
    }


    public function testWrite ()
    {
        $fragment = new LayoutFragment ($this->document);
        $fragment->write('Test LayoutFragment::write!')->after('p');

        $this->assertNoRegression($fragment, 'result.html');
    }


    public function testAnnotate ()
    {
        $fragment = new LayoutFragment ($this->document);
        $fragment->write('Test LayoutFragment::annotate!')->after('p');

        $this->assertNoRegression($fragment, 'result.html');
    }


    public function testNodes ()
    {
        $fragment = new LayoutFragment ($this->document);
        $this->assertNoRegression($fragment->nodes('p')->value(), 'result.txt');
    }


    public function testAssets ()
    {
        $fragmentDefault = new LayoutFragment (clone $this->document);
        $fragmentReplace = new LayoutFragment (clone $this->document);
        $fragmentRegex = new LayoutFragment (clone $this->document);

        $default = $fragmentDefault->assets();
        $replace = $fragmentReplace->assets(['google.com' => 'example.com']);
        $regex = $fragmentRegex->assets(['/^(https?:).*/'=>'$1example.com'], TRUE);

        $this->assertNoRegression(implode("\n", $default), 'default.txt');
        $this->assertNoRegression(implode("\n", $replace), 'replace.txt');
        $this->assertNoRegression(implode("\n", $regex), 'regex.txt');
    }


    public function testReverse ()
    {
        $fragment = new LayoutFragment ($this->document);
        $fragment->reverse('.b_heading__title');
        $fragment->reverse('.b_list__item');

        $this->assertNoRegression($fragment, 'result.html');
    }


    public function testRandomize ()
    {
        $fragmentX = new LayoutFragment (clone $this->document);
        $fragmentX->randomize('.b_heading__title');
        $fragmentX->randomize('.b_list__item');

        $fragmentY = new LayoutFragment (clone $this->document);
        $fragmentY->randomize('.b_heading__title');
        $fragmentY->randomize('.b_list__item');

        $stringPartsX = str_split((string) $fragmentX);
        sort($stringPartsX);
        $stringPartsX = implode($stringPartsX);

        $stringPartsY = str_split((string) $fragmentY);
        sort($stringPartsY);
        $stringPartsY = implode($stringPartsY);

        $this->assertTrue((string) $fragmentX != (string) $fragmentY);
        $this->assertTrue($stringPartsX == $stringPartsY);

        $this->assertNoRegression($stringPartsX, 'chars_x.txt');
        $this->assertNoRegression($stringPartsY, 'chars_y.txt');
    }

}