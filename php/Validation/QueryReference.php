<?php namespace Validation;

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
        if (!$query->isSearchQuery() && $query->getCache())
            return 'Type ' . $type . ' query ' . $query->getName()
                . ': only search querys can be cached.';
        $vars = array();
        $objs = array();
        foreach ($query->getInputVarNames() as $var)
            $vars[$var] = $query->isInputVarArray($var);
        foreach ($query->getInputObjNames() as $obj) 
            $objs[$obj] = $query->isInputObjArray($obj);
        switch ($query->getUse()) {
            case 'all':
            case 'first':
                if ($query->getLimitVar() !== null)
                    return 'Type ' . $type . ' query ' . $query->getName()
                        . ': limitVar is only allowed for input and env limits';
                break;
            case 'input':
                if ($query->getLimitVar() === null)
                    return 'Type ' . $type . ' query ' . $query->getName()
                        . ': limitVar is required for input and env limits';
                break;
                if (!isset($vars[$query->getLimitVar()]))
                    return 'Type ' . $type . ' query ' . $query->getName()
                        . ': limitVar ' . $query->getLimitVar()
                        . ' is not found as input ';
                if (!in_array(
                    $query->getInputVarType($query->getLimitVar()), 
                    ['byte', 'short', 'int', 'long', 'sbyte', 'ushort', 'uint', 'ulong'])
                    ) return 'Type ' . $type . ' query ' . $query->getName()
                        .  ': input variable ' . $query->getLimitVar()
                        . ' cannot be used as limit var. integer expected.';
                break;
            case 'env':
                if ($query->getLimitVar() === null)
                    return 'Type ' . $type . ' query ' . $query->getName()
                        . ': limitVar is required for input and env limits';
                break;
                if (!isset($env[$query->getLimitVar()]))
                    return 'Type ' . $type . ' query ' . $query->getName()
                        . ': limitVar ' . $query->getLimitVar()
                        . ' is not found as environment variable';
                if (!in_array(
                    $env[$query->getLimitVar()], 
                    ['byte', 'short', 'int', 'long', 'sbyte', 'ushort', 'uint', 'ulong'])
                    ) return 'Type ' . $type . ' query ' . $query->getName()
                        .  ': environment variable ' . $query->getLimitVar()
                        . ' cannot be used as limit var. integer expected.';
                break;
        }
        $error = $this->checkBound($query->getBounds(), $env, $attr, $joints, $vars, $objs);
        if ($error != null)
            return 'Type ' . $type . ' query ' . $query->getName() . ' -> ' . $error;
        $used = array();
        foreach ($query->getSortNames() as $name) {
            if (\in_array($name, $used))
                return 'Type ' . $type . ' query ' . $query->getName()
                    . ': cannot sort by ' . $name . ' twice';
            $used []= $name;
            if (!isset($attr[$name]) && !isset($joints[$name]))
                return 'Type ' . $type . ' query ' . $query->getName()
                    . ': type doesn\'t contain sort member ' . $name;
        }
        return null;
    }

    private function checkBound(\Data\Bound $bound,
        array $env, array $attr, array $joints, array $vars, array $obj): ?string 
    {
        switch (true) {
            case $bound instanceof \Data\InputBound: {
                if (!array_key_exists($bound->getName(), $vars))
                    return 'Input: variable '.$bound->getName().' not found';
                if ($vars[$bound->getName()])
                    return 'Input: variable '.$bound->getName().' is an array (use "InSet" instead)';
            } break;
            case $bound instanceof \Data\ObjectBound: {
                if (!array_key_exists($bound->getName(), $obj))
                    return 'Object: variable '.$bound->getName().' not found';
                if ($obj[$bound->getName()])
                    return 'Object: variable '.$bound->getName().' is an array (use "InSet" instead)';
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
            case $bound instanceof \Data\InSetBound: {
                $error = $this->checkBound($bound->getContent(), $env, $attr, $joints, $vars, $obj);
                if ($error !== null)
                    return 'Inset -> ' . $error;
                if (array_key_exists($bound->getList(), $vars)) {
                    if (!$vars[$bound->getList()])
                        return 'Inset: list ' . $bound->getList() . ' is not an array';
                }
                elseif (array_key_exists($bound->getList(), $obj)) {
                    if (!$obj[$bound->getList()])
                        return 'Inset: list ' . $bound->getList() . ' is not an array';
                }
                else return 'Inset: input or object ' . $bound->getList() . ' not found';
            } break;
            case $bound instanceof \Data\IsNullBound: {
                $error = $this->checkBound($bound->getContent(), $env, $attr, $joints, $vars, $obj);
                if ($error !== null)
                    return 'IsNull -> ' . $error;
            } break;
            default: 
                return 'unknown type ' . gettype($bound);
        }
        return null;
    }
}