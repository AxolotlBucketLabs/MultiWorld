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

namespace czechpmdevs\multiworld\generator\skyblock\populator;

use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\generator\object\OakTree;
use pocketmine\world\generator\populator\Populator;

class Island implements Populator {

	public function populate(ChunkManager $world, int $chunkX, int $chunkZ, Random $random): void {
		for($x = -1; $x <= 1; $x++) {
			for($y = -1; $y <= 1; $y++) {
				for($z = -1; $z <= 1; $z++) {
					$blockY = 68 + $y;
					$block = $y === 1 ? VanillaBlocks::GRASS() : VanillaBlocks::DIRT();
					$world->setBlockAt(256 + $x, $blockY, 256 + $z, $block);
					$world->setBlockAt(259 + $x, $blockY, 256 + $z, $block);
					$world->setBlockAt(256 + $x, $blockY, 253 + $z, $block);
				}
			}
		}

		$world->setBlockAt(256, 70, 252, VanillaBlocks::CHEST());
		$tree = new OakTree;
		$tree->getBlockTransaction($world, 260, 70, 257, $random)?->apply();
		$world->setBlockAt(256, 68, 256, VanillaBlocks::BEDROCK());
	}
}
