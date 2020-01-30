<?php namespace Data;

class TypeBucket {
    private $types;
    private $param;

    public function __construct(Build $build, Type $type, array $types) {
        $this->types = array();
        $this->param = array();

        $used = array();
        $this->loadType($build, $type);
        $used []= $type->getName();

        $modified = true;
        while ($modified) {
            $modified = false;
            foreach ($types as $t) {
                if (in_array($t->getName(), $used))
                    continue;
                if ($t->getBase() !== null && \in_array($t->getBase(), $used)) {
                    $this->loadType($build, $t);
                    $used []= $t->getName();
                    $modified = true;
                }
            }
        }
    }

    private function loadType(Build $build, Type $type) {
        $this->types []= $type;
        foreach ($type->getAttributes() as $attr) {
            if ($attr->getSecurity()->isExclude($build, Build::getBuildMode()))
                continue;
            $name = 'c' . (string)count($this->param);
            $this->param[$name] = array(
                'name' => $name,
                'type' => $type->getName(),
                'dbtype' => $type->getDbName(),
                'source' => $attr->getName(),
                'kind' => 'attribute'
            );
        }
        foreach ($type->getJoints() as $joint) {
            if ($joint->getSecurity()->isExclude($build, Build::getBuildMode()))
                continue;
            $name = 'c' . (string)count($this->param);
            $this->param[$name] = array(
                'name' => $name,
                'type' => $type->getName(),
                'dbtype' => $type->getDbName(),
                'source' => $joint->getName(),
                'kind' => 'joint'
            );
        }
    }

    public function getTypes(): array {
        return $this->types;
    }

    public function getType(string $name): ?Type {
        foreach ($this->types as $type)
            if ($type->getName() == $name)
                return $type;
        return null;
    }

    private function convert(array $entry): TypeBucketEntry {
        $result = new TypeBucketEntry();
        $result->name = $entry['name'];
        $result->type = $entry['type'];
        $result->dbtype = $entry['dbtype'];
        $result->source = $entry['source'];
        $result->kind = $entry['kind'];
        return $result;
    }

    public function getParams(): array {
        return array_map (function ($e) {
            return $this->convert($e);
        }, $this->param);
    }

    public function getParam(string $name): ?TypeBucketEntry {
        if (isset($this->param[$name]))
            return $this->convert($this->param[$name]);
        else return null;
    }

    public function getFromAttribute(string $type, string $source): ?TypeBucketEntry {
        foreach ($this->param as $entry) {
            if ($entry['type'] == $type && $entry['source'] == $source && $entry['kind'] == 'attribute')
                return $this->convert($entry);
        }
        return null;
    }

    public function getFromJoint(string $type, string $source): ?TypeBucketEntry {
        foreach ($this->param as $entry) {
            if ($entry['type'] == $type && $entry['source'] == $source && $entry['kind'] == 'joint')
                return $this->convert($entry);
        }
        return null;
    }
}

class TypeBucketEntry {
    /** string */
    public $name;
    /** string */
    public $type;
    /** string */
    public $dbtype;
    /** string */
    public $source;
    /** 'attribute' | 'joint' */
    public $kind;
}