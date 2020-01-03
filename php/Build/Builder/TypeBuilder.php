<?php namespace Build\Builder;

require_once __DIR__ . '/../../Data/DataDefinition.php';
require_once __DIR__ . '/../BuildConfig.php';
require_once __DIR__ . '/../Token.php';

use \Build\BuildConfig as Config;
use \Build\Token as Token;
use \Data\DataDefinition as DataDef;

class TypeBuilder {
    public function buildTypeCode(Config $config, DataDef $data, \Data\Type $type): Token {
        return Token::multi(
            Token::text('<?php namespace '),
            Token::text(substr($data->getEnvironment()->getBuild()->getClassNamespace(), 1)),
            Token::textnl('\Data;'),
            Token::nl(),
            Token::textnl('//import db management file'),
            Token::text('require_once '),
            $config->useRelativePaths 
                ? Token::text('__DIR__ . \'/')
                : Token::text('\''),
            Token::text($config->useRelativePaths
                ? ($config->dbScriptPath)($config->dbOutputDir . '/Data/' . $type->getName() . '.php')
                : $config->dbScriptPath
            ),
            Token::textnl('\';'),
            Token::textnl('require_once __DIR__ . \'/../Environment.php\';') ,
            $type->getBase() === null 
                ? Token::text('')
                : Token::multi(
                    Token::text('require_once __DIR__ . \'/'),
                    Token::text($type->getBase()),
                    Token::textnl('.php\';')
                ),
            Token::nl(),
            Token::text('use '),
            Token::text($data->getEnvironment()->getBuild()->getClassNamespace()),
            Token::textnl('\Environment as Env;'),
            Token::nl(),
            Token::text('class '),
            Token::text($type->getName()),
            $type->getBase() === null 
                ? Token::text('')
                : Token::multi(
                    Token::text(' extends '),
                    Token::text($data->getEnvironment()->getBuild()->getClassNamespace()),
                    Token::text('\\Data\\'),
                    Token::text($type->getBase())
                ),
            Token::textnlpush(' {'),
            $type->getBase() === null
                ? Token::textnl('protected $id;')
                : Token::text(''),
            Token::array(array_map(function ($attr) use ($config, $data, $type) {
                return Token::multi(
                    Token::text($data->getEnvironment()->getBuild()->getPublicMemberAccess()
                        ? 'public'
                        : 'protected'),
                    Token::text(' $'),
                    Token::text($attr->getName()),
                    Token::textnl(';')
                );
            }, $type->getAttributes())),
            Token::array(array_map(function ($joint) use ($config, $data, $type) {
                return Token::multi(
                    Token::text($data->getEnvironment()->getBuild()->getPublicMemberAccess()
                        ? 'public'
                        : 'private'),
                    Token::text(' $'),
                    Token::text($joint->getName()),
                    Token::textnl(';')
                );
            }, $type->getJoints())),
            Token::nl(),
            //getter & setter
            $type->getBase() === null 
                ? Token::multi(
                    Token::textnlpush('public function getId(): ?int {'),
                    Token::textnlpop('return $this->id;'),
                    Token::textnl('}'),
                    Token::nl()
                )
                : Token::text(''),
            Token::array(array_map(function ($attr) {
                $type = self::getPhpTypeName($attr->getType(), $attr->getOptional());
                return Token::multi(
                    Token::text('public function get'),
                    Token::text(ucfirst($attr->getName())),
                    Token::text('()'),
                    $type !== null
                        ? Token::text(': ' . $type)
                        : Token::text(''),
                    Token::textnlpush(' {'),
                    Token::text('return $this->'),
                    Token::text($attr->getName()),
                    Token::textnlpop(';'),
                    Token::textnl('}'),
                    Token::nl(),
                    Token::text('public function set'),
                    Token::text(ucfirst($attr->getName())),
                    Token::text('('),
                    $type !== null 
                        ? Token::text($type . ' $')
                        : Token::text('$'),
                    Token::text($attr->getName()),
                    Token::textnlpush(') {'),
                    Token::text('$this->'),
                    Token::text($attr->getName()),
                    Token::text(' = $'),
                    Token::text($attr->getName()),
                    Token::textnlpop(';'),
                    Token::textnl('}'),
                    Token::nl()
                );
            }, $type->getAttributes())),
            Token::array(array_map(function ($joint) use ($data) {
                return Token::multi(
                    Token::text('public function get'),
                    Token::text(ucfirst($joint->getName())),
                    Token::text('($loadCached = true): '),
                    Token::text($joint->getRequired() ? '' : '?'),
                    Token::text($data->getEnvironment()->getBuild()->getClassNamespace()),
                    Token::text('\\Data\\'),
                    Token::text($joint->getTarget()),
                    Token::textnlpush(' {'),
                    $joint->getRequired()
                        ? Token::text('return ')
                        : Token::multi(
                            Token::text('return $this->'),
                            Token::text($joint->getName()),
                            Token::text(' === null ? null : ')
                        ),
                    Token::text($data->getEnvironment()->getBuild()->getClassNamespace()),
                    Token::text('\\Data\\'),
                    Token::text($joint->getTarget()),
                    Token::text('::load($this->'),
                    Token::text($joint->getName()),
                    Token::textnlpop(', $loadCached);'),
                    Token::textnl('}'),
                    Token::nl(),
                    Token::text('public function set'),
                    Token::text(ucfirst($joint->getName())),
                    Token::text('('),
                    Token::text($joint->getRequired() ? '' : '?'),
                    Token::text($data->getEnvironment()->getBuild()->getClassNamespace()),
                    Token::text('\\Data\\'),
                    Token::text($joint->getTarget()),
                    Token::text(' $'),
                    Token::text($joint->getName()),
                    Token::textnlpush(') {'),
                    Token::text('$this->'),
                    Token::text($joint->getName()),
                    Token::text(' = $'),
                    $joint->getRequired()
                        ? Token::text('')
                        : Token::multi(
                            Token::text($joint->getName()),
                            Token::text(' === null ? null : $')
                        ),
                    Token::text($joint->getName()),
                    Token::text('->getId()'),
                    Token::textnlpop(';'),
                    Token::textnl('}'),
                    Token::nl()
                );
            }, $type->getJoints())),
            //__constructor()
            Token::multi(
                Token::textnlpush('public function __construct() {'),
                $type->getBase() === null 
                    ? Token::textnl('$this->id = null;')
                    : Token::textnl('parent::__construct();'),
                Token::array(array_map(function ($attr) use ($config, $data, $type) {
                    $default = $attr->getDefault();
                    $res = array();
                    $res []= Token::text('$this->');
                    $res []= Token::text($attr->getName());
                    $res []= Token::text(' = ');
                    switch (true) {
                        case $attr->getType() == 'date' && $default === null:
                            $res []= Token::text('time()');
                            break;
                        case $attr->getType() == 'json':
                            $res []= Token::multi(
                                Token::text('json_decode(\''),
                                Token::text(\addslashes($default)),
                                Token::text('\', true)')
                            );
                            break;
                        case $default === null:
                            $res []= Token::text('null');
                            break;
                        case is_bool($default):
                            $res []= Token::text($default ? 'true' : 'false');
                            break;
                        case is_int($default):
                            $res []= Token::text((string)$default);
                            break;
                        case is_float($default):
                            $res []= Token::text((string)$default);
                            break;
                        case is_string($default):
                            $res []= Token::text('\'' . addslashes($default) . '\'');
                            break;
                        default: $res []= Token::text('null'); break;
                    }
                    $res []= Token::textnl(';');
                    return $res;
                }, $type->getAttributes())),
                Token::array(array_map(function ($joint) use ($config, $data, $type) {
                    return Token::multi(
                        Token::text('$this->'),
                        Token::text($joint->getName()),
                        Token::textnl(' = null;')
                    );
                }, $type->getJoints())),
                Token::pop(),
                Token::textnl('}'),
                Token::nl(),
            ),
            //static load($id, $cached): ?Type
            Token::multi(
                Token::text('public static function load(int $id, bool $enableCache = true): ?'),
                Token::text($this->getRootType($data, $type)),
                Token::textnlpush(' {'),
                Token::textnlpush('if ($enableCache && isset(self::$buffer[$id])) {'),
                Token::textnl('$obj = new self();'),
                Token::textnl('$obj->deserialize(self::$buffer[$id]);'),
                Token::textnlpop('return $obj;'),
                Token::textnl('}'),
                Token::textnlpush('$result = \\DB::getResult(""'),
                Token::frame(Token::multi(
                    Token::text('SELECT id'),
                    Token::array(array_map(function ($name) {
                        return Token::multi(
                            Token::text(', `'),
                            Token::text($name),
                            Token::text('`')
                        );
                    }, $this->getFlatVariableNames($data, $type))),
                    Token::nl(),
                    Token::text('FROM `'),
                    Token::text($data->getEnvironment()->getBuild()->getDbPrefix()),
                    Token::text($type->getName()),
                    Token::textnl('`'),
                    Token::array(array_map(function ($name) use ($data) {
                        return Token::multi(
                            Token::text('JOIN `'),
                            Token::text($data->getEnvironment()->getBuild()->getDbPrefix()),
                            Token::text($name),
                            Token::textnl('` USING (id)'),
                        );
                    }, $this->getParentTypes($data, $type))),
                    Token::text('WHERE id=$id;'),
                ), '. "', '" . PHP_EOL', 'addslashes'),
                Token::textnlpop('"'),
                Token::textnl(');'),
                Token::textnlpop('return static::loadFromDbResult($result);'),
                Token::textnl('}'),
                Token::nl()
            ),
            //serializer & deserializer
            Token::multi(
                Token::textnlpush('protected function serialize(): array {'),
                $type->getBase() === null 
                    ? Token::multi(
                        Token::textnlpush('return array('),
                        Token::text('\'id\' => $this->id'),
                    )
                    : Token::multi(
                        Token::textnlpush('return array_replace('),
                        Token::textnl('parent::serialize(),'),
                        Token::textnlpush('array('),
                        Token::text('\'id\' => $this->id')
                    ),
                Token::array(array_map(function ($attr) {
                    $data = Token::multi(
                        Token::text('$this->'),
                        Token::text($attr->getName())
                    );
                    switch ($attr->getType()) {
                        case 'json':
                            $data = Token::multi(
                                Token::text('json_encode('),
                                $data,
                                Token::text(', JSON_UNESCAPED_UNICODE '),
                                Token::text('| JSON_UNESCAPED_SLASHES '),
                                Token::text('| JSON_NUMERIC_CHECK)')
                            );
                            break;
                    }
                    return Token::multi(
                        Token::textnl(','),
                        Token::text('\''),
                        Token::text(\addslashes($attr->getName())),
                        Token::text('\' => '),
                        $data
                    );
                }, $type->getAttributes())),
                Token::array(array_map(function ($joint) {
                    return Token::multi(
                        Token::textnl(','),
                        Token::text('\''),
                        Token::text(\addslashes($joint->getName())),
                        Token::text('\' => $this->'),
                        Token::text($joint->getName())
                    );
                }, $type->getJoints())),
                Token::nl(),
                Token::pop(),
                $type->getBase() === null 
                    ? Token::text('')
                    : Token::textnlpop(')'),
                Token::textnlpop(');'),
                Token::textnl('}'),
                Token::nl(),
                Token::textnlpush('protected function deserialize(array $data) {'),
                $type->getBase() === null
                    ? Token::textnl('$this->id = $data[\'id\'];')
                    : Token::textnl('parent::deserialize($data);'),
                Token::array(array_map(function ($attr) {
                    $data = Token::multi(
                        Token::text('$data[\''),
                        Token::text(\addslashes($attr->getName())),
                        Token::text('\']')
                    );
                    switch ($attr->getType()) {
                        case 'json':
                            $data = Token::multi(
                                Token::text('json_decode('),
                                $data,
                                Token::text(', true)')
                            );
                            break;
                    }
                    return Token::multi(
                        Token::text('$this->'),
                        Token::text($attr->getName()),
                        Token::text(' = '),
                        $data,
                        Token::textnl(';')
                    );
                }, $type->getAttributes())),
                Token::array(array_map(function ($joint) {
                    return Token::multi(
                        Token::text('$this->'),
                        Token::text($joint->getName()),
                        Token::text(' = $data[\''),
                        Token::text(addslashes($joint->getName())),
                        Token::textnl('\'];')
                    );
                }, $type->getJoints())),
                Token::pop(),
                Token::textnl('}'),
                Token::nl()
            ),
            //static factory buffer
            $type->getBase() === null 
                ? Token::multi(
                    Token::textnl('protected static $buffer = array();'),
                    Token::nl(),
                    Token::textnlpush('public static function clearBuffer(?int $id = null) {'),
                    Token::textnlpush('if ($id === null)'),
                    Token::textnlpop('self::$buffer = array();'),
                    Token::textnlpop('else unset(self::$buffer[$id]);'),
                    Token::textnl('}'),
                    Token::nl()
                )
                : Token::text(''),
            //loadFromDbResult
            Token::multi(
                Token::text('protected static function loadFromDbResult(\DBResult $result): ?'),
                Token::text($this->getRootType($data, $type)),
                Token::textnlpush(' {'),
                Token::textnl('$data = new self();'),
                Token::textnl('$entry = $result->getEntry();'),
                Token::textnlpush('if (!$entry)'),
                Token::textnlpop('return null;'),
                Token::textnl('$data->id = (int)$entry[\'id\'];'),
                Token::array(array_map(function ($attr) {
                    $entry = Token::multi(
                        Token::text('$entry[\''),
                        Token::text(\addslashes($attr->getName())),
                        Token::text('\']')
                    );
                    $original = $entry;
                    $entry = self::getWrappedTypeFromSql($attr->getType(), $entry);
                    if ($attr->getOptional())
                        $entry = Token::multi(
                            $original,
                            Token::text(' === null ? null : '),
                            $entry
                        );
                    return Token::multi(
                        Token::text('$data->'),
                        Token::text($attr->getName()),
                        Token::text(' = '),
                        $entry,
                        Token::textnl(';')
                    );
                }, $this->getFlatAttributes($data, $type))),
                Token::array(array_map(function ($joint) {
                    return Token::multi(
                        Token::text('$data->'),
                        Token::text($joint->getName()),
                        Token::text(' = '),
                        $joint->getRequired()
                            ? Token::text('')
                            : Token::multi(
                                Token::text('$entry[\''),
                                Token::text(\addslashes($joint->getName())),
                                Token::text('\'] === null ? null : ')
                            ),
                        Token::text('(int)$entry[\''),
                        Token::text(\addslashes($joint->getName())),
                        Token::textnl('\'];')
                    );
                }, $this->getFlatJoints($data, $type))),
                Token::textnl('self::$buffer[$data->id] = $data->serialize();'),
                Token::textnlpop('return $data;'),
                Token::textnl('}'),
                Token::nl()
            ),
            //save
            Token::multi(
                Token::textnlpush('public function save() {'),
                Token::multi(
                    Token::textnl('//update buffer'),
                    Token::textnlpush('if ($this->id !== null && !isset(self::$buffer[$this->id])) {'),
                    Token::textnlpush('if (static::load($this->id) === null)'),
                    Token::textnlpop('$this->id = null;'),
                    Token::pop(),
                    Token::textnl('}')
                ),
                Token::textnlpush('if ($this->id === null) {'),
                Token::multi(
                    Token::textnl('//add to db'),
                    Token::array(array_map(function ($attr) {
                        $res = array();
                        $res []= Token::text('$_');
                        $res []= Token::text($attr->getName());
                        $res []= Token::text(' = ');
                        $entry = Token::text('$this->' . $attr->getName());
                        $baseEntry = $entry;
                        switch ($attr->getType()) {
                            case 'bool':
                                $entry = Token::multi(
                                    $entry, 
                                    Token::text(' ? \'TRUE\' : \'FALSE\'')
                                );
                                break;
                            case 'byte':
                            case 'short':
                            case 'int':
                            case 'long':
                            case 'sbyte':
                            case 'ushort':
                            case 'uint':
                            case 'ulong':
                                $entry = Token::multi(Token::text('(string)'), $entry);
                                break;
                            case 'float':
                            case 'double':
                                $entry = Token::multi(Token::text('(string)'), $entry);
                                break;
                            case 'string':
                            case 'bytes':
                                $entry = Token::multi(
                                    Token::text('\'\\\'\' . \\DB::escape('), 
                                    $entry,
                                    Token::text(') . \'\\\'\'')
                                );
                                break;
                            case 'date':
                                $entry = Token::multi(
                                    Token::text('\'FROM_UNIXTIME(\' . '),
                                    $entry,
                                    Token::text(' . \')\'')
                                );
                                break;
                            case 'json':
                                $entry = Token::multi(
                                    Token::text('\'\\\'\' . \\DB::escape('), 
                                    Token::text('json_encode('),
                                    $entry,
                                    Token::Text(', JSON_UNESCAPED_UNICODE '),
                                    Token::text('| JSON_UNESCAPED_SLASHES '),
                                    Token::text('| JSON_NUMERIC_CHECK'),
                                    Token::text(')) . \'\\\'\'')
                                );
                                break;
                        }
                        if ($attr->getOptional()) {
                            $entry = Token::multi(
                                $baseEntry,
                                Token::text(' === null ? \'NULL\' : '),
                                $entry
                            );
                        }
                        $res []= $entry;
                        $res []= Token::textnl(';');
                        return $res;
                    }, $type->getAttributes())),
                    Token::array(array_map(function ($joint) {
                        return Token::multi(
                            Token::text('$_'),
                            Token::text($joint->getName()),
                            Token::text(' = $this->'),
                            Token::text($joint->getName()),
                            $joint->getRequired()
                                ? Token::text('')
                                : Token::multi(
                                    Token::text(' === null ? \'NULL\' : $this->'),
                                    Token::text($joint->getName())
                                ),
                            Token::textnl(';')
                        );
                    }, $type->getJoints())),
                    $type->getBase() === null 
                        ? Token::multi(
                            Token::textnlpush('$result = \\DB::getMultiResult(""'),
                            Token::frame(Token::multi(
                                Token::text('INSERT INTO `'),
                                Token::text($data->getEnvironment()->getBuild()->getDbPrefix()),
                                Token::text($type->getName()),
                                Token::textnlpush('`'),
                                Token::text('('),
                                Token::array(self::intersperce(array_merge(
                                    array_map(function ($attr) {
                                        return Token::multi(
                                            Token::text('`'),
                                            Token::text($attr->getName()),
                                            Token::text('`')
                                        );
                                    }, $type->getAttributes()),
                                    array_map(function ($joint) {
                                        return Token::multi(
                                            Token::text('`'),
                                            Token::text($joint->getName()),
                                            Token::text('`')
                                        );
                                    }, $type->getJoints())
                                ), Token::text(', '))),
                                Token::textnlpop(')'),
                                Token::text('VALUES ('),
                                Token::array(self::intersperce(array_merge(
                                    array_map(function ($attr) {
                                        return Token::multi(
                                            Token::text('${_'),
                                            Token::text($attr->getName()),
                                            Token::text('}')
                                        );
                                    }, $type->getAttributes()),
                                    array_map(function ($joint) {
                                        return Token::multi(
                                            Token::text('${_'),
                                            Token::text($joint->getName()),
                                            Token::text('}')
                                        );
                                    }, $type->getJoints())
                                ), Token::text(', '))),
                                Token::textnl(');'),
                                Token::text('SELECT LAST_INSERT_ID() AS "id";')
                            ), '. "', '" . PHP_EOL', 'addslashes'),
                            Token::textnlpop('"'),
                            Token::textnl(');'),
                            Token::textnlpush('if ($set = $result->getResult())'),
                            Token::textnlpop('$set->free();'),
                            Token::textnl('$id = (int)($result->getResult()->getEntry())[\'id\'];'),
                            Token::textnl('$this->id = $id;'),
                            Token::textnl('static::load($id); //load this item to the buffer'),
                        )
                        : Token::multi(
                            Token::textnl('parent::save();'),
                            Token::textnl('$_id = $this->id;'),
                            Token::textnlpush('$result = \\DB::getMultiResult(""'),
                            Token::frame(Token::multi(
                                Token::text('INSERT INTO `'),
                                Token::text($data->getEnvironment()->getBuild()->getDbPrefix()),
                                Token::text($type->getName()),
                                Token::textnlpush('`'),
                                Token::text('(id'),
                                Token::array(array_merge(
                                    array_map(function ($attr) {
                                        return Token::multi(
                                            Token::text(', `'),
                                            Token::text($attr->getName()),
                                            Token::text('`')
                                        );
                                    }, $type->getAttributes()),
                                    array_map(function ($joint) {
                                        return Token::multi(
                                            Token::text(', `'),
                                            Token::text($joint->getName()),
                                            Token::text('`')
                                        );
                                    }, $type->getJoints())
                                )),
                                Token::textnlpop(')'),
                                Token::text('VALUES (${_id}'),
                                Token::array(array_merge(
                                    array_map(function ($attr) {
                                        return Token::multi(
                                            Token::text(', ${_'),
                                            Token::text($attr->getName()),
                                            Token::text('}')
                                        );
                                    }, $type->getAttributes()),
                                    array_map(function ($joint) {
                                        return Token::multi(
                                            Token::text(', ${_'),
                                            Token::text($joint->getName()),
                                            Token::text('}')
                                        );
                                    }, $type->getJoints())
                                )),
                                Token::text(');')
                            ), '. "', '" . PHP_EOL', 'addslashes'),
                            Token::textnlpop('"'),
                            Token::textnl(');'),
                            Token::textnl('$result->free();'),
                            Token::textnl('static::load($_id); //load this item to the buffer')
                        )
                ),
                Token::pop(),
                Token::textnl('}'),
                Token::textnlpush('else {'),
                Token::multi(
                    Token::textnl('//update db'),
                    $type->getBase() === null
                        ? Token::text('')
                        : Token::textnl('parent::save();'),
                    Token::textnl('$own_raw = $this->serialize();'),
                    Token::textnl('$other_raw = self::$buffer[$this->id];'),
                    Token::textnl('$diff = array();'),
                    Token::textnlpush('foreach ($own_raw as $key => $value)'),
                    Token::textnlpush('if ($other_raw[$key] != $value)'),
                    Token::textnlpop('$diff[ $key ] = $value;'),
                    Token::pop(),
                    Token::textnlpush('if (count($diff) == 0)'),
                    Token::textnlpop('return;'),
                    Token::text('$sql = \'UPDATE `'),
                    Token::text($data->getEnvironment()->getBuild()->getDbPrefix()),
                    Token::text($type->getName()),
                    Token::textnl('` SET \';'),
                    Token::textnl('$first = true;'),
                    Token::textnlpush('foreach ($diff as $key => $value) {'),
                    Token::text('if (!in_array($key, ['),
                    Token::array(self::intersperce(array_merge(
                        array_map(function ($attr) {
                            return Token::multi(
                                Token::text('\''),
                                Token::text(addslashes($attr->getName())),
                                Token::text('\'')
                            );
                        }, $type->getAttributes()),
                        array_map(function ($joint) {
                            return Token::multi(
                                Token::text('\''),
                                Token::text(addslashes($joint->getName())),
                                Token::text('\'')
                            );
                        }, $type->getJoints())
                    ), Token::text(', '))),
                    Token::textnlpush(']))'),
                    Token::textnlpop('continue;'),
                    Token::textnl('if ($first) $first = false;'),
                    Token::textnl('else $sql .= \', \';'),
                    Token::textnl('$sql .= \'`\' . $key . \'`=\';'),
                    Token::textnlpush('switch (true) {'),
                    Token::textnl('case $value === null: $sql .= \'NULL\'; break;'),
                    Token::textnl('case is_bool($value): $sql .= $value ? \'TRUE\' : \'FALSE\'; break;'),
                    Token::textnl('case is_int($value): $sql .= (string)$value; break;'),
                    Token::textnl('case is_float($value): $sql .= (string)$value; break;'),
                    Token::textnl('case is_string($value): $sql .= \'"\' . \\DB::escape($value) . \'"\'; break;'),
                    Token::textnlpop('default: $sql .= \'NULL\'; break;'),
                    Token::textnlpop('}'),
                    Token::textnl('}'),
                    Token::textnl('$sql .= \' WHERE id=\' . $this->id . \';\';'),
                    Token::textnl('$result = \\DB::getResult($sql);'),
                    Token::textnl('$result->free();'),
                    Token::textnlpush('if (static::load($this->id, false) !== null)'),
                    Token::textnlpop('$this->deserialize(self::$buffer[$this->id]);')
                ),
                Token::pop(),
                Token::textnl('}'),
                Token::pop(),
                Token::textnl('}'),
                Token::nl()
            ),
            //delete
            Token::multi(
                Token::textnlpush('public function delete() {'),
                Token::textnlpush('if ($this->id === null)'),
                Token::textnlpop('return;'),
                $type->getBase() === null
                    ? Token::text('')
                    : Token::textnl('parent::delete();'),
                Token::text('$result = \\DB::getResult(\'DELETE FROM `'),
                Token::text($data->getEnvironment()->getBuild()->getDbPrefix()),
                Token::text($type->getName()),
                Token::textnl('` WHERE id=\' . $this->id . \';\');'),
                Token::textnl('$result->free();'),
                Token::textnl('unset(self::$buffer[$this->id]);'),
                Token::textnlpop('$this->id = null;'),
                Token::textnl('}')
            ),
            //joins
            Token::array((function () use ($data, $type) {
                $result = array();
                foreach (self::getForeignJoints($data, $type->getName()) as $name => $list) {
                    $result []= array_map(function ($joint) use ($data, $name) {
                        return Token::multi(
                            Token::nl(),
                            Token::text('public function from'),
                            Token::text(ucfirst($name)),
                            Token::text(ucfirst($joint->getName())),
                            Token::textnlpush('(bool $loadCached = true): array {'),
                            Token::textnl('$id = $this->id;'),
                            Token::textnlpush('$result = \\DB::getResult(""'),
                            Token::frame(Token::multi(
                                Token::textnl('SELECT id'),
                                Token::text('FROM `'),
                                Token::text($data->getEnvironment()->getBuild()->getDbPrefix()),
                                Token::text($name),
                                Token::textnl('`'),
                                Token::text('WHERE `'),
                                Token::text($joint->getName()),
                                Token::text('`=$id;')
                            ), '. "', '" . PHP_EOL', 'addslashes'),
                            Token::textnlpop('"'),
                            Token::textnl(');'),
                            Token::textnl('$list = array();'),
                            Token::textnlpush('while ($entry = $result->getEntry()) {'),
                            Token::text('if (($item = '),
                            Token::text($data->getEnvironment()->getBuild()->getClassNamespace()),
                            Token::text('\\Data\\'),
                            Token::text($name),
                            Token::textnlpush('::load((int)$entry[\'id\'], $loadCached)) !== null)'),
                            Token::textnlpop('$list []= $item;'),
                            Token::pop(),
                            Token::textnl('}'),
                            Token::textnlpop('return $list;'),
                            Token::textnl('}')
                        );
                    }, $list);
                }
                return $result;
            })()),
            //querys
            Token::array(array_map(function ($query) use ($config, $data, $type) {
                $bound = self::getBounds($data->getEnvironment(), $type, $query, $query->getBounds());
                if ($bound === null) $bound = Token::text('FALSE');
                $content = null;
                $returnValue = null;
                switch (true) {
                    case $query->isSearchQuery():
                        $content = Token::multi(
                            Token::textnl('$list = array();'),
                            Token::textnlpush('$result = \\DB::getResult(""'),
                            Token::frame(Token::multi(
                                Token::text('SELECT id'),
                                Token::array(array_map(function ($name) {
                                    return Token::multi(
                                        Token::text(', `'),
                                        Token::text($name),
                                        Token::text('`')
                                    );
                                }, $this->getFlatVariableNames($data, $type))),
                                Token::nl(),
                                Token::text('FROM `'),
                                Token::text($data->getEnvironment()->getBuild()->getDbPrefix()),
                                Token::text($type->getName()),
                                Token::textnl('`'),
                                Token::array(array_map(function ($name) use ($data) {
                                    return Token::multi(
                                        Token::text('JOIN `'),
                                        Token::text($data->getEnvironment()->getBuild()->getDbPrefix()),
                                        Token::text($name),
                                        Token::textnl('` USING (id)'),
                                    );
                                }, $this->getParentTypes($data, $type))),
                                Token::text('WHERE '),
                            ), '. "', '" . PHP_EOL', 'addslashes'),
                            $bound,
                            Token::textnlpop(';"'),
                            Token::textnl(');'),
                            Token::textnlpush('while ($entry = $result->getEntry())'),
                            Token::textnlpush('if (($obj = self::loadFromDbResult($entry)) !== null)'),
                            Token::textnlpop('$list []= $obj;'),
                            Token::pop(),
                            Token::textnl('return $list;')
                        );
                        $returnValue = Token::text('array');
                        break;
                    case $query->isDeleteQuery():
                        $content = Token::multi(
                            Token::textnlpush('$result = \\DB::getResult(""'),
                            Token::frame(Token::multi(
                                Token::text('DELETE `'),
                                Token::text($data->getEnvironment()->getBuild()->getDbPrefix()),
                                Token::text($type->getName()),
                                Token::text('`'),
                                Token::array(array_map(function ($name) use ($data) {
                                    return Token::multi(
                                        Token::text(', `'),
                                        Token::text($data->getEnvironment()->getBuild()->getDbPrefix()),
                                        Token::text($name),
                                        Token::text('`'),
                                    );
                                }, $this->getParentTypes($data, $type))),
                                Token::nl(),
                                Token::text('FROM `'),
                                Token::text($data->getEnvironment()->getBuild()->getDbPrefix()),
                                Token::text($type->getName()),
                                Token::textnl('`'),
                                Token::array(array_map(function ($name) use ($data) {
                                    return Token::multi(
                                        Token::text('INNER JOIN `'),
                                        Token::text($data->getEnvironment()->getBuild()->getDbPrefix()),
                                        Token::text($name),
                                        Token::textnl('` USING (id)'),
                                    );
                                }, $this->getParentTypes($data, $type))),
                                Token::text('WHERE '),
                            ), '. "', '" . PHP_EOL', 'addslashes'),
                            $bound,
                            Token::textnlpop(';"'),
                            Token::textnl(');'),
                            Token::textnl('$result->free();'),
                            Token::textnl('static::clearBuffer();')
                        );
                        break;
                }
                return Token::multi(
                    Token::nl(),
                    Token::text('public static function '),
                    Token::text($query->getName()),
                    Token::text('('),
                    Token::array(self::intersperce(array_merge(
                        array_map(function ($name) use ($query) {
                            $type = self::getPhpTypeName(
                                $query->getInputVarType($name),
                                false
                            );
                            return Token::multi(
                                Token::text($type === null ? '' : $type . ' '),
                                Token::text('$'),
                                Token::text($name)
                            );
                        }, $query->getInputVarNames()),
                        array_map(function ($name) use ($query, $data) {
                            return Token::multi(
                                Token::text($data->getEnvironment()->getBuild()->getClassNamespace()),
                                Token::text('\\Data\\'),
                                Token::text($query->getInputObjTarget($name)),
                                Token::text(' $'),
                                Token::text($name)
                            );
                        }, $query->getInputObjNames())
                    ), Token::text(', '))),
                    Token::text(')'),
                    $returnValue === null ? Token::text('') 
                        : Token::multi(
                            Token::text(': '),
                            $returnValue
                        ),
                    Token::textnlpush(' {'),
                    $content === null ? Token::text('') : $content,
                    Token::pop(),
                    Token::textnl('}')
                );
            }, $type->getAccess())),
            Token::pop(),
            Token::textnl('}')
        );
    }

    private static function getForeignJoints(DataDef $data, string $mainType): array {
        $result = array();
        foreach ($data->getTypes() as $type) {
            $result[$type->getName()] = array();
            foreach ($type->getJoints() as $joint)
                if ($joint->getTarget() == $mainType)
                    $result[$type->getName()] []= $joint;
        }
        return $result;
    }

    private static function getBounds(\Data\Environment $env, \Data\Type $type, \Data\Query $query, \Data\Bound $bound): ?Token {
        switch (true) {
            case $bound instanceof \Data\InputBound: 
                return Token::multi(
                    Token::text('" . '),
                    self::getWrappedTypeForSql(
                        $query->getInputVarType($bound->getName()),
                        Token::text('$' . $bound->getName())
                    ),
                    Token::text(' . "')
                );
            case $bound instanceof \Data\ObjectBound: 
                return Token::multi(
                    Token::text('" . $'),
                    Token::text($bound->getName()),
                    Token::text('->getId() . "')
                );
            case $bound instanceof \Data\TargetBound: 
                return Token::multi(
                    Token::text('`'),
                    Token::text($bound->getName()),
                    Token::text('`')
                );
            case $bound instanceof \Data\EnvBound:
                return Token::multi(
                    Token::text('" . '),
                    self::getWrappedTypeForSql(
                        $env->getEnvVar($bound->getName())->getType(),
                        Token::multi(
                            Token::text('Env::get'),
                            Token::text(ucfirst($bound->getName())),
                            Token::text('()')
                        )
                    ),
                    Token::text(' . "')
                );
            case $bound instanceof \Data\JointBound: 
                return Token::multi(
                    Token::text('`'),
                    Token::text($bound->getName()),
                    Token::text('`')
                );
            case $bound instanceof \Data\ValueBound:
                return Token::multi(
                    Token::text('" . '),
                    self::getWrappedTypeForSql(
                        $env->getEnvVar($bound->getName())->getType(),
                        self::getConversionOfVariable(
                            $bound->getType(),
                            $bound->getValue()
                        )
                    ),
                    Token::text(' . "')
                );
            case $bound instanceof \Data\TrueBound:
                return Token::text('TRUE');
            case $bound instanceof \Data\FalseBound:
                return Token::text('FALSE');
            case $bound instanceof \Data\NotBound:
                $b = self::getBounds($env, $type, $query, $bound->getChild());
                if ($b === null) return null;
                return Token::multi(
                    Token::text('NOT ('),
                    $b,
                    Token::text(')')
                );
            case $bound instanceof \Data\CompareBound:
                $l = self::getBounds($env, $type, $query, $bound->getLeft());
                $r = self::getBounds($env, $type, $query, $bound->getRight());
                if ($l === null || $r === null || $bound->getMethod() === null)
                    return null;
                return Token::multi(
                    Token::text('('),
                    $l,
                    Token::text(' ' . $bound->getMethod() . ' '),
                    $r,
                    Token::text(')')
                );
            case $bound instanceof \Data\BoolBound:
                $l = self::getBounds($env, $type, $query, $bound->getLeft());
                $r = self::getBounds($env, $type, $query, $bound->getRight());
                if ($l === null || $r === null || $bound->getMethod() === null)
                    return null;
                switch ($bound->getMethod()) {
                    case 'and': 
                        return Token::multi(
                            Token::text('('),
                            $l,
                            Token::text(' AND '),
                            $r,
                            Token::text(')')
                        );
                        break;
                    case 'or': 
                        return Token::multi(
                            Token::text('('),
                            $l,
                            Token::text(' OR '),
                            $r,
                            Token::text(')')
                        );
                        break;
                    case 'xor': 
                        return Token::multi(
                            Token::text('('),
                            $l,
                            Token::text(' XOR '),
                            $r,
                            Token::text(')')
                        );
                        break;
                    case 'nand': 
                        return Token::multi(
                            Token::text('NOT ('),
                            $l,
                            Token::text(' AND '),
                            $r,
                            Token::text(')')
                        );
                        break;
                    case 'nor': 
                        return Token::multi(
                            Token::text('NOT ('),
                            $l,
                            Token::text(' OR '),
                            $r,
                            Token::text(')')
                        );
                        break;
                    case 'xnor': 
                        return Token::multi(
                            Token::text('NOT ('),
                            $l,
                            Token::text(' XOR '),
                            $r,
                            Token::text(')')
                        );
                        break;
                    default: return null;
                }
            default: return null;
        }
    }

    private static function getPhpTypeName(string $type, bool $optional): ?string {
        switch ($type) {
            case 'bool': return $optional ? '?bool' : 'bool';
            case 'byte':
            case 'short':
            case 'int':
            case 'long':
            case 'sbyte':
            case 'ushort':
            case 'uint':
            case 'ulong': return $optional ? '?int' : 'int';
            case 'float':
            case 'double': return $optional ? '?float' : 'float';
            case 'string':
            case 'bytes': return $optional ? '?string' : 'string';
            case 'date': return $optional ? '?int' : 'int'; 
            case 'json': return null; 
            default: return null;
        }
    }

    private static function getWrappedTypeFromSql(string $type, Token $entry): Token {
        switch ($type) {
            case 'bool':
                $entry = Token::multi(Token::text('(bool)'), $entry);
                break;
            case 'byte':
            case 'short':
            case 'int':
            case 'long':
            case 'sbyte':
            case 'ushort':
            case 'uint':
            case 'ulong':
                $entry = Token::multi(Token::text('(int)'), $entry);
                break;
            case 'float':
            case 'double':
                $entry = Token::multi(Token::text('(float)'), $entry);
                break;
            case 'string':
            case 'bytes':
                $entry = Token::multi(Token::text('(string)'), $entry);
                break;
            case 'date':
                $entry = Token::multi(
                    Token::text('strtotime('), 
                    $entry,
                    Token::text(')')
                );
                break;
            case 'json':
                $entry = Token::multi(
                    Token::text('json_decode((string)'),
                    $entry,
                    Token::text(', true)')
                );
                break;
        }
        return $entry;

    }

    private static function getWrappedTypeForSql(string $type, Token $entry): Token {
        switch ($type) {
            case 'bool':
                $entry = Token::multi(
                    Token::text('('),
                    $entry,
                    Token::text(' ? \'TRUE\' : \'FALSE\')')
                );
                break;
            case 'byte':
            case 'short':
            case 'int':
            case 'long':
            case 'sbyte':
            case 'ushort':
            case 'uint':
            case 'ulong':
                $entry = Token::multi(Token::text('(string)'), $entry);
                break;
            case 'float':
            case 'double':
                $entry = Token::multi(Token::text('(string)'), $entry);
                break;
            case 'string':
            case 'bytes':
                $entry = Token::multi(
                    Token::text('\'\\\'\' . \\DB::encode('),
                    $entry,
                    Token::text(') . \'\\\'\')')
                );
                break;
            case 'date':
                $entry = Token::multi(Token::text('(string)'), $entry);
                break;
            case 'json':
                $entry = Token::multi(
                    Token::text('\'\\\'\' . \\DB::encode('),
                    $entry,
                    Token::text(') . \'\\\'\')')
                );
                break;
        }
        return $entry;

    }

    private static function getConversionOfVariable(string $type, $value): Token {
        switch (true) {
            case $type == 'date' && $value === null:
                return Token::text('time()');
                break;
            case $value === null:
                return Token::text('null');
                break;
            case is_bool($value):
                return Token::text((string)$value);
                break;
            case is_int($value):
                return Token::text((string)$value);
                break;
            case is_float($value):
                return Token::text((string)$value);
                break;
            case is_string($value):
                return Token::text('\'' . addslashes($value) . '\'');
                break;
            default: return Token::text('null'); break;
        }
    }

    private static function intersperce(array $array, $element): array {
        $result = array();
        $first = true;
        foreach ($array as $a) {
            if ($first) $first = false;
            else $result []= $element;
            $result []= $a;
        }
        return $result;
    }

    private function getParentTypes(DataDef $data, \Data\Type $type): array {
        $result = array();
        $type = $type->getBase() === null 
            ? null 
            : $data->getType($type->getBase());
        while ($type !== null) {
            $result []= $type->getName();
            $type = $type->getBase() === null 
                ? null 
                : $data->getType($type->getBase());
        }
        return $result;
    }

    private function getRootType(DataDef $data, \Data\Type $type): string {
        $typeName = $type->getName();
        while ($type !== null) {
            $typeName = $type->getName();
            $type = $type->getBase() === null 
                ? null 
                : $data->getType($type->getBase());
        }
        return $typeName;
    }

    private function getFlatVariableNames(DataDef $data, \Data\Type $type): array {
        $base = $type->getBase() === null 
            ? null 
            : $data->getType($type->getBase());
        $result = $base === null ? array() : $this->getFlatVariableNames($data, $base);
        foreach ($type->getAttributes() as $attr)
            $result []= $attr->getName();
        foreach ($type->getJoints() as $joint)
            $result []= $joint->getName();
        return $result;
    }

    private function getFlatAttributes(DataDef $data, \Data\Type $type): array {
        $base = $type->getBase() === null 
            ? null 
            : $data->getType($type->getBase());
        $result = $base === null ? array() : $this->getFlatAttributes($data, $base);
        foreach ($type->getAttributes() as $attr)
            $result []= $attr;
        return $result;
    }

    private function getFlatJoints(DataDef $data, \Data\Type $type): array {
        $base = $type->getBase() === null 
            ? null 
            : $data->getType($type->getBase());
        $result = $base === null ? array() : $this->getFlatJoints($data, $base);
        foreach ($type->getJoints() as $joint)
            $result []= $joint;
        return $result;
    }
}