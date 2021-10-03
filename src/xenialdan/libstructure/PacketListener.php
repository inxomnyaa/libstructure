<?php

namespace xenialdan\libstructure;

use InvalidArgumentException;
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\BlockLegacyIds;
use pocketmine\block\tile\TileFactory;
use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\StructureBlockUpdatePacket;
use pocketmine\network\mcpe\protocol\StructureTemplateDataRequestPacket;
use pocketmine\network\mcpe\protocol\StructureTemplateDataResponsePacket;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginException;
use RuntimeException;
use xenialdan\libstructure\block\StructureBlock;
use xenialdan\libstructure\tile\StructureBlockTags;
use xenialdan\libstructure\tile\StructureBlockTile;
use xenialdan\libstructure\window\StructureBlockInventory;

class PacketListener implements Listener
{
	/** @var Plugin|null */
	private static ?Plugin $registrant = null;

	public static function isRegistered(): bool
	{
		return self::$registrant instanceof Plugin;
	}

	public static function getRegistrant(): Plugin
	{
		return self::$registrant;
	}

	public static function unregister(): void
	{
		self::$registrant = null;
	}

	/**
	 * @param Plugin $plugin
	 * @throws PluginException|RuntimeException
	 */
	public static function register(Plugin $plugin): void
	{
		if (self::isRegistered()) {
			return;//silent return
		}

		self::$registrant = $plugin;
		try {
			TileFactory::getInstance()->register(StructureBlockTile::class, [StructureBlockTags::TAG_ID, "minecraft:structure_block"]);
			BlockFactory::getInstance()->register(new StructureBlock(new BlockIdentifier(BlockLegacyIds::STRUCTURE_BLOCK,0, null, StructureBlockTile::class), "Structure Block"), true);
		} catch (InvalidArgumentException) {
		}
		$plugin->getServer()->getPluginManager()->registerEvents(new self, $plugin);
	}

	public function onDataPacketReceiveEvent(DataPacketReceiveEvent $e)
	{
		if ($e->getPacket() instanceof StructureBlockUpdatePacket) $this->onStructureBlockUpdatePacket($e);
		if ($e->getPacket() instanceof StructureTemplateDataRequestPacket) $this->onStructureTemplateDataExportRequestPacket($e);
		if ($e->getPacket() instanceof StructureTemplateDataResponsePacket) $this->onStructureTemplateDataExportResponsePacket($e);
	}

	private function onStructureBlockUpdatePacket(DataPacketReceiveEvent $e)
	{
		if (!$e->getPacket() instanceof StructureBlockUpdatePacket) return;
		//** @var StructureBlockUpdatePacket $pk */
		var_dump($e->getPacket());//TODO remove
		$session = $e->getOrigin();
		$window = $session->getInvManager()->getWindow($session->getInvManager()->getCurrentWindowId());
		//Hack to close the inventory (client does not send inventory close packet for structure blocks)
		if($window instanceof StructureBlockInventory){
			$session->getPlayer()->removeCurrentWindow();
		}
	}

	private function onStructureTemplateDataExportRequestPacket(DataPacketReceiveEvent $e)
	{
		/** @var StructureTemplateDataRequestPacket $pk */
		$pk = $e->getPacket();
		#$player = $e->getOrigin()->getPlayer();
		if ($pk instanceof StructureTemplateDataRequestPacket) {
			var_dump($pk);//TODO remove
		}
	}

	private function onStructureTemplateDataExportResponsePacket(DataPacketReceiveEvent $e)
	{
		/** @var StructureTemplateDataResponsePacket $pk */
		$pk = $e->getPacket();
		#$player = $e->getOrigin()->getPlayer();
		if ($pk instanceof StructureTemplateDataResponsePacket) {
			var_dump($pk);//TODO remove
		}
	}
}