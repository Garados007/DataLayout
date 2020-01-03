<?php namespace Validation;

require_once __DIR__ . '/../Data/DataDefinition.php';

class LinkReference {

    public function check(\Data\DataDefinition $data): ?string {
        foreach ($data->getTypes() as $type) {
            foreach ($type->getLinks() as $link) {
                if ($type->getAttribute($link->getAttribute()) === null)
                    return 'link of type ' . $type->getName() .
                        ': attribute ' . $link->getAttribute() . ' not found';
                if ($data->getType($link->getTarget()) === null)
                    return 'link of type ' . $type->getName() .
                        ': target ' . $link->getTarget() . ' not found';
                if ($data->getType($link->getTarget())->getAttribute($link->getTarAttribute()) === null)
                    return 'link of type ' . $type->getName() .
                        ': target attribute ' . $link->getTarAttribute() . 
                        ' not found in ' . $link->getTarget();
                $source = $type->getAttribute($link->getAttribute())->getType();
                $target = $data->getType($link->getTarget())
                    ->getAttribute($link->getTarAttribute())->getType();
                if ($source != $target)
                    return 'link of type ' . $type->getName() .
                        ': type mismatch - attribute '
                        . $link->getAttribute() . ' is ' . $source 
                        . ' but attribute ' . $link->getTarAttribute()
                        . ' of ' . $link->getTarget() . ' is ' . $target; 
            }

            foreach ($type->getAccess() as $access) {
                foreach ($access->getInputObjNames() as $name)
                    if ($data->getType($access->getInputObjTarget($name)) === null) {
                        return 'query ' . $access->getName() . ' of type ' .
                            $type->getName() . ': input object type ' .
                            $access->getInputObjTarget($name) . ' not found';
                    } 
                
            }
        }
        return null;
    }
}