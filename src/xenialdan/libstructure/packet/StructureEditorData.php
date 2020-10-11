<?php

declare(strict_types=1);

namespace xenialdan\libstructure\packet;

class StructureEditorData
{
    /** @var string */
    public $structureName;
    /** @var string */
    public $string2;
    /** @var bool */
    public $includePlayers;
    /** @var bool */
    public $showBoundingBox;
    /** @var int */
    public $mode;
    /** @var StructureSettings */
    public $structureSettings;
}