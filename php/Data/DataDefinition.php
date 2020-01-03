<?php namespace Data;

require_once __DIR__ . '/Environment.php';
require_once __DIR__ . '/Type.php';

class DataDefinition {
    private $environment;
    private $types;
    
    private function __construct() {}

    public function getEnvironment(): \Data\Environment {
        return $this->environment;
    }

    public function getTypes(): array {
        return $this->types;
    }

    public function getType(string $name): ?\Data\Type {
        foreach ($this->types as $type)
            if ($type->getName() == $name)
                return $type;
        return null;
    }

    public static function loadFromXml(\SimpleXMLElement $element): ?DataDefinition {
        if ($element->getName() != "DataDefinition")
            return null;

        $def = new \Data\DataDefinition();

        if (isset($element->Environment))
            $def->environment = \Data\Environment::loadFromXml($element->Environment);
        else $def->environment = null;

        $def->types = array();
        if (isset($element->Types)) 
            foreach ($element->Types->children() as $child) {
                $type = \Data\Type::loadFromXml($child);
                if ($type !== null)
                    $def->types []= $type;
            }

        return $def;
    }
}
