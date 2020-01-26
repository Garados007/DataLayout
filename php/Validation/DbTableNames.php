<?php namespace Validation;

class DbTableNames {

    public function check(\Data\DataDefinition $data): ?string {
        $maxSize = null;
        switch (true) {
            case $data->getEnvironment()->getBuild() instanceof \Data\PhpBuild:
                $maxSize = $data->getEnvironment()->getBuild()->getMaxDbTableNameLength();
                break;
            default: return null;
        }
        if ($maxSize <= 0) return null;
        //create buckets
        $buckets = array();
        foreach ($data->getTypes() as $type) {
            $name = $type->getName();
            $prefix = \substr($name, 0, $maxSize);
            if (!isset($buckets[$prefix]))
                $buckets[$prefix] = array();
            $buckets[$prefix] []= $name;
        }
        while (($key = $this->getFullKey($buckets, $maxSize)) !== null) {
            if (strlen($key) == 0)
                return 'it exists to many types to shrink their names to fit the db requirements';
            $this->combine($buckets, \substr($key, 0, - 1));
        }
        //set the types corresponding to their buckets
        foreach ($buckets as $key => $bucket) {
            $max = count($bucket);
            $len = \strlen((string)$max);
            for ($i = 0; $i < $max; ++$i) {
                $type = $data->getType($bucket[$i]);
                $name = $max == 1 && $key == $bucket[$i]
                    ? $key 
                    : $key . str_pad((string)($i + 1), $len, '0', STR_PAD_LEFT);
                $type->setDbName($name);
            }
        }
        //create contraint buckets
        $buckets = array();
        foreach ($data->getTypes() as $type) {
            foreach ($type->getContraintNames($data, $data->getEnvironment()->getBuild()) as $name) {
                $prefix = \substr($name, 0, $maxSize);
                if (!isset($buckets[$prefix]))
                    $buckets[$prefix] = array();
                $buckets[$prefix] []= $name;
            }
        }
        while (($key = $this->getFullKey($buckets, $maxSize)) !== null) {
            if (strlen($key) == 0)
                return 'it exists to many contraints to shrink their names to fit the db requirements';
            $this->combine($buckets, \substr($key, 0, -1));
        }
        //set the contraint table
        $table = array();
        foreach ($buckets as $key => $bucket) {
            $max = count($bucket);
            $len = \strlen((string)$max);
            for ($i = 0; $i < $max; ++$i) {
                $name = $max == 1 && $key == $bucket[$i]
                    ? $key
                    : $key . str_pad((string)($i + 1), $len, '0', STR_PAD_LEFT);
                $table[$bucket[$i]] = $name;
            }
        }
        foreach ($data->getTypes() as $type) {
            $type->setConstraintTable($table);
        }
        //finish
        return null;
    }

    private function getFullKey(array $buckets, int $maxSize): ?string {
        foreach ($buckets as $key => $value) {
            $count = count($value);
            $name = $key . (string)$count;
            if (\strlen($name) > $maxSize)
                return $key;
        }
        return null;
    }

    private function combine(array &$buckets, string $prefix) {
        $result = array();
        $remove = array();
        foreach ($buckets as $key => $value) {
            if ($prefix == \substr($key, 0, \strlen($prefix))) {
                $result = array_merge($result, $value);
                $remove []= $key;
            }
        }
        foreach ($remove as $key)
            unset($buckets[$key]);
        $buckets[$prefix] = $result;
    }
}