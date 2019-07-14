<?php
namespace PocketDockConsole;

use pocketmine\utils\TextFormat;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\utils\Utils;
use pocketmine\Player;
use pocketmine\scheduler\Task;
use pocketmine\utils\Internet;

class RunCommand extends Task {

    public $temp = [];
    public $owner = null;

    public function __construct($owner) {
        $this->owner = $owner;
    }

    public function onRun($currentTick) {
        $buffer = $this->owner->thread->getBuffer();
        if (substr($buffer, 0, 6) == "{JSON}") {
            $buffer = str_replace("{JSON}", "", $buffer);
            $this->parseJSON(trim($buffer));
            $this->owner->thread->buffer = "";
            $this->updateInfo();
        } elseif (substr($buffer, -1) == "\r" && $buffer && !$this->isJSON(trim($buffer)) && !strpos($buffer, "{JSON}")) {
            $buffer = trim($buffer);
            echo $buffer . "\n";
            $this->owner->attachment->log("info", $buffer);
            $this->owner->getServer()->dispatchCommand(new ConsoleCommandSender, $buffer);
            $this->owner->thread->buffer = "";
            $this->updateInfo();
        } elseif ($this->isJSON(trim($buffer)) && trim($buffer) != "") {
            $this->parseJSON($buffer);
            $this->owner->thread->buffer = "";
            $this->updateInfo();
        }

        if ($this->owner->thread->sendUpdate) {
            $this->updateInfo();
            $this->owner->sendFiles();
        }

        $this->owner->thread->sendUpdate = false;

        if (substr($currentTick, -2) == 20) {
            $this->updateInfo();
            $this->owner->thread->sendUpdate = false;
            $this->owner->thread->buffer = "";
        }

        if ($this->owner->thread->clearstream) {
            $this->owner->attachment->stream = "";
            $this->owner->thread->clearstream = false;
        }

        $currentTickSubString = substr(strval($currentTick), -2);
        if ($currentTickSubString === "10") {
            $this->updateInfo();
        }
    }

    public function isJSON($string) {
        return !preg_match('/[^,:{}\\[\\]0-9.\\-+Eaeflnr-u \\n\\r\\t]/', preg_replace('/"(\\.|[^"\\\\])*"/', '', $string));
    }

    /*public function isJSON($string) {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }*/

    public function parseJSON($string) {
        $data = json_decode($string, true);
        if ($data == NULL) {
            return false;
            $this->owner->getLogger()->info("File is not JSON");
        }
        $keys = array_keys($data);
        switch ($keys[0]) {
            case "op":
                $this->owner->getServer()->addOp($data[$keys[0]]['name']);
                $this->owner->getLogger()->info($data[$keys[0]]['name'] . " is now op!");
            break;
            case "kick":
                if (($player = $this->owner->getServer()->getPlayerExact($data[$keys[0]]['name'])) instanceof Player) {
                    $player->kick();
                    $this->owner->getLogger()->info($data[$keys[0]]['name'] . " has been kicked!");
                } else {
                    $this->owner->getLogger()->info($data[$keys[0]]['name'] . " is not a valid player!");
                }
            break;
            case "ban":
                $this->owner->getServer()->getNameBans()->addBan($data[$keys[0]]['name']);
                $this->owner->getLogger()->info($data[$keys[0]]['name'] . " has been banned!");
            break;
            case "banip":
                if (($player = $this->owner->getServer()->getPlayerExact($data[$keys[0]]['name'])) instanceof Player) {
                    $this->owner->getServer()->getIPBans()->addBan($player->getAddress());
                }
            break;
            case "unban":
                if (preg_match("/^([01]?\\d\\d?|2[0-4]\\d|25[0-5])\\.([01]?\\d\\d?|2[0-4]\\d|25[0-5])\\.([01]?\\d\\d?|2[0-4]\\d|25[0-5])\\.([01]?\\d\\d?|2[0-4]\\d|25[0-5])$/", $data[$keys[0]]['name'])) {
                    $this->owner->getServer()->getIPBans()->remove($data[$keys[0]]['name']);
                } else {
                    $this->owner->getServer()->getNameBans()->remove($data[$keys[0]]['name']);
                }
                $this->owner->getLogger()->info($data[$keys[0]]['name'] . " has been unbanned!");
            break;
            case "deop":
                $this->owner->getServer()->removeOp($data[$keys[0]]['name']);
                $this->owner->getLogger()->info($data[$keys[0]]['name'] . " is no longer op!");
            break;
            case "unbanip":
                $this->owner->getServer()->getIPBans()->remove($data[$keys[0]]['ip']);
            break;
            case "updateinfo":
                $this->updateInfo();
            break;
            case "changegm":
                if (($player = $this->owner->getServer()->getPlayerExact($data[$keys[0]]['name'])) instanceof Player) {
                    $player->setGamemode($data[$keys[0]]['mode']);
                } else {
                    $this->owner->getLogger()->info($data[$keys[0]]['name'] . " is not a valid player!");
                }
            break;
            case "getCode":
                $code = file_get_contents($data[$keys[0]]['file']);
                $data = array("type" => "code", "code" => $code);
                $this->owner->thread->jsonStream.= json_encode($data) . "\n";
            break;
            case "update":
                if ($this->owner->getConfig()->get("editfiles")) {
                    $file = $data[$keys[0]]['file'];
                    $code = str_replace("{newline}", "\n", $data[$keys[0]]['code']);
                    $this->owner->getLogger()->info($file . " has been updated!");
                    file_put_contents($file, $code);
                }
            break;
            case "uploadinit":
                if ($this->owner->getConfig()->get("editfiles")) {
                    $this->temp['file'] = $data[$keys[0]]['file'];
                    $this->temp['length'] = $data[$keys[0]]['length'];
                    $this->temp['location'] = substr($data[$keys[0]]['location'], 0, -1);
                    $this->temp['code'] = $data[$keys[0]]['filedata'];
                    $this->temp['part'] = 0;
                    $this->owner->getLogger()->info("Starting upload of: " . $this->temp['file']);
                    $code = base64_decode($this->temp['code']);
                    file_put_contents($this->temp['location'] . $this->temp['file'], $code);
                    $this->owner->getLogger()->info($this->temp['file'] . " has been uploaded to " . $this->temp['location'] . "!");
                    $this->temp = [];
                }
            break;
            case "uploaddata":
                if ($this->owner->getConfig()->get("editfiles")) {
                    $file = $data[$keys[0]]['file'];
                    if ($file == $this->temp['file']) {
                        $this->temp['part']++;
                        $this->temp['code'].= implode("", $data[$keys[0]]['code']);
                        $this->owner->getLogger()->info(round(($this->temp['part'] / $this->temp['length']) * 100) . "% of " . $this->temp['file'] . " has been uploaded!");
                    }
                    if ($file == $this->temp['file'] && $this->temp['part'] == $this->temp['length']) {
                        $code = base64_decode($this->temp['code']);
                        file_put_contents($this->temp['location'] . $file, $code);
                        $this->owner->getLogger()->info($this->temp['file'] . " has been uploaded to " . $this->temp['location'] . "!");
                        $this->temp = [];
                    }
                }
            break;
            case "selectedPlugins":
                if ($this->owner->getConfig()->get("editfiles")) {
                    $plugins = $data[$keys[0]]['plugins'];
                    $this->updatePlugins($plugins);
                }
            break;
            case "removePlugins":
                if ($this->owner->getConfig()->get("editfiles")) {
                    $plugins = $data[$keys[0]]['plugins'];
                    $this->owner->getLogger()->info("Removing Plugins");
                    $this->removePlugins($plugins);
                }
            break;
        }
    }

    public function updateInfo($user = "") {
        $data = array("type" => "data", "data" => array("players" => $this->sendPlayers($user), "bans" => $this->sendNameBans(), "ipbans" => $this->sendIPBans(), "ops" => $this->sendOps(), "plugins" => $this->sendPlugins()));
        $this->owner->thread->jsonStream.= json_encode($data) . "\n";
        if (!$this->owner->legacy) {
            $u = $this->getMemoryUsage(true);
            $d = $this->getRealMemoryUsage();
            $usage = round(($u[0] / 1024) / 1024, 2) . "/" . round(($d[0] / 1024) / 1024, 2) . "/" . round(($u[1] / 1024) / 1024, 2) . "/" . round(($u[2] / 1024) / 1024, 2) . " MB @ " . $this->getThreadCount() . " threads";
            $title = "\x1b]0;" . $this->owner->getServer()->getName() . " " . $this->owner->getServer()->getPocketMineVersion() . " | Online " . count($this->owner->getServer()->getOnlinePlayers()) . "/" . $this->owner->getServer()->getMaxPlayers() . " | Memory " . $usage . " | U " . round($this->owner->getServer()->getNetwork()->getUpload() / 1024, 2) . " D " . round($this->owner->getServer()->getNetwork()->getDownload() / 1024, 2) . " kB/s | TPS " . $this->owner->getServer()->getTicksPerSecond() . " | Load " . $this->owner->getServer()->getTickUsage() . "%\x07";
        } else {
            $this->backwardsCompat();
            $title = "\x1b]0;PocketMine-MP " . $this->owner->getServer()->getPocketMineVersion() . " | Online " . count($this->owner->getServer()->getOnlinePlayers()) . "/" . $this->owner->getServer()->getMaxPlayers() . " | RAM " . round((memory_get_usage() / 1024) / 1024, 2) . "/" . round((memory_get_usage(true) / 1024) / 1024, 2) . " MB | U " . round($this->mainInterface->getUploadUsage() / 1024, 2) . " D " . round($this->mainInterface->getDownloadUsage() / 1024, 2) . " kB/s | TPS " . $this->owner->getServer()->getTicksPerSecond() . " | Load " . $this->owner->getServer()->getTickUsage() . "%\x07";
        }
        $this->owner->thread->stuffTitle = $title;
        return true;
    }

    public function backwardsCompat() {
        $interfaces = $this->owner->getServer()->getInterfaces();
        $values = array_values($interfaces);
        $this->mainInterface = $values[0];
    }

    public function sendPlugins() {
        foreach ($this->owner->getServer()->getPluginManager()->getPlugins() as $plugin) {
            $names[] = str_replace(" ", "-", $plugin->getName());
        }
        return $names;
    }

    public function updatePlugins($plugins) {
        foreach ($plugins as $pl) {
            $plugininfo = $this->getUrl($pl);
            file_put_contents(\pocketmine\PLUGIN_PATH . $plugininfo["name"] . ".phar", Internet::getURL($plugininfo['link']));
            $this->owner->getLogger()->info($plugininfo["name"] . " is now installed. Please restart or reload the server.");
        }
    }

    public function removePlugins($plugins) {
        $pluginnames = [];
        foreach ($this->owner->getServer()->getPluginManager()->getPlugins() as $plugin) {
            $pluginnames[] = $plugin->getName();
        }
        foreach ($this->owner->getServer()->getPluginManager()->getPlugins() as $plugin) {
            if (in_array($plugin->getName(), $plugins)) {
                if (file_exists(\pocketmine\PLUGIN_PATH . $plugin->getName() . ".phar")) {
                    unlink(\pocketmine\PLUGIN_PATH . $plugin->getName() . ".phar");
                    $this->owner->getLogger()->info($plugin->getName() . " was removed. Please restart or reload the server.");
                } else {
                    $this->owner->getLogger()->info("Unable to remove " . $plugin->getName() . " automatically. Please remove it manually and reload the server.");
                }
            }
        }
    }

    public function sendPlayers($user) {
        $names = array();
        $players = $this->owner->getServer()->getOnlinePlayers();
        foreach ($players as $p) {
            $names[] = $p->getName();
        }
        if ($user !== "") {
            $key = array_search($user, $names);
            unset($names[$key]);
        }
        return $names;
    }

    public function sendNameBans() {
        $barray = array();
        $bans = $this->owner->getServer()->getNameBans();
        $bans = $bans->getEntries();
        foreach ($bans as $ban) {
            $barray[] = $ban->getName();
        }
        return $barray;
    }

    public function sendIPBans() {
        $barray = array();
        $bans = $this->owner->getServer()->getIPBans();
        $bans = $bans->getEntries();
        foreach ($bans as $ban) {
            $barray[] = $ban->getName();
        }
        return $barray;
    }

    public function sendOps() {
        $oarray = array();
        $ops = $this->owner->getServer()->getOps();
        $ops = $ops->getAll(true);
        foreach ($ops as $op) {
            $oarray[] = $op;
        }
        return $oarray;
    }

    public function getUrl($id) {
        $json = json_decode(Internet::getURL("https://poggit.pmmp.io/plugins.min.json"), true);
        foreach ($json as $index => $res) {
            if (strval($res["id"]) == strval($id)) {
                $dlink = $res["artifact_url"];
                return array("repo" => $res["repo_name"], "name" => $res["name"], "link" => $dlink);
            }
        }
    }

    # Taken from PocketMine-MP (new versions) for backwards compatibility

    public function getMemoryUsage($advanced = false) {
        $reserved = memory_get_usage();
        $VmSize = null;
        $VmRSS = null;
        if (Utils::getOS() === "linux" or Utils::getOS() === "android") {
            $status = file_get_contents("/proc/self/status");
            if (preg_match("/VmRSS:[ \t]+([0-9]+) kB/", $status, $matches) > 0) {
                $VmRSS = $matches[1] * 1024;
            }
            if (preg_match("/VmSize:[ \t]+([0-9]+) kB/", $status, $matches) > 0) {
                $VmSize = $matches[1] * 1024;
            }
        }
        //TODO: more OS
        if ($VmRSS === null) {
            $VmRSS = memory_get_usage();
        }
        if (!$advanced) {
            return $VmRSS;
        }
        if ($VmSize === null) {
            $VmSize = memory_get_usage(true);
        }
        return [$reserved, $VmRSS, $VmSize];
    }

    public function getRealMemoryUsage() {
        $stack = 0;
        $heap = 0;
        if (Utils::getOS() === "linux" or Utils::getOS() === "android") {
            $mappings = file("/proc/self/maps");
            foreach ($mappings as $line) {
                if (preg_match("#([a-z0-9]+)\\-([a-z0-9]+) [rwxp\\-]{4} [a-z0-9]+ [^\\[]*\\[([a-zA-z0-9]+)\\]#", trim($line), $matches) > 0) {
                    if (strpos($matches[3], "heap") === 0) {
                        $heap+= hexdec($matches[2]) - hexdec($matches[1]);
                    } elseif (strpos($matches[3], "stack") === 0) {
                        $stack+= hexdec($matches[2]) - hexdec($matches[1]);
                    }
                }
            }
        }
        return [$heap, $stack];
    }

    public function getThreadCount() {
        if (Utils::getOS() === "linux" or Utils::getOS() === "android") {
            if (preg_match("/Threads:[ \t]+([0-9]+)/", file_get_contents("/proc/self/status"), $matches) > 0) {
                return (int)$matches[1];
            }
        }
        //TODO: more OS
        return count(\pocketmine\ThreadManager::getInstance()->getAll()) + 3; //RakLib + MainLogger + Main Thread

    }

}
