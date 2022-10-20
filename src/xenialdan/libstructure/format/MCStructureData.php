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
use function array_search;
use function count;
use function range;

class MCStructureData{
	/**
	 * @var int[]
	 * @phpstan-var array<int, list<int>>
	 * layer => index
	 */
	public array $blockIndices = [];
	/**
	 * @var CompoundTag[]
	 * @phpstan-var list<CompoundTag>
	 */
	public array $entities = [];
	//TODO				palettename  tagName
	/** @phpstan-var array<string, array<string, list<int>|CompoundTag<string, CompoundTag>> */
	public array $palettes = [];

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
			$data->palettes[$paletteName] = [
				MCStructure::TAG_PALETTE_BLOCK_PALETTE => $paletteData->getListTag(MCStructure::TAG_PALETTE_BLOCK_PALETTE)?->getValue(),
				//compound -> int => compound data
				MCStructure::TAG_PALETTE_BLOCK_POSITION_DATA => $paletteData->getCompoundTag(MCStructure::TAG_PALETTE_BLOCK_POSITION_DATA)
			];
		}
		$data->entities = $compoundTag->getListTag(MCStructure::TAG_ENTITIES)->getValue();
		return $data;
	}

	//toNBT
	public function toNBT() : CompoundTag{
		$blockIndices = new ListTag();
		foreach($this->blockIndices as $indices){
			$walk = $indices;
			array_walk($walk, static function(&$value, $key){
				$value = new IntTag($value);
			});
			/** @var ListTag[] $walk */
			$blockIndices->push(new ListTag($walk, NBT::TAG_Int));
		}
		$compoundTag = (new CompoundTag())->setTag(MCStructure::TAG_BLOCK_INDICES, $blockIndices);
		$palettes = new CompoundTag();
		foreach($this->palettes as $paletteName => $paletteData){
			$palette = (new CompoundTag())
				->setTag(MCStructure::TAG_PALETTE_BLOCK_PALETTE, new ListTag($paletteData[MCStructure::TAG_PALETTE_BLOCK_PALETTE]))
				->setTag(MCStructure::TAG_PALETTE_BLOCK_POSITION_DATA, $paletteData[MCStructure::TAG_PALETTE_BLOCK_POSITION_DATA]);
			$palettes->setTag($paletteName, $palette);
		}
		$compoundTag->setTag(MCStructure::TAG_ENTITIES, new ListTag($this->entities, NBT::TAG_Compound));
		$compoundTag->setTag(MCStructure::TAG_PALETTE, $palettes);
		return $compoundTag;
	}

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

//						if(($i = $this->blockIndices[$layer][$offset] ?? -1) !== -1){
						if(($i = $indices[$offset] ?? -1) !== -1){
							if(($fullId = $palette[$i] ?? null) !== null){
								try{
									$layers[$layer]->set($x, $y, $z, $fullId);
								}catch(Exception $e){
									GlobalLogger::get()->logException($e);
								}
							}
						}
						//nbt
						if($this->palettes[$paletteName][MCStructure::TAG_PALETTE_BLOCK_POSITION_DATA]->getTag((string) $offset) !== null){
							$tag1 = $this->palettes[$paletteName][MCStructure::TAG_PALETTE_BLOCK_POSITION_DATA]->getCompoundTag((string) $offset);
							$structure->blockEntities[World::blockHash($x, $y, $z)] = $tag1->getCompoundTag(MCStructure::TAG_PALETTE_BLOCK_ENTITY_DATA);
						}
					}
				}
			}
		}

		$structure->setLayers($layers);

		return $structure;
	}

	//create MCStructureData from MCStructure
	public static function fromStructure(MCStructure $structure) : MCStructureData{
		$data = new MCStructureData();
		$paletteName = $structure->getPaletteName();
		/** @phpstan-var list<int> $indices */
		$indices = [];
		foreach($structure->getLayers() as $layer => $palettedBlockArray){
			$palettedBlockArray = $structure->getPalettedBlockArray($layer);
			$data->writePalette($paletteName, $palettedBlockArray);

			//write block indices
			for($x = 0; $x < $structure->size->getX(); $x++){
				for($y = 0; $y < $structure->size->getY(); $y++){
					for($z = 0; $z < $structure->size->getZ(); $z++){
						$fullId = $palettedBlockArray->get($x, $y, $z);
						$offset = (int) (($x * $structure->size->getZ() * $structure->size->getY()) + ($y * $structure->size->getZ()) + $z);
						if($fullId === 0xff_ff_ff_ff){
							$data->blockIndices[$layer][$offset] = -1;
							continue;
						}

						$index = array_search($fullId, $indices, true);
						if($index === false){
							$index = count($indices);
							$indices[$index] = $fullId;
						}
						$data->blockIndices[$layer][$offset] = $index;
					}
				}
			}
		}

		foreach($structure->blockEntities as $hash => $blockEntity){
			World::getBlockXYZ($hash, $x, $y, $z);
			$offset = (int) (($x * $structure->size->getZ() * $structure->size->getY()) + ($y * $structure->size->getZ()) + $z);
			if(!isset($data->palettes[$paletteName][MCStructure::TAG_PALETTE_BLOCK_POSITION_DATA])){
				$data->palettes[$paletteName][MCStructure::TAG_PALETTE_BLOCK_POSITION_DATA] = new CompoundTag();
			}
			$data->palettes[$paletteName][MCStructure::TAG_PALETTE_BLOCK_POSITION_DATA]->setTag((string) $offset, (new CompoundTag())->setTag(MCStructure::TAG_PALETTE_BLOCK_ENTITY_DATA, $blockEntity));
		}

		$data->entities = $structure->getEntitiesRaw();

		return $data;
	}

	/** @phpstan-return array<string, int> */
	private function parsePalette(string $paletteName) : array{
		/** @var BlockStatesParser $blockStatesParser */
		$blockStatesParser = BlockStatesParser::getInstance();
		$palette = [];
		foreach($this->palettes[$paletteName][MCStructure::TAG_PALETTE_BLOCK_PALETTE] as $index => $blockStateTag){
			$blockState = $blockStatesParser->getFromCompound($blockStateTag);
			$palette[$index] = $blockState->getFullId();
		}
		return $palette;
	}

	/** @phpstan-param array<string,int> $palette */
	private function writePalette(string $paletteName, PalettedBlockArray $palette) : void{
		/** @var BlockStatesParser $blockStatesParser */
		$blockStatesParser = BlockStatesParser::getInstance();
		#$this->palettes[$paletteName] = [];
		$index = 0;
		$palette->collectGarbage();
		foreach($palette->getPalette() as $fullId){
			if($fullId !== 0xff_ff_ff_ff){
				$blockState = $blockStatesParser->getFullId($fullId);
				$this->palettes[$paletteName][MCStructure::TAG_PALETTE_BLOCK_PALETTE][$index] = $blockState->state->getBlockState();
				$index++;
			}
		}
	}


}