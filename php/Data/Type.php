<?php namespace Data;

use \Build\Token as Token;
use \Data\Security;

class Type {
    private $attributes;
    private $links;
    private $joints;
    private $access;
    private $name;
    private $base;
    private $fullQuery;
    private $extension = null;
    private $dbName = null;
    private $bucket = null;
    private $createSecurity;
    private $loadSecurity;
    private $deleteSecurity;
    private $defaultAttributeSecurity;
    private $defaultJointSecurity;
    
    private function __construct() {}

    public function getAttributes(): array {
        return $this->attributes;
    }

    public function getAttribute(string $name): ?\Data\Attribute {
        foreach ($this->attributes as $attr)
            if ($attr->getName() == $name)
                return $attr;
        return null;
    }

    public function getLinks(): array {
        return $this->links;
    }

    public function getJoints(): array {
        return $this->joints;
    }

    public function getJoint(string $name): ?\Data\Joint {
        foreach ($this->joints as $joint)
            if ($joint->getName() == $name)
                return $joint;
        return null;
    }

    public function getAccess(): array {
        return $this->access;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getBase(): ?string {
        return $this->base;
    }

    public function getFullQuery(): bool {
        return $this->fullQuery;
    }

    public function getExtension(): ?string {
        return $this->extension;
    }

    public function setExtension(?string $extension) {
        $this->extension = $extension;
    }

    public function getDbName(): string {
        return $this->dbName ?: $this->name;
    }

    public function setDbName(string $name) {
        $this->dbName = $name;
    }

    public function getBucket(): ?TypeBucket {
        return $this->bucket;
    }

    public function setBucket(?TypeBucket $bucket) {
        $this->bucket = $bucket;
    }

    public function getCreateSecurity(): Security {
        return $this->createSecurity;
    }

    public function getLoadSecurity(): Security {
        return $this->loadSecurity;
    }

    public function getDeleteSecurity(): Security {
        return $this->deleteSecurity;
    }

    public function getDefaultAttributeSecurity(): Security {
        return $this->defaultAttributeSecurity;
    }
    
    public function getDefaultJointSecurity(): Security {
        return $this->defaultJointSecurity;
    }

    public static function loadFromXml(\SimpleXMLElement $element): ?\Data\Type {
        if ($element->getName() != "Type")
            return null;

        $type = new Type();

        $type->attributes = array();
        if (isset($element->Attributes))
            foreach ($element->Attributes->children() as $child) {
                $attr = \Data\Attribute::loadFromXml($child);
                if ($attr !== null)
                    $type->attributes []= $attr;
            }

        $type->links = array();
        if (isset($element->Links))
            foreach ($element->Links->children() as $child) {
                $link = \Data\Link::loadFromXml($child);
                if ($link !== null)
                    $type->links []= $link;
            }

        $type->joints = array();
        if (isset($element->Joints))
            foreach ($element->Joints->children() as $child) {
                $joint = \Data\Joint::loadFromXml($child);
                if ($joint !== null)
                    $type->joints []= $joint;
            }

        $type->access = array();
        if (isset($element->Access))
            foreach ($element->Access->children() as $child) {
                $query = \Data\Query::loadFromXml($child);
                if ($query !== null)
                    $type->access []= $query;
            }
        
        $type->name = (string)$element->attributes()->name;
        if (isset($element->attributes()->base))
            $type->base = (string)$element->attributes()->base;
        else $type->base = null;
        if (isset($element->attributes()->fullQuery))
            $type->fullQuery = filter_var(
                (string)$element->attributes()->fullQuery,
                FILTER_VALIDATE_BOOLEAN
            );
        else $type->fullQuery = false;
        $type->createSecurity = isset($element->Security->Create)
            ? Security::loadFromXml($element->Security->Create)
            : new Security();
        $type->loadSecurity = isset($element->Security->Load)
            ? Security::loadFromXml($element->Security->Load)
            : new Security();
        $type->deleteSecurity = isset($element->Security->Delete)
            ? Security::loadFromXml($element->Security->Delete)
            : new Security();
        $type->defaultAttributeSecurity = isset($element->Security->DefaultAttribute)
            ? Security::loadFromXml($element->Security->DefaultAttribute)
            : new Security();
        $type->defaultJointSecurity = isset($element->Security->DefaultJoint)
            ? Security::loadFromXml($element->Security->DefaultJoint)
            : new Security();

        return $type;
    }

    public function buildSqlCreateTable(\Data\Build $build): Token {
        return Token::multi(
            Token::text('CREATE TABLE IF NOT EXISTS `'),
            Token::text($build->getDbPrefix() . $this->getDbName()),
            Token::textnlpush('` ('),
            Token::text('id BIGINT UNSIGNED NOT NULL '),
            $this->base === null
                ? Token::text('AUTO_INCREMENT ')
                : Token::text(''),
            Token::textnl('PRIMARY KEY'),
            Token::text(', `_type` TEXT NOT NULL'),
            Token::array(array_map(function ($attr) {
                $res = array();
                $res []= Token::nl();
                $res []= Token::text(', `');
                $res []= Token::text($attr->getName() . '` ');
                $type = '';
                switch ($attr->getType()) {
                    case 'bool': $type = 'BOOLEAN'; break;
                    case 'byte': $type = 'TINYINT UNSIGNED'; break;
                    case 'short': $type = 'SMALLINT'; break;
                    case 'int': $type = 'INT'; break;
                    case 'long': $type = 'BIGINT'; break;
                    case 'sbyte': $type = 'TINYINT'; break;
                    case 'ushort': $type = 'SMALLINT UNSIGNED'; break;
                    case 'uint': $type = 'INT UNSIGNED'; break;
                    case 'ulong': $type = 'BIGINT UNSIGNED'; break;
                    case 'float': $type = 'FLOAT'; break;
                    case 'double': $type = 'DOUBLE'; break;
                    case 'string': $type = 'TEXT'; break;
                    case 'bytes': $type = 'BLOB'; break;
                    case 'date': $type = 'DATETIME'; break;
                    case 'json': $type = 'JSON'; break;
                    default: $type = 'TEXT'; break;
                }
                $res []= Token::text($type);
                if (!$attr->getOptional())
                    $res [] = Token::text(' NOT NULL');
                if ($attr->getDefault() !== null) {
                    $res []= Token::text(' DEFAULT ');
                    $enableOutput = true;
                    switch (true) {
                        case $attr->getType() == 'bytes':
                            $res []= Token::text('0x' . bin2hex($attr->getDefault()));
                            break;
                        case $attr->getType() == 'date':
                            $res []= Token::text(
                                $attr->getDefault() == 'now'
                                ? 'NOW'
                                : 'FROM_UNIXTIME(' . $attr->getDefault() . ')'
                            );
                            break;
                        case \is_bool($attr->getDefault()):
                            $res []= Token::text(
                                $attr->getDefault() ? 'TRUE' : 'FALSE'
                            );
                            break;
                        case \is_int($attr->getDefault()):
                            $res []= Token::text((string)$attr->getDefault());
                            break;
                        case \is_float($attr->getDefault()):
                            $res []= Token::text((string)$attr->getDefault());
                            break;
                        default: $enableOutput = false; break;
                    }
                    if (!$enableOutput) {
                        unset($res[count($res) - 1]);
                    }
                }
                if ($attr->getUnique()) {
                    $res []= Token::text(' UNIQUE');
                }
                return $res;
            }, $this->attributes)),
            Token::array(array_map(function ($joint) {
                $res = array();
                $res []= Token::nl();
                $res []= Token::text(', `');
                $res []= Token::text($joint->getName());
                $res []= Token::text('` BIGINT UNSIGNED');
                if ($joint->getRequired())
                    $res []= Token::text(' NOT NULL');
                return $res;
            }, $this->joints)),
            Token::nl(),
            Token::pop(),
            Token::textnl(');')
        );
    }

    public function buildSqlAddForeignKeys(\Data\DataDefinition $data, \Data\Build $build): Token {
        return Token::multi(
            $this->base === null
                ? Token::text('')
                : Token::multi(
                    Token::text('ALTER TABLE `'),
                    Token::text($build->getDbPrefix() . $this->getDbName()),
                    Token::textnlpush('`'),
                    Token::text('ADD CONSTRAINT `'),
                    Token::text($build->getDbPrefix()),
                    Token::textnl('_FK_ID`'),
                    Token::textnl('FOREIGN KEY (id)'),
                    Token::text('REFERENCES `'),
                    Token::text($build->getDbPrefix()),
                    Token::text($data->getType($this->base)->getDbName()),
                    Token::textnl('`(id)'),
                    Token::textnlpop('ON DELETE CASCADE;')
                ),
            Token::array(array_map(function ($link) use ($build, $data) {
                return Token::multi(
                    Token::text('ALTER TABLE `'),
                    Token::text($build->getDbPrefix() . $this->getDbName()),
                    Token::textnlpush('`'),
                    Token::text('ADD CONSTRAINT `'),
                    Token::text($build->getDbPrefix()),
                    Token::text('_FK_'),
                    Token::text($link->getName()),
                    Token::textnl('`'),
                    Token::text('FOREIGN KEY (`'),
                    Token::text($link->getAttribute()),
                    Token::textnl('`)'),
                    Token::text('REFERENCES `'),
                    Token::text($build->getDbPrefix()),
                    Token::text($data->getType($link->getTarget())->getDbName()),
                    Token::text('`(`'),
                    Token::text($link->getTarAttribute()),
                    Token::textnl('`)'),
                    Token::textnlpop('ON DELETE CASCADE;')
                );
            }, $this->links)),
            Token::array(array_map(function ($joint) use ($build, $data) {
                return Token::multi(
                    Token::text('ALTER TABLE `'),
                    Token::text($build->getDbPrefix() . $this->getDbName()),
                    Token::textnlpush('`'),
                    Token::text('ADD CONSTRAINT `'),
                    Token::text($build->getDbPrefix()),
                    Token::text('_FK_'),
                    Token::text($joint->getName()),
                    Token::textnl('`'),
                    Token::text('FOREIGN KEY (`'),
                    Token::text($joint->getName()),
                    Token::textnl('`)'),
                    Token::text('REFERENCES `'),
                    Token::text($build->getDbPrefix()),
                    Token::text($data->getType($joint->getTarget())->getDbName()),
                    Token::textnl('`(`id`)'),
                    Token::textnlpop('ON DELETE CASCADE;')
                );
            }, $this->joints)),
        );
    }

    private function intersperce(array $data, mixed $value): array {
        $result = array();
        $first = true;
        foreach ($data as $d) {
            if ($first) $first = false;
            else $result []= $value;
            $result []= $d;
        }
        return $result;
    }

    private function mres($value)
    {
        $search = array("\\",  "\x00", "\n",  "\r",  "'",  '"', "\x1a");
        $replace = array("\\\\","\\0","\\n", "\\r", "\'", '\"', "\\Z");
    
        return str_replace($search, $replace, $value);
    }

    public function import(callable $renamer, callable $env) {
        $this->name = $renamer($this->name);
        $this->base = $renamer($this->base);
        foreach ($this->links as $link)
            $link->import($renamer);
        foreach ($this->joints as $joint)
            $joint->import($renamer);
        foreach ($this->access as $query)
            $query->import($renamer, $env);
    }
}
