<?php namespace Data;

class Link {
    private $attribute;
    private $target;
    private $tarAttribute;
    private $name;

    private function __construct() {}

    public function getAttribute(): string {
        return $this->attribute;
    }

    public function getTarget(): string {
        return $this->target;
    }

    public function getTarAttribute(): string {
        return $this->tarAttribute;
    }

    public function getName(): string {
        return $this->name;
    }
    
    public static function loadFromXml(\SimpleXMLElement $element): ?Link {
        if ($element->getName() != "Link")
        return null;

        $link = new \Data\Link();
        $link->attribute = (string)$element->attributes()->attribute;
        $link->target = (string)$element->attributes()->target;
        $link->tarAttribute = (string)$element->attributes()->tarAttribute;
        $ea = $element->attributes();
        if (isset($ea['name']))
            $link->name = (string)$element->attributes()->name;
        else $link->name = '';

        return $link;
    }
}
