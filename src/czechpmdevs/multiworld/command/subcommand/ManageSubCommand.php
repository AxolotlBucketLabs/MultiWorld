<?php

/**
 * MultiWorld - PocketMine plugin that manages worlds.
 * Copyright (C) 2018 - 2023  CzechPMDevs
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace czechpmdevs\multiworld\command\subcommand;

use CortexPE\Commando\BaseSubCommand;
use czechpmdevs\libpmform\response\FormResponse;
use czechpmdevs\libpmform\type\CustomForm;
use czechpmdevs\libpmform\type\SimpleForm;
use czechpmdevs\multiworld\util\LanguageManager;
use czechpmdevs\multiworld\util\WorldUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\Server;
use function array_filter;
use function array_map;
use function array_values;
use function is_int;
use function is_numeric;
use function is_string;
use function time;
use function trim;

class ManageSubCommand extends BaseSubCommand {
	protected function prepare(): void {
		$this->setPermission("multiworld.command.manage");
	}
	/**
	 * @param array<string, mixed> $args
	 */
	public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
		if(!$sender instanceof Player) {
			$sender->sendMessage("§cThis command can be used only in-game!");
			return;
		}

		$form = new SimpleForm("World Manager", "Select action", true);
		$form->addButton("Create a new world");
		$form->addButton("Delete world");
		$form->addButton("Show world info");
		$form->addButton("Load world");
		$form->addButton("Unload world");
		$form->addButton("Teleport to the world");
		$form->addButton("Teleport player to the world");
		$form->addButton("Update server lobby");
		$form->addButton("Update world spawn");

		$form->setCallback(static function(Player $player, FormResponse $response): void {
			$customForm = new CustomForm("World Manager");

			switch($response->getData()) {
				case 0:
					$customForm->addLabel("Create world");
					$customForm->addInput("World name");
					$customForm->addInput("World seed");
					$customForm->addDropdown("Generator", $generators = ["Normal", "Custom", "Nether", "End", "Flat", "Void", "SkyBlock"]);

					$customForm->setCallback(static function(Player $player, FormResponse $response) use ($generators): void {
						$name = self::getFormInput($response, 1);
						$seedInput = self::getFormInput($response, 2);
						$generator = self::getDropdownSelection($response, 3, $generators);
						if($name === null || trim($name) === "" || $seedInput === null || (trim($seedInput) !== "" && !is_numeric($seedInput)) || $generator === null) {
							$player->sendMessage(LanguageManager::translateMessage($player, "forms-invalid"));
							return;
						}

						$seed = trim($seedInput) === "" ? time() : (int)$seedInput;

						$cmd = "mw create \"$name\" \"$seed\" \"$generator\"";
						Server::getInstance()->dispatchCommand($player, $cmd);
					});

					$player->sendForm($customForm);
					break;
				case 1:
					$customForm->addLabel("Remove world");
					$customForm->addDropdown("World name", $worlds = WorldUtils::getAllWorlds());

					$customForm->setCallback(static function(Player $player, FormResponse $response) use ($worlds): void {
						$world = self::getDropdownSelection($response, 1, $worlds);
						if($world === null) {
							$player->sendMessage(LanguageManager::translateMessage($player, "forms-invalid"));
							return;
						}

						Server::getInstance()->dispatchCommand($player, "mw delete \"$world\"");
					});

					$player->sendForm($customForm);
					break;

				case 2:
					$customForm->addLabel("Get information about the world");
					$customForm->addDropdown("Worlds", $worlds = WorldUtils::getAllWorlds());

					$customForm->setCallback(static function(Player $player, FormResponse $response) use ($worlds): void {
						$world = self::getDropdownSelection($response, 1, $worlds);
						if($world === null) {
							$player->sendMessage(LanguageManager::translateMessage($player, "forms-invalid"));
							return;
						}

						Server::getInstance()->dispatchCommand($player, "mw info \"$world\"");
					});

					$player->sendForm($customForm);
					break;

				case 3:
					$customForm->addLabel("Load world");
					$customForm->addDropdown("World to load", $worlds = array_values(array_filter(WorldUtils::getAllWorlds(), fn(string $worldName) => !Server::getInstance()->getWorldManager()->isWorldLoaded($worldName))));

					$customForm->setCallback(static function(Player $player, FormResponse $response) use ($worlds): void {
						$world = self::getDropdownSelection($response, 1, $worlds);
						if($world === null) {
							$player->sendMessage(LanguageManager::translateMessage($player, "forms-invalid"));
							return;
						}

						Server::getInstance()->dispatchCommand($player, "mw load \"$world\"");
					});

					$player->sendForm($customForm);
					break;

				case 4:
					$customForm->addLabel("Unload world");
					$customForm->addDropdown("World to unload", $worlds = array_values(array_filter(WorldUtils::getAllWorlds(), fn(string $worldName) => Server::getInstance()->getWorldManager()->isWorldLoaded($worldName))));

					$customForm->setCallback(static function(Player $player, FormResponse $response) use ($worlds): void {
						$world = self::getDropdownSelection($response, 1, $worlds);
						if($world === null) {
							$player->sendMessage(LanguageManager::translateMessage($player, "forms-invalid"));
							return;
						}

						Server::getInstance()->dispatchCommand($player, "mw unload \"$world\"");
					});

					$player->sendForm($customForm);
					break;

				case 5:
					$customForm->addLabel("Teleport to world");
					$customForm->addDropdown("World", $worlds = WorldUtils::getAllWorlds());

					$customForm->setCallback(static function(Player $player, FormResponse $response) use ($worlds): void {
						$world = self::getDropdownSelection($response, 1, $worlds);
						if($world === null) {
							$player->sendMessage(LanguageManager::translateMessage($player, "forms-invalid"));
							return;
						}

						Server::getInstance()->dispatchCommand($player, "mw teleport \"$world\"");
					});

					$player->sendForm($customForm);
					break;

				case 6:
					$customForm->addLabel("Teleport player to world");
					$customForm->addDropdown("Player", $players = array_values(array_map(fn(Player $player) => $player->getName(), Server::getInstance()->getOnlinePlayers())));
					$customForm->addDropdown("World", $worlds = WorldUtils::getAllWorlds());

					$customForm->setCallback(static function(Player $player, FormResponse $response) use ($players, $worlds): void {
						$target = self::getDropdownSelection($response, 1, $players);
						$world = self::getDropdownSelection($response, 2, $worlds);
						if($target === null || $world === null) {
							$player->sendMessage(LanguageManager::translateMessage($player, "forms-invalid"));
							return;
						}

						Server::getInstance()->dispatchCommand($player, "mw teleport \"$world\" \"$target\"");
					});

					$player->sendForm($customForm);
					break;
				case 7:
					Server::getInstance()->dispatchCommand($player, "mw setspawn");
					break;
				case 8:
					Server::getInstance()->dispatchCommand($player, "mw setlobby");
					break;
			}
		});

		$sender->sendForm($form);
	}

	private static function getFormInput(FormResponse $response, int $index): ?string {
		$data = $response->getData();
		if(!is_array($data)) {
			return null;
		}

		$value = $data[$index] ?? null;
		return is_string($value) ? $value : null;
	}

	/** @param array<array-key, mixed> $options */
	private static function getDropdownSelection(FormResponse $response, int $index, array $options): ?string {
		$data = $response->getData();
		if(!is_array($data)) {
			return null;
		}

		$selection = $data[$index] ?? null;
		if(!is_int($selection)) {
			return null;
		}

		$option = $options[$selection] ?? null;
		return is_string($option) ? $option : null;
	}
}
