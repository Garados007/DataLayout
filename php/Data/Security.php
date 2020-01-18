<?php namespace Data;

use \Data\Environment;
use \Data\Build;

class Security {
    private $builds = array();
    private $default = null;
    private $base = null;

    public function isInclude(Build $build, ?string $buildMode = null, ?bool $get = null): bool {
        if ($buildMode === null)
            $buildMode = Build::getBuildMode();
        $access = $get === null ? 'any' : ($get ? 'get' : 'set');
        if (isset($this->builds[$buildMode][$access]))
            return $this->builds[$buildMode][$access];
        if (isset($this->builds[$buildMode]['any']))
            return $this->builds[$buildMode]['any'];
        if ($this->default[$access] !== null)
            return $this->default[$access];
        if ($this->default['any'] !== null)
            return $this->default['any'];
        if ($this->base !== null)
            return $this->base->isInclude($build, $buildMode, $get);
        else return $build->isSecurityInclude();
    }

    public function isExclude(Build $build, ?string $buildMode = null, ?bool $get = null): bool {
        return !$this->isInclude($build, $buildMode, $get);
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
            $access = isset($child->attributes()->access)
                ? (string)$child->attributes()->access
                : 'any';
            if ($profile !== null && $profile != Environment::getProfile())
                continue;
            if ($build === null || $build == 'any')
                $security->default[$access] = $include;
            else $security->builds[$build][$access] = $include;
        }
        return $security;
    }
}