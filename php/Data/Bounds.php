<?php namespace Data;

abstract class Bound {
    
    protected function __construct() {}

    public static function loadFromXml(\SimpleXMLElement $element): ?Bound {
        switch ($element->getName()) {
            //value output
            case 'Input': return \Data\InputBound::loadBoundFromXml($element);
            case 'Object': return \Data\ObjectBound::loadBoundFromXml($element);
            case 'Target': return \Data\TargetBound::loadBoundFromXml($element);
            case 'Env': return \Data\EnvBound::loadBoundFromXml($element);
            case 'Joint': return \Data\JointBound::loadBoundFromXml($element);
            case 'Value': return \Data\ValueBound::loadBoundFromXml($element);
            //bool output
            case 'True': return \Data\TrueBound::loadBoundFromXml($element);
            case 'False': return \Data\FalseBound::loadBoundFromXml($element);
            case 'Not': return \Data\NotBound::loadBoundFromXml($element);
            case 'Compare': return \Data\CompareBound::loadBoundFromXml($element);
            case 'Bool': return \Data\BoolBound::loadBoundFromXml($element);
            case 'InSet': return \Data\InSetBound::loadBoundFromXml($element);
            case 'IsNull': return \Data\IsNullBound::loadBoundFromXml($element);
            //unknown
            default: return null;
        }
    }

    public function import(callable $env) {

    }
}

abstract class ValueOutputBound extends Bound {

}

class InputBound extends ValueOutputBound {
    private $name;

    public function getName(): string {
        return $this->name;
    }

    public static function loadBoundFromXml(\SimpleXMLElement $element): ?InputBound {
        if ($element->getName() != 'Input')
            return null;
        
        $input = new \Data\InputBound();
        $input->name = (string)$element->attributes()->name;
        return $input;
    }
}

class ObjectBound extends ValueOutputBound {
    private $name;

    public function getName(): string {
        return $this->name;
    }

    public static function loadBoundFromXml(\SimpleXMLElement $element): ?ObjectBound {
        if ($element->getName() != 'Object')
            return null;
        
        $input = new \Data\ObjectBound();
        $input->name = (string)$element->attributes()->name;
        return $input;
    }
}

class TargetBound extends ValueOutputBound {
    private $name;

    public function getName(): string {
        return $this->name;
    }

    public static function loadBoundFromXml(\SimpleXMLElement $element): ?TargetBound {
        if ($element->getName() != 'Target')
            return null;
        
        $target = new \Data\TargetBound();
        $target->name = (string)$element->attributes()->name;
        return $target;
    }
}

class EnvBound extends ValueOutputBound {
    private $name;

    public function getName(): string {
        return $this->name;
    }

    public static function loadBoundFromXml(\SimpleXMLElement $element): ?EnvBound {
        if ($element->getName() != 'Env')
            return null;
        
        $env = new \Data\EnvBound();
        $env->name = (string)$element->attributes()->name;
        return $env;
    }
    
    public function import(callable $env) {
        $this->name = $env($this->name);
    }
}

class JointBound extends ValueOutputBound {
    private $name;

    public function getName(): string {
        return $this->name;
    }

    public static function loadBoundFromXml(\SimpleXMLElement $element): ?JointBound {
        if ($element->getName() != 'Joint')
            return null;
        
        $env = new \Data\JointBound();
        $env->name = (string)$element->attributes()->name;
        return $env;
    }
}

class ValueBound extends ValueOutputBound {
    private $type;
    private $value;

    public function getType(): string {
        return $this->type;
    }

    public function getValue() {
        return $this->value;
    }

    public static function loadBoundFromXml(\SimpleXMLElement $element): ?ValueBound {
        if ($element->getName() != 'Value')
            return null;
        
        $value = new \Data\ValueBound();
        $v = (string)$element->attributes()->value;
        switch ((string)$element->attributes()->type) {
            case 'bool':
                $value->type = 'bool';
                $value->value = filter_var($v, FILTER_VALIDATE_BOOLEAN);
                break;
            case 'byte':
            case 'short':
            case 'int':
            case 'long':
            case 'sbyte':
            case 'ushort':
            case 'uint':
            case 'ulong':
                $value->type = 'int';
                $value->value = (int)$v;
                break;
            case 'float':
            case 'double':
                $value->type = 'float';
                $value->value = (float)$v;
                break;
            case 'string':
                $value->type = 'string';
                $value->value = $v;
                break;
            case 'bytes':
                $value->type = 'bytes';
                $value->value = \base64_decode($v);
                break;
            case 'date':
                $value->type = 'date';
                $value->value = $v == 'now' ? 'now' : \strtotime($v, 0);
                break;
            case 'json':
                $value->type = 'json';
                $value->value = \json_encode(\json_decode($v), 
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
                break;
            default: return null;
        }

        return $value;
    }
}

abstract class BooleanOutputBound extends Bound {

}

class TrueBound extends BooleanOutputBound {
    
    public static function create(): TrueBound {
        return new TrueBound();
    }

    public static function loadBoundFromXml(\SimpleXMLElement $element): ?TrueBound {
        if ($element->getName() != 'True')
            return null;
        return new \Data\TrueBound();
    }
}

class FalseBound extends BooleanOutputBound {
    
    public static function loadBoundFromXml(\SimpleXMLElement $element): ?FalseBound {
        if ($element->getName() != 'False')
            return null;
        return new \Data\FalseBound();
    }
}

class NotBound extends BooleanOutputBound {
    private $child;

    public function getChild(): Bound {
        return $this->child;
    }
    
    public static function loadBoundFromXml(\SimpleXMLElement $element): ?NotBound {
        if ($element->getName() != 'Not')
            return null;

        $children = $element->children();
        $not = new \Data\NotBound;
        $not->child = \Data\Bound::loadFromXml($children[0]);
        if ($not->child === null)
            return null;

        return $not;
    }

    public function import(callable $env) {
        $this->child->import($env);
    }
}

class CompareBound extends BooleanOutputBound {
    private $left;
    private $right;
    private $method;

    public function getLeft(): Bound {
        return $this->left;
    }

    public function getRight(): Bound {
        return $this->right;
    }

    public function getMethod(): string {
        return $this->method;
    }

    public static function loadBoundFromXml(\SimpleXMLElement $element): ?CompareBound {
        if ($element->getName() != 'Compare')
            return null;

        $comp = new \Data\CompareBound();
        $children = $element->children();
        $comp->left = \Data\Bound::loadFromXml($children[0]);
        $comp->right = \Data\Bound::loadFromXml($children[1]);
        switch ((string)$element->attributes()->type) {
            case '=': $comp->method = '='; break;
            case 'lt': $comp->method = '<'; break;
            case 'gt': $comp->method = '>'; break;
            case 'leq': $comp->method = '<='; break;
            case 'geq': $comp->method = '>='; break;
            case '!=': $comp->method = '<>'; break;
            case 'eq': $comp->method = '='; break;
            case 'neq': $comp->method = '<>'; break;
            default: return null;
        }
        if ($comp->left === null || $comp->right === null)
            return null;
        return $comp;
    }
    
    public function import(callable $env) {
        $this->left->import($env);
        $this->right->import($env);
    }
}

class BoolBound extends BooleanOutputBound {
    private $left;
    private $right;
    private $method;

    public function getLeft(): Bound {
        return $this->left;
    }

    public function getRight(): Bound {
        return $this->right;
    }

    public function getMethod(): string {
        return $this->method;
    }

    public static function loadBoundFromXml(\SimpleXMLElement $element): ?BoolBound {
        if ($element->getName() != 'Bool')
            return null;

        $bool = new \Data\BoolBound();
        $children = $element->children();
        $bool->left = \Data\Bound::loadFromXml($children[0]);
        $bool->right = \Data\Bound::loadFromXml($children[1]);
        $bool->method = (string)$element->attributes()->type;

        if ($bool->left === null || $bool->right === null)
            return null;
        return $bool;
    }

    public function import(callable $env) {
        $this->left->import($env);
        $this->right->import($env);
    }
}

class InSetBound extends BooleanOutputBound {
    private $list;
    private $content;

    public function getList(): string {
        return $this->list;
    }

    public function getContent(): Bound {
        return $this->content;
    }

    public static function loadBoundFromXml(\SimpleXMLElement $element): ?InSetBound {
        if ($element->getName() != 'InSet')
            return null;

        $inset = new \Data\InSetBound();
        $children = $element->children();
        $inset->content = \Data\Bound::loadFromXml($children[0]);
        $inset->list = (string)$element->attributes()->list;

        if ($inset->content === null)
            return null;
        return $inset;
    }

    public function import(callable $env) {
        $this->content->import($env);
    }
}

class IsNullBound extends BooleanOutputBound {
    private $content;

    public function getContent(): Bound {
        return $this->content;
    }

    public static function loadBoundFromXml(\SimpleXMLElement $element): ?IsNullBound {
        if ($element->getName() != 'IsNull')
            return null;

        $isnull = new \Data\IsNullBound();
        $children = $element->children();
        $isnull->content = \Data\Bound::loadFromXml($children[0]);

        if ($isnull->content === null)
            return null;
        return $isnull;
    }

    public function import(callable $env) {
        $this->content->import($env);
    }
}
