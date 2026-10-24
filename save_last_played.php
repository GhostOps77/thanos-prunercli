#!/usr/bin/php
<?php

require_once 'vendor/autoload.php';

use GetOpt\{GetOpt, Operand};
use PrunerCLI\Utils;

$getopt = new GetOpt();

$getopt->addOperand(
  Operand::create("inst-mc-dir", Operand::REQUIRED)
    ->setDescription(".minecraft folder of the MC Instance")
);

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

  Utils::save_all_worlds_last_played_value_to_config(
    Utils::win_to_wsl_linux_path($getopt->getOperand('inst-mc-dir'))
  );
} catch (Exception $e) {
  if (count($argv) === 1 or $getopt->getOption('help')) {
    echo $getopt->getHelpText();
    exit(0);
  }

  echo $e->getMessage() . PHP_EOL . PHP_EOL . $getopt->getHelpText();
  exit(1);
}

?>
