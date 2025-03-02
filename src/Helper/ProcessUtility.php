<?php

namespace Drutiny\Helper;

use Symfony\Component\Process\Process;

class ProcessUtility
{

    /**
     * Copy the Process configuration from one process to another.
     */
    public static function copyConfiguration(Process $from, Process $to):void
    {
        $to->setEnv($from->getEnv());
        $to->setWorkingDirectory($from->getWorkingDirectory());
        $to->setTimeout($from->getTimeout());
        $to->setPty($from->isPty());
        $to->setTty($from->isTty());
        $to->setIdleTimeout($from->getIdleTimeout());
        $to->setInput($from->getInput());
    }

    /**
     * Merge the environment vars of a process with provided vars.
     */
    public static function mergeEnv(Process $process, array $vars):void
    {
        $process->setEnv(array_merge($vars, $process->getEnv()));
    }

    public static function replacePlaceholders(Process $process):Process
    {
        $commandline = $process->getCommandLine();
        $vars = $process->getEnv();

        $new = Process::fromShellCommandline(preg_replace_callback('/\$([_a-zA-Z]++[_a-zA-Z0-9]*+)/', function ($matches) use ($vars) {
                return isset($vars[$matches[1]]) ? ProcessUtility::escapeArgument($vars[$matches[1]]) : '$'.$matches[1];
        }, $commandline));

        self::copyConfiguration($process, $new);
        return $new;
    }

    /**
     * Escapes a string to be used as a shell argument.
     */
    private static function escapeArgument(?string $argument): string
    {
        if ('' === $argument || null === $argument) {
            return '""';
        }
        if ('\\' !== \DIRECTORY_SEPARATOR) {
            return "'".str_replace("'", "'\\''", $argument)."'";
        }
        if (false !== strpos($argument, "\0")) {
            $argument = str_replace("\0", '?', $argument);
        }
        if (!preg_match('/[\/()%!^"<>&|\s]/', $argument)) {
            return $argument;
        }
        $argument = preg_replace('/(\\\\+)$/', '$1$1', $argument);

        return '"'.str_replace(['"', '^', '%', '!', "\n"], ['""', '"^^"', '"^%"', '"^!"', '!LF!'], $argument).'"';
    }
}
