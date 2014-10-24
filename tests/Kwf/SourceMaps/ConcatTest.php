<?php
class Kwf_SourceMaps_ConcatTest extends PHPUnit_Framework_TestCase
{
    public function testConcat()
    {
        $map1 = new Kwf_SourceMaps_SourceMap(Kwf_SourceMaps_TestData::$testMap, Kwf_SourceMaps_TestData::$testGeneratedCode);
        $map2 = new Kwf_SourceMaps_SourceMap(Kwf_SourceMaps_TestData::$testMap, Kwf_SourceMaps_TestData::$testGeneratedCode);
        $map1->concat($map2);
        $mappings = $map1->getMappings();
        $this->assertEquals(13*2+1, count($mappings));
    }
}
