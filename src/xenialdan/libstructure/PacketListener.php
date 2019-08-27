<?php

namespace xenialdan\libstructure;

use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\network\mcpe\protocol\StructureBlockUpdatePacket;
use pocketmine\network\mcpe\protocol\StructureTemplateDataExportRequestPacket;
use pocketmine\network\mcpe\protocol\StructureTemplateDataExportResponsePacket;
use pocketmine\plugin\Plugin;
use pocketmine\Server;

class PacketListener implements Listener
{
    /** @var Plugin|null */
    private static $registrant;

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
     */
    public static function register(Plugin $plugin): void
    {
        if (self::isRegistered()) {
            return;//silent return
        }

        self::$registrant = $plugin;
        $plugin->getServer()->getPluginManager()->registerEvents(new self, $plugin);
        PacketPool::registerPacket(new \xenialdan\libstructure\packet\StructureBlockUpdatePacket());
    }

    public function onDataPacketReceiveEvent(DataPacketReceiveEvent $e)
    {
        if ($e->getPacket() instanceof StructureBlockUpdatePacket) $this->onStructureBlockUpdatePacket($e);
        if ($e->getPacket() instanceof StructureTemplateDataExportRequestPacket) $this->onStructureTemplateDataExportRequestPacket($e);
        if ($e->getPacket() instanceof StructureTemplateDataExportResponsePacket) $this->onStructureTemplateDataExportResponsePacket($e);
    }

    private function onStructureBlockUpdatePacket(DataPacketReceiveEvent $e)
    {
        if (!($pk = $e->getPacket()) instanceof StructureBlockUpdatePacket) throw new \InvalidArgumentException(get_class($pk) . " is not a " . StructureBlockUpdatePacket::class);
        /** @var StructureBlockUpdatePacket $pk */
        var_dump($e->getPacket());
    }

    private function onStructureTemplateDataExportRequestPacket(DataPacketReceiveEvent $e)
    {
        if (!($pk = $e->getPacket()) instanceof StructureTemplateDataExportRequestPacket) throw new \InvalidArgumentException(get_class($e->getPacket()) . " is not a " . StructureTemplateDataExportRequestPacket::class);
        /** @var StructureTemplateDataExportRequestPacket $pk */
        Server::getInstance()->getLogger()->debug("Got StructureTemplateDataExportRequestPacket " . $e->getPacket()->getRemaining());
    }

    private function onStructureTemplateDataExportResponsePacket(DataPacketReceiveEvent $e)
    {
        if (!($pk = $e->getPacket()) instanceof StructureTemplateDataExportResponsePacket) throw new \InvalidArgumentException(get_class($e->getPacket()) . " is not a " . StructureTemplateDataExportResponsePacket::class);
        /** @var StructureTemplateDataExportResponsePacket $pk */
        Server::getInstance()->getLogger()->debug("Got StructureTemplateDataExportResponsePacket " . $e->getPacket()->getRemaining());
    }

}