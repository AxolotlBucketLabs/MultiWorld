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
use CortexPE\Commando\constraint\InGameRequiredConstraint;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\world\biome\BiomeRegistry;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;
use function min;

class DebugSubCommand extends BaseSubCommand {
	protected function prepare(): void {
		$this->addConstraint(new InGameRequiredConstraint($this));
	}

	/**
	 * @param array<string, mixed> $args
	 */
	public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
		if(!$sender instanceof Player) {
			throw new AssumptionFailedError("Sender is not a player");
		}

		$position = $sender->getPosition();
		$x = $position->getFloorX();
		$y = $position->getFloorY();
		$z = $position->getFloorZ();
		$sender->sendMessage("Current position: $x, $y, $z");

		$chunkX = $x >> Chunk::COORD_BIT_SIZE;
		$chunkZ = $z >> Chunk::COORD_BIT_SIZE;
		$sender->sendMessage("Current chunk position: $chunkX, $chunkZ");

		$chunk = $position->getWorld()->getChunk($chunkX, $chunkZ);
		$id = $chunk?->getBiomeId($x & Chunk::COORD_MASK, min($y, World::Y_MAX - 1), $z & Chunk::COORD_MASK) ?? 0;
		$biome = BiomeRegistry::getInstance()->getBiome($id);
		$sender->sendMessage("Current biome: [$id] {$biome->getName()}");
	}
}
