<?php

declare(strict_types=1);

use czechpmdevs\multiworld\generator\void\VoidGenerator;
use czechpmdevs\multiworld\util\LanguageManager;
use pocketmine\block\Block;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\block\VanillaBlocks;
use pocketmine\player\Player;
use pocketmine\world\SimpleChunkManager;
use pocketmine\world\format\Chunk;

require dirname(__DIR__) . "/vendor/autoload.php";

final class SmokePlayer extends Player {
	public function __construct(
		private string $smokeName,
		private string $smokeLocale
	){}

	public function getName() : string{
		return $this->smokeName;
	}

	public function getLocale() : string{
		return $this->smokeLocale;
	}

	public function __destruct(){}
}

function expectSame(string $description, string $expected, string $actual) : void{
	if($expected !== $actual){
		fwrite(STDERR, sprintf("%s Expected '%s', got '%s'.\n", $description, $expected, $actual));
		exit(1);
	}
}

LanguageManager::$defaultLang = "eng";
LanguageManager::$languages = [
	"eng" => ["smoke" => "English"],
	"fra" => ["smoke" => "French"],
	"vie" => ["smoke" => "Vietnamese"]
];
LanguageManager::$players = [];

expectSame("Vietnamese client locale", "Vietnamese", LanguageManager::translateMessage(new SmokePlayer("locale", "vi_VN"), "smoke"));
expectSame("Unsupported client locale fallback", "English", LanguageManager::translateMessage(new SmokePlayer("fallback", "nl_NL"), "smoke"));
LanguageManager::$players["override"] = "fra";
expectSame("Explicit player language", "French", LanguageManager::translateMessage(new SmokePlayer("override", "vi_VN"), "smoke"));

RuntimeBlockStateRegistry::getInstance();
$world = new SimpleChunkManager(-64, 320);
$world->setChunk(0, 0, new Chunk([], false));
$world->setChunk(16, 16, new Chunk([], false));
$generator = new VoidGenerator(0, "");
$generator->generateChunk($world, 0, 0);
$generator->generateChunk($world, 16, 16);

$spawnChunk = $world->getChunk(0, 0);
$distantChunk = $world->getChunk(16, 16);
if($spawnChunk === null || $distantChunk === null){
	fwrite(STDERR, "The smoke test chunks were not available.\n");
	exit(1);
}

$grassStateId = VanillaBlocks::GRASS()->getStateId();
if($spawnChunk->getBlockStateId(0, 64, 0) !== $grassStateId){
	fwrite(STDERR, "VoidGenerator did not place grass in the spawn chunk.\n");
	exit(1);
}
if($distantChunk->getBlockStateId(0, 64, 0) !== Block::EMPTY_STATE_ID){
	fwrite(STDERR, "VoidGenerator placed grass outside the spawn chunk.\n");
	exit(1);
}

echo "Language selection and void-world generation smoke checks passed.\n";
