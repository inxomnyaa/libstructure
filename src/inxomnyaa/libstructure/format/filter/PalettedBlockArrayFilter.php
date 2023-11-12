<?php

declare(strict_types=1);

namespace inxomnyaa\libstructure\format\filter;

use Exception;
use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\nbt\tag\Tag;
use pocketmine\nbt\UnexpectedTagTypeException;
use pocketmine\Server;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use pocketmine\world\format\PalettedBlockArray;

class PalettedBlockArrayFilter{

	/**
	 * Returns only blocks contained in $stateIds
	 *
	 * @param PalettedBlockArray $layer
	 * @param int[]              $stateIds
	 *
	 * @phpstan-param list<int>  $stateIds
	 *
	 * @return PalettedBlockArray
	 */
	public static function withStateIds(PalettedBlockArray $layer, array $stateIds) : PalettedBlockArray{
		$layer = clone $layer;
		$palette = $layer->getPalette();
		foreach($palette as $id){
			if($id === 0xff_ff_ff_ff) continue;
			if(!in_array($id, $stateIds, true)){
				$layer->replaceAll($id, -1);
			}
		}
		return $layer;
	}

	/**
	 * Returns all blocks except those contained in $stateIds
	 *
	 * @param PalettedBlockArray $layer
	 * @param int[]              $stateIds
	 *
	 * @phpstan-param list<int>  $stateIds
	 *
	 * @return PalettedBlockArray
	 */
	public static function exceptStateIds(PalettedBlockArray $layer, array $stateIds) : PalettedBlockArray{
		$layer = clone $layer;
		$palette = $layer->getPalette();
		foreach($palette as $id){
			if($id === 0xff_ff_ff_ff) continue;
			if(in_array($id, $stateIds, true)){
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
	public static function withBlockIds(PalettedBlockArray $layer, array $blockIds) : PalettedBlockArray{
		$layer = clone $layer;
		$palette = $layer->getPalette();
		foreach($palette as $stateId){
			if($stateId === 0xff_ff_ff_ff) continue;
			$blockState = GlobalBlockStateHandlers::getSerializer()->serialize($stateId);
			if(!in_array($blockState->getName(), $blockIds, true)){
				$layer->replaceAll($stateId, -1);
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
	public static function exceptBlockIds(PalettedBlockArray $layer, array $blockIds) : PalettedBlockArray{
		$layer = clone $layer;
		$palette = $layer->getPalette();
		foreach($palette as $stateId){
			if($stateId === 0xff_ff_ff_ff) continue;
			$blockState = GlobalBlockStateHandlers::getSerializer()->serialize($stateId);
			if(in_array($blockState->getName(), $blockIds, true)){
				$layer->replaceAll($stateId, -1);
			}
		}
		return $layer;
	}

	/**
	 * Returns only blocks that have any blockstates contained in $blockStateNames
	 *
	 * @param PalettedBlockArray   $layer
	 * @param string[]             $blockStateNames
	 *
	 * @phpstan-param list<string> $blockStateNames
	 *
	 * @return PalettedBlockArray
	 */
	public static function withBlockStates(PalettedBlockArray $layer, array $blockStateNames) : PalettedBlockArray{
		$layer = clone $layer;
		$palette = $layer->getPalette();
		foreach($palette as $stateId){
			if($stateId === 0xff_ff_ff_ff) continue;
			$blockState = GlobalBlockStateHandlers::getSerializer()->serialize($stateId);
			$matches = false;
			foreach($blockStateNames as $blockStateName){
				if($blockState->getState($blockStateName) !== null){
					$matches = true;
					break;
				}
			}
			if(!$matches){
				$layer->replaceAll($stateId, -1);
			}
		}
		return $layer;
	}

	/**
	 * Returns only blocks that have any blockstates contained in $blockStates and their values match
	 *
	 * @param PalettedBlockArray         $layer
	 * @param Tag[]                      $blockStates keys are blockstates, values are the new Tags
	 *
	 * @phpstan-param array<string, Tag> $blockStates
	 *
	 * @return PalettedBlockArray
	 */
	public static function withBlockStatesAndValues(PalettedBlockArray $layer, array $blockStates) : PalettedBlockArray{
		$layer = clone $layer;
		$palette = $layer->getPalette();
		foreach($palette as $stateId){
			if($stateId === 0xff_ff_ff_ff) continue;
			$blockState = GlobalBlockStateHandlers::getSerializer()->serialize($stateId);
			$matches = false;
			foreach($blockStates as $blockStateName => $blockStateValue){
				if($blockState->getState($blockStateName) !== null && $blockState->getState($blockStateName)->equals($blockStateValue)){
					$matches = true;
					break;
				}
			}
			if(!$matches){
				$layer->replaceAll($stateId, -1);
			}
		}
		return $layer;
	}

	/**
	 * Replaces the $blockStateIds keys with the $blockStateIds values
	 *
	 * @param PalettedBlockArray      $layer
	 * @param array                   $blockStateIds state ids
	 *
	 * @phpstan-param array<int, int> $blockStateIds
	 *
	 * @return PalettedBlockArray
	 */
	public static function replaceStateIds(PalettedBlockArray $layer, array $blockStateIds) : PalettedBlockArray{
		$layer = clone $layer;
		foreach($blockStateIds as $from => $to){
			$layer->replaceAll($from, $to);
		}
		return $layer;
	}

	/**
	 * Replaces all blockstates with new values from $blockStates if the blockstate exists
	 *
	 * @param PalettedBlockArray         $layer
	 * @param Tag[]                      $blockStates keys are blockstates, values are the new Tags
	 *
	 * @phpstan-param array<string, Tag> $blockStates
	 * @return PalettedBlockArray
	 */
	public static function replaceBlockStates(PalettedBlockArray $layer, array $blockStates) : PalettedBlockArray{
		$layer = clone $layer;
		$palette = $layer->getPalette();
		foreach($palette as $stateId){
			if($stateId === 0xff_ff_ff_ff) continue;
			try{
				$blockState = GlobalBlockStateHandlers::getSerializer()->serialize($stateId);

				$newBlockState = self::replaceBlockStateValues($blockState, $blockStates);
				if($newBlockState->equals($blockState)) continue;

				$newStateId = GlobalBlockStateHandlers::getDeserializer()->deserialize($newBlockState);//fails if the blockstate doesn't exist
				$layer->replaceAll($stateId, $newStateId);
			}catch(Exception $e){
				Server::getInstance()->getLogger()->logException($e);
			}
		}
		return $layer;
	}

	/**
	 * @param Tag[]                      $blockStates keys are blockstates, values are the new Tags
	 *
	 * @phpstan-param array<string, Tag> $blockStates
	 */
	private static function replaceBlockStateValues(BlockStateData $blockStateData, array $blockStates) : BlockStateData{
		$newBlockStates = $blockStateData->getStates();
		foreach($blockStates as $blockStateName => $blockStateValue){
			if(($newBlockStates[$blockStateName] ?? null) !== null){
				if($newBlockStates[$blockStateName]->getType() !== $blockStateValue->getType()){
					throw new UnexpectedTagTypeException("Blockstate $blockStateName has a different type than the old value.");
				}
				$newBlockStates[$blockStateName] = $blockStateValue;
			}
		}
		return BlockStateData::current($blockStateData->getName(), $newBlockStates);
	}
}