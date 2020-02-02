<?php namespace Data;

class Build {
    protected $supported;
    protected $security;

    protected function __construct() {
        $this->supported = true;
    }

    public function getSupported(): bool {
        return $this->supported;
    }

    public function getSecurity(): string {
        return $this->security;
    }

    public function isSecurityInclude(): bool {
        return $this->security == 'include';
    }

    public function isSecurityExclude(): bool {
        return $this->security == 'exclude';
    }

    public static function empty(): Build {
        $build = new Build();
        $build->supported = true;
        $build->security = 'include';
        return $build;
    }

    protected static $buildMode = 'php';

    public static function setBuildMode(string $mode) {
        self::$buildMode = $mode;
    }

    public static function getBuildMode(): string {
        return self::$buildMode;
    }

    public static function loadFromXml(\SimpleXMLElement $element): ?Build {
        if ($element->getName() != "Build")
            return null;

        switch (self::$buildMode) {
            case 'php':
                $build = PhpBuild::loadFromXml($element);
                break;
            case 'php-graphql':
                $build = PHPGraphqlBuild::loadFromXml($element);
                break;
            case 'elm':
                $build = ElmBuild::loadFromXml($element);
                break;
            default:
                throw new \Exception('build mode ' . self::$buildMode . ' is not supported');
        }

        if (isset($element->attributes()->security))
            $build->security = (string)$element->attributes()->security;

        return $build;
    }
}

class PhpBuild extends Build {
    private $dbEngine;
    private $dbPrefix;
    private $classNamespace;
    private $publicMemberAccess;
    private $maxDbTableNameLength;
    private $fullQuery;

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

    public function getFullQuery(): string {
        return $this->fullQuery;
    }

    public function isFullQueryNone(): bool {
        return $this->fullQuery == 'none';
    }

    public function isFullQueryAuto(): bool {
        return $this->fullQuery == 'auto';
    }

    public function isFullQueryAll(): bool {
        return $this->fullQuery == 'all';
    }

    public static function empty(): Build {
        $build = new PhpBuild();
        $build->supported = true;
        $build->dbEngine = 'sql-mabron-db-connector';
        $build->dbPrefix = '';
        $build->classNamespace = '';
        $build->publicMemberAccess = false;
        $build->maxDbTableNameLength = 0;
        $build->fullQuery = 'auto';
        $build->security = 'include';
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
            if (isset($element->PHP->attributes()->fullQuery))
                $build->fullQuery = (string)$element->PHP->attributes()->fullQuery;

            if ($build->classNamespace !== '')
                $build->classNamespace = '\\' . $build->classNamespace;
        }
        return $build;
    }
}

class PhpGraphqlBuild extends Build {
    private $dbClassNamespace;
    private $classNamespace;
    private $classPrefix;
    private $standalone;
    private $internalPermissionChecks;
    private $classLoaderType;
    private $pagination;
    private $separateMutation;

    public function getDbClassNamespace(): ?string {
        return $this->dbClassNamespace == '' 
            ? null 
            : $this->dbClassNamespace;
    }

    public function getClassNamespace(): ?string {
        return $this->classNamespace == '' 
            ? null 
            : $this->classNamespace;
    }

    public function getClassPrefix(): string {
        return $this->classPrefix;
    }

    public function getStandalone(): bool {
        return $this->standalone;
    }

    public function getInternalPermissionChecks(): bool {
        return $this->internalPermissionChecks;
    }

    public function getClassLoaderType(): ?string {
        return $this->classLoaderType == '' ? null : $this->classLoaderType;
    }

    public function getPagination(): string {
        return $this->pagination;
    }

    public function isPaginationNone(): bool {
        return $this->pagination == 'none';
    }

    public function isPaginationTypes(): bool {
        return $this->pagination == 'types';
    }

    public function isPaginationExceptQuery(): bool {
        return $this->pagination == 'exceptQuery';
    }

    public function isPaginationFull(): bool {
        return $this->pagination == 'full';
    }

    public function getSeparateMutation(): bool {
        return $this->separateMutation;
    }

    public static function empty(): Build {
        $build = new PhpGraphqlBuild();
        $build->supported = true;
        $build->classPrefix = '';
        $build->standalone = false;
        $build->internalPermissionChecks = true;
        $build->classLoaderType = '';
        $build->pagination = 'exceptQuery';
        $build->separateMutation = true;
        $build->security = 'include';
        return $build;
    }

    public static function loadFromXml(\SimpleXMLElement $element): ?Build {
        if ($element->getName() != "Build")
            return null;

        $build = self::empty();
        if (!isset($element->{'PHP-GraphQL'}))
            return $build;
        $attr = $element->{'PHP-GraphQL'}->attributes();
        
        if (isset($attr->dbClassNamespace))
            $build->dbClassNamespace = (string)$attr->dbClassNamespace;
        if (isset($attr->classNamespace))
            $build->classNamespace = (string)$attr->classNamespace;
        if (isset($attr->classPrefix))
            $build->classPrefix = (string)$attr->classPrefix;
        if (isset($attr->standalone))
            $build->standalone = \filter_var(
                (string)$attr->standalone,
                FILTER_VALIDATE_BOOLEAN
            );
        if (isset($attr->internalPermissionChecks))
            $build->internalPermissionChecks = \filter_var(
                (string)$attr->internalPermissionChecks,
                FILTER_VALIDATE_BOOLEAN
            );
        if (isset($attr->classLoaderType))
            $build->classLoaderType = (string)$attr->classLoaderType;
        if (isset($attr->pagination))
            $build->pagination = (string)$attr->pagination;
        if (isset($attr->separateMutation))
            $build->separateMutation = \filter_var(
                (string)$attr->separateMutation,
                FILTER_VALIDATE_BOOLEAN
            );

        return $build;
    }
}

class ElmBuild extends Build {
    private $namespace;
    private $container;
    private $graphlql;

    public function getNamespace(): string {
        return $this->namespace;
    }

    public function getContainer(): ?string {
        return $this->container == ''
            ? null 
            : $this->container;
    }

    public function getGraphQL(): ?string {
        return $this->graphql == ''
            ? null 
            : $this->graphql;
    }

    public static function empty(): Build {
        $build = new ElmBuild();
        $build->supported = true;
        $build->namespace = 'Data';
        $build->container = '';
        return $build;
    }

    public static function loadFromXml(\SimpleXMLElement $element): ?Build {
        if ($element->getName() != "Build")
            return null;

        $build = self::empty();
        if (!isset($element->Elm))
            return $build;
        
        if (isset($element->Elm->attributes()->supported))
            $build->supported = filter_var(
                (string)$element->PHP->attributes()->supported,
                FILTER_VALIDATE_BOOLEAN
            );
        if (isset($element->Elm->attributes()->namespace))
            $build->namespace = (string)$element->Elm->attributes()->namespace;
        if (isset($element->Elm->attributes()->container))
            $build->container = (string)$element->Elm->attributes()->container;
        if (isset($element->Elm->attributes()->graphql))
            $build->graphql = (string)$element->Elm->attributes()->graphql;

        return $build;
    }
}