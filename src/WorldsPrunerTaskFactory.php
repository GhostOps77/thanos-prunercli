<?php

namespace PrunerCLI;

use Aternos\Taskmaster\Task\{TaskFactory, TaskInterface};

use PrunerCLI\WorldPrunerTask;

use Generator;


class WorldsPrunerTaskFactory extends TaskFactory {
  protected Generator $taskGenerator;

  public function __construct(
    /**
     * @var string[] worlds_dir_list
     */
    protected array $worlds_dir_list,
    protected ?string $output_dir = null,
    protected int $min_inhabitated_time = 0
  ) {
    $this->taskGenerator = $this->generateTasks();
  }

  protected function getWorldsDirList(): array {
    return $this->worlds_dir_list;
  }

  /**
   * @return Generator<WorldPrunerTask>
   */
  protected function generateTasks(): Generator {
    foreach ($this->getWorldsDirList() as $world_dir) {
      $world_output_dir = null;
      if ($this->output_dir != null)
        $world_output_dir = $this->output_dir . DIRECTORY_SEPARATOR . basename($world_dir);

      yield new WorldPrunerTask($world_dir, $world_output_dir, $this->min_inhabitated_time);
    }
  }

  /**
   * @inheritDoc
   */
  public function createNextTask(?string $group): ?TaskInterface {
    if (!$this->taskGenerator->valid())
      return null;

    $task = $this->taskGenerator->current();
    $this->taskGenerator->next();
    return $task;
  }
}
