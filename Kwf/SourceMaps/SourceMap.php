<?php
/**
 * Various Sourcemaps Utilities
 *
 * An instance of this class represents a source map plus the minimied file
 */
class Kwf_SourceMaps_SourceMap
{
    protected $_map;
    protected $_fileContents;
    protected $_mappings;
    protected $_mappingsChanged = false; //set to true if _mappings changed and _map['mappings'] is outdated

    private static $_mappingSeparator = '/^[,;]/';

    /**
     * @param string contents of the source map
     * @param string contents of the minified file
     */
    public function __construct($mapContents, $fileContents)
    {
        if (is_string($mapContents)) {
            $this->_map = json_decode($mapContents);
            if (!$this->_map) {
                throw new Exception("Failed parsing map: ".json_last_error());
            }
        } else {
            $this->_map = $mapContents;
        }
        if (!isset($this->_map->version)) {
            throw new Exception("Invalid Source Map");
        }
        if ($this->_map->version != 3) {
            throw new Exception("Unsupported Version");
        }
        $this->_fileContents = $fileContents;
    }

    public function setFile($file)
    {
        $this->_map->file = $file;
    }

    public function getFile()
    {
        return $this->_map->file;
    }

    /**
     * Create a new, empty sourcemap
     *
     * @param string contents of the minified file
     */
    public static function createEmptyMap($fileContents)
    {
        $map = (object)array(
            'version' => 3,
            'mappings' => str_repeat(';', substr_count($fileContents, "\n")),
            'sources' => array(),
            'names' => array(),
        );
        return new self($map, $fileContents);
    }

    /**
     * Adds a mapping
     *
     * @param integer $generatedLine The line number in generated file
     * @param integer $generatedColumn The column number in generated file
     * @param integer $originalLine The line number in original file
     * @param integer $originalColumn The column number in original file
     * @param string $sourceFile The original source file
     */
    public function addMapping($generatedLine, $generatedColumn, $originalLine, $originalColumn, $originalSource, $originalName = null)
    {
        if (!isset($this->_mappings)) $this->getMappings();
        $this->_mappings[] = array(
            'generatedLine' => $generatedLine,
            'generatedColumn' => $generatedColumn,
            'originalLine' => $originalLine,
            'originalColumn' => $originalColumn,
            'originalSource' => $originalSource,
            'originalName' => $originalName,
        );
        $this->_mappingsChanged = true;
    }


    /**
     * Generates the mappings string
     *
     * Parts based on https://github.com/oyejorge/less.php/blob/master/lib/Less/SourceMap/Generator.php
     * Apache License Version 2.0
     *
     * @return string
     */
    private function _generateMappings()
    {
        if (!isset($this->_mappings) && $this->_map->mappings) {
            //up to date, nothing to do
            return;
        }
        $this->_mappingsChanged = false;
        if (!count($this->_mappings)) {
            return '';
        }

        $this->_map->sources = array();
        foreach($this->_mappings as $m) {
            if ($m['originalSource'] && !in_array($m['originalSource'], $this->_map->sources)) {
                $this->_map->sources[] = $m['originalSource'];
            }
        }

        $this->_map->names = array();
        foreach($this->_mappings as $m) {
            if ($m['originalName'] && !in_array($m['originalName'], $this->_map->names)) {
                $this->_map->names[] = $m['originalName'];
            }
        }

        // group mappings by generated line number.
        $groupedMap = $groupedMapEncoded = array();
        foreach($this->_mappings as $m){
            $groupedMap[$m['generatedLine']][] = $m;
        }
        ksort($groupedMap);

        $lastGeneratedLine = $lastOriginalSourceIndex = $lastOriginalNameIndex = $lastOriginalLine = $lastOriginalColumn = 0;

        foreach($groupedMap as $lineNumber => $lineMap) {
            while(++$lastGeneratedLine < $lineNumber){
                $groupedMapEncoded[] = ';';
            }

            $lineMapEncoded = array();
            $lastGeneratedColumn = 0;

            foreach($lineMap as $m){
                $mapEncoded = Kwf_SourceMaps_Base64VLQ::encode($m['generatedColumn'] - $lastGeneratedColumn);
                $lastGeneratedColumn = $m['generatedColumn'];

                // find the index
                if ($m['originalSource']) {
                    $index = array_search($m['originalSource'], $this->_map->sources);
                    $mapEncoded .= Kwf_SourceMaps_Base64VLQ::encode($index - $lastOriginalSourceIndex);
                    $lastOriginalSourceIndex = $index;

                    // lines are stored 0-based in SourceMap spec version 3
                    $mapEncoded .= Kwf_SourceMaps_Base64VLQ::encode($m['originalLine'] - 1 - $lastOriginalLine);
                    $lastOriginalLine = $m['originalLine'] - 1;

                    $mapEncoded .= Kwf_SourceMaps_Base64VLQ::encode($m['originalColumn'] - $lastOriginalColumn);
                    $lastOriginalColumn = $m['originalColumn'];

                    if ($m['originalName']) {
                        $index = array_search($m['originalName'], $this->_map->names);
                        $mapEncoded .= Kwf_SourceMaps_Base64VLQ::encode($index - $lastOriginalNameIndex);
                        $lastOriginalNameIndex = $index;
                    }
                }

                $lineMapEncoded[] = $mapEncoded;
            }

            $groupedMapEncoded[] = implode(',', $lineMapEncoded) . ';';
        }

        $this->_map->mappings = rtrim(implode($groupedMapEncoded), ';');
    }

    /**
     * Performant Source Map aware string replace
     *
     * @param string
     * @param string
     */
    public function stringReplace($string, $replace)
    {
        if ($this->_mappingsChanged) $this->_generateMappings();

        if (strpos("\n", $string)) throw new Exception('string must not contain \n');
        if (strpos("\n", $replace)) throw new Exception('replace must not contain \n');

        $adjustOffsets = array();
        $pos = 0;
        $str = $this->_fileContents;
        $offset = 0;
        while (($pos = strpos($str, $string, $pos)) !== false) {
            $this->_fileContents = substr($this->_fileContents, 0, $pos+$offset).$replace.substr($this->_fileContents, $pos+$offset+strlen($string));
            $offset += strlen($replace)-strlen($string);
            $line = substr_count(substr($str, 0, $pos), "\n")+1;
            $column = $pos - strrpos(substr($str, 0, $pos), "\n"); //strrpos can return false for first line which will subtract 0 (=false)
            $adjustOffsets[$line][] = array(
                'column' => $column,
                'absoluteOffset' => $offset,
                'offset' => strlen($replace)-strlen($string)
            );
            $pos = $pos + strlen($string);
        }
        $generatedLine = 1;
        $previousGeneratedColumn = 0;
        $newPreviousGeneratedColumn = 0;

        $str = $this->_map->mappings;

        $newMappings = '';
        while (strlen($str) > 0) {
            if ($str[0] === ';') {
                $generatedLine++;
                $newMappings .= $str[0];
                $str = substr($str, 1);
                $previousGeneratedColumn = 0;
                $newPreviousGeneratedColumn = 0;
            } else if ($str[0] === ',') {
                $newMappings .= $str[0];
                $str = substr($str, 1);
            } else {
                // Generated column.
                $temp = Kwf_SourceMaps_Base64VLQ::decode($str);
                $generatedColumn = $previousGeneratedColumn + $temp['value'];
                $previousGeneratedColumn = $generatedColumn;
                $newGeneratedColumn = $newPreviousGeneratedColumn + $temp['value'];
                $str = $temp['rest'];

                $offset = 0;
                if (isset($adjustOffsets[$generatedLine])) {
                    foreach ($adjustOffsets[$generatedLine] as $col) {
                        if ($generatedColumn > $col['column']) {
                            $offset += $col['offset'];
                        }
                    }
                }
                $generatedColumn += $offset;
                $newMappings .= Kwf_SourceMaps_Base64VLQ::encode($generatedColumn - $newPreviousGeneratedColumn);
                $newPreviousGeneratedColumn = $generatedColumn;

                //read rest of block as it is
                while (strlen($str) > 0 && !preg_match(self::$_mappingSeparator, $str[0])) {
                    $newMappings .= $str[0];
                    $str = substr($str, 1);
                }
            }
        }
        $this->_map->mappings = $newMappings;
        unset($this->_map->{'_x_org_koala-framework_last'}); //has to be re-calculated
        unset($this->_mappings); //force re-parse
    }

    /**
     * Return all mappings
     *
     * @return array with assoc array containing: generatedLine, generatedColumn, originalSource, originalLine, originalColumn, name
     */
    public function getMappings()
    {
        if (isset($this->_mappings)) {
            return $this->_mappings;
        }

        $this->_mappings = array();

        $generatedLine = 1;
        $previousGeneratedColumn = 0;
        $previousOriginalLine = 0;
        $previousOriginalColumn = 0;
        $previousSource = 0;
        $previousName = 0;

        $str = $this->_map->mappings;

        while (strlen($str) > 0) {
            if ($str[0] === ';') {
                $generatedLine++;
                $str = substr($str, 1);
                $previousGeneratedColumn = 0;
            } else if ($str[0] === ',') {
                $str = substr($str, 1);
            } else {
                $mapping = array();
                $mapping['generatedLine'] = $generatedLine;

                // Generated column.
                $temp = Kwf_SourceMaps_Base64VLQ::decode($str);
                $mapping['generatedColumn'] = $previousGeneratedColumn + $temp['value'];
                $previousGeneratedColumn = $mapping['generatedColumn'];
                $str = $temp['rest'];

                if (strlen($str) > 0 && !preg_match(self::$_mappingSeparator, $str[0])) {
                    // Original source.
                    $temp = Kwf_SourceMaps_Base64VLQ::decode($str);
                    $mapping['originalSource'] = (isset($this->_map->sourceRoot) ? $this->_map->sourceRoot.'/' : '')
                                                 . $this->_map->sources[$previousSource + $temp['value']];
                    $previousSource += $temp['value'];
                    $str = $temp['rest'];
                    if (strlen($str) === 0 || preg_match(self::$_mappingSeparator, $str[0])) {
                        throw new Exception('Found a source, but no line and column');
                    }

                    // Original line.
                    $temp = Kwf_SourceMaps_Base64VLQ::decode($str);
                    $mapping['originalLine'] = $previousOriginalLine + $temp['value'];
                    $previousOriginalLine = $mapping['originalLine'];
                    // Lines are stored 0-based
                    $mapping['originalLine'] += 1;
                    $str = $temp['rest'];
                    if (strlen($str) === 0 || preg_match(self::$_mappingSeparator, $str[0])) {
                        throw new Exception('Found a source and line, but no column');
                    }

                    // Original column.
                    $temp = Kwf_SourceMaps_Base64VLQ::decode($str);
                    $mapping['originalColumn'] = $previousOriginalColumn + $temp['value'];
                    $previousOriginalColumn = $mapping['originalColumn'];
                    $str = $temp['rest'];

                    if (strlen($str) > 0 && !preg_match(self::$_mappingSeparator, $str[0])) {
                        // Original name.
                        $temp = Kwf_SourceMaps_Base64VLQ::decode($str);
                        $mapping['name'] = $this->_map->names[$previousName + $temp['value']];
                        $previousName += $temp['value'];
                        $str = $temp['rest'];
                    }
                }
                $this->_mappings[] = $mapping;
            }
        }
        return $this->_mappings;
    }

    protected function _addLastExtension()
    {
        $previousGeneratedColumn = 0;
        $previousOriginalLine = 0;
        $previousOriginalColumn = 0;
        $previousSource = 0;
        $previousName = 0;
        $lineCount = 0;

        $str = $this->_map->mappings;

        while (strlen($str) > 0) {
            if ($str[0] === ';') {
                $str = substr($str, 1);
                $previousGeneratedColumn = 0;
                $lineCount++;
            } else if ($str[0] === ',') {
                $str = substr($str, 1);
            } else {
                // Generated column.
                $temp = Kwf_SourceMaps_Base64VLQ::decode($str);
                $previousGeneratedColumn = $previousGeneratedColumn + $temp['value'];
                $str = $temp['rest'];

                if (strlen($str) > 0 && !preg_match(self::$_mappingSeparator, $str[0])) {
                    // Original source.
                    $temp = Kwf_SourceMaps_Base64VLQ::decode($str);
                    $previousSource += $temp['value'];
                    $str = $temp['rest'];
                    if (strlen($str) === 0 || preg_match(self::$_mappingSeparator, $str[0])) {
                        throw new Exception('Found a source, but no line and column');
                    }

                    // Original line.
                    $temp = Kwf_SourceMaps_Base64VLQ::decode($str);
                    $previousOriginalLine = $previousOriginalLine + $temp['value'];
                    $str = $temp['rest'];
                    if (strlen($str) === 0 || preg_match(self::$_mappingSeparator, $str[0])) {
                        throw new Exception('Found a source and line, but no column');
                    }

                    // Original column.
                    $temp = Kwf_SourceMaps_Base64VLQ::decode($str);
                    $previousOriginalColumn = $previousOriginalColumn + $temp['value'];
                    $str = $temp['rest'];

                    if (strlen($str) > 0 && !preg_match(self::$_mappingSeparator, $str[0])) {
                        // Original name.
                        $temp = Kwf_SourceMaps_Base64VLQ::decode($str);
                        $previousName += $temp['value'];
                        $str = $temp['rest'];
                    }
                }
            }
        }
        $this->_map->{'_x_org_koala-framework_last'} = (object)array(
            'source' => $previousSource,
            'originalLine' => $previousOriginalLine,
            'originalColumn' => $previousOriginalColumn,
            'name' => $previousName,
        );

        if (substr_count($this->_fileContents, "\n") != $lineCount) {
            throw new Exception("line count in mapping ($lineCount) doesn't match file (".substr_count($this->_fileContents, "\n").")");
        }
    }

    /**
     * Concat sourcemaps and keep mappings intact
     *
     * This is implemented very efficent by avoiding to parse the whole mappings string.
     */
    public function concat(Kwf_SourceMaps_SourceMap $other)
    {
        if ($this->_mappingsChanged) $this->_generateMappings();
        if (!isset($this->_map->{'_x_org_koala-framework_last'})) {
            $this->_addLastExtension();
        }

        if (substr($this->_fileContents, -1) != "\n") {
            $this->_fileContents .= "\n";
            $this->_map->mappings .= ';';
        }
        $this->_fileContents .= $other->_fileContents;

        $data = $other->getMapContentsData();
        if (!$data->mappings) {
            $data->mappings = 'AAAAA'.str_repeat(';', substr_count($other->_fileContents, "\n"));
            $data->{'_x_org_koala-framework_last'} = (object)array(
                'source' => 0,
                'originalLine' => 0,
                'originalColumn' => 0,
                'name' => 0,
            );
        }

        $previousFileLast = $this->_map->{'_x_org_koala-framework_last'};
        $previousFileSourcesCount = count($this->_map->sources);
        $previousFileNamesCount = count($this->_map->names);
        $otherMappings = '';

        if ($data->sources) {
            //$this->_map->sources .= ($this->_map->sources ? ',' : '').substr(json_encode($data->sources), 1, -1);
            $this->_map->sources = array_merge($this->_map->sources, $data->sources);
        }
        if ($data->names) {
            //$this->_map->names .= ($this->_map->names ? ',' : '').substr(json_encode($data->names), 1, -1);
            $this->_map->names = array_merge($this->_map->names, $data->names);
        }

        $otherMappings = $data->mappings;

        $str = '';

        while (strlen($otherMappings) > 0 && $otherMappings[0] === ';') {
            $str .= $otherMappings[0];
            $otherMappings = substr($otherMappings, 1);
        }
        if (strlen($otherMappings) > 0) {
            // Generated column.
            $temp = Kwf_SourceMaps_Base64VLQ::decode($otherMappings);
            $otherMappings = $temp['rest'];
            $str  .= Kwf_SourceMaps_Base64VLQ::encode($temp['value']);
            if (strlen($otherMappings) > 0 && !preg_match(self::$_mappingSeparator, $otherMappings[0])) {

                // Original source.
                $temp = Kwf_SourceMaps_Base64VLQ::decode($otherMappings);
                $otherMappings = $temp['rest'];
                $str  .= Kwf_SourceMaps_Base64VLQ::encode($temp['value'] + $previousFileSourcesCount - $previousFileLast->source);

                // Original line.
                $temp = Kwf_SourceMaps_Base64VLQ::decode($otherMappings);
                $otherMappings = $temp['rest'];
                $str  .= Kwf_SourceMaps_Base64VLQ::encode($temp['value'] - $previousFileLast->originalLine);

                // Original column.
                $temp = Kwf_SourceMaps_Base64VLQ::decode($otherMappings);
                $otherMappings = $temp['rest'];
                $str  .= Kwf_SourceMaps_Base64VLQ::encode($temp['value'] - $previousFileLast->originalColumn);

                // Original name.
                if (strlen($otherMappings) > 0 && !preg_match(self::$_mappingSeparator, $otherMappings[0])) {
                    $temp = Kwf_SourceMaps_Base64VLQ::decode($otherMappings);
                    $otherMappings = $temp['rest'];
                    $str  .= Kwf_SourceMaps_Base64VLQ::encode($temp['value'] + $previousFileNamesCount - $previousFileLast->name);
                } else if (!count($data->names)) {
                    //file doesn't have names at all, we don't have to adjust that offset
                } else {
                    //loop thru mappings until we find a block with name
                    while (strlen($otherMappings) > 0) {
                        if ($otherMappings[0] === ';') {
                            $str .= $otherMappings[0];
                            $otherMappings = substr($otherMappings, 1);
                        } else if ($otherMappings[0] === ',') {
                            $str .= $otherMappings[0];
                            $otherMappings = substr($otherMappings, 1);
                        } else {
                            // Generated column.
                            $temp = Kwf_SourceMaps_Base64VLQ::decode($otherMappings);
                            $str .= Kwf_SourceMaps_Base64VLQ::encode($temp['value']);
                            $otherMappings = $temp['rest'];

                            if (strlen($otherMappings) > 0 && !preg_match(self::$_mappingSeparator, $otherMappings[0])) {
                                // Original source.
                                $temp = Kwf_SourceMaps_Base64VLQ::decode($otherMappings);
                                $str .= Kwf_SourceMaps_Base64VLQ::encode($temp['value']);
                                $otherMappings = $temp['rest'];

                                // Original line.
                                $temp = Kwf_SourceMaps_Base64VLQ::decode($otherMappings);
                                $str .= Kwf_SourceMaps_Base64VLQ::encode($temp['value']);
                                $otherMappings = $temp['rest'];

                                // Original column.
                                $temp = Kwf_SourceMaps_Base64VLQ::decode($otherMappings);
                                $str .= Kwf_SourceMaps_Base64VLQ::encode($temp['value']);
                                $otherMappings = $temp['rest'];

                                if (strlen($otherMappings) > 0 && !preg_match(self::$_mappingSeparator, $otherMappings[0])) {
                                    // Original name.
                                    $temp = Kwf_SourceMaps_Base64VLQ::decode($otherMappings);
                                    $str .= Kwf_SourceMaps_Base64VLQ::encode($temp['value'] + $previousFileNamesCount - $previousFileLast->name);
                                    $otherMappings = $temp['rest'];
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }

        $this->_map->mappings .= $str . $otherMappings;

        $this->_map->{'_x_org_koala-framework_last'}->source += $data->{'_x_org_koala-framework_last'}->source+1;
        $this->_map->{'_x_org_koala-framework_last'}->name += $data->{'_x_org_koala-framework_last'}->name+1;
        $this->_map->{'_x_org_koala-framework_last'}->originalLine = $data->{'_x_org_koala-framework_last'}->originalLine;
        $this->_map->{'_x_org_koala-framework_last'}->originalColumn = $data->{'_x_org_koala-framework_last'}->originalColumn;
    }

    /**
     * Returns the contents of the minified file
     */
    public function getFileContents()
    {
        return $this->_fileContents;
    }

    /**
     * Returns the contents of the source map as string
     *
     * @return string
     */
    public function getMapContents($includeLastExtension = true)
    {
        if ($this->_mappingsChanged) $this->_generateMappings();
        if ($includeLastExtension && !isset($this->_map->{'_x_org_koala-framework_last'})) {
            $this->_addLastExtension();
        }
        return json_encode($this->_map);
    }

    /**
     * Returns the contents of the source map as object (that can be json_encoded)
     *
     * @return stdObject
     */
    public function getMapContentsData($includeLastExtension = true)
    {
        if ($this->_mappingsChanged) $this->_generateMappings();
        if ($includeLastExtension && !isset($this->_map->{'_x_org_koala-framework_last'})) {
            $this->_addLastExtension();
        }
        return $this->_map;
    }

    /**
     * Save the source map to a file
     *
     * @param string file name the source map should be saved to
     * @param string optional file name the minified file should be saved to
     */
    public function save($mapFileName, $fileFileName = null)
    {
        if ($fileFileName !== null) file_put_contents($fileFileName, $this->_fileContents);
        file_put_contents($mapFileName, $this->getMapContents());
    }
}
