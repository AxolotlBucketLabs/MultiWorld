# MultiWorld

MultiWorld adds world management commands, custom generators, and an in-game form to Axolotl-PM.

AxolotlBucketLabs maintains this fork of [CzechPMDevs/MultiWorld](https://github.com/CzechPMDevs/MultiWorld).

[![Build and validate](https://github.com/AxolotlBucketLabs/MultiWorld/actions/workflows/build.yml/badge.svg)](https://github.com/AxolotlBucketLabs/MultiWorld/actions/workflows/build.yml)

## Requirements

- Axolotl-PM with plugin API 5.36.0 or newer.
- PHP 8.4 with the `yaml` and `json` extensions.

The workflow currently builds and analyzes the plugin against Axolotl-PM 5.51.2. Compatibility with API 5.36.0 and upstream PocketMine-MP is not tested by that workflow.

## Features

- Create, load, unload, duplicate, rename, delete, and inspect worlds.
- Teleport yourself or another player to a world.
- Set a world's spawn or change the server lobby.
- Generate worlds with built-in and custom generators.
- Manage common world tasks through an in-game form.
- Display messages in each player's language when a matching translation is available.

## Install

1. Download `MultiWorld.phar` from a successful `Build and validate` run in the repository's GitHub Actions.
2. Copy the PHAR into the server's `plugins` directory.
3. Restart the server.
4. Run `/mw help` in game or from the console.

The workflow uploads the PHAR as an Actions artifact. It does not publish a GitHub release.

## Commands

The main command is `/multiworld`. Its aliases are `/mw` and `/wm`. The permissions under `multiworld.command` in `plugin.yml` default to `op`.

| Command | Description | Alias |
| --- | --- | --- |
| `/mw create <name> [seed] [generator]` | Create a world. The seed defaults to a random value and the generator defaults to `normal`. | `new`, `c` |
| `/mw debug` | Show your position, chunk, and biome. | |
| `/mw delete <world>` | Delete a world. The default world cannot be deleted. | `remove`, `rm`, `del` |
| `/mw duplicate <world> [newName]` | Copy a world. The new name defaults to `<world>_copy`. | `dp` |
| `/mw help [page]` | Show command help. Pages are numbered 1 to 3. | `?` |
| `/mw info [world]` | Show information about a world. Without a name, shows your current world. | `i` |
| `/mw list` | List generated worlds, including unloaded worlds. | `ls`, `l` |
| `/mw load <world>` | Load a generated world. | `ld` |
| `/mw manage` | Open the in-game world management form. | `mng`, `m` |
| `/mw rename <world> <newName>` | Rename a world. | `rnm`, `rn` |
| `/mw setlobby` | Set the server's default world and spawn to your current position. In game only. | `lobby` |
| `/mw setspawn` | Set the current world's spawn to your position. In game only. | `spawn` |
| `/mw teleport <world> [player]` | Teleport yourself or a named player to a world's spawn. | `tp` |
| `/mw unload <world>` | Unload a world. The default world cannot be unloaded. | `uld` |

## Generators

Use the optional `generator` argument with `/mw create`.

| Name | Aliases | Generator |
| --- | --- | --- |
| `normal` | `classic`, `basic`, `vanilla` | VanillaGenerator Overworld |
| `nether` | `hell` | VanillaGenerator Nether |
| `ender` | `end` | MultiWorld End generator |
| `skyblock` | `sb` | MultiWorld SkyBlock generator |
| `void` | `empty`, `emptyworld` | MultiWorld Void generator |
| `flat` | `superflat` | Built-in flat generator |
| `normal_old` | | Built-in normal generator |
| `nether_old` | | Built-in Nether generator |

For example, `/mw create resource 12345 skyblock` creates a SkyBlock world named `resource` with seed `12345`.

## Configuration

MultiWorld creates `plugins/MultiWorld/config.yml` on first start. The main settings are:

- `prefix`: Prefix used in plugin messages.
- `language`: Fallback language used when the player's client language is unavailable.
- `force-default-language`: Use the configured language for every player when set to `true`.

Language files are stored in `plugins/MultiWorld/languages/`.

## Development and builds

Use PHP 8.4 and Composer to install the locked development dependencies:

```sh
composer install --prefer-dist --no-interaction --no-progress --no-scripts --no-plugins
php tools/prepare-devtools-virions.php
php tests/smoke.php
php vendor/bin/phpstan analyse --no-progress
```

The `Build and validate` workflow runs on pushes, pull requests, and manual dispatch. It checks language selection and void-world generation, uses AxolotlBucketLabs DevTools to build and validate the PHAR, runs PHPStan at `max` against Axolotl-PM, and uploads the PHAR and build metadata as an artifact.

## Credits

MultiWorld was created by CzechPMDevs. The plugin authors also include [VixikCZ](https://github.com/VixikCZ), [fuyutsuki](https://github.com/fuyutsuki), [kaliiks](https://github.com/kaliiks), and AxolotlBucketLabs.

The Overworld and Nether generators were ported from the Glowstone project by [Muqsit](https://github.com/Muqsit).

The icon was created by Freepik from [Flaticon](https://www.flaticon.com/) under the [CC BY 3.0 license](https://creativecommons.org/licenses/by/3.0/).

## License

MultiWorld is licensed under [GNU GPL-3.0-or-later](LICENSE).
