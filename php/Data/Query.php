<?php namespace Data;

use \Data\Security;

class Query {
    private $inputVars;
    private $inputObj;
    private $sort;
    private $bounds;
    private $name;
    private $use;
    private $limit;
    private $limitVar;
    private $cache;
    private $security;

    private function __construct() {}

    public function getInputVarNames(): array {
        return array_keys($this->inputVars);
    }

    public function getInputVarType(string $name): ?string {
        if (isset($this->inputVars[$name]))
            return $this->inputVars[$name]['type'];
        else return null;
    }
    
    public function isInputVarArray(string $name): ?bool {
        if (isset($this->inputVars[$name]))
            return $this->inputVars[$name]['array'];
        else return null;
    }

    public function getInputObjNames(): array {
        return array_keys($this->inputObj);
    }

    public function getInputObjTarget(string $name): ?string {
        if (isset($this->inputObj[$name]))
            return $this->inputObj[$name]['type'];
        else return null;
    }

    public function isInputObjArray(string $name): ?bool {
        if (isset($this->inputObj[$name]))
            return $this->inputObj[$name]['array'];
        else return null;
    }

    public function getSortNames(): array {
        return array_map(
            function ($e) { return $e['name']; }, 
            $this->sort
        );
    }

    public function getSortAscend(string $name): ?bool {
        foreach ($this->sort as $e)
            if ($e['name'] == $name)
                return $e['ascend'];
        return null;
    }

    public function getBounds(): \Data\Bound {
        return $this->bounds;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getUse(): string {
        return $this->use;
    }

    public function isSearchQuery(): bool {
        return $this->use == 'search';
    }

    public function isDeleteQuery(): bool {
        return $this->use == 'delete';
    }

    public function getLimit(): string {
        return $this->limit;
    }

    public function isLimitAll(): bool {
        return $this->limit == 'all';
    }

    public function isLimitFirst(): bool {
        return $this->limit == 'first';
    }

    public function isLimitInput(): bool {
        return $this->limit == 'input';
    }

    public function isLimitEnv(): bool {
        return $this->limit == 'env';
    }

    public function getLimitVar(): ?string {
        return $this->limitVar;
    }

    public function getCache(): bool {
        return $this->cache;
    }

    public function getSecurity(): Security {
        return $this->security;
    }

    public static function loadFromXml(\SimpleXMLElement $element): ?Query {
        if ($element->getName() != "Query")
            return null;

        $query = new Query();
        $query->inputVars = array();
        $query->inputObj = array();
        if (isset($element->Inputs))
            foreach ($element->Inputs->children() as $child) {
                switch ($child->getName()) {
                    case 'InputVar':
                        $query->inputVars
                            [(string)$child->attributes()->name]
                            = array(
                                'type' => (string)$child->attributes()->type,
                                'array' => isset($child->attributes()->array)
                                    ? filter_var(
                                        (string)$child->attributes()->array,
                                        FILTER_VALIDATE_BOOLEAN
                                    )
                                    : false
                            );
                        break;
                    case 'InputObj':
                        $query->inputObj
                            [(string)$child->attributes()->name]
                            = array(
                                'type' => (string)$child->attributes()->target,
                                'array' => isset($child->attributes()->array)
                                    ? filter_var(
                                        (string)$child->attributes()->array,
                                        FILTER_VALIDATE_BOOLEAN
                                    )
                                    : false
                            );
                        break;
                }
            }
        $bounds = isset($element->Bounds) 
            ? $element->Bounds->children()
            : array();
        if (count($bounds) == 1)
            $query->bounds = \Data\Bound::loadFromXml($bounds[0]);
        elseif (count($bounds) == 0) 
            $query->bounds = \Data\TrueBound::create();
        $query->sort = array();
        if (isset($element->Sort))
            foreach ($element->Sort->children() as $sort) {
                $query->sort []= array(
                    'name' => (string)$sort->attributes()->name,
                    'ascend' => isset($sort->attributes()->order)
                        ? (string)$sort->attributes()->order == 'ascend'
                        : true
                );
            }

        $query->name = (string)$element->attributes()->name;
        if (isset($element->attributes()->use))
            $query->use = (string)$element->attributes()->use;
        else $query->use = 'search';
        if (isset($element->attributes()->limit))
            $query->limit = (string)$element->attributes()->limit;
        else $query->limit = 'all';
        if (isset($element->attributes()->limitVar))
            $query->limitVar = (string)$element->attributes()->limitVar;
        else $query->limitVar = null;
        if (isset($element->attributes()->cache))
            $query->cache = filter_var(
                (string)$element->attributes()->cache,
                FILTER_VALIDATE_BOOLEAN
            );
        else $query->cache = false;
        $query->security = isset($element->Security)
            ? Security::loadFromXml($element->Security)
            : new Security();

        if ($query->bounds == null)
            return null;

        return $query;
    }
    
    public function import(callable $renamer, callable $env) {
        foreach ($this->inputObj as $key => $value)
            $this->inputObj[$key]['type'] = $renamer($value['type']);
        $this->bounds->import($env);
    }
}