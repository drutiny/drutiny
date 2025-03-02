<?php

namespace Drutiny\Sandbox;

use Drutiny\Audit\AuditInterface;

/**
 * Deprecated 2.x Drutiny functions on the Sandbox.
 */
trait Drutiny2xBackwardCompatibilityTrait
{

    public function getTarget()
    {
        foreach (debug_backtrace() as $trace) {
            if (!isset($trace['class'])) {
                continue;
            }
            if ($trace['object'] instanceof AuditInterface) {
                $audit = $trace['class'];
                break;
            }
        }
        $this->audit->getLogger()->warning(__METHOD__ . ' is deprecated. Please use $this->target object property instead in ' . $audit);
        return $this->audit->getTarget();
    }

    public function logger()
    {
        foreach (debug_backtrace() as $trace) {
            if (!isset($trace['class'])) {
                continue;
            }
            if ($trace['object'] instanceof AuditInterface) {
                $audit = $trace['class'];
                break;
            }
        }
        $this->audit->getLogger()->warning(__METHOD__ . ' is deprecated. Please use $this->logger object property instead in ' . $audit);
        return $this->audit->getLogger();
    }

    public function exec($command)
    {
        foreach (debug_backtrace() as $trace) {
            if (!isset($trace['class'])) {
                continue;
            }
            if ($trace['object'] instanceof AuditInterface) {
                $audit = $trace['class'];
                break;
            }
        }
        $this->logger()->warning(__METHOD__.' is a deprecated method. Please use $this->target->getService("exec"). In ' . $audit);

        return $this->getTarget()->run($command);
    }

    public function drush($opts = [])
    {
        foreach (debug_backtrace() as $trace) {
            if (!isset($trace['class'])) {
                continue;
            }
            if ($trace['object'] instanceof AuditInterface) {
                $audit = $trace['class'];
                break;
            }
        }
        $this->audit->getLogger()->warning(__METHOD__.' is a deprecated method. Please use $sandbox->getTarget()->getService("drush"). Called by: ' . $audit);
      //return $this->getTarget()->getService('drush');

        return new class ($this->audit->getTarget(), $opts) {
            protected $target;
            protected $opts;
            public function __construct($target, $opts)
            {
                $this->target = $target;
                $this->opts = $opts;
            }
            public function __call($method, $args)
            {
              // preg_match_all('/((?:^|[A-Z])[a-z]+)/', $method, $matches);
              // $method = implode(':', array_map('strtolower', $matches[0]));
                foreach ($this->opts as $key => $value) {
                    if (is_numeric($key)) {
                        $args[] = '--' . $value;
                        continue;
                    } elseif ($value === null) {
                        $args[] = '--' . $key;
                        continue;
                    }
                    $args[] = '--' . $key . '=' . sprintf('%s', $value);
                }

                $exec = call_user_func_array([$this->target->getService('drush'), $method], $args);
                return $exec->run(function ($output) use ($args) {
                    if (in_array("--format=json", $args)) {
                        return json_decode($output, true);
                    }
                    return explode(PHP_EOL, $output);
                });
            }
        };
    }
}
