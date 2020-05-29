<?php

namespace xenialdan\libstructure\format;

use Ds\Map;
use pocketmine\utils\BinaryDataException;
use pocketmine\utils\Utils;
use SplFileObject;

class NBSFile
{

    /**
     * Parses a *.mcstructure file from an InputStream
     * @param string $path path to the .mcstructure file
     * @return MCStructure object representing the given .mcstructure file
     * @throws \RuntimeException
     * @see MCStructure
     */
    public static function parse(string $path): ?MCStructure
    {
        // int => Layer
        $layerHashMap = new Map();

        ### HEADER ###
        try {
            $path = Utils::cleanPath(realpath($path));
            $file = new SplFileObject($path);
            $file->rewind();
            //TODO test
            $fread = $file->fread($file->getSize());
            if ($fread === false) throw new \StringOutOfBoundsException("Could not read file $path");
            (new LittleEndianNBTSerializer())->read($fread)//todo import
            $namedTag = (new NetworkLittleEndianNBTStream())->read($contentsStateNBT);
            //Load
            $vs = $namedTag->getList('structure_world_origin');
            $structureWorldOrigin = new Vector3($vs[0],$vs[1],$vs[2]);
            //TODO continue here
        } catch (\Exception $e) {
            print $e;
        }
        return null;
    }

    /**
     * Sets a note at a tick in a song
     * @param int $layerIndex
     * @param int $ticks
     * @param int $instrument
     * @param int $key
     * @param Map $layerHashMap
     */
    private static function setNote(int $layerIndex, int $ticks, int $instrument, int $key, Map &$layerHashMap): void
    {
        $layer = $layerHashMap->get($layerIndex, new Layer("", 100));
        #if ($layer === null) {
        #    $layer = new Layer();
        if (!$layerHashMap->hasKey($layerIndex)) $layerHashMap->put($layerIndex, $layer);
        #}
        $layer->setNote($ticks, new Note($instrument, $key));
    }
}