<?php namespace Test\TestEnvironment\Data;

//import db management file
require_once __DIR__ . '/../../../../lib/script/db.php';
require_once __DIR__ . '/../Environment.php';
require_once __DIR__ . '/ObjectA.php';

use \Test\TestEnvironment\Environment as Env;

class ObjectB extends \Test\TestEnvironment\Data\ObjectA {
    public $ext;
    public $leaf;

    public function getExt(): bool {
        return $this->ext;
    }

    public function setExt(bool $ext) {
        $this->ext = $ext;
    }

    public function getLeaf($loadCached = true): ?\Test\TestEnvironment\Data\ObjectA {
        return $this->leaf === null ? null : \Test\TestEnvironment\Data\ObjectA::load($this->leaf, $loadCached);
    }

    public function setLeaf(?\Test\TestEnvironment\Data\ObjectA $leaf) {
        $this->leaf = $leaf === null ? null : $leaf->getId();
    }

    public function __construct() {
        parent::__construct();
        $this->ext = false;
        $this->leaf = null;
    }

    public static function load(int $id, bool $enableCache = true): ?ObjectA {
        if ($enableCache && isset(self::$buffer[$id])) {
            $obj = new self();
            $obj->deserialize(self::$buffer[$id]);
            return $obj;
        }
        $result = \DB::getResult(""
            . "SELECT id, `par1`, `par2`, `par3`, `json`, `created`, `parent`, `ext`, `leaf`" . PHP_EOL
            . "FROM `test_ObjectB`" . PHP_EOL
            . "JOIN `test_ObjectA` USING (id)" . PHP_EOL
            . "WHERE id=$id;"
        );
        return static::loadFromDbResult($result);
    }

    protected function serialize(): array {
        return array_replace(
            parent::serialize(),
            array(
                'id' => $this->id,
                'ext' => $this->ext,
                'leaf' => $this->leaf
            )
        );
    }

    protected function deserialize(array $data) {
        parent::deserialize($data);
        $this->ext = $data['ext'];
        $this->leaf = $data['leaf'];
    }

    protected static function loadFromDbResult(\DBResult $result): ?ObjectA {
        $data = new self();
        $entry = $result->getEntry();
        if (!$entry)
            return null;
        $data->id = (int)$entry['id'];
        $data->par1 = (int)$entry['par1'];
        $data->par2 = (int)$entry['par2'];
        $data->par3 = $entry['par3'] === null ? null : (string)$entry['par3'];
        $data->json = json_decode((string)$entry['json'], true);
        $data->created = strtotime($entry['created']);
        $data->ext = (bool)$entry['ext'];
        $data->parent = $entry['parent'] === null ? null : (int)$entry['parent'];
        $data->leaf = $entry['leaf'] === null ? null : (int)$entry['leaf'];
        self::$buffer[$data->id] = $data->serialize();
        return $data;
    }

    public function save() {
        //update buffer
        if ($this->id !== null && !isset(self::$buffer[$this->id])) {
            if (static::load($this->id) === null)
                $this->id = null;
        }
        if ($this->id === null) {
            //add to db
            $_ext = $this->ext ? 'TRUE' : 'FALSE';
            $_leaf = $this->leaf === null ? 'NULL' : $this->leaf;
            parent::save();
            $_id = $this->id;
            $result = \DB::getMultiResult(""
                . "INSERT INTO `test_ObjectB`" . PHP_EOL
                . "    (id, `ext`, `leaf`)" . PHP_EOL
                . "VALUES (${_id}, ${_ext}, ${_leaf});"
            );
            $result->free();
            static::load($_id); //load this item to the buffer
        }
        else {
            //update db
            parent::save();
            $own_raw = $this->serialize();
            $other_raw = self::$buffer[$this->id];
            $diff = array();
            foreach ($own_raw as $key => $value)
                if ($other_raw[$key] != $value)
                    $diff[ $key ] = $value;
            if (count($diff) == 0)
                return;
            $sql = 'UPDATE `test_ObjectB` SET ';
            $first = true;
            foreach ($diff as $key => $value) {
                if (!in_array($key, ['ext', 'leaf']))
                    continue;
                if ($first) $first = false;
                else $sql .= ', ';
                $sql .= '`' . $key . '`=';
                switch (true) {
                    case $value === null: $sql .= 'NULL'; break;
                    case is_bool($value): $sql .= $value ? 'TRUE' : 'FALSE'; break;
                    case is_int($value): $sql .= (string)$value; break;
                    case is_float($value): $sql .= (string)$value; break;
                    case is_string($value): $sql .= '"' . \DB::escape($value) . '"'; break;
                    default: $sql .= 'NULL'; break;
                }
            }
            $sql .= ' WHERE id=' . $this->id . ';';
            $result = \DB::getResult($sql);
            $result->free();
            if (static::load($this->id, false) !== null)
                $this->deserialize(self::$buffer[$this->id]);
        }
    }

    public function delete() {
        if ($this->id === null)
            return;
        parent::delete();
        $result = \DB::getResult('DELETE FROM `test_ObjectB` WHERE id=' . $this->id . ';');
        $result->free();
        unset(self::$buffer[$this->id]);
        $this->id = null;
    }

    public static function fetchExt(): array {
        $list = array();
        $result = \DB::getResult(""
            . "SELECT id, `par1`, `par2`, `par3`, `json`, `created`, `parent`, `ext`, `leaf`" . PHP_EOL
            . "FROM `test_ObjectB`" . PHP_EOL
            . "JOIN `test_ObjectA` USING (id)" . PHP_EOL
            . "WHERE (`ext` = TRUE);"
        );
        while ($entry = $result->getEntry())
            if (($obj = self::loadFromDbResult($entry)) !== null)
                $list []= $obj;
        return $list;
    }

    public static function deleteNotExt() {
        $result = \DB::getResult(""
            . "DELETE `test_ObjectB`, `test_ObjectA`" . PHP_EOL
            . "FROM `test_ObjectB`" . PHP_EOL
            . "INNER JOIN `test_ObjectA` USING (id)" . PHP_EOL
            . "WHERE (`ext` = FALSE);"
        );
        $result->free();
        static::clearBuffer();
    }
}
