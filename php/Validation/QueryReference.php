<?php namespace Validation;

require_once __DIR__ . '/../Data/DataDefinition.php';

class QueryReference {

    public function check(\Data\DataDefinition $data): ?string {
        $env = array();
        foreach ($data->getEnvironment()->getEnvVars() as $var) {
            if (isset($env[$var->getName()]))
                return 'duplicate definition of environment variable '
                    . $var->getName();
            $env[$var->getName()] = $var->getType();
        }
        foreach ($data->getTypes() as $type) {
            if (($error = $this->checkType($type, $env)) !== null)
                return $error;
        }
        return null;
    }

    private function checkType(\Data\Type $type, array $env): ?string {
        $attr = array();
        $joint = array();
        foreach ($type->getAttributes() as $a) {
            if (isset($attr[$a->getName()]))
                return 'duplicate definition of attribute '
                    . $a->getName() . ' in ' . $type->getName();
            $attr[$a->getName()] = $a->getType();
        }
        foreach ($type->getJoints() as $j) {
            $joint[$j->getName()] = $j->getTarget();
        }
        foreach ($type->getAccess() as $query) {
            if (($error = $this->checkQuery($query, $type->getName(), $env, $attr, $joint)) !== null)
                return $error;
        }
        return null;
    }

    private function checkQuery(\Data\Query $query, string $type, array $env, array $attr, array $joints): ?string {
        $vars = array();
        $objs = array();
        foreach ($query->getInputVarNames() as $var)
            $vars[$var] = $query->getInputVarType($var);
        foreach ($query->getInputObjNames() as $obj) 
            $objs[$obj] = $query->getInputObjTarget($obj);
        $error = $this->checkBound($query->getBounds(), $env, $attr, $joints, $vars, $objs);
        if ($error != null)
            return 'Type ' . $type . ' query ' . $query->getName() . ' -> ' . $error;
        return null;
    }

    private function checkBound(\Data\Bound $bound,
        array $env, array $attr, array $joints, array $vars, array $obj): ?string 
    {
        switch (true) {
            case $bound instanceof \Data\InputBound: {
                if (!isset($vars[$bound->getName()]))
                    return 'Input: variable '.$bound->getName().' not found';
            } break;
            case $bound instanceof \Data\ObjectBound: {
                if (!isset($obj[$bound->getName()]))
                    return 'Object: variable '.$bound->getName().' not found';
            } break;
            case $bound instanceof \Data\TargetBound: {
                if (!isset($attr[$bound->getName()]))
                    return 'Target: variable '.$bound->getName().' not found';
            } break;
            case $bound instanceof \Data\EnvBound: {
                if (!isset($env[$bound->getName()]))
                return 'Env: variable '.$bound->getName().' not found';
            } break;
            case $bound instanceof \Data\JointBound: {
                if (!isset($joints[$bound->getName()]))
                return 'Joint: variable '.$bound->getName().' not found';
            } break;
            case $bound instanceof \Data\ValueBound: break;
            case $bound instanceof \Data\TrueBound: break;
            case $bound instanceof \Data\FalseBound: break;
            case $bound instanceof \Data\NotBound: {
                $error = $this->checkBound($bound->getChild(), $env, $attr, $joints, $vars, $obj);
                if ($error !== null)
                    return 'Not -> ' . $error;
            } break;
            case $bound instanceof \Data\CompareBound: {
                $error = $this->checkBound($bound->getLeft(), $env, $attr, $joints, $vars, $obj);
                if ($error !== null)
                    return 'Compare [1] -> ' . $error;
                $error = $this->checkBound($bound->getRight(), $env, $attr, $joints, $vars, $obj);
                if ($error !== null)
                    return 'Compare [2] -> ' . $error;
            } break;
            case $bound instanceof \Data\BoolBound: {
                $error = $this->checkBound($bound->getLeft(), $env, $attr, $joints, $vars, $obj);
                if ($error !== null)
                    return 'Bool [1] -> ' . $error;
                $error = $this->checkBound($bound->getRight(), $env, $attr, $joints, $vars, $obj);
                if ($error !== null)
                    return 'Bool [2] -> ' . $error;
            } break;
            default: 
                return 'unknown type ' . gettype($bound);
        }
        return null;
    }
}