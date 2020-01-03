<?php namespace Data;

require_once __DIR__ . '/Bounds.php';

class Query {
    private $inputVars;
    private $inputObj;
    private $bounds;
    private $name;
    private $use;

    private function __construct() {}

    public function getInputVarNames(): array {
        return array_keys($this->inputVars);
    }

    public function getInputVarType(string $name): ?string {
        if (isset($this->inputVars[$name]))
            return $this->inputVars[$name];
        else return null;
    }

    public function getInputObjNames(): array {
        return array_keys($this->inputObj);
    }

    public function getInputObjTarget(string $name): ?string {
        if (isset($this->inputObj[$name]))
            return $this->inputObj[$name];
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
                        = (string)$child->attributes()->type;
                    break;
                case 'InputObj':
                    $query->inputObj
                        [(string)$child->attributes()->name]
                        = (string)$child->attributes()->target;
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

        if ($query->bounds == null)
            return null;

        return $query;
    }
}