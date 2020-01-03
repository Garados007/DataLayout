<?php namespace Data;

class Attribute {
    private $name;
    private $type; 
    private $default;
    private $unique;
    private $optional;

    private function __construct() {}

    public function getName(): string {
        return $this->name;
    }

    public function getType(): string {
        return $this->type;
    }

    public function getDefault() {
        return $this->default;
    }

    public function getUnique(): bool {
        return $this->unique;
    }

    public function getOptional(): bool {
        return $this->optional;
    }

    public static function loadFromXml(\SimpleXMLElement $element): ?Attribute {
        if ($element->getName() != "Attribute")
            return null;

        $xa = $element->attributes();
        $attr = new Attribute();
        $attr->name = (string)$element->attributes()->name;
        $attr->type = (string)$element->attributes()->type;
        if (isset($xa['default']))
            $attr->default = (string)$element->attributes()->default;
        else $attr->default = null;
        if (isset($xa['unique']))
            $attr->unique = filter_var(
                (string)$element->attributes()->unique,
                FILTER_VALIDATE_BOOLEAN
            );
        else $attr->unique = false;
        if (isset($xa['optional']))
            $attr->optional = filter_var(
                (string)$element->attributes()->optional,
                FILTER_VALIDATE_BOOLEAN
            );
        else $attr->optional = false;

        switch ($attr->type) {
            case 'bool':
                $attr->default = $attr->default === null
                    ? false 
                    : \filter_var($attr->default, FILTER_VALIDATE_BOOLEAN);
                break;
            case 'byte':
            case 'short':
            case 'int':
            case 'long':
            case 'sbyte':
            case 'ushort':
            case 'uint':
            case 'ulong':
                $attr->default = $attr->default === null 
                    ? 0
                    : \intval($attr->default);
                break;
            case 'float':
            case 'double':
                $attr->default = $attr->default === null 
                    ? 0.0
                    : \floatval($attr->default);
                break;
            case 'string':
                $attr->default = $attr->default === null 
                    ? ''
                    : \strval($attr->default);
                break;
            case 'bytes':
                $attr->default = $attr->default == null 
                    ? ''
                    : \base64_decode($attr->default, true);
                break;
            case 'date':
                $attr->default = $attr->default == null 
                    ? null //now
                    : $attr->default == 'now'
                    ? null //now
                    : \strtotime($attr->default, 0);
                break;
            case 'json':
                $attr->default = $attr->default == null 
                    ? ''
                    : \json_encode(\json_decode($attr->default, true), 
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
                break;
            default: return null;
        }

        return $attr;
    }
}