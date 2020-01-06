<?php namespace Data;

require_once __DIR__ . '/Bounds.php';

class Query {
    private $inputVars;
    private $inputObj;
    private $bounds;
    private $name;
    private $use;
    private $limit;
    private $cache;

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

    public function getCache(): bool {
        return $this->cache;
    }

    public static function loadFromXml(\SimpleXMLElement $element): ?Query {
        if ($element->getName() != "Query")
            return null;

        $query = new Query();
        $query->inputVars = array();
        $query->inputObj = array();
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
        $bounds = $element->Bounds->children();
        if (count($bounds) == 1)
            $query->bounds = \Data\Bound::loadFromXml($bounds[0]);

        $query->name = (string)$element->attributes()->name;
        if (isset($element->attributes()->use))
            $query->use = (string)$element->attributes()->use;
        else $query->use = 'search';
        if (isset($element->attributes()->limit))
            $query->limit = (string)$element->attributes()->limit;
        else $query->limit = 'all';
        if (isset($element->attributes()->cache))
            $query->cache = filter_var(
                (string)$element->attributes()->cache,
                FILTER_VALIDATE_BOOLEAN
            );
        else $query->cache = false;

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