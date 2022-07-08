<?php

declare(strict_types=1);

namespace alvin0319\AdvancedBan\command;

use alvin0319\AdvancedBan\Loader;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginOwned;
use pocketmine\plugin\PluginOwnedTrait;
use SOFe\AwaitGenerator\Await;
use function array_shift;
use function assert;
use function count;
use function date;
use function implode;
use function in_array;
use function is_numeric;
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
		if(count($args) > 1){
			try{
				$date = $this->parseDate(array_shift($args));
			}catch(\InvalidArgumentException $e){
				$sender->sendMessage(Loader::$prefix . "Failed to parse date: {$e->getMessage()}");
			}
		}
		Await::f2c(function() use ($sender, $player, $reason, $date) : \Generator{
			if(yield from Loader::getInstance()->isBannedName($player)){
				$sender->sendMessage(Loader::$prefix . "Player is already banned");
				return;
			}
			$deviceIds = yield from Loader::getDatabase()->getSession($player);
			assert(count($deviceIds) > 0, "Player not found");
			yield from Loader::getDatabase()->banName($player, $date->getTimestamp(), $reason, $sender->getName());
			foreach($deviceIds as $deviceId){
				yield from Loader::getDatabase()->banDevice($deviceId, $date->getTimestamp(), $reason, $sender->getName());
			}
			$sender->sendMessage(Loader::$prefix . "Banned player {$player}. Reason: {$reason}");
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
}