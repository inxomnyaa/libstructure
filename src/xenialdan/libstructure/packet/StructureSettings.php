<?php

declare(strict_types=1);

namespace xenialdan\libstructure\packet;

class StructureSettings
{
    /** @var string */
    public $paletteName;
    /** @var bool */
    public $ignoreEntities;
    /** @var bool */
    public $ignoreBlocks;
    /** @var int */
    public $sizeX;
    /** @var int */
    public $sizeY;
    /** @var int */
    public $sizeZ;
    /** @var int */
    public $offsetX;
    /** @var int */
    public $offsetY;
    /** @var int */
    public $offsetZ;
    /** @var int (long) */
    public $lastTouchedByPlayerId;
    /** @var int */
    public $rotation;
    /** @var int */
    public $mirror;
    /** @var float */
    public $integrityValue;
    /** @var int (uvarint) */
    public $integritySeed;

    public function __toString()
    {
        return PHP_EOL . implode(PHP_EOL, is_array($r = print_r($this, true))?$r:[$r]);
    }
}