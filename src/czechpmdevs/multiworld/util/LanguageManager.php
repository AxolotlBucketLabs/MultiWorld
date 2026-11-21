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

namespace czechpmdevs\multiworld\util;

use czechpmdevs\multiworld\MultiWorld;
use Exception;
use LogicException;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use function base64_decode;
use function basename;
use function glob;
use function is_array;
use function is_string;
use function json_decode;
use function strtolower;
use function str_replace;
use function substr;
use function yaml_parse_file;

class LanguageManager {
	private const PLAYER_LOCALE_LANGUAGES = [
		"cs" => "ces",
		"zh" => "chs",
		"de" => "deu",
		"en" => "eng",
		"fr" => "fra",
		"id" => "ind",
		"ja" => "jpn",
		"ko" => "kor",
		"pt" => "por",
		"ru" => "rus",
		"es" => "spa",
		"tl" => "tgl",
		"th" => "tha",
		"tr" => "tur",
		"vi" => "vie"
	];

	// Use built-in English messages when language resources are unavailable.
	private const DEFAULT_LANGUAGE = <<<'JSON'
	{
	  "not-perms": "§cYou do not have permission to use this command",
	  "default-usage": "§cUsage: §7/mw help",
	  "ingame": "§cThis command can be only used in game.",
	  "help": "§2--- Showing MultiWorld help page {%0} of {%1} (/mw help <page>) ---",
	  "help-1": "§2/mw create §fCreate a world",
	  "help-2": "§2/mw teleport §fTeleport to a world",
	  "help-3": "§2/mw list §f Displays a list of all worlds",
	  "help-4": "§2/mw load §fLoads a world",
	  "help-5": "§2/mw unload §fUnloads a world",
	  "help-6": "§2/mw delete §fRemoves a world",
	  "help-7": "§2/mw info §fDisplays information about a world",
	  "help-8": "§2/mw gamerule §fManages the gamerules of a level",
	  "help-9": "§2/mw manage §fDisplays form for managing with worlds",
	  "help-10": "§2/mw rename §fRenames the world",
	  "help-11": "§2/mw duplicate §fDuplicates a world",
	  "help-12": "§2/mw setspawn §fUpdate world spawn",
	  "help-13": "§2/mw setlobby §fUpdate server lobby position",
	  "create-usage": "§cUsage: §7/mw create <name> [seed] [generator]",
	  "create-exists": "§cWorld {%0} is already generated!",
	  "create-gennotexists": "§cGenerator {%0} not found.",
	  "create-generating": "§aGenerating world {%0}",
	  "create-done": "World {%0} was generated using seed: {%1} and generator: {%2}.",
	  "teleport-usage": "§cUsage: §7/mw teleport <world> [player]",
	  "teleport-levelnotexists": "§cWorld {%0} has not been created yet. Try /mw create to create a world.",
	  "teleport-load": "Loading world {%0}...",
	  "teleport-done-1": "§aYou were teleported to {%0}.",
	  "teleport-done-2": "§aPlayer {%1} was teleported to {%0}.",
	  "teleport-playernotexists": "§cPlayer does not exist.",
	  "teleport-error": "§cAn error occurred whilst teleporting player: {%0}",
	  "list-done": "§aWorlds ({%0}):",
	  "load-usage": "§cUsage: §7/mw load <world>",
	  "load-notexists": "§cWorld {%0} does not exist.",
	  "load-loaded": "§cUnable to load the world.",
	  "load-done": "§aWorld loaded.",
	  "load-error": "§cAn error occurred whilst loading the world: {%0}",
	  "unload-usage": "§cUsage: §7/mw unload <world>",
	  "unload-levelnotexists": "§c{%0} does not exist.",
	  "unload-unloaded": "§cUnable to unload the world.",
	  "unload-default": "§cUnable to unload default level.",
	  "unload-done": "§aWorld unloaded.",
	  "delete-usage": "§cUsage: §7/mw delete <world>",
	  "delete-levelnotexists": "§cWorld does not exist.",
	  "delete-done": "§aWorld deleted. ({%0} files removed.)",
	  "info-levelnotexists": "§c{%0} does not exist.",
	  "info-unloaded": "§cCould not fetch info from the world {%0}, due to the world could not be loaded.",
	  "info": "§a--- {%0} ---",
	  "info-name": "§7Name: {%0}",
	  "info-folderName": "§7Folder name: {%0}",
	  "info-players": "§7Player count: {%0}",
	  "info-generator": "§7Generator: {%0}",
	  "info-seed": "§7Seed: {%0}",
	  "info-time": "§7Time: {%0}",
	  "gamerule-usage": "§cUsage: §7/mw gamerule <list|gamerule> [true|false] [level]",
	  "gamerule-list": "§aAvailable game rules: {%0}",
	  "gamerule-levelnotfound": "§cWorld {%0} not found.",
	  "gamerule-notexists": "§cGame rule {%0} does not exist.",
	  "gamerule-done": "§aGame rule {%0} in world {%1} was changed to {%2}.",
	  "rename-usage": "§cUsage: §7/mw rename <from> <to>",
	  "rename-exists": "§cLevel with name {%0} already exists.",
	  "rename-levelnotfound": "§cLevel {%0} does not exist.",
	  "rename-done": "§aName of level {%0} was changed to {%1}.",
	  "rename-error": "§cCould not rename the world (Access denied)",
	  "duplicate-usage": "§cUsage: §7/mw duplicate <world> [newName]",
	  "duplicate-exists": "§cWorld with name {%0} already exists.",
	  "duplicate-levelnotfound": "§cLevel {%0} does not exist.",
	  "duplicate-done": "World {%0} duplicated, name of the duplicate is {%1}",
	  "setspawn-success": "§a{%0} world spawn set to {%1}",
	  "setlobby-error": "§cCould not update default world: {%0}",
	  "setlobby-success": "§aServer lobby position set to {%0} at {%1}",
	  "forms-invalid": "§cInvalid result."
	}
	JSON;

	public static string $defaultLang;

	/** @var string[][] $languages */
	public static array $languages = [];
	/** @var string[] $players */
	public static array $players = [];

	private static bool $forceDefaultLang = false;

	public function __construct() {
		$configuration = MultiWorld::getInstance()->getConfig()->getAll();
		$defaultLang = $configuration["language"];
		if(!is_string($defaultLang)) {
			throw new LogicException("Could not fetch default language from config.yml");
		}

		LanguageManager::$defaultLang = $defaultLang;
		if($langResources = glob(MultiWorld::getInstance()->getDataFolder() . "/languages/*.yml")) {
			foreach($langResources as $langResource) {
				$fileContents = self::parseLanguage(yaml_parse_file($langResource));
				if($fileContents === null) {
					MultiWorld::getInstance()->getLogger()->debug("Could not load language file ($langResource) - invalid data given");
					continue;
				}

				LanguageManager::$languages[basename($langResource, ".yml")] = $fileContents;
			}
		}

		if(!isset(LanguageManager::$languages[LanguageManager::$defaultLang])) {
			$defaultLanguage = self::parseLanguage(json_decode(self::DEFAULT_LANGUAGE, true));
			if($defaultLanguage === null) {
				throw new LogicException("Could not load the default language");
			}
			LanguageManager::$languages[LanguageManager::$defaultLang] = $defaultLanguage;
		}

		if(isset($configuration["force-default-language"])) {
			LanguageManager::$forceDefaultLang = (bool)$configuration["force-default-language"];
		}
	}

	/** @return array<string, string>|null */
	private static function parseLanguage(mixed $data): ?array {
		if(!is_array($data)) {
			return null;
		}

		$language = [];
		foreach($data as $key => $value) {
			if(!is_string($key) || !is_string($value)) {
				return null;
			}
			$language[$key] = $value;
		}

		return $language;
	}

	/**
	 * @param string[] $params
	 */
	public static function translateMessage(CommandSender $sender, string $messageIndex, array $params = []): string {
		try {
			$lang = LanguageManager::$defaultLang;
			if($sender instanceof Player) {
				$lang = LanguageManager::$players[$sender->getName()] ?? self::getPlayerLanguage($sender) ?? $lang;
			}

			if(empty(LanguageManager::$languages[$lang]) || LanguageManager::$forceDefaultLang) {
				$lang = LanguageManager::$defaultLang;
			}

			if(empty(LanguageManager::$languages[$lang])) {
				$lang = "eng";
			}

			$message = LanguageManager::$languages[$lang][$messageIndex];

			foreach($params as $index => $param) {
				$message = str_replace("{%$index}", $param, $message);
			}

		} catch(Exception $exception) {
			MultiWorld::getInstance()->getLogger()->error("LanguageManager error: " . $exception->getMessage() . " Try remove language resources and restart the server.");
			return "";
		}

		return $message;
	}

	private static function getPlayerLanguage(Player $player): ?string {
		$localeLanguage = strtolower(substr(str_replace("-", "_", $player->getLocale()), 0, 2));
		return self::PLAYER_LOCALE_LANGUAGES[$localeLanguage] ?? null;
	}
}
