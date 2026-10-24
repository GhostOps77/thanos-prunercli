#!/usr/bin/php
<?php

require_once 'vendor/autoload.php';

use GetOpt\{GetOpt, Operand, Option};

use PrunerCLI\{SavesPrunerTaskFactory, Utils};


$getopt = new GetOpt([
  Option::create('h', 'help', GetOpt::NO_ARGUMENT)
    ->setDescription('Shows this help message'),
  Option::create('t', 'min-inhabitated-time', GetOpt::OPTIONAL_ARGUMENT)
    ->setDefaultValue(Utils::DEFAULT_MIN_INHABITATED_TIME)
    // // ->setArgumentName('inhabitated time')
    ->setDescription(
      'Minimum time a chunk must remain inhabited to avoid being deleted. '
      . '(Usage: #d#h#m#s#ms or #gt).'
    )
]);


$getopt->addOperands([
  Operand::create('inst-mc-dir', Operand::REQUIRED)
    ->setDescription(".minecraft folder of the MC Instance")
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
} catch (Exception $e) {
  if (count($argv) === 1 or $getopt->getOption('help')) {
    echo $getopt->getHelpText();
    exit(0);
  }

  echo $e->getMessage() . PHP_EOL . PHP_EOL . $getopt->getHelpText();
  exit(1);
}


function prune_all_saves(string $inst_mc_dir, int $min_inhabitated_time) {
  if (!is_dir($inst_mc_dir) or basename($inst_mc_dir) !== '.minecraft')
    exit("Instance path must be a folder." . PHP_EOL);

  $taskmaster = new \Aternos\Taskmaster\Taskmaster();
  $taskmaster->autoDetectWorkers(8);
  $taskmaster->addTaskFactory(
    new SavesPrunerTaskFactory($inst_mc_dir, $min_inhabitated_time)
  );

  Utils::run_tasks($taskmaster);
  Utils::save_all_worlds_last_played_value_to_config($inst_mc_dir);
}


try {
  $getopt->process();

  prune_all_saves(
    Utils::win_to_wsl_linux_path($getopt->getOperand('inst-mc-dir')),
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
