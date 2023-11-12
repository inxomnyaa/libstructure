<?php

declare(strict_types=1);

namespace inxomnyaa\libstructure\format;

use Exception;
use GlobalLogger;
use inxomnyaa\libblockstate\BlockStatesParser;
use pocketmine\data\bedrock\block\BlockStateDeserializeException;
use pocketmine\data\bedrock\block\BlockStateSerializeException;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\Server;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use pocketmine\world\format\PalettedBlockArray;
use pocketmine\world\World;
use function array_search;
use function count;
use function range;
use function var_dump;

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
//			print_r($paletteData);
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
							if(($stateId = $palette[$i] ?? null) !== null){
								try{
									$layers[$layer]->set($x, $y, $z, $stateId);
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
//			var_dump($data->palettes);
			$data->writePalette($paletteName, $palettedBlockArray);
//			var_dump($data->palettes[$paletteName][MCStructure::TAG_PALETTE_BLOCK_PALETTE]);

			//write block indices
			for($x = 0; $x < $structure->size->getX(); $x++){
				for($y = 0; $y < $structure->size->getY(); $y++){
					for($z = 0; $z < $structure->size->getZ(); $z++){
						$stateId = $palettedBlockArray->get($x, $y, $z);
						$offset = (int) (($x * $structure->size->getZ() * $structure->size->getY()) + ($y * $structure->size->getZ()) + $z);
						if($stateId === 0xff_ff_ff_ff){
							$data->blockIndices[$layer][$offset] = -1;
							continue;
						}

						$index = array_search($stateId, $indices, true);
						if($index === false){
							$index = count($indices);
							$indices[$index] = $stateId;
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
		var_dump("LAYERS", count($data->blockIndices));

		return $data;
	}

	/** @phpstan-return array<string, int> */
	private function parsePalette(string $paletteName) : array{
		$blockStateDeserializer = GlobalBlockStateHandlers::getDeserializer();
		$blockDataUpgrader = GlobalBlockStateHandlers::getUpgrader();
		$palette = [];
		foreach($this->palettes[$paletteName][MCStructure::TAG_PALETTE_BLOCK_PALETTE] as $i => $blockStateNbt){

			//TODO: remember data for unknown states so we can implement them later
			try{
				$blockStateData = $blockDataUpgrader->upgradeBlockStateNbt($blockStateNbt);
			}catch(BlockStateDeserializeException $e){
				//while not ideal, this is not a fatal error
				Server::getInstance()->getLogger()->error("Failed to upgrade blockstate: " . $e->getMessage() . " offset $i in palette, blockstate NBT: " . $blockStateNbt->toString());
				$palette[$i] = $blockStateDeserializer->deserialize(GlobalBlockStateHandlers::getUnknownBlockStateData());
				continue;
			}
			try{
				$palette[$i] = $blockStateDeserializer->deserialize($blockStateData);
			}catch(BlockStateDeserializeException $e){
				Server::getInstance()->getLogger()->error("Failed to deserialize blockstate: " . $e->getMessage() . " offset $i in palette, blockstate NBT: " . $blockStateNbt->toString());
				$palette[$i] = $blockStateDeserializer->deserialize(GlobalBlockStateHandlers::getUnknownBlockStateData());
			}
		}
		return $palette;
	}

	private function writePalette(string $paletteName, PalettedBlockArray $palette) : void{
		$blockStateSerializer = GlobalBlockStateHandlers::getSerializer();
		$i = 0;
		$palette->collectGarbage();
		foreach($palette->getPalette() as $stateId){
			if($stateId !== 0xff_ff_ff_ff){//TODO check if this still works/is needed
				try{
					$this->palettes[$paletteName][MCStructure::TAG_PALETTE_BLOCK_PALETTE][$i] = $blockStateSerializer->serialize($stateId)->toNbt();
					$i++;
				}catch(BlockStateSerializeException $e){
					//while not ideal, this is not a fatal error
					Server::getInstance()->getLogger()->error("Failed to serialize blockstate: " . $e->getMessage() . " offset $i in palette, blockstate ID: " . $stateId);
				}
			}
		}
	}


}