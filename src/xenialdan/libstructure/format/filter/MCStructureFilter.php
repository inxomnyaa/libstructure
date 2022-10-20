<?php

declare(strict_types=1);

namespace xenialdan\libstructure\format\filter;

use pocketmine\nbt\NoSuchTagException;
use pocketmine\nbt\UnexpectedTagTypeException;
use pocketmine\world\format\PalettedBlockArray;
use xenialdan\libblockstate\BlockStatesParser;
use xenialdan\libblockstate\exception\BlockQueryParsingFailedException;

class MCStructureFilter{

	/**
	 * Returns only blocks contained in $fullIds
	 *
	 * @param PalettedBlockArray $layer
	 * @param array              $fullIds
	 *
	 * @phpstan-param list<int>  $fullIds
	 *
	 * @return PalettedBlockArray
	 */
	public static function with(PalettedBlockArray $layer, array $fullIds = []) : PalettedBlockArray{
		$layer = clone $layer;
		$palette = $layer->getPalette();
		foreach($palette as $id){
			if($id === 0xff_ff_ff_ff) continue;
			if(!in_array($id, $fullIds, true)){
				$layer->replaceAll($id, -1);
			}
		}
		return $layer;
	}

	/**
	 * Returns all blocks except those contained in $fullIds
	 *
	 * @param PalettedBlockArray $layer
	 * @param array              $fullIds
	 *
	 * @phpstan-param list<int>  $fullIds
	 *
	 * @return PalettedBlockArray
	 */
	public static function except(PalettedBlockArray $layer, array $fullIds = []) : PalettedBlockArray{
		$layer = clone $layer;
		$palette = $layer->getPalette();
		foreach($palette as $id){
			if($id === 0xff_ff_ff_ff) continue;
			if(in_array($id, $fullIds, true)){
				$layer->replaceAll($id, -1);
			}
		}
		return $layer;
	}

	/**
	 * Returns only blocks that have the same string ID as any contained in $blockIds
	 *
	 * @param PalettedBlockArray   $layer
	 * @param string[]             $blockIds
	 *
	 * @phpstan-param list<string> $blockIds
	 *
	 * @return PalettedBlockArray
	 */
	public static function withBlockIds(PalettedBlockArray $layer, array $blockIds = []) : PalettedBlockArray{
		$layer = clone $layer;
		$palette = $layer->getPalette();
		/** @var BlockStatesParser $blockStatesParser */
		$blockStatesParser = BlockStatesParser::getInstance();
		foreach($palette as $id){
			if($id === 0xff_ff_ff_ff) continue;
			$blockState = $blockStatesParser->getFullId($id);
			if(!in_array($blockState->state->getId(), $blockIds, true)){
				$layer->replaceAll($id, -1);
			}
		}
		return $layer;
	}

	/**
	 * Returns all blocks except those that have the same string ID as any contained in $blockIds
	 *
	 * @param PalettedBlockArray   $layer
	 * @param string[]             $blockIds
	 *
	 * @phpstan-param list<string> $blockIds
	 *
	 * @return PalettedBlockArray
	 */
	public static function exceptBlockIds(PalettedBlockArray $layer, array $blockIds = []) : PalettedBlockArray{
		$layer = clone $layer;
		$palette = $layer->getPalette();
		/** @var BlockStatesParser $blockStatesParser */
		$blockStatesParser = BlockStatesParser::getInstance();
		foreach($palette as $id){
			if($id === 0xff_ff_ff_ff) continue;
			$blockState = $blockStatesParser->getFullId($id);
			if(in_array($blockState->state->getId(), $blockIds, true)){
				$layer->replaceAll($id, -1);
			}
		}
		return $layer;
	}

	/**
	 * Returns only blocks that have any blockstates contained in $blockStates
	 *
	 * @param PalettedBlockArray   $layer
	 * @param string[]             $blockStates
	 *
	 * @phpstan-param list<string> $blockStates
	 *
	 * @return PalettedBlockArray
	 */
	public static function withBlockStates(PalettedBlockArray $layer, array $blockStates = []) : PalettedBlockArray{
		$layer = clone $layer;
		$palette = $layer->getPalette();
		/** @var BlockStatesParser $blockStatesParser */
		$blockStatesParser = BlockStatesParser::getInstance();
		foreach($palette as $id){
			if($id === 0xff_ff_ff_ff) continue;
			$blockState = $blockStatesParser->getFullId($id);
			$compoundTag = $blockState->state->getBlockState()->getCompoundTag("states");
			foreach($blockStates as $blockState){
				if($compoundTag->getTag($blockState) === null){
					$layer->replaceAll($id, -1);
				}
			}
		}
		return $layer;
	}

	/**
	 * Returns only blocks that have any blockstates contained in $blockStates and their values match
	 *
	 * @param PalettedBlockArray           $layer
	 * @param string[]                     $blockStates
	 *
	 * @phpstan-param array<string, mixed> $blockStates
	 *
	 * @return PalettedBlockArray
	 */
	public static function withBlockStatesAndValues(PalettedBlockArray $layer, array $blockStates = []) : PalettedBlockArray{
		$layer = clone $layer;
		$palette = $layer->getPalette();
		/** @var BlockStatesParser $blockStatesParser */
		$blockStatesParser = BlockStatesParser::getInstance();
		foreach($palette as $id){
			if($id === 0xff_ff_ff_ff) continue;
			$blockState = $blockStatesParser->getFullId($id);
			$compoundTag = $blockState->state->getBlockState()->getCompoundTag("states");
			foreach($blockStates as $stateName => $stateValue){
				if(!(($state = $compoundTag->getTag($stateName)) !== null && $state->getValue() === $stateValue)){
					$layer->replaceAll($id, -1);
				}
			}
		}
		return $layer;
	}

	/**
	 * Replaces the $data keys with the $blockIds values
	 *
	 * @param PalettedBlockArray      $layer
	 * @param array                   $blockIds full ids
	 *
	 * @phpstan-param array<int, int> $blockIds full ids
	 *
	 * @return PalettedBlockArray
	 */
	public static function replace(PalettedBlockArray $layer, array $blockIds = []) : PalettedBlockArray{
		$layer = clone $layer;
		foreach($blockIds as $from => $to){
			$layer->replaceAll($from, $to);
		}
		return $layer;
	}

	/**
	 * Replaces all blockstates with new values from $blockStates if the blockstate exits
	 *
	 * @param PalettedBlockArray           $layer
	 * @param array[]                      $blockStates keys are blockstates, values are the new values
	 *
	 * @phpstan-param array<string, mixed> $blockStates keys are blockstates, values are the new values
	 * @return PalettedBlockArray
	 * @throws NoSuchTagException Technically impossible to throw
	 */
	public static function replaceBlockStates(PalettedBlockArray $layer, array $blockStates = []) : PalettedBlockArray{
		$layer = clone $layer;
		$palette = $layer->getPalette();
		/** @var BlockStatesParser $blockStatesParser */
		$blockStatesParser = BlockStatesParser::getInstance();
		foreach($palette as $id){
			if($id === 0xff_ff_ff_ff) continue;
			$blockState = $blockStatesParser->getFullId($id);
			try{
				$newBlockState = $blockState->replaceBlockStateValues($blockStates, false);//automatically skips if no changes are made
				$layer->replaceAll($blockState->getFullId(), $newBlockState->getFullId());
			}catch(UnexpectedTagTypeException | BlockQueryParsingFailedException){
			}
		}
		return $layer;
	}
}