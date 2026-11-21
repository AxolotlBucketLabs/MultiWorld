<?php

declare(strict_types=1);

namespace czechpmdevs\multiworld\tools;

use FilesystemIterator;
use JsonException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Throwable;
use function array_map;
use function copy;
use function count;
use function dirname;
use function explode;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function fwrite;
use function implode;
use function is_array;
use function is_dir;
use function is_file;
use function is_link;
use function is_string;
use function json_decode;
use function mkdir;
use function preg_replace_callback;
use function rmdir;
use function sprintf;
use function str_contains;
use function str_replace;
use function strlen;
use function substr;
use function substr_count;
use function trim;
use function unlink;
use const DIRECTORY_SEPARATOR;
use const JSON_THROW_ON_ERROR;
use const STDERR;
use const STDOUT;

$projectRoot = dirname(__DIR__);
$virionsDirectory = $projectRoot . DIRECTORY_SEPARATOR . '.devtools-virions';
$packages = [
	'cortexpe/commando' => 'Commando',
	'muqsit/simple-packet-handler' => 'PacketHandler',
	'czechpmdevs/libpmform' => 'libpmform',
	'muqsit/vanillagenerator' => 'VanillaGenerator'
];

try {
	$lock = readJsonFile($projectRoot . DIRECTORY_SEPARATOR . 'composer.lock');
	$installed = readJsonFile($projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR . 'installed.json');
	$lockedPackages = indexPackages([...($lock['packages'] ?? []), ...($lock['packages-dev'] ?? [])]);
	$installedPackages = indexPackages($installed['packages'] ?? []);
	$sourceDirectories = [];

	foreach($packages as $composerName => $virionName) {
		$lockedPackage = $lockedPackages[$composerName] ?? null;
		$installedPackage = $installedPackages[$composerName] ?? null;
		$lockedReference = is_array($lockedPackage) ? ($lockedPackage['source']['reference'] ?? null) : null;
		$installedReference = is_array($installedPackage) ? ($installedPackage['source']['reference'] ?? null) : null;
		if(!is_string($lockedReference) || $lockedReference === '' || $lockedReference !== $installedReference) {
			throw new RuntimeException(sprintf('Composer install does not match the locked source for %s. Run composer install and try again.', $composerName));
		}

		$sourceDirectory = $projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $composerName);
		if(!is_file($sourceDirectory . DIRECTORY_SEPARATOR . 'virion.yml') || !is_dir($sourceDirectory . DIRECTORY_SEPARATOR . 'src')) {
			throw new RuntimeException(sprintf('The installed Composer package %s is missing virion.yml or src/.', $composerName));
		}
		$sourceDirectories[$virionName] = $sourceDirectory;
	}

	if(is_link($virionsDirectory) || (file_exists($virionsDirectory) && !is_dir($virionsDirectory))) {
		throw new RuntimeException('The generated virions path must be a regular directory.');
	}
	if(is_dir($virionsDirectory)) {
		removeDirectory($virionsDirectory);
	}
	if(!mkdir($virionsDirectory, 0777, true) && !is_dir($virionsDirectory)) {
		throw new RuntimeException('Could not create the generated virions directory.');
	}

	foreach($sourceDirectories as $virionName => $sourceDirectory) {
		$targetDirectory = $virionsDirectory . DIRECTORY_SEPARATOR . $virionName;
		if(!mkdir($targetDirectory, 0777, true)) {
			throw new RuntimeException(sprintf('Could not create the staging directory for %s.', $virionName));
		}
		foreach(['virion.yml', 'composer.json', 'LICENSE'] as $fileName) {
			$sourceFile = $sourceDirectory . DIRECTORY_SEPARATOR . $fileName;
			if($fileName === 'virion.yml' && is_file($sourceFile)) {
				copyVirionManifest($sourceFile, $targetDirectory . DIRECTORY_SEPARATOR . $fileName);
			} elseif(is_file($sourceFile) && !copy($sourceFile, $targetDirectory . DIRECTORY_SEPARATOR . $fileName)) {
				throw new RuntimeException(sprintf('Could not copy %s for %s.', $fileName, $virionName));
			}
		}
		copyDirectory($sourceDirectory . DIRECTORY_SEPARATOR . 'src', $targetDirectory . DIRECTORY_SEPARATOR . 'src');
		if($virionName === 'VanillaGenerator') {
			patchVanillaGenerator($targetDirectory);
		} elseif($virionName === 'PacketHandler') {
			patchSimplePacketHandler($targetDirectory);
		}
		$resourcesDirectory = $sourceDirectory . DIRECTORY_SEPARATOR . 'resources';
		if(is_dir($resourcesDirectory)) {
			copyDirectory($resourcesDirectory, $targetDirectory . DIRECTORY_SEPARATOR . 'resources');
		}
	}

	fwrite(STDOUT, sprintf('Prepared %d locked virions in .devtools-virions.', count($sourceDirectories)) . PHP_EOL);
} catch(Throwable $error) {
	fwrite(STDERR, $error->getMessage() . PHP_EOL);
	exit(1);
}

/** @return array<string, mixed> */
function readJsonFile(string $path): array {
	$contents = file_get_contents($path);
	if($contents === false) {
		throw new RuntimeException(sprintf('Could not read %s.', $path));
	}

	try {
		$data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
	} catch(JsonException $error) {
		throw new RuntimeException(sprintf('Could not parse %s: %s', $path, $error->getMessage()), 0, $error);
	}
	if(!is_array($data)) {
		throw new RuntimeException(sprintf('Expected a JSON object in %s.', $path));
	}

	return $data;
}

/** @param mixed $packages @return array<string, array<string, mixed>> */
function indexPackages(mixed $packages): array {
	if(!is_array($packages)) {
		throw new RuntimeException('Composer package metadata is invalid.');
	}

	$indexed = [];
	foreach($packages as $package) {
		if(is_array($package) && is_string($package['name'] ?? null)) {
			$indexed[$package['name']] = $package;
		}
	}

	return $indexed;
}

function copyVirionManifest(string $sourceFile, string $targetFile): void {
	$contents = file_get_contents($sourceFile);
	if($contents === false) {
		throw new RuntimeException(sprintf('Could not read %s.', $sourceFile));
	}

	$normalized = preg_replace_callback(
		'/^(\h*php:\h*\[)([^\]\r\n]+)(\]\h*)$/m',
		static function(array $matches): string {
			$versions = array_map(static fn(string $version): string => "'" . trim($version, " \t'\"") . "'", explode(',', $matches[2]));
			return $matches[1] . implode(', ', $versions) . $matches[3];
		},
		$contents
	);
	if(!is_string($normalized) || file_put_contents($targetFile, $normalized) === false) {
		throw new RuntimeException(sprintf('Could not prepare %s.', $sourceFile));
	}
}

function patchVanillaGenerator(string $virionDirectory): void {
	$path = $virionDirectory . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, [
		'src',
		'muqsit',
		'vanillagenerator',
		'generator',
		'overworld',
		'decorator',
		'TreeDecorator.php'
	]);
	$contents = file_get_contents($path);
	if($contents === false) {
		throw new RuntimeException('Could not read VanillaGenerator TreeDecorator.php.');
	}

	$lineEnding = str_contains($contents, "\r\n") ? "\r\n" : "\n";
	$methods = implode($lineEnding, [
		"\tprivate static function createTree(string \$class, Random \$random, BlockTransaction \$transaction) : GenericTree{",
		"\t\tif(\$class === GenericTree::class){",
		"\t\t\treturn new GenericTree(\$random, \$transaction);",
		"\t\t}",
		'',
		"\t\treturn self::createCustomTree(\$class, \$random, \$transaction);",
		"\t}",
		'',
		"\t/** @devtools-allow-dynamic-class \$class */",
		"\tprivate static function createCustomTree(string \$class, Random \$random, BlockTransaction \$transaction) : GenericTree{",
		"\t\tif(!class_exists(\$class)){",
		"\t\t\tthrow new Exception(\"Tree class \$class does not exist.\");",
		"\t\t}",
		"\t\tif(!is_subclass_of(\$class, GenericTree::class)){",
		"\t\t\tthrow new Exception(\"Tree class \$class must extend GenericTree.\");",
		"\t\t}",
		'',
		"\t\treturn new \$class(\$random, \$transaction);",
	"\t}",
	]);
	$methodAnchor = "\tpublic function populate(ChunkManager \$world, Random \$random, int \$chunkX, int \$chunkZ, Chunk \$chunk) : void{";
	$constructor = '$tree = new $class($random, $txn);';
	$replacement = '$tree = self::createTree($class, $random, $txn);';
	if(substr_count($contents, $methodAnchor) !== 1 || substr_count($contents, $constructor) !== 1) {
		throw new RuntimeException('VanillaGenerator TreeDecorator.php no longer matches the expected source.');
	}
	$patched = str_replace($methodAnchor, $methods . $lineEnding . $lineEnding . $methodAnchor, $contents);
	$patched = str_replace($constructor, $replacement, $patched);
	if(file_put_contents($path, $patched) !== strlen($patched)) {
		throw new RuntimeException('Could not apply the guarded tree factory to VanillaGenerator.');
	}
	patchMesaGroundGenerator($virionDirectory);
}

function patchMesaGroundGenerator(string $virionDirectory): void {
	$path = $virionDirectory . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, [
		'src',
		'muqsit',
		'vanillagenerator',
		'generator',
		'ground',
		'MesaGroundGenerator.php'
	]);
	$contents = file_get_contents($path);
	if($contents === false) {
		throw new RuntimeException('Could not read VanillaGenerator MesaGroundGenerator.php.');
	}

	$replacements = [
		'$this->setColoredGroundLayer($world, $x, $y, $z, $y < $seaLevel || $y > 128 ? 1 : ($colored ? $color : -1));' => '$this->setColoredGroundLayer($world, $x, $y, $z, $y < $seaLevel || $y > 128 ? DyeColor::ORANGE() : ($colored ? $color : null));',
		'private function setColoredGroundLayer(ChunkManager $world, int $x, int $y, int $z, DyeColor $color) : void{' => 'private function setColoredGroundLayer(ChunkManager $world, int $x, int $y, int $z, ?DyeColor $color) : void{',
		'$world->setBlockAt($x, $y, $z, $color >= 0 ? VanillaBlocks::STAINED_CLAY()->setColor($color) : VanillaBlocks::HARDENED_CLAY());' => '$world->setBlockAt($x, $y, $z, $color !== null ? VanillaBlocks::STAINED_CLAY()->setColor($color) : VanillaBlocks::HARDENED_CLAY());'
	];
	foreach($replacements as $search => $replace) {
		if(substr_count($contents, $search) !== 1) {
			throw new RuntimeException('VanillaGenerator MesaGroundGenerator.php no longer matches the expected source.');
		}
		$contents = str_replace($search, $replace, $contents);
	}
	if(file_put_contents($path, $contents) !== strlen($contents)) {
		throw new RuntimeException('Could not patch VanillaGenerator MesaGroundGenerator.php.');
	}
}

function patchSimplePacketHandler(string $virionDirectory): void {
	$patches = [
		'src/muqsit/simplepackethandler/utils/Utils.php' => [
			['use function is_a;', '', 1],
			['use ReflectionProperty;', '', 1],
			['$network_ids = [];', "\$network_ids = [];\n\t\t\$packets = \$pool->getAll();\n\t\t\$packet_ids_by_class = [];\n\t\tforeach(\$packets as \$registered_packet) {\n\t\t\t\$packet_ids_by_class[get_class(\$registered_packet)] = \$registered_packet->pid();\n\t\t}", 1],
			['public static function parseClosureSignature(', "\tprivate static function isTypeCompatible(string \$type, string \$expected) : bool{\n\t\treturn \$type === \$expected\n\t\t\t|| in_array(\$expected, \\class_parents(\$type) ?: [], true)\n\t\t\t|| in_array(\$expected, \\class_implements(\$type) ?: [], true);\n\t}\n\n\tpublic static function parseClosureSignature(", 1],
			['is_a($name, $parameter_compare, true)', 'self::isTypeCompatible($name, $parameter_compare)', 1],
			['assert(is_a($packet, DataPacket::class, true));', 'assert($packet === DataPacket::class || in_array(DataPacket::class, \\class_parents($packet) ?: [], true));', 1],
			['$type::class', 'get_class($type)', 1],
			["assert(is_int(\$packet::NETWORK_ID));\n\t\t\t\t\$network_ids[] = \$packet::NETWORK_ID;", "\$packet_id = \$packet_ids_by_class[\$packet] ?? null;\n\t\t\t\tif(!is_int(\$packet_id)) {\n\t\t\t\t\tthrow new InvalidArgumentException(\"Packet class \$packet is not registered in the packet pool.\");\n\t\t\t\t}\n\t\t\t\t\$network_ids[] = \$packet_id;", 1],
			["\t\t\t\tstatic \$packets = null;\n\t\t\t\t\$packets ??= (new ReflectionProperty(\$pool, \"pool\"))->getValue(\$pool);\n", '', 1],
			['if(!is_a($validate, $packet::class, true)){', 'if(!self::isTypeCompatible($validate, get_class($packet))){', 1]
		],
		'src/muqsit/simplepackethandler/interceptor/PacketInterceptorListener.php' => [
			['$packet::NETWORK_ID', '$packet->pid()', 2]
		],
		'src/muqsit/simplepackethandler/monitor/PacketMonitorListener.php' => [
			['$packet::NETWORK_ID', '$packet->pid()', 2]
		]
	];
	foreach($patches as $relativePath => $replacements) {
		$path = $virionDirectory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
		$contents = file_get_contents($path);
		if($contents === false) {
			throw new RuntimeException(sprintf('Could not read SimplePacketHandler source %s.', $relativePath));
		}
		$lineEnding = str_contains($contents, "\r\n") ? "\r\n" : "\n";
		$contents = str_replace("\r\n", "\n", $contents);
		foreach($replacements as [$search, $replace, $count]) {
			if(substr_count($contents, $search) !== $count) {
				throw new RuntimeException(sprintf('SimplePacketHandler source %s no longer matches the expected source.', $relativePath));
			}
			$contents = str_replace($search, $replace, $contents);
		}
		$contents = str_replace("\n", $lineEnding, $contents);
		if(file_put_contents($path, $contents) !== strlen($contents)) {
			throw new RuntimeException(sprintf('Could not patch SimplePacketHandler source %s.', $relativePath));
		}
	}
}

function copyDirectory(string $sourceDirectory, string $targetDirectory): void {
	if(!mkdir($targetDirectory, 0777, true) && !is_dir($targetDirectory)) {
		throw new RuntimeException(sprintf('Could not create %s.', $targetDirectory));
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($sourceDirectory, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::SELF_FIRST
	);
	foreach($iterator as $entry) {
		if(!$entry instanceof SplFileInfo) {
			continue;
		}
		if($entry->isLink()) {
			throw new RuntimeException(sprintf('Linked files are not supported in %s.', $sourceDirectory));
		}
		$relativePath = substr($entry->getPathname(), strlen($sourceDirectory) + 1);
		$targetPath = $targetDirectory . DIRECTORY_SEPARATOR . $relativePath;
		if($entry->isDir()) {
			if(!mkdir($targetPath, 0777, true) && !is_dir($targetPath)) {
				throw new RuntimeException(sprintf('Could not create %s.', $targetPath));
			}
		} elseif($entry->isFile() && !copy($entry->getPathname(), $targetPath)) {
			throw new RuntimeException(sprintf('Could not copy %s.', $entry->getPathname()));
		}
	}
}

function removeDirectory(string $directory): void {
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach($iterator as $entry) {
		if(!$entry instanceof SplFileInfo) {
			continue;
		}
		if($entry->isLink() || $entry->isFile()) {
			if(!unlink($entry->getPathname())) {
				throw new RuntimeException(sprintf('Could not remove %s.', $entry->getPathname()));
			}
		} elseif($entry->isDir() && !rmdir($entry->getPathname())) {
			throw new RuntimeException(sprintf('Could not remove %s.', $entry->getPathname()));
		}
	}
	if(!rmdir($directory)) {
		throw new RuntimeException(sprintf('Could not remove %s.', $directory));
	}
}
