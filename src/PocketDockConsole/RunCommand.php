<?php

namespace PocketDockConsole;

use pocketmine\utils\TextFormat;
use pocketmine\command\Command;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\scheduler\PluginTask;
use pocketmine\Server;
use pocketmine\Player;

class RunCommand extends PluginTask {

    public function __construct($owner) {
        $this->owner = $owner;
        $interfaces = $this->getOwner()->getServer()->getInterfaces();
        $values = array_values($interfaces);
        $this->mainInterface = $values[0];
    }

    public function onRun($currentTick) {
        $buffer = $this->getOwner()->thread->getBuffer();
        if (substr($buffer, -1) == "\r" && $buffer && !$this->isJSON(trim($buffer))) {
            $buffer = trim($buffer);
            echo $buffer . "\n";
            $this->getOwner()->getServer()->dispatchCommand(new ConsoleCommandSender, $buffer);
            $this->getOwner()->thread->buffer     = "";
            $title                                = "\x1b]0;PocketMine-MP " . $this->getOwner()->getServer()->getPocketMineVersion() . " | Online " . count($this->getOwner()->getServer()->getOnlinePlayers()) . "/" . $this->getOwner()->getServer()->getMaxPlayers() . " | RAM " . round((memory_get_usage() / 1024) / 1024, 2) . "/" . round((memory_get_usage(true) / 1024) / 1024, 2) . " MB | U ". round($this->mainInterface->getUploadUsage() / 1024, 2) ." D ". round($this->mainInterface->getDownloadUsage() / 1024, 2) ." kB/s | TPS " . $this->getOwner()->getServer()->getTicksPerSecond() . "\x07";
            $this->getOwner()->thread->stuffTitle = $title;
            $this->updateInfo();
        } elseif ($this->isJSON(trim($buffer)) && trim($buffer) != "") {
            $this->parseJSON($buffer);
            $this->getOwner()->thread->buffer     = "";
            $title                                = "\x1b]0;PocketMine-MP " . $this->getOwner()->getServer()->getPocketMineVersion() . " | Online " . count($this->getOwner()->getServer()->getOnlinePlayers()) . "/" . $this->getOwner()->getServer()->getMaxPlayers() . " | RAM " . round((memory_get_usage() / 1024) / 1024, 2) . "/" . round((memory_get_usage(true) / 1024) / 1024, 2) . " MB | U ". round($this->mainInterface->getUploadUsage() / 1024, 2) ." D ". round($this->mainInterface->getDownloadUsage() / 1024, 2) ." kB/s | TPS " . $this->getOwner()->getServer()->getTicksPerSecond() . "\x07";
            $this->getOwner()->thread->stuffTitle = $title;
            $this->updateInfo();
        }
    }

    public function isJSON($string) {
        return !preg_match('/[^,:{}\\[\\]0-9.\\-+Eaeflnr-u \\n\\r\\t]/', preg_replace('/"(\\.|[^"\\\\])*"/', '', $string));
    }

    public function parseJSON($string) {
        $data = json_decode($string, true);
        $keys = array_keys($data);
        switch($keys[0]) {
            case "op":
                $this->getOwner()->getServer()->addOp($data[$keys[0]]['name']);
                $this->getOwner()->getLogger()->info($data[$keys[0]]['name'] . " is now op!");
                break;
            case "kick":
                safe_var_dump($this->getOwner()->getServer()->getPlayerExact($data[$keys[0]]['name']));
                if(($player = $this->getOwner()->getServer()->getPlayerExact($data[$keys[0]]['name'])) instanceof Player){
                    $player->kick();
                    $this->getOwner()->getLogger()->info($data[$keys[0]]['name'] . " has been kicked!");
                }
                break;
            case "ban":
                $this->getOwner()->getServer()->getNameBans()->addBan($data[$keys[0]]['name']);
                $this->getOwner()->getLogger()->info($data[$keys[0]]['name'] . " has been banned!");
                break;
            case "banip":
                safe_var_dump($this->getOwner()->getServer()->getPlayerExact($data[$keys[0]]['name']));
                if(($player = $this->getOwner()->getServer()->getPlayerExact($data[$keys[0]]['name'])) instanceof Player){
                    $this->getOwner()->getServer()->getIPBans()->addBan($player->getAddress());
                }
                break;
            case "unban":
                $this->getOwner()->getServer()->getNameBans()->remove($data[$keys[0]]['name']);
                $this->getOwner()->getLogger()->info($data[$keys[0]]['name'] . " has been unbanned!");
                break;
            case "deop":
                $this->getOwner()->getServer()->removeOp($data[$keys[0]]['name']);
                $this->getOwner()->getLogger()->info($data[$keys[0]]['name'] . " is no longer op!");
                break;
            case "unbanip":
                $this->getOwner()->getServer()->getIPBans()->remove($data[$keys[0]]['ip']);
                break;
            case "updateinfo":
                $this->updateInfo();
                break;
        }
    }

    public function updateInfo() {
        $data = array("players" => $this->sendPlayers(), "bans" => $this->sendNameBans(), "ipbans" => $this->sendIPBans(), "ops" => $this->sendOps());
        $this->getOwner()->thread->stuffToSend .= json_encode($data) . "\n";
        return true;
    }

    public function sendPlayers() {
        $names = array();
        $players = $this->getOwner()->getServer()->getOnlinePlayers();
        foreach($players as $p) {
            $names[] = $p->getName();
        }
        return $names;
    }

    public function sendNameBans() {
        $barray = array();
        $bans = $this->getOwner()->getServer()->getNameBans();
        $bans = $bans->getEntries();
        foreach($bans as $ban) {
            $barray[] = $ban->getName();
        }
        return $barray;
    }

    public function sendIPBans() {
        $barray = array();
        $bans = $this->getOwner()->getServer()->getIPBans();
        $bans = $bans->getEntries();
        foreach($bans as $ban) {
            $barray[] = $ban->getName();
        }
        return $barray;
    }

    public function sendOps() {
        $oarray = array();
        $ops = $this->getOwner()->getServer()->getOps();
        $ops = $ops->getAll(true);
        foreach($ops as $op) {
            $oarray[] = $op;
        }
        return $oarray;
    }

}
