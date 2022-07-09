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
use function count;
use function json_decode;

final class PardonCommand extends Command implements PluginOwned{
	use PluginOwnedTrait;

	public function __construct(){
		parent::__construct("pardon", "Pardon a player");
		$this->setPermission("advancedban.command.pardon");
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : void{
		if(!$this->testPermission($sender)){
			return;
		}
		if(count($args) < 1){
			$sender->sendMessage(Loader::$prefix . "Usage: /pardon <player>");
			return;
		}
		$player = array_shift($args);
		Await::f2c(function() use ($player, $sender) : \Generator{
			$data = yield from Loader::getDatabase()->getSession($player);
			if(count($data) > 0){
				if(yield from Loader::getInstance()->isBannedName($player)){
					yield from Loader::getDatabase()->pardonName($player);
				}
				$deviceIds = json_decode($data[0]["deviceIds"], true);
				foreach($deviceIds as $deviceId){
					if(yield from Loader::getInstance()->isBannedDevice($deviceId)){
						yield from Loader::getDatabase()->pardonDevice($deviceId);
					}
				}
				$sender->sendMessage(Loader::$prefix . "Pardoned player $player.");
			}else{
				$sender->sendMessage(Loader::$prefix . "Player not found.");
			}
		});
	}
}