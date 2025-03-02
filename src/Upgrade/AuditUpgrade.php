<?php

namespace Drutiny\Upgrade;

use Drutiny\Audit\AuditInterface;
use Symfony\Component\Console\Exception\InvalidArgumentException;

class AuditUpgrade
{
    const SPACE = 4;

    protected $reflection;
    protected $params = [];

    public function __construct(\ReflectionClass $reflection)
    {
        $this->reflection = $reflection;
    }

    public static function fromAudit(AuditInterface $audit)
    {
        return new static(new \ReflectionClass($audit));
    }

    public function getParamAnnotations()
    {
        preg_match_all('/\* @Param\(([^\)]+)/m', $this->reflection->getDocComment(), $matches);
        $params = [];
        foreach ($matches[1] as $blob) {
            $param = [];
            foreach (explode(PHP_EOL, $blob) as $line) {
                if (preg_match('/(name|type|default|description) = "(.*)",?/', $line, $result)) {
                    $param[$result[1]] = $result[2];
                }
            }
            $params[] = $param;
        }
        return $params;
    }

    public function addParameter($name, $desc = '', $mode = 'static::PARAMETER_OPTIONAL', $default = null)
    {
        $this->params[$name] = [
            'name' => $name,
            'description' => $desc,
            'mode' => $mode,
            'default' => $default
        ];
        return $this;
    }

    public function addParameterFromException(InvalidArgumentException $e)
    {
        $parameter = strtr($e->getMessage(), [
          'The "' => '',
          '" argument does not exist.' => '',
        ]);
        return $this->addParameter($parameter);
    }


    public function getParamAnnotationReplacements()
    {
        $replacements = [];
        preg_match_all('/\* @Param\(([^\)]+)/m', $this->reflection->getDocComment(), $matches);
        foreach ($matches[0] as $find) {
            $replacements[$find.')'.PHP_EOL.' '] = '';
        }
        return $replacements;
    }

    public function getParameterDeclaration($name, $desc = '', $mode = 'static::PARAMETER_OPTIONAL', $default = null)
    {
        $configure_code = [];
        $configure_code[] = '$this->addParameter(';
        $configure_code[] = "'$name',";
        $configure_code[] = "$mode,";
        $configure_code[] = "'$desc'".(isset($default) ? ',' : '');
        if (isset($default)) {
            $configure_code[] = var_export($default, true);
        }
        $configure_code[] = ');';

        return $this->indent($configure_code);
    }

    public function getParamUpgradeMessage()
    {
        $message = "Please specify parameters in a configure method declaration:\n";
        $message .= "// Class: " . $this->reflection->getName() . "\n";
        $message .= "public function configure():void {\n";
        foreach ($this->getParamAnnotations() as $param) {
            $message .= $this->getParameterDeclaration($param['name'], $param['description'] ?? '', null, $param['default'] ?? null);
        }
        foreach ($this->params as $param) {
            $message .= $this->getParameterDeclaration($param['name'], $param['description'] ?? '', $param['mode'], $param['default'] ?? null);
        }
        $message .= "}\n";
        return $message;
    }

    public function indent(array $lines, $level = 2)
    {
        $code = '';
        foreach ($lines as $line) {
            $code .= str_pad($line, ($level * static::SPACE) + strlen($line), " ", STR_PAD_LEFT).PHP_EOL;
            if (strpos($line, '{') !== false) {
                $level++;
            }
            if (strpos($line, '}') !== false) {
                $level--;
            }
        }
        return $code;
    }
}
