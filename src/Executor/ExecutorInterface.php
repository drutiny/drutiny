<?php

namespace Drutiny\Executor;

use Symfony\Component\Console\Style\SymfonyStyle;

/**
 *
 */
interface ExecutorInterface {

  /**
   *
   */
  public function __construct(SymfonyStyle $io);

  /**
   *
   */
  public function execute($command);

}
