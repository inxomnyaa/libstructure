<?php

namespace xenialdan\libstructure\format;

use InvalidArgumentException;
use pocketmine\math\Vector3;
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\nbt\NBT;
use pocketmine\nbt\NbtDataException;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\UnexpectedTagTypeException;
use pocketmine\Server;
use pocketmine\utils\Filesystem;
use pocketmine\utils\MainLogger;
use UnexpectedValueException;
use xenialdan\libstructure\exception\StructureFileException;
use xenialdan\libstructure\exception\StructureFormatException;

class MCStructureFile
{
	//https://github.com/df-mc/structure/blob/651c5d323dbfb24991dafdb72129e2a8a478a81b/structure.go#L66-L91
	public const TAG_STRUCTURE_WORLD_ORIGIN = 'structure_world_origin';
	public const TAG_FORMAT_VERSION = 'format_version';
	public const TAG_SIZE = 'size';
	public const TAG_STRUCTURE = 'structure';
	public const TAG_BLOCK_INDICES = 'block_indices';
	public const TAG_ENTITIES = 'entities';
	public const TAG_PALETTE = 'palette';
	public const TAG_PALETTE_DEFAULT = 'default';
	public const TAG_PALETTE_BLOCK_PALETTE = 'block_palette';
	public const TAG_PALETTE_BLOCK_POSITION_DATA = 'block_position_data';
	public const EXTENSION_MCSTRUCTURE = '.mcstructure';

	/**
	 * Parses a *.mcstructure file
	 * @param string $path path to the .mcstructure file
	 * @return MCStructure object representing the given .mcstructure file
	 * @throws InvalidArgumentException
	 * @throws StructureFileException
	 * @see MCStructure
	 */
	public static function parse(string $path): MCStructure
	{
		$pathext = pathinfo($path, PATHINFO_EXTENSION);
		if ('.' . strtolower($pathext) !== self::EXTENSION_MCSTRUCTURE) throw new InvalidArgumentException("File extension $pathext for file $path is not " . self::EXTENSION_MCSTRUCTURE);
		$path = Filesystem::cleanPath(realpath($path));
		$fread = file_get_contents($path);
		if ($fread === false) throw new StructureFileException("Could not read file $path");
		try {
			$namedTag = (new LittleEndianNBTSerializer())->read($fread)->mustGetCompoundTag();
			#Server::getInstance()->getLogger()->debug($namedTag->toString(2));
			//version
			$version = $namedTag->getInt(self::TAG_FORMAT_VERSION);
			if ($version === null) throw new StructureFormatException(self::TAG_FORMAT_VERSION . " must be present and valid integer");
			//structure origin
			$structureWorldOrigin = self::parseVec3($namedTag, self::TAG_STRUCTURE_WORLD_ORIGIN, true);//TODO check if optional (makes it V3{0,0,0})
			//size
			$size = self::parseVec3($namedTag, self::TAG_SIZE, false);
			return new MCStructure($version, $structureWorldOrigin, $size, $namedTag->getCompoundTag(self::TAG_STRUCTURE));
		} catch (NbtDataException $e) {
			Server::getInstance()->getLogger()->logException($e);
		} catch (UnexpectedValueException $e) {
			Server::getInstance()->getLogger()->logException($e);
		} catch (UnexpectedTagTypeException $e) {
			Server::getInstance()->getLogger()->logException($e);
		}
		throw new StructureFileException("Failed to read $path");
	}

	/**
	 * @param CompoundTag $nbt
	 * @param string $tagName
	 * @param bool $optional
	 * @return Vector3
	 * @throws UnexpectedValueException
	 * @throws UnexpectedTagTypeException
	 */
	private static function parseVec3(CompoundTag $nbt, string $tagName, bool $optional): Vector3
	{
		$pos = $nbt->getListTag($tagName);
		if ($pos === null and $optional) {
			return new Vector3(0, 0, 0);
		}
		if (!($pos instanceof ListTag) or $pos->getTagType() !== NBT::TAG_Int) {
			throw new UnexpectedValueException("'$tagName' should be a List<Int>");
		}
		/** @var IntTag[] $values */
		$values = $pos->getValue();
		if (count($values) !== 3) {
			throw new UnexpectedValueException("Expected exactly 3 entries in '$tagName' tag");
		}
		return new Vector3($values[0]->getValue(), $values[1]->getValue(), $values[2]->getValue());
	}
}