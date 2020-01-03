<?php namespace Data;

require_once __DIR__ . '/Attribute.php';
require_once __DIR__ . '/Build.php';

class Environment {
    private $envVars;
    private $build;

    private function __construct() {}

    public function getEnvVars(): array {
        return $this->envVars;
    }

    public function getEnvVar(string $name): ?Attribute {
        foreach ($this->envVars as $var)
            if ($var->getName() == $name)
                return $var;
        return null;
    }

    public function getBuild(): \Data\Build {
        return $this->build;
    }

    public static function loadFromXml(\SimpleXMLElement $element): ?Environment {
        if ($element->getName() != "Environment")
            return null;

        $env = new Environment();
        $env->envVars = array();

        if (isset($element->EnvVars))
            foreach ($element->EnvVars->children() as $child) {
                $attr = \Data\Attribute::loadFromXml($child);
                if ($attr !== null)
                $env->envVars []= $attr;
            }
        
        if (isset($element->Build))
            $env->build = \Data\Build::loadFromXml($element->Build);
        else $env->build = \Data\Build::empty();

        return $env;
    }
}