<?php

declare(strict_types=1);

namespace xenialdan\libstructure\format;

use Exception;
use GlobalLogger;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\world\format\PalettedBlockArray;
use pocketmine\world\World;
use xenialdan\libblockstate\BlockStatesParser;
use function range;
use function var_dump;

class MCStructureData{
	/**
	 * @var int[]
	 * @phpstan-var array<int, array<int, int>>
	 * layer => index
	 */
	public array $blockIndices = [];
	public array $entities = [];
	/** @phpstan-var array<string, array<int, CompoundTag>> */
	public array $palettes = [];
	public array $blockEntity = [];

	private array $layers = [];
	/** @phpstan-var array<string, CompoundTag> */
	private array $blockPositionData = [];

	public static function fromNBT(?CompoundTag $compoundTag) : MCStructureData{
		$data = new MCStructureData();
		$blockIndices = $compoundTag->getListTag(MCStructure::TAG_BLOCK_INDICES);
		/**
		 * @var int     $layer
		 * @var ListTag $list
		 */
		foreach($blockIndices as $layer => $list){
			$data->blockIndices[$layer] = $list->getAllValues();
		}
		$palettes = $compoundTag->getCompoundTag(MCStructure::TAG_PALETTE);
		foreach($palettes as $paletteName => $paletteData){
			$data->palettes[$paletteName] = $paletteData->getListTag(MCStructure::TAG_PALETTE_BLOCK_PALETTE)?->getValue();
			$data->blockPositionData[$paletteName] = $paletteData->getCompoundTag(MCStructure::TAG_PALETTE_BLOCK_POSITION_DATA);
		}
		return $data;
	}

	//toNBT

	public function parse(MCStructure $structure) : MCStructure{//TODO layer or palette parameter?
		$structure->usePalette(MCStructure::TAG_PALETTE_DEFAULT);//TODO
		$paletteName = $structure->getPaletteName();//TODO check if empty/not set
		//TODO check if palette was already parsed
		/** @var PalettedBlockArray[] $layers */
		$layers = [];

		$palette = $this->parsePalette($paletteName);

		foreach($this->blockIndices as $layer => $indices){
			$layers[$layer] = new PalettedBlockArray(0xff_ff_ff_ff);

			//positions
			$l = $structure->size->getZ();
			$h = $structure->size->getY();
			foreach(range(0, $structure->size->getZ() - 1) as $z){
				foreach(range(0, $structure->size->getY() - 1) as $y){
					foreach(range(0, $structure->size->getX() - 1) as $x){
						$offset = (int) (($x * $l * $h) + ($y * $l) + $z);

						if(($i = $this->blockIndices[$layer][$offset] ?? -1) !== -1){
							if(($fullId = $palette[$i] ?? null) !== null){
								try{
									$layers[$layer]->set($x, $y, $z, $fullId);
								}catch(Exception $e){
									GlobalLogger::get()->logException($e);
								}
							}
						}
						//nbt
						if($this->blockPositionData[$paletteName]->getTag((string) $offset) !== null){
							/** @var CompoundTag<CompoundTag> $tag1 */
							$tag1 = $this->blockPositionData[$paletteName]->getCompoundTag((string) $offset);
							$structure->blockEntities[World::blockHash($x, $y, $z)] = $tag1->getCompoundTag(MCStructure::TAG_PALETTE_BLOCK_ENTITY_DATA);
						}
					}
				}
			}
		}

		$structure->setLayers($layers);

		return $structure;
	}

	/** @phpstan-return array<string, int> */
	private function parsePalette(string $paletteName) : array{
		/** @var BlockStatesParser $blockStatesParser */
		$blockStatesParser = BlockStatesParser::getInstance();
		$palette = [];
		foreach($this->palettes[$paletteName] as $index => $blockStateTag){
			$blockState = $blockStatesParser->getFromCompound($blockStateTag);
			$palette[$index] = $blockState->getFullId();
		}
		return $palette;
	}

	//writePalette
	//writeNBT


}