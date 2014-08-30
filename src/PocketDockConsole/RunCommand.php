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
            $this->updateInfo();
        } elseif ($this->isJSON(trim($buffer)) && trim($buffer) != "") {
            $this->parseJSON($buffer);
            $this->getOwner()->thread->buffer     = "";
            $this->updateInfo();
        }
        if($this->getOwner()->thread->sendUpdate) {
            $this->updateInfo();
        }
        $this->getOwner()->thread->sendUpdate = false;
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
                if(($player = $this->getOwner()->getServer()->getPlayerExact($data[$keys[0]]['name'])) instanceof Player){
                    $this->getOwner()->getServer()->getIPBans()->addBan($player->getAddress());
                }
                break;
            case "unban":
                if(preg_match("/^([01]?\\d\\d?|2[0-4]\\d|25[0-5])\\.([01]?\\d\\d?|2[0-4]\\d|25[0-5])\\.([01]?\\d\\d?|2[0-4]\\d|25[0-5])\\.([01]?\\d\\d?|2[0-4]\\d|25[0-5])$/", $data[$keys[0]]['name'])){
                    $this->getOwner()->getServer()->getIPBans()->remove($data[$keys[0]]['name']);
                } else {
                    $this->getOwner()->getServer()->getNameBans()->remove($data[$keys[0]]['name']);
                }
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
            case "changegm":
                if(($player = $this->getOwner()->getServer()->getPlayerExact($data[$keys[0]]['name'])) instanceof Player){
                    $player->setGamemode($data[$keys[0]]['mode']);
                }
                break;
        }
    }

    public function updateInfo($user = "") {
        $data = array("players" => $this->sendPlayers($user), "bans" => $this->sendNameBans(), "ipbans" => $this->sendIPBans(), "ops" => $this->sendOps());
        $this->getOwner()->thread->jsonStream .= json_encode($data) . "\n";
        $title                                = "\x1b]0;PocketMine-MP " . $this->getOwner()->getServer()->getPocketMineVersion() . " | Online " . count($this->getOwner()->getServer()->getOnlinePlayers()) . "/" . $this->getOwner()->getServer()->getMaxPlayers() . " | RAM " . round((memory_get_usage() / 1024) / 1024, 2) . "/" . round((memory_get_usage(true) / 1024) / 1024, 2) . " MB | U ". round($this->mainInterface->getUploadUsage() / 1024, 2) ." D ". round($this->mainInterface->getDownloadUsage() / 1024, 2) ." kB/s | TPS " . $this->getOwner()->getServer()->getTicksPerSecond() . "\x07";
        $this->getOwner()->thread->stuffTitle = $title;
        return true;
    }

    public function sendPlayers($user) {
        $names = array();
        $players = $this->getOwner()->getServer()->getOnlinePlayers();
        foreach($players as $p) {
            $names[] = $p->getName();
        }
        if($user !== "") {
            $key = array_search($user, $names);
            unset($names[$key]);
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
