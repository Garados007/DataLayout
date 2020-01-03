<?php namespace Test\TestEnvironment\Data;

//import db management file
require_once __DIR__ . '/../../../../lib/script/db.php';
require_once __DIR__ . '/../Environment.php';

use \Test\TestEnvironment\Environment as Env;

class ObjectA {
    protected $id;
    public $par1;
    public $par2;
    public $par3;
    public $json;
    public $created;
    public $parent;

    public function getId(): ?int {
        return $this->id;
    }

    public function getPar1(): int {
        return $this->par1;
    }

    public function setPar1(int $par1) {
        $this->par1 = $par1;
    }

    public function getPar2(): int {
        return $this->par2;
    }

    public function setPar2(int $par2) {
        $this->par2 = $par2;
    }

    public function getPar3(): ?string {
        return $this->par3;
    }

    public function setPar3(?string $par3) {
        $this->par3 = $par3;
    }

    public function getJson() {
        return $this->json;
    }

    public function setJson($json) {
        $this->json = $json;
    }

    public function getCreated(): int {
        return $this->created;
    }

    public function setCreated(int $created) {
        $this->created = $created;
    }

    public function getParent($loadCached = true): ?\Test\TestEnvironment\Data\ObjectA {
        return $this->parent === null ? null : \Test\TestEnvironment\Data\ObjectA::load($this->parent, $loadCached);
    }

    public function setParent(?\Test\TestEnvironment\Data\ObjectA $parent) {
        $this->parent = $parent === null ? null : $parent->getId();
    }

    public function __construct() {
        $this->id = null;
        $this->par1 = 0;
        $this->par2 = 0;
        $this->par3 = 'a';
        $this->json = json_decode('[]', true);
        $this->created = time();
        $this->parent = null;
    }

    public static function load(int $id, bool $enableCache = true): ?ObjectA {
        if ($enableCache && isset(self::$buffer[$id])) {
            $obj = new self();
            $obj->deserialize(self::$buffer[$id]);
            return $obj;
        }
        $result = \DB::getResult(""
            . "SELECT id, `par1`, `par2`, `par3`, `json`, `created`, `parent`" . PHP_EOL
            . "FROM `test_ObjectA`" . PHP_EOL
            . "WHERE id=$id;"
        );
        return static::loadFromDbResult($result);
    }

    protected function serialize(): array {
        return array(
            'id' => $this->id,
            'par1' => $this->par1,
            'par2' => $this->par2,
            'par3' => $this->par3,
            'json' => json_encode($this->json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK),
            'created' => $this->created,
            'parent' => $this->parent
        );
    }

    protected function deserialize(array $data) {
        $this->id = $data['id'];
        $this->par1 = $data['par1'];
        $this->par2 = $data['par2'];
        $this->par3 = $data['par3'];
        $this->json = json_decode($data['json'], true);
        $this->created = $data['created'];
        $this->parent = $data['parent'];
    }

    protected static $buffer = array();

    public static function clearBuffer(?int $id = null) {
        if ($id === null)
            self::$buffer = array();
        else unset(self::$buffer[$id]);
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
        $data->parent = $entry['parent'] === null ? null : (int)$entry['parent'];
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
            $_par1 = (string)$this->par1;
            $_par2 = (string)$this->par2;
            $_par3 = $this->par3 === null ? 'NULL' : '\'' . \DB::escape($this->par3) . '\'';
            $_json = '\'' . \DB::escape(json_encode($this->json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK)) . '\'';
            $_created = 'FROM_UNIXTIME(' . $this->created . ')';
            $_parent = $this->parent === null ? 'NULL' : $this->parent;
            $result = \DB::getMultiResult(""
                . "INSERT INTO `test_ObjectA`" . PHP_EOL
                . "    (`par1`, `par2`, `par3`, `json`, `created`, `parent`)" . PHP_EOL
                . "VALUES (${_par1}, ${_par2}, ${_par3}, ${_json}, ${_created}, ${_parent});" . PHP_EOL
                . "SELECT LAST_INSERT_ID() AS \"id\";"
            );
            if ($set = $result->getResult())
                $set->free();
            $id = (int)($result->getResult()->getEntry())['id'];
            $this->id = $id;
            static::load($id); //load this item to the buffer
        }
        else {
            //update db
            $own_raw = $this->serialize();
            $other_raw = self::$buffer[$this->id];
            $diff = array();
            foreach ($own_raw as $key => $value)
                if ($other_raw[$key] != $value)
                    $diff[ $key ] = $value;
            if (count($diff) == 0)
                return;
            $sql = 'UPDATE `test_ObjectA` SET ';
            $first = true;
            foreach ($diff as $key => $value) {
                if (!in_array($key, ['par1', 'par2', 'par3', 'json', 'created', 'parent']))
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
        $result = \DB::getResult('DELETE FROM `test_ObjectA` WHERE id=' . $this->id . ';');
        $result->free();
        unset(self::$buffer[$this->id]);
        $this->id = null;
    }

    public function fromObjectAParent(bool $loadCached = true): array {
        $id = $this->id;
        $result = \DB::getResult(""
            . "SELECT id" . PHP_EOL
            . "FROM `test_ObjectA`" . PHP_EOL
            . "WHERE `parent`=$id;"
        );
        $list = array();
        while ($entry = $result->getEntry()) {
            if (($item = \Test\TestEnvironment\Data\ObjectA::load((int)$entry['id'], $loadCached)) !== null)
                $list []= $item;
        }
        return $list;
    }

    public function fromObjectBLeaf(bool $loadCached = true): array {
        $id = $this->id;
        $result = \DB::getResult(""
            . "SELECT id" . PHP_EOL
            . "FROM `test_ObjectB`" . PHP_EOL
            . "WHERE `leaf`=$id;"
        );
        $list = array();
        while ($entry = $result->getEntry()) {
            if (($item = \Test\TestEnvironment\Data\ObjectB::load((int)$entry['id'], $loadCached)) !== null)
                $list []= $item;
        }
        return $list;
    }

    public static function fetchIdentity(int $par): array {
        $list = array();
        $result = \DB::getResult(""
            . "SELECT id, `par1`, `par2`, `par3`, `json`, `created`, `parent`" . PHP_EOL
            . "FROM `test_ObjectA`" . PHP_EOL
            . "WHERE ((" . (string)$par . " = `par1`) AND (`par1` = `par2`));"
        );
        while ($entry = $result->getEntry())
            if (($obj = self::loadFromDbResult($entry)) !== null)
                $list []= $obj;
        return $list;
    }

    public static function deleteByPar2(int $par) {
        $result = \DB::getResult(""
            . "DELETE `test_ObjectA`" . PHP_EOL
            . "FROM `test_ObjectA`" . PHP_EOL
            . "WHERE (" . (string)$par . " = `par2`);"
        );
        $result->free();
        static::clearBuffer();
    }

    public static function fetchChilds(\Test\TestEnvironment\Data\ObjectA $node): array {
        $list = array();
        $result = \DB::getResult(""
            . "SELECT id, `par1`, `par2`, `par3`, `json`, `created`, `parent`" . PHP_EOL
            . "FROM `test_ObjectA`" . PHP_EOL
            . "WHERE (" . $node->getId() . " = `parent`);"
        );
        while ($entry = $result->getEntry())
            if (($obj = self::loadFromDbResult($entry)) !== null)
                $list []= $obj;
        return $list;
    }
}
