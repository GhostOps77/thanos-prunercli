<?php

namespace PrunerCLI;


use Aternos\Taskmaster\Taskmaster;
// use Aternos\Thanos\{Helper, Thanos};
use Aternos\Thanos\Pattern\Factory\ForceLoadedChunkPatternFactory;
use Aternos\Thanos\Pattern\InhabitedTimePattern;
use Aternos\Thanos\Thanos;
// use Aternos\Thanos\World\AnvilWorld;
use Aternos\Thanos\World\World;
use \Aternos\IO\System\Directory\Directory;
use Aternos\Nbt\IO\Reader\GZipCompressedStringReader;
use Aternos\Nbt\NbtFormat;
use Aternos\Nbt\Tag\{CompoundTag, Tag};


const TIME_PARSER_RE = '/^(?!$)(?:(?:(?\'days\'\d+)d)?(?:(?\'hr\'\d+)hr?)?(?:(?\'min\'\d+)m(?!s))?(?:(?\'sec\'\d+)(?:s(?:ec)?)?)?|(?\'gt\'\d+)g?t)$/i';

const GAME_TICKS_PER_SECOND = 20; # 1sec = 20gt
const GAME_TICKS_PER_MINUTE = GAME_TICKS_PER_SECOND * 60;
const GAME_TICKS_PER_HOUR = GAME_TICKS_PER_MINUTE * 60;
const GAME_TICKS_PER_DAY = GAME_TICKS_PER_HOUR * 24;
const CONFIG_FILENAME = '.thanos_config.json';


class Utils {
  public const GAME_TICKS_PER_SECOND = GAME_TICKS_PER_SECOND;
  public const GAME_TICKS_PER_MINUTE = GAME_TICKS_PER_MINUTE;
  public const GAME_TICKS_PER_HOUR = GAME_TICKS_PER_HOUR;
  public const GAME_TICKS_PER_DAY = GAME_TICKS_PER_DAY;

  const DEFAULT_MIN_INHABITATED_TIME = 10 * GAME_TICKS_PER_MINUTE; // 10 mins

  public static function run_tasks(Taskmaster $taskmaster) {
    foreach ($taskmaster->waitAndHandleTasks() as $task) {
      if ($task->getError()) {
        $taskmaster->stop();
        throw $task->getError();
      }
    }
    $taskmaster->stop();
  }

  public static function prune_world(
    string $input_dir, ?string $output_dir = null, int $min_inhabitated_time = 0
  ) {
    $input_dir = realpath(rtrim($input_dir, DIRECTORY_SEPARATOR));

    if ($input_dir === false or !is_dir($input_dir) or count(scandir($input_dir)) === 2)
      exit('World must be a directory and not empty' . PHP_EOL);

    $moveOutput = false;

    if ($output_dir === null) {
      $output_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'thanos-' . uniqid();
      $moveOutput = true;
    }

    else if (!file_exists($output_dir))
      mkdir($output_dir, recursive: true);

    else if (count(scandir($output_dir)) === 2)
      exit('Output directory must be empty' . PHP_EOL);

    $startTime = microtime(true);
    $destination = new Directory($output_dir);
    $world = World::open($input_dir);
    $thanos = new Thanos([
      new ForceLoadedChunkPatternFactory(),
      new InhabitedTimePattern($min_inhabitated_time, false),
    ]);
    // $thanos->setMinInhabitedTime($min_inhabitated_time);
    $removedChunks = $thanos->snap($world, $destination);
    if ($moveOutput) {
      // Helper::removeDirectory($input_dir);
      // Helper::copyDirectory($output_dir, $input_dir);
      // Helper::removeDirectory($output_dir);

      $world->getSource()->delete();
      $destination->move($world->getSource()->getPath());
    }

    echo sprintf(
      'Removed %d chunks in %.2f seconds from "%s"', $removedChunks,
      round(microtime(true) - $startTime, 2), basename($input_dir)
    ) . PHP_EOL;
  }


  public static function parse_time_input(string $timeString): int {
    $re_match = preg_match_all(
      TIME_PARSER_RE, preg_replace('/\s+/', '', ltrim($timeString, "0")),
      $matches, PREG_SET_ORDER, 0
    );

    if ($re_match === null)
      exit(
        "The inhabitated time is not in the right format. (Got "
        . $timeString . ")" . PHP_EOL
      );

    $min_inhabitated_time = (
      intval($matches[0]['days'] ?? 0) * GAME_TICKS_PER_DAY
      + intval($matches[0]['hr'] ?? 0) * GAME_TICKS_PER_HOUR
      + intval($matches[0]['min'] ?? 0) * GAME_TICKS_PER_MINUTE
      + intval($matches[0]['sec'] ?? 0) * GAME_TICKS_PER_SECOND
    ) or intval($matches[0]['gt'] ?? 0);

    if ($min_inhabitated_time <= 0)
      exit(
        "Minimum inhabitated time must be greater than 0, but got "
        . $min_inhabitated_time . PHP_EOL
      );

    return $min_inhabitated_time;
  }


  public static function win_to_wsl_linux_path(string $path): string {
    $res = str_replace('\\', '/', $path);
    if (strpos($res, ':/') === 1)# and IntlChar::isalpha($res[0]))
      $res = '/mnt/' . strtolower($res[0]) . substr($res, 2);

    if (!is_dir($res) or !file_exists($res))
      exit('"' . $path . '" folder doesn\'t exist.');

    $res = realpath($res);

    if ($res == false)
      exit('"' . $path . '" is not a proper folder path.');

    return $res . DIRECTORY_SEPARATOR;
  }

  public static function save_all_worlds_last_played_value_to_config(string $inst_mc_dir) {
    if (!is_dir($inst_mc_dir) or basename($inst_mc_dir) !== '.minecraft')
      exit("Instance path must be a folder." . PHP_EOL);
  
    $inst_mc_dir = rtrim($inst_mc_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $saves_dir = $inst_mc_dir . 'saves' . DIRECTORY_SEPARATOR;
    $worlds_dirs_list = scandir($saves_dir);
  
    if (count($worlds_dirs_list) === 2)
      exit("Saves folder is empty" . PHP_EOL);
  
    $last_played_json = [];
  
    foreach ($worlds_dirs_list as $world_dir) {
      if ($world_dir === '.' or $world_dir === '..')
        continue;
  
      $nbt_data = Tag::load(new GZipCompressedStringReader(
        file_get_contents($saves_dir . $world_dir . DIRECTORY_SEPARATOR . 'level.dat'),
        NbtFormat::JAVA_EDITION
      ));
  
      if (!$nbt_data instanceof CompoundTag)
        return;
  
      $last_played_json[$world_dir] = $nbt_data->getCompound('Data')->getLong('LastPlayed')->getValue();
    }
  
    $res = [];
    $res['last_played_timestamps'] = $last_played_json;
    file_put_contents($inst_mc_dir . CONFIG_FILENAME, json_encode($res));
  }
}
