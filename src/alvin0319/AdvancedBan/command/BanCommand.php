<?php

declare(strict_types=1);

namespace alvin0319\AdvancedBan\command;

use alvin0319\AdvancedBan\Loader;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginOwned;
use pocketmine\plugin\PluginOwnedTrait;
use pocketmine\Server;
use SOFe\AwaitGenerator\Await;
use function array_intersect;
use function array_shift;
use function count;
use function date;
use function implode;
use function in_array;
use function is_numeric;
use function json_decode;
use function strlen;
use function time;
use function trim;

final class BanCommand extends Command implements PluginOwned{
	use PluginOwnedTrait;

	public function __construct(){
		parent::__construct("ban", "Ban a player");
		$this->setPermission("advancedban.command.ban");
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : void{
		if(!$this->testPermission($sender)){
			return;
		}
		if(count($args) < 2){
			$sender->sendMessage(Loader::$prefix . "Usage: /ban <player> <reason> [date]");
			return;
		}
		[$player, $reason] = $args;
		array_shift($args);
		array_shift($args);
		$date = null;
		if(count($args) > 0){
			try{
				$date = $this->parseDate(array_shift($args));
			}catch(\InvalidArgumentException $e){
				$sender->sendMessage(Loader::$prefix . "Failed to parse date: {$e->getMessage()}");
			}
		}
		Await::f2c(function() use ($sender, $player, $reason, $date) : \Generator{
			if(yield from Loader::getInstance()->isBannedName($player)){
				$sender->sendMessage(Loader::$prefix . "Player is already banned.");
				return;
			}
			$deviceIds = yield from Loader::getDatabase()->getSession($player);
			if(count($deviceIds) === 0){
				yield from Loader::getDatabase()->createSession($player, json_encode([]));
				$deviceIds = [];
			}
			yield from Loader::getDatabase()->banName($player, $date?->getTimestamp() ?? -1, $reason, $sender->getName());
			foreach(json_decode($deviceIds[0]["deviceIds"]) as $deviceId){
				yield from Loader::getDatabase()->banDevice($deviceId, $date?->getTimestamp() ?? -1, $reason, $sender->getName());
			}
			$sender->sendMessage(Loader::$prefix . "Banned player {$player}. Reason: {$reason}");
			$this->kickMatchPlayer($player);
			if(($target = $sender->getServer()->getPlayerExact($player)) !== null){
				$target->kick(Loader::$prefix . "Banned by {$sender->getName()}. Reason: {$reason}");
			}
		});
	}

	private function parseDate(string $args) : \DateTime{
		$day = 0;
		$hour = 0;
		$min = 0;
		$sec = 0;

		$len = strlen($args);
		$tempStr = "";
		for($i = 0; $i < $len; $i++){
			$str = $args[$i];
			if(trim($str) === ""){
				continue;
			}
			if(is_numeric($str)){
				$tempStr .= $str;
			}elseif(in_array($str, $allowed = ["d", "h", "m", "s"])){
				switch($str){
					case "d":
						$day = (int) $tempStr;
						break;
					case "h":
						$hour = (int) $tempStr;
						break;
					case "m":
						$min = (int) $tempStr;
						break;
					case "s":
						$sec = (int) $tempStr;
						break;
					default:
						throw new \InvalidArgumentException("Unexpected identifier $str, expected one of " . implode(", ", $allowed));
				}
			}
		}
		return \DateTime::createFromFormat("m-d-Y H:i:s",
			date("m-d-Y H:i:s", time() + (60 * 60 * 24 * $day) + (60 * 60 * $hour) + (60 * $min) + $sec)
		);
	}

	private function kickMatchPlayer(array $deviceIds) : void{
		Await::f2c(function() use ($deviceIds) : \Generator{
			foreach(Server::getInstance()->getOnlinePlayers() as $player){
				$data = yield from Loader::getDatabase()->getSession($player->getName());
				if(count($data) > 0){
					$playerDeviceIds = json_decode($data[0]["deviceIds"]);
					if(count(array_intersect($playerDeviceIds, $deviceIds)) > 0){
						$player->kick("You have been banned.");
					}
				}
			}
		});
	}
}