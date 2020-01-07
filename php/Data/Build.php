<?php namespace Data;

class Build {
    private $supported;
    private $dbEngine;
    private $dbPrefix;
    private $classNamespace;
    private $publicMemberAccess;
    private $maxDbTableNameLength;

    private function __construct() {}

    public function getSupported(): bool {
        return $this->supported;
    }

    public function getDbEngine(): string {
        return $this->dbEngine;
    }

    public function getDbPrefix(): string {
        return $this->dbPrefix;
    }

    public function getClassNamespace(): string {
        return $this->classNamespace;
    }

    public function getPublicMemberAccess(): bool {
        return $this->publicMemberAccess;
    }

    public function getMaxDbTableNameLength(): int {
        return $this->maxDbTableNameLength;
    }

    public static function empty(): Build {
        $build = new Build();
        $build->supported = true;
        $build->dbEngine = 'sql-mabron-db-connector';
        $build->dbPrefix = '';
        $build->classNamespace = '';
        $build->publicMemberAccess = false;
        $build->maxDbTableNameLength = 0;
        return $build;
    }

    public static function loadFromXml(\SimpleXMLElement $element): ?Build {
        if ($element->getName() != "Build")
            return null;

        $build = self::empty();
        if (isset($element->PHP)) {
            if (isset($element->PHP->attributes()->supported))
                $build->supported = filter_var(
                    (string)$element->PHP->attributes()->supported,
                    FILTER_VALIDATE_BOOLEAN
                );
            if (isset($element->PHP->attributes()->dbEngine))
                $build->dbEngine = (string)$element->PHP->attributes()->dbEngine;
            if (isset($element->PHP->attributes()->dbPrefix))
                $build->dbPrefix = (string)$element->PHP->attributes()->dbPrefix;
            if (isset($element->PHP->attributes()->classNamespace))
                $build->classNamespace = (string)$element->PHP->attributes()->classNamespace;
            if (isset($element->PHP->attributes()->publicMemberAccess))
                $build->publicMemberAccess = filter_var(
                    (string)$element->PHP->attributes()->publicMemberAccess,
                    FILTER_VALIDATE_BOOLEAN
                );
            if (isset($element->PHP->attributes()->maxDbTableNameLength))
                $build->maxDbTableNameLength = (int)$element->PHP->attributes()->maxDbTableNameLength;

            if ($build->classNamespace !== '')
                $build->classNamespace = '\\' . $build->classNamespace;
        }
        return $build;
    }
}