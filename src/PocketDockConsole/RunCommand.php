<?php

namespace PocketDockConsole;

use pocketmine\utils\TextFormat;
use pocketmine\command\Command;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\scheduler\PluginTask;
use pocketmine\Server;

class RunCommand extends PluginTask {

    public function onRun($currentTick) {
        $buffer = $this->getOwner()->thread->getBuffer();
        if(substr($buffer, -1) == "\r") {
            $buffer = str_replace("\r", "", $buffer);
            echo $buffer . "\n";
            $this->getOwner()->getServer()->dispatchCommand(new ConsoleCommandSender, $buffer);
            $this->getOwner()->thread->buffer = "";
            $title = "\x1b]0;PocketMine-MP " . $this->getOwner()->getServer()->getPocketMineVersion() . " | Online " . count($this->getOwner()->getServer()->getOnlinePlayers()) . "/" . $this->getOwner()->getServer()->getMaxPlayers() . " | RAM " . round((memory_get_usage() / 1024) / 1024, 2) . "/" . round((memory_get_usage(true) / 1024) / 1024, 2) . " MB | U -1 D -1 kB/s | TPS " . $this->getOwner()->getServer()->getTicksPerSecond() . "\x07";
            $this->getOwner()->thread->stuffTitle = $title;
        }
    }
}
