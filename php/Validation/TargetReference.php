<?php namespace Validation;

class TargetReference {

    public function check(\Data\DataDefinition $data): ?string {
        foreach ($data->getTypes() as $type) {
            $names = array('id' => 'native');
            $base = $type->getBase() === null 
                ? array()
                : self::buildReferenceTree($data, $type->getBase());
            foreach ($type->getAttributes() as $attr) {
                if (isset($names[$attr->getName()])) {
                    return 'type ' . $type->getName() . 
                        ': cannot redeclare attribute' . 
                        $attr->getName() . ' (source: ' .
                        $names[$attr->getName()] . ')';
                }
                else {
                    $names[$attr->getName()] = 'attribute';
                }
                if (isset($base[$attr->getName()])) {
                    $bv = $base[$attr->getName()];
                    return 'type ' . $type->getName() . 
                        ': cannot redeclare inherited ' . 
                        $bv['use'] . ' ' . $attr->getName() . 
                        ' (source: ' . $bv['type'] . ')';
                }
            }
            foreach ($type->getJoints() as $joint) {
                if (isset($names[$joint->getName()])) {
                    return 'type ' . $type->getName() . 
                        ': cannot redeclare joint ' . 
                        $joint->getName() . ' (source: ' .
                        $names[$joint->getName()] . ')';
                }
                else {
                    $names[$joint->getName()] = 'joint';
                }

                if ($joint->getRequired() && $joint->getTarget() == $type->getName())
                    return 'type ' . $type->getName() .
                        ' joint ' . $joint->getName() .
                        ': cannot force join its own type';

                if ($data->getType($joint->getTarget()) === null)
                    return 'type ' . $type->getName() .
                        ' joint ' . $joint->getName() .
                        ': cannot find type ' . $joint->getTarget();

                
                if (isset($base[$joint->getName()])) {
                    $bv = $base[$joint->getName()];
                    return 'type ' . $type->getName() . 
                        ': cannot redeclare inherited ' . 
                        $bv['use'] . ' ' . $joint->getName() . 
                        ' (source: ' . $bv['type'] . ')';
                }
            }
        }
        return null;
    }

    private function buildReferenceTree(\Data\DataDefinition $data, string $table, array $ignore = array()): array {
        if (\in_array($table, $ignore))
            return array();
        $ignore []= $table;
        $obj = $data->getType($table);
        if ($obj === null)
            return array();
        
        $result = $obj->getBase() === null 
            ? array(
                'id' => array(
                    'type' => 'native',
                    'use' => 'native'
                )
            )
            : self::buildReferenceTree($data, $obj->getBase());

        foreach ($obj->getAttributes() as $attr)
            $result [$attr->getName()] = array(
                'type' => $table,
                'use' => 'attribute'
            );

        foreach ($obj->getJoints() as $joint)
            $result [$joint->getName()] = array(
                'type' => $table,
                'use' => 'joint'
            );

        return $result;
    }
}