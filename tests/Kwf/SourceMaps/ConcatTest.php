<?php
class Kwf_SourceMaps_ConcatTest extends PHPUnit_Framework_TestCase
{
    public function testConcat()
    {
        $map1 = new Kwf_SourceMaps_SourceMap(Kwf_SourceMaps_TestData::$testMap, Kwf_SourceMaps_TestData::$testGeneratedCode);
        $map2 = new Kwf_SourceMaps_SourceMap(Kwf_SourceMaps_TestData::$testMap, Kwf_SourceMaps_TestData::$testGeneratedCode);
        $map1->concat($map2);

        $mappings = $map1->getMappings();
        $this->assertEquals(13*2, count($mappings));
        $this->assertEquals($mappings[0], array(
            'generatedLine' => 1,
            'generatedColumn' => 1,
            'originalSource' => '/the/root/one.js',
            'originalLine' => 1,
            'originalColumn' => 1,
        ));
        $this->assertEquals($mappings[12], array(
            'generatedLine' => 2,
            'generatedColumn' => 28,
            'originalSource' => '/the/root/two.js',
            'originalLine' => 2,
            'originalColumn' => 10,
            'name' => 'n'
        ));

        $mappingsOffs = 13;
        $genLineOffs = 2;
        $this->assertEquals($mappings[$mappingsOffs+0], array(
            'generatedLine' => $genLineOffs+1,
            'generatedColumn' => 1,
            'originalSource' => '/the/root/one.js',
            'originalLine' => 1,
            'originalColumn' => 1,
        ));
        $this->assertEquals($mappings[$mappingsOffs+12], array(
            'generatedLine' => $genLineOffs+2,
            'generatedColumn' => 28,
            'originalSource' => '/the/root/two.js',
            'originalLine' => 2,
            'originalColumn' => 10,
            'name' => 'n'
        ));

        $contents = $map1->getFileContents();
        $contents = explode("\n", $contents);
        $this->assertEquals(2*2, count($contents));
    }

    public function testConcatThree()
    {
        $map1 = new Kwf_SourceMaps_SourceMap(Kwf_SourceMaps_TestData::$testMap, Kwf_SourceMaps_TestData::$testGeneratedCode);
        $map2 = new Kwf_SourceMaps_SourceMap(Kwf_SourceMaps_TestData::$testMap, Kwf_SourceMaps_TestData::$testGeneratedCode);
        $map3 = new Kwf_SourceMaps_SourceMap(Kwf_SourceMaps_TestData::$testMap, Kwf_SourceMaps_TestData::$testGeneratedCode);
        $map1->concat($map2);
        $map1->concat($map3);

        $mappings = $map1->getMappings();
        $this->assertEquals(13*3, count($mappings));
        $this->assertEquals($mappings[0], array(
            'generatedLine' => 1,
            'generatedColumn' => 1,
            'originalSource' => '/the/root/one.js',
            'originalLine' => 1,
            'originalColumn' => 1,
        ));
        $this->assertEquals($mappings[12], array(
            'generatedLine' => 2,
            'generatedColumn' => 28,
            'originalSource' => '/the/root/two.js',
            'originalLine' => 2,
            'originalColumn' => 10,
            'name' => 'n'
        ));

        $mappingsOffs = 13*2;
        $genLineOffs = 2*2;
        $this->assertEquals($mappings[$mappingsOffs+0], array(
            'generatedLine' => $genLineOffs+1,
            'generatedColumn' => 1,
            'originalSource' => '/the/root/one.js',
            'originalLine' => 1,
            'originalColumn' => 1,
        ));
        $this->assertEquals($mappings[$mappingsOffs+12], array(
            'generatedLine' => $genLineOffs+2,
            'generatedColumn' => 28,
            'originalSource' => '/the/root/two.js',
            'originalLine' => 2,
            'originalColumn' => 10,
            'name' => 'n'
        ));

        $contents = $map1->getFileContents();
        $contents = explode("\n", $contents);
        $this->assertEquals(2*3, count($contents));
    }
}
