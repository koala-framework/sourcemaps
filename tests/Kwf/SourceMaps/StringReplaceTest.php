<?php
class Kwf_SourceMaps_StringReplaceTest extends PHPUnit_Framework_TestCase
{
    public function testStringReplace()
    {
        $map = new Kwf_SourceMaps_SourceMap(Kwf_SourceMaps_TestData::$testMap, Kwf_SourceMaps_TestData::$testGeneratedCode);
        $map->stringReplace('baz', 'asdfasdf');

             //0        1         2         3         4
             //1234567890123456789012345678901234567890123
        $s = " ONE.foo=function(a){return asdfasdf(a);};\n".
             " TWO.inc=function(a){return a+1;};";
        $this->assertEquals($map->getFileContents(), $s);

        $mappings = $map->getMappings();
        $this->assertEquals($mappings[5], array(
            'generatedLine' => 1,
            'generatedColumn' => 28, //must not change
            'originalSource' => '/the/root/one.js',
            'originalLine' => 2,
            'originalColumn' => 10,
            'name' => 'baz'
        ));
        $this->assertEquals($mappings[6], array(
            'generatedLine' => 1,
            'generatedColumn' => 32+5,  //this neets to be shifted
            'originalSource' => '/the/root/one.js',
            'originalLine' => 2,
            'originalColumn' => 14,
            'name' => 'bar'
        ));

        //first of line 2
        $this->assertEquals($mappings[7], array(
            'generatedLine' => 2,
            'generatedColumn' => 1, //must not change
            'originalSource' => '/the/root/two.js',
            'originalLine' => 1,
            'originalColumn' => 1,
        ));
    }

    public function testStringReplaceSecondLine()
    {
        $map = new Kwf_SourceMaps_SourceMap(Kwf_SourceMaps_TestData::$testMap, Kwf_SourceMaps_TestData::$testGeneratedCode);
        $map->stringReplace('inc', 'increment');

             //0        1         2         3         4
             //1234567890123456789012345678901234567890123
        $s = " ONE.foo=function(a){return baz(a);};\n".
             " TWO.increment=function(a){return a+1;};";
        $this->assertEquals($map->getFileContents(), $s);

        $mappings = $map->getMappings();
        //last of line 1
        $this->assertEquals($mappings[6], array(
            'generatedLine' => 1,
            'generatedColumn' => 32,
            'originalSource' => '/the/root/one.js',
            'originalLine' => 2,
            'originalColumn' => 14,
            'name' => 'bar'
        ));


        $this->assertEquals($mappings[7], array(
            'generatedLine' => 2,
            'generatedColumn' => 1, //don't change
            'originalSource' => '/the/root/two.js',
            'originalLine' => 1,
            'originalColumn' => 1,
        ));
        $this->assertEquals($mappings[8], array(
            'generatedLine' => 2,
            'generatedColumn' => 5, //don't change
            'originalSource' => '/the/root/two.js',
            'originalLine' => 1,
            'originalColumn' => 5,
        ));
        $this->assertEquals($mappings[9], array(
            'generatedLine' => 2,
            'generatedColumn' => 9+6,
            'originalSource' => '/the/root/two.js',
            'originalLine' => 1,
            'originalColumn' => 11,
        ));
        $this->assertEquals($mappings[10], array(
            'generatedLine' => 2,
            'generatedColumn' => 18+6,
            'originalSource' => '/the/root/two.js',
            'originalLine' => 1,
            'originalColumn' => 21,
            'name' => 'n'
        ));
        $this->assertEquals($mappings[12], array(
            'generatedLine' => 2,
            'generatedColumn' => 28+6,
            'originalSource' => '/the/root/two.js',
            'originalLine' => 2,
            'originalColumn' => 10,
            'name' => 'n'
        ));
    }

    public function testStringReplaceMultipleInOneLine()
    {
        $map = new Kwf_SourceMaps_SourceMap(Kwf_SourceMaps_TestData::$testMap, Kwf_SourceMaps_TestData::$testGeneratedCode);
        $map->stringReplace('a', 'xbbbbxxxxxxxx');

             //0        1         2         3         4
             //1234567890123456789012345678901234567890123
          //   ONE.foo=function(a){return baz(a);};
        $s = " ONE.foo=function(xbbbbxxxxxxxx){return bxbbbbxxxxxxxxz(xbbbbxxxxxxxx);};\n".
          //   TWO.inc=function(a){return a+1;};
             " TWO.inc=function(xbbbbxxxxxxxx){return xbbbbxxxxxxxx+1;};";
        $this->assertEquals($map->getFileContents(), $s);

        $mappings = $map->getMappings();
        $this->assertEquals($mappings[3], array(
            'generatedLine' => 1,
            'generatedColumn' => 18,
            'originalSource' => '/the/root/one.js',
            'originalLine' => 1,
            'originalColumn' => 21,
            'name' => 'bar'
        ));
        $this->assertEquals($mappings[4], array(
            'generatedLine' => 1,
            'generatedColumn' => 21+12,
            'originalSource' => '/the/root/one.js',
            'originalLine' => 2,
            'originalColumn' => 3,
        ));
        $this->assertEquals($mappings[5], array(
            'generatedLine' => 1,
            'generatedColumn' => 28+12,
            'originalSource' => '/the/root/one.js',
            'originalLine' => 2,
            'originalColumn' => 10,
            'name' => 'baz'
        ));
        $this->assertEquals($mappings[6], array(
            'generatedLine' => 1,
            'generatedColumn' => 32+12+12,
            'originalSource' => '/the/root/one.js',
            'originalLine' => 2,
            'originalColumn' => 14,
            'name' => 'bar'
        ));


        $this->assertEquals($mappings[10], array(
            'generatedLine' => 2,
            'generatedColumn' => 18,
            'originalSource' => '/the/root/two.js',
            'originalLine' => 1,
            'originalColumn' => 21,
            'name' => 'n'
        ));
        $this->assertEquals($mappings[11], array(
            'generatedLine' => 2,
            'generatedColumn' => 21+12,
            'originalSource' => '/the/root/two.js',
            'originalLine' => 2,
            'originalColumn' => 3,
        ));
        $this->assertEquals($mappings[12], array(
            'generatedLine' => 2,
            'generatedColumn' => 28+12,
            'originalSource' => '/the/root/two.js',
            'originalLine' => 2,
            'originalColumn' => 10,
            'name' => 'n'
        ));
    }
}
