<?php

declare(strict_types=1);

namespace alvin0319\AdvancedBan;

use alvin0319\AdvancedBan\command\BanCommand;
use alvin0319\AdvancedBan\command\PardonCommand;
use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\SingletonTrait;
use poggit\libasynql\DataConnector;
use poggit\libasynql\libasynql;
use SOFe\AwaitGenerator\Await;
use function array_shift;
use function count;
use function in_array;
use function json_decode;
use function json_encode;
use function strtolower;
use function time;

final class Loader extends PluginBase{
	use SingletonTrait;

	public static string $prefix = "§l§6NT §f> §r§7";

	private DataConnector $connector;

	private static Database $database;

	private array $bannedQueue = [];

	public static function getDatabase() : Database{
		return self::$database;
	}

	protected function onLoad() : void{
		self::setInstance($this);
	}

	protected function onEnable() : void{
		$this->connector = libasynql::create($this, $this->getConfig()->get("database"), [
			"sqlite" => "sqlite.sql",
			"mysql" => "mysql.sql",
		]);
		self::$database = new Database($this->connector);
		Await::f2c(function() : \Generator{
			yield from self::$database->initPlayer();
			yield from self::$database->initDeviceBan();
			yield from self::$database->initNameBan();
		});
		$this->connector->waitAll();
		$this->getServer()->getPluginManager()->registerEvent(PlayerPreLoginEvent::class, function(PlayerPreLoginEvent $event) : void{
			Await::f2c(function() use ($event) : \Generator{
				$playerData = yield from self::$database->getSession($name = strtolower($event->getPlayerInfo()->getUsername()));
				$deviceIds = [];
				if(count($playerData) > 0){
					$data = array_shift($playerData);
					$deviceIds = json_decode($data["deviceIds"], true);
					if(yield from $this->isBannedName($name)){
						$this->bannedQueue[$name] = yield from $this->getBannedReason($name);
					}
					foreach($deviceIds as $deviceId){
						if(yield from $this->isBannedDevice($deviceId)){
							$this->bannedQueue[$name] = yield from $this->getBannedReason($deviceId);
							break;
						}
					}
				}else{
					yield from self::$database->createSession(strtolower($event->getPlayerInfo()->getUsername()), "[]");
				}
				$deviceId = $event->getPlayerInfo()->getExtraData()["DeviceId"];
				if(!in_array($deviceId, $deviceIds)){
					$deviceIds[] = $deviceId;
				}
				yield from self::$database->updateSession($name, json_encode($deviceIds));
			});
		}, EventPriority::LOWEST, $this);
		$this->getServer()->getPluginManager()->registerEvent(PlayerLoginEvent::class, function(PlayerLoginEvent $event) : void{
			$player = $event->getPlayer();
			if(isset($this->bannedQueue[strtolower($event->getPlayer()->getName())])){
				$player->kick("You are banned from this server. Reason: {$this->bannedQueue[strtolower($event->getPlayer()->getName())]}");
				unset($this->bannedQueue[strtolower($event->getPlayer()->getName())]);
			}
		}, EventPriority::LOWEST, $this);
		$this->getServer()->getPluginManager()->registerEvent(PlayerJoinEvent::class, function(PlayerJoinEvent $event) : void{
			$player = $event->getPlayer();
			if(isset($this->bannedQueue[strtolower($player->getName())])){
				unset($this->bannedQueue[strtolower($player->getName())]);
				$this->getScheduler()->scheduleTask(new ClosureTask(function() use ($player) : void{
					$player->kick("You are banned from this server. Reason: {$this->bannedQueue[strtolower($player->getName())]}");
				}));
			}
		}, EventPriority::LOWEST, $this);

		$banCommand = $this->getServer()->getCommandMap()->getCommand("ban");
		if($banCommand !== null){
			$banCommand->setLabel($banCommand->getLabel() . "__disabled");
			$this->getServer()->getCommandMap()->unregister($banCommand);
		}
		$pardonCommand = $this->getServer()->getCommandMap()->getCommand("pardon");
		if($pardonCommand !== null){
			$pardonCommand->setLabel($pardonCommand->getLabel() . "__disabled");
			$this->getServer()->getCommandMap()->unregister($pardonCommand);
		}

		$this->getServer()->getCommandMap()->registerAll("ban", [
			new BanCommand(),
			new PardonCommand()
		]);
	}

	protected function onDisable() : void{
		$this->connector->close();
	}

	public function isBannedDevice(string $deviceId) : \Generator{
		$banned = yield from self::$database->isBannedDevice($deviceId);
		if(count($banned) > 0){
			$expireAt = $banned[0]["expireAt"];
			if($expireAt !== -1 && $expireAt <= time()){
				yield from self::$database->pardonDevice($deviceId);
				return false;
			}
			return true;
		}
		return false;
	}

	public function isBannedName(string $name) : \Generator{
		$name = strtolower($name);
		$banned = yield from self::$database->isBannedName($name);
		if(count($banned) > 0){
			$expireAt = $banned[0]["expireAt"];
			if($expireAt !== -1 && $expireAt <= time()){
				yield from self::$database->pardonName($name);
				return false;
			}
			return true;
		}
		return false;
	}

	public function getBannedReason(string $name) : \Generator{
		$name = strtolower($name);
		$nameBanned = yield from self::$database->isBannedName($name);
		if(count($nameBanned) > 0){
			return $nameBanned[0]["reason"];
		}
		$deviceBanned = yield from self::$database->isBannedDevice($name);
		if(count($deviceBanned) > 0){
			return $deviceBanned[0]["reason"];
		}
		return "Unknown";
	}
}