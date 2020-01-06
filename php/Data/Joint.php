<?php namespace Data;

class Joint {
    private $name;
    private $target;
    private $required;

    private function __construct() {}

    public function getName(): string {
        return $this->name;
    }

    public function getTarget(): string {
        return $this->target;
    }

    public function getRequired(): bool {
        return $this->required;
    }

    public static function loadFromXml(\SimpleXMLElement $element): ?Joint {
        if ($element->getName() != "Joint")
            return null;

        $joint = new Joint();
        $joint->name = (string)$element->attributes()->name;
        $joint->target = (string)$element->attributes()->target;
        if (isset($element->attributes()->required))
            $joint->required = filter_var(
                (string)$element->attributes()->required,
                FILTER_VALIDATE_BOOLEAN
            );
        else $joint->required = true;

        return $joint;
    }
    
    public function import(callable $renamer) {
        $this->target = $renamer($this->target);
    }
}