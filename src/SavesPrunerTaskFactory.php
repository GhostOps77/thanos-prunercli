<?php

namespace PrunerCLI;

use Aternos\Nbt\IO\Reader\GZipCompressedStringReader;
use Aternos\Nbt\NbtFormat;
use Aternos\Nbt\Tag\Tag;
use Aternos\Taskmaster\Task\TaskInterface;

use PrunerCLI\WorldsPrunerTaskFactory;

use Generator;

const CONFIG_FILENAME = '.thanos_config.json';


class SavesPrunerTaskFactory extends WorldsPrunerTaskFactory {
  protected Generator $taskGenerator;
  protected array $last_played_json;

  public function __construct(
    protected string $inst_mc_dir,
    protected int $min_inhabitated_time = 0
  ) {
    $thanos_config_path = $this->inst_mc_dir . CONFIG_FILENAME;

    if (!file_exists($thanos_config_path) or is_dir($thanos_config_path))
      $last_played_json = array();

    else {
      $thanos_config_file_content = file_get_contents($thanos_config_path);

      if ($thanos_config_file_content === false)
        $last_played_json = array();
      else 
        $last_played_json = json_decode($thanos_config_file_content, true)['last_played_timestamps'];
    }

    $this->last_played_json = $last_played_json;
    $this->output_dir = null;
    $this->worlds_dir_list = $this->getSavesDirList();
    $this->taskGenerator = $this->generateTasks();
  }

  /**
   * @return string[]
   */
  protected function getSavesDirList(): array {
    $saves_dir = $this->inst_mc_dir . 'saves' . DIRECTORY_SEPARATOR;

    if (!file_exists($saves_dir) or !is_dir($saves_dir))
      exit('Saves folder for "' . $this->inst_mc_dir . '" doesn\'t exist.');

    $worlds_dir_list = scandir($saves_dir);
    $res = array();

    foreach ($worlds_dir_list as $world_dir) {
      if ($world_dir === '.' or $world_dir === '..')
        continue;

      $world_dir_abs = $saves_dir . $world_dir . DIRECTORY_SEPARATOR;

      if (!is_dir($world_dir_abs) or !file_exists($world_dir_abs))
        continue;

      $world_nbt_data = Tag::load(
        new GZipCompressedStringReader(
          file_get_contents($world_dir_abs . 'level.dat'), NbtFormat::JAVA_EDITION
        )
      );

      $world_last_played_timestamp = $world_nbt_data->getCompound('Data')->getLong('LastPlayed')->getValue();
      $world_last_played_old = $this->last_played_json[$world_dir] ?? -1;

      // echo $world_dir . ' ' . $world_last_played_old . ' ' . $world_last_played_timestamp . PHP_EOL;
      if ($world_last_played_old === -1 or $world_last_played_old < $world_last_played_timestamp)
        array_push($res, $world_dir_abs);

      else
        echo 'Skipping "' . $world_dir . '" world (haven\'t played recently).' . PHP_EOL;
    }

    return $res;
  }
}
