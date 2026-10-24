<?php

namespace PrunerCLI;

class WorldPrunerTask extends \Aternos\Taskmaster\Task\Task {
  public function __construct(
    protected string $input_dir,
    protected ?string $output_dir,
    protected int $min_inhabitated_time = 6000,
  ) {}

  public function run(): void {
    Utils::prune_world($this->input_dir, $this->output_dir, $this->min_inhabitated_time);
  }
}

?>
