<?php namespace Data;

require_once __DIR__ . '/Attribute.php';
require_once __DIR__ . '/Build.php';

class Environment {
    private $envVars;
    private $build;
    private static $profile = null;

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

    public static function setProfile(?string $profile) {
        self::$profile = $profile;
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
        
        if (isset($element->Build)) {
            $node = null;
            $expect = self::$profile ?: 'default';
            $list = \is_array($element->Build) ? $element->Build : [ $element->Build ];
            foreach ($list as $child) {
                $name = isset($child->attributes()->profile)
                    ? (string)$child->attributes()->profile
                    : 'default';
                if ($name == $expect) {
                    $node = $child;
                    break;
                }
            }
            if ($node === null)
                throw new \Exception('profile ' . $expect . ' not found');
            $env->build = \Data\Build::loadFromXml($node);
        }
        elseif (self::$profile === null) 
            $env->build = \Data\Build::empty();
        else throw new \Exception('profile ' . self::$profile . ' not found');

        return $env;
    }

    public function import(array $vars, callable $renamer) {
        foreach ($vars as $var) {
            $var->import($renamer);
            $this->envVars[] = $var;
        }
    }
}