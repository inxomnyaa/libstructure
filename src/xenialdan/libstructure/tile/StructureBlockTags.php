<?php

declare(strict_types=1);

namespace xenialdan\libstructure\tile;

interface StructureBlockTags
{
    const TAG_ID = "StructureBlock";
    const TAG_DATA = "data";
    const TAG_DATA_INVENTORY_MODEL = 0;
    const TAG_DATA_DATA = 1;
    const TAG_DATA_SAVE = 2;
    const TAG_DATA_LOAD = 3;
    const TAG_DATA_CORNER = 4;
    const TAG_DATA_EXPORT = 5;

}