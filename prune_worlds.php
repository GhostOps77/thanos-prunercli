#!/usr/bin/php
<?php

require_once 'vendor/autoload.php';

use Aternos\Taskmaster\Taskmaster;
use GetOpt\{GetOpt, Operand, Option};
use PrunerCLI\{WorldsPrunerTaskFactory, Utils};


function prune_all_worlds(
  array $worlds_dir_list, string $output_dir = null, int $min_inhabitated_time = 0
) {
  $taskmaster = new Taskmaster();
  $taskmaster->autoDetectWorkers(6);

  foreach ($worlds_dir_list as $world_dir)
    $world_dir = Utils::win_to_wsl_linux_path($world_dir);

  $taskmaster->addTaskFactory(
    new WorldsPrunerTaskFactory($worlds_dir_list, $output_dir, $min_inhabitated_time)
  );

  Utils::run_tasks($taskmaster);
}


$getopt = new GetOpt([
  Option::create('h', 'help', GetOpt::NO_ARGUMENT)
    ->setDescription('Shows this help message'),
  Option::create('o', 'output-dir', GetOpt::OPTIONAL_ARGUMENT)
    ->setDescription('Output directory path for the pruned world'),
  Option::create('t', 'min-inhabitated-time', GetOpt::OPTIONAL_ARGUMENT)
    ->setDefaultValue(Utils::DEFAULT_MIN_INHABITATED_TIME)
    // ->setArgumentName('inhabitated time')
    ->setDescription(
      'Minimum time a chunk must remain inhabited to avoid being deleted. '
      . '(Usage: #d#h#m#s#ms or #gt).'
    ),
]);


$getopt->addOperands([
  Operand::create('worlds', Operand::MULTIPLE+Operand::REQUIRED)
    ->setDescription('Input directory path of the world'),
]);


$getopt->getHelp()->setTexts([
  'usage-options' => 'options',
  'usage-operands' => 'arguments',
  'operands-title' => "Arguments:\n",
  'options-title' => "Options:\n",
  'options-listing' => ', ',
  'commands-title' => "Commands:\n"
]);


try {
  $getopt->process();

  prune_all_worlds(
    $getopt->getOperand('worlds'),
    Utils::win_to_wsl_linux_path($getopt->getOperand('output-dir')),
    Utils::parse_time_input($getopt->getOption('min-inhabitated-time'))
  );
} catch (Exception $e) {
  if (count($argv) === 1 or $getopt->getOption('help')) {
    echo $getopt->getHelpText();
    exit(0);
  }

  echo $e->getMessage() . PHP_EOL . PHP_EOL . $getopt->getHelpText();
  exit(1);
}
