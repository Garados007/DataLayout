<?php namespace Data;

use \Data\Environment;
use \Data\Build;

class Security {
    /** This rule will be applied as a default */
    public const ANY = 0;
    /** This rule will be applied if the value should be getted */
    public const GET = 1;
    /** This rule will be applied if the value should be setted */
    public const SET = 2;
    /** This rule will be applied if the value should be created */
    public const CREATE = 3;

    private $builds = array();
    private $default = null;
    private $base = null;

    public function isInclude(Build $build, ?string $buildMode = null, int $rule = self::ANY): bool {
        if ($buildMode === null)
            $buildMode = Build::getBuildMode();
        if (isset($this->builds[$buildMode][$rule]))
            return $this->builds[$buildMode][$rule];
        if (isset($this->builds[$buildMode][self::ANY]))
            return $this->builds[$buildMode][self::ANY];
        if (isset($this->default[$rule]))
            return $this->default[$rule];
        if (isset($this->default[self::ANY]))
            return $this->default[self::ANY];
        if ($this->base !== null)
            return $this->base->isInclude($build, $buildMode, $rule);
        else return $build->isSecurityInclude();
    }

    public function isExclude(Build $build, ?string $buildMode = null, int $rule = self::ANY): bool {
        return !$this->isInclude($build, $buildMode, $rule);
    }

    public function getBase(): ?Security {
        return $this->base;
    }

    public function setBase(?Security $base) {
        $this->base = $base;
    }

    public static function loadFromXml(\SimpleXMLElement $element): Security {
        $security = new Security();
        foreach ($element->children() as $child) {
            $include = $child->getName() == 'Include';
            $profile = isset($child->attributes()->profile)
                ? (string)$child->attributes()->profile
                : null;
            $build = isset($child->attributes()->build)
                ? (string)$child->attributes()->build
                : null;
            $rule = isset($child->attributes()->access)
                ? (string)$child->attributes()->access
                : 'any';
            switch ($rule) {
                case 'get': $access = self::GET; break;
                case 'set': $access = self::SET; break;
                case 'create': $access = self::CREATE; break;
                default: $access = self::ANY; break;
            }
            if ($profile !== null && $profile != Environment::getProfile())
                continue;
            if ($build === null || $build == 'any')
                $security->default[$access] = $include;
            else $security->builds[$build][$access] = $include;
        }
        return $security;
    }
}