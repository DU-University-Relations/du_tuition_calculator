<?php

namespace Drupal\du_tuition_calculator\Service;

use Symfony\Component\Process\Process;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

class CliQueueInvoker {

  public function __construct(
    protected LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * Run the TC queues on target.
   *
   * @param 'local'|'dev'|'test'|'live' $target
   * @param 'both'|'year'|'cost' $what
   * @param string|null $site_machine_name Pantheon site id (optional override).
   * @return array{ok:bool,stdout:string,stderr:string,exit_code:int}
   */
  public function run(string $target, ?string $site_machine_name = NULL): array {
    $logger = $this->loggerFactory->get('du_tuition_calculator');
    $site = $site_machine_name ?: (getenv('PANTHEON_SITE_NAME') ?: 'du-core'); 

    $commands = $this->buildCommands($target, $site);

    $stdout = '';
    $stderr = '';
    $exit = 0;

    $drupal_root = \Drupal::root();                 
    $project_root = dirname($drupal_root);          
    $drush_bin = $project_root . '/vendor/bin/drush'; 

    foreach ($commands as $cmd) {

        $proc = Process::fromShellCommandline($cmd);
        $proc->setWorkingDirectory($drupal_root);
        $proc->setTimeout(600);
        $proc->run();

      $stdout .= $proc->getOutput();
      $stderr .= $proc->getErrorOutput();
      $exit = $proc->getExitCode() ?? 0;

      $logger->notice('Ran: %cmd (exit %code)', ['%cmd' => $cmd, '%code' => (string) $exit]);
      if ($exit !== 0) {
        $logger->error("STDERR:\n" . $proc->getErrorOutput());
        break; 
      }
    }

    return ['ok' => $exit === 0, 'stdout' => $stdout, 'stderr' => $stderr, 'exit_code' => $exit];
  }

  /**
   * Build shell commands
   *
   * @return string[]
   */
  protected function buildCommands(string $target, string $site): array {
    $year = 'du_tuition_calculator_year_queue';
    $cost = 'du_tuition_calculator_cost_queue';

    $current_env = getenv('PANTHEON_ENVIRONMENT') ?: null;

    if ($target === 'local' || ($current_env && $current_env === $target)) {
      $drupal_root = \Drupal::root();
      $project_root = dirname($drupal_root);
      $drush_bin = $project_root . '/vendor/bin/drush';
      $base = escapeshellcmd($drush_bin);
    }
    else {
      $base = "terminus drush {$site}.{$target}";
    }

    return [
      "{$base} du-tcq",
      ($target === 'local' || ($current_env && $current_env === $target))
        ? "{$base} queue-run {$year}"
        : "{$base} -- queue-run {$year}",
      ($target === 'local' || ($current_env && $current_env === $target))
        ? "{$base} queue-run {$cost}"
        : "{$base} -- queue-run {$cost}",
      ];
    
  }
}
