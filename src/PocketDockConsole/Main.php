<?php

namespace PocketDockConsole;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use pocketmine\command\Command;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\command\CommandSender;
use pocketmine\scheduler\PluginTask;
use pocketmine\event\Listener;

class Main extends PluginBase implements Listener {

    public function onLoad() {
        $this->getLogger()->info(TextFormat::WHITE . "Loaded");
    }

    public function onEnable() {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->saveDefaultConfig();
        $this->reloadConfig();
        $this->getLogger()->info(TextFormat::DARK_GREEN . "Enabled");
        $this->thread = new SocksServer("0.0.0.0", $this->getConfig()->get("port"), $this->getServer()->getLogger(), $this->getServer()->getLoader(), $this->getConfig()->get("password"), stream_get_contents($this->getResource("PluginIndex.html")), $this->getConfig()->get("backlog"));
        $this->getServer()->getScheduler()->scheduleRepeatingTask(new RunCommand($this), 1);
        $this->lastBufferLine = "";
        $attachment           = new Attachment($this->thread);
        $this->getServer()->getLogger()->addAttachment($attachment);
    }

    public function onCommand(CommandSender $sender, Command $command, $label, array $args) {
        switch ($command->getName()) {
            case "consoleclients":
                if (!$sender->hasPermission("pocketdockconsole.command.consoleclients")) {
        			$sender->sendMessage(TextFormat::RED . "[PocketDockConsole] Get some permissions...");
        			return true;
        		}
                $authedclients = explode(";", $this->thread->connectedips);
                if (count($authedclients) < 2) {
                    $sender->sendMessage("[PocketDockConsole] There are no connected clients");
                    return true;
                }
                $sender->sendMessage("[PocketDockConsole] Connected client(s) are: " . implode("; ", $authedclients));
                return true;
            case "killclient":
                if (!$sender->hasPermission("pocketdockconsole.command.killclient")) {
                    $sender->sendMessage(TextFormat::RED . "[PocketDockConsole] Get some permissions...");
                    return true;
                }
                if (!isset($args[0])) {
                    $sender->sendMessage($command->getUsage());
                    return true;
                }
                $sender->sendMessage("[PocketDockConsole] Killing client: " . $args[0]);
                $this->thread->clienttokill = $args[0];
                return true;
            default:
                return false;
        }
    }

    public function onDisable() {
        $this->getLogger()->info(TextFormat::DARK_RED . "Disabled");
        $this->thread->stop();
    }

}
