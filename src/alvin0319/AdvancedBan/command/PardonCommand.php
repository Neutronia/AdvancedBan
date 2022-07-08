<?php

declare(strict_types=1);

namespace alvin0319\AdvancedBan\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginOwned;
use pocketmine\plugin\PluginOwnedTrait;

final class PardonCommand extends Command implements PluginOwned{
	use PluginOwnedTrait;

	public function __construct(){
		parent::__construct("pardon", "Pardon a player");
		$this->setPermission("advancedban.command.pardon");
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : void{
		// TODO: Implement execute() method.
	}
}