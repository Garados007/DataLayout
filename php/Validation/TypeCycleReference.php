<?php namespace Validation;

require_once __DIR__ . '/../Data/DataDefinition.php';

class TypeCycleReference {
    private $data;

    public function check(\Data\DataDefinition $data): ?string {
        $this->data = $data;

        $refTable = array();
        $types = $data->getTypes();
        $marks = array();
        $left = array();

        foreach ($types as $type) {
            $refTable[ $type->getName() ] = $type->getBase();
            $marks[ $type->getName() ] = null;
            $left []= $type->getName();
        }

        for ($mark = 1; count($left) > 0; $mark++) {
            $type = $left[0];
            while ($type !== null) {
                $left = array_values(array_diff($left, [ $type ]));
                if ($marks[$type] == $mark) {
                    return 'loop found: ' . $this->extractLoop($refTable, $type);
                }
                $marks[$type] = $mark;
                $type = $refTable[ $type ];

                if (!array_key_exists($type, $refTable) && $type !== null) {
                    var_dump($refTable);
                    return 'type not found: ' . $type;
                }
            }
        }

        return null;
    }

    protected function extractLoop($refTable, $startType): string {
        $type = $startType;
        $result = $type;
        do {
            $type = $refTable[ $type ];
            $result .= ' -> ' . $type;
        }
        while ($type != $startType && $type != null);
        return $result;
    }
}