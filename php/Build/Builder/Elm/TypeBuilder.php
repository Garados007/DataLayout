<?php namespace Build\Builder\Elm;

use \Build\BuildConfig as Config;
use \Build\Token as Token;
use \Data\DataDefinition as DataDef;
use \Data\Type;

class TypeBuilder {
    public function buildTypeCode(Config $config, DataDef $data): Token {
        $depends = $this->getTypeDependency($data);
        $typeList = array();
        $statics = array();
        $typeDef = Token::multi(
            Token::array(array_map(function ($type) use ($data, $depends, &$typeList, &$statics) {
                $build = $data->getEnvironment()->getBuild();
                $static = array();
                foreach ($type->getAccess() as $query)
                    if ($query->isSearchQuery() && $query->getSecurity()->isInclude($build, 'elm')) {
                        $args = array();
                        foreach ($query->getInputVarNames() as $name) {
                            $t = $this->getElmValueType($query->getInputVarType($name), false);
                            if ($query->isInputVarArray($name))
                                $t = Token::multi(
                                    Token::text('(List '),
                                    $t,
                                    Token::text(')')
                                );
                            $args []= Token::multi(
                                Token::text($this->getDict($query->getInputVarType($name))),
                                Token::text('.Dict '),
                                $t,
                            );
                        }
                        foreach ($query->getInputObjNames() as $name) {
                            $t = Token::multi(
                                Token::text(\ucfirst($query->getInputObjTarget($name))),
                                Token::text('Id'),
                            );
                            if ($query->isInputVarArray($name))
                                $t = Token::multi(
                                    Token::text('(List '),
                                    $t,
                                    Token::text(')')
                                );
                            $args []= Token::multi(
                                Token::text('AssocList.Dict '),
                                $t,
                            );
                        }
                        if (count($args) == 0)
                            $args []= Token::text('Maybe');
                        if ($query->isLimitFirst())
                            $args []= Token::multi(
                                Token::text(\ucfirst($type->getName())),
                                Token::text('Id')
                            );
                        else $args []= Token::multi(
                            Token::text('ApiIterator '),
                            Token::text(\ucfirst($type->getName())),
                            Token::text('Id')
                        );
                        $statType = null;
                        for ($i = count($args) - 1; $i >= 0; $i--) 
                            if ($statType === null)
                                $statType = $args[$i];
                            else $statType = Token::multi(
                                $args[$i],
                                Token::text(' ('),
                                $statType,
                                Token::text(')'),
                            );
                        
                        $static []= Token::multi(
                            Token::text(\lcfirst($query->getName())),
                            Token::text(': '),
                            $statType
                        );
                    }
                $first = $type->getBase() !== null;
                $result = Token::multi(
                    Token::nl(),
                    Token::text('type '),
                    Token::text(\ucfirst($type->getName())),
                    Token::text('Id = '),
                    Token::text(\ucfirst($type->getName())),
                    Token::textnl('Id String'),
                    Token::nl(),
                    Token::text(\lcfirst($type->getName())),
                    Token::text('Id : '),
                    Token::text(\ucfirst($type->getName())),
                    Token::textnl('Id -> String'),
                    Token::text(\lcfirst($type->getName())),
                    Token::text('Id ('),
                    Token::text(\ucfirst($type->getName())),
                    Token::textnl('Id id) = id'),
                    Token::nl(),
                    Token::text('type alias '),
                    Token::text(\ucfirst($type->getName())),
                    Token::textnlpush(' ='),
                    $type->getBase() === null
                        ? Token::multi(
                            Token::text('{ id: '),
                            Token::text(\ucfirst($type->getName())),
                            Token::textnl('Id'),
                        )
                        : Token::text(''),
                    Token::array(\array_map(
                        function ($token) use (&$first) {
                            if ($token === null)
                                return null;
                            if ($first) {
                                $head = Token::text('{ ');
                                $first = false;
                            }
                            else $head = Token::text(', ');
                            return Token::multi(
                                $head,
                                $token,
                                Token::nl()
                            );
                        },
                        \array_merge(
                            array_map(function ($attr) use ($build) {
                                if ($attr->getSecurity()->isExclude($build, 'elm'))
                                    return null;
                                return Token::multi(
                                    Token::text(\lcfirst($attr->getName())),
                                    Token::text(': '),
                                    $this->getElmValueType($attr->getType(), $attr->getOptional()),
                                );
                            }, $type->getAttributes()),
                            array_map(function ($joint) use ($build) {
                                if ($joint->getSecurity()->isExclude($build, 'elm'))
                                    return null;
                                return Token::multi(
                                    Token::text(\lcfirst($joint->getName())),
                                    Token::text(': '),
                                    $joint->getRequired()
                                        ? Token::text('')
                                        : Token::text('Maybe '),
                                    Token::text(\ucfirst($joint->getTarget())),
                                    Token::text('Id'),
                                );
                            }, $type->getJoints()),
                            (function () use ($data, $type, $build) {
                                $list = array();
                                foreach ($data->getTypes() as $other) 
                                    foreach ($other->getJoints() as $joint)
                                        if ($joint->getTarget() == $type->getName()) {
                                            if ($joint->getSecurity()->isExclude($build, 'elm'))
                                                continue;
                                            $list []= Token::multi(
                                                Token::text('from'),
                                                Token::text(\ucfirst($other->getName())),
                                                Token::text(\ucfirst($joint->getName())),
                                                Token::text(': Maybe (ApiIterator '),
                                                Token::text(\ucfirst($this->getRootType(
                                                    $data,
                                                    $other->getName(),
                                                ))),
                                                Token::text('Id)'),
                                            );
                                        }
                                return $list;
                            })(),
                        ),
                    )),
                    isset($depends[$type->getName()])
                        ? Token::multi(
                            Token::text(', subType: '),
                            Token::text(\ucfirst($type->getName())),
                            Token::textnl('SubType'),
                        )
                        : Token::text(''),
                    Token::textnlpop('}'),
                    isset($depends[$type->getName()])
                        ? Token::multi(
                            Token::nl(),
                            Token::text('type '),
                            Token::text(\ucfirst($type->getName())),
                            Token::textnlpush('SubType'),
                            Token::text('= '),
                            Token::text(\ucfirst($type->getName())),
                            Token::text('Base'),
                            Token::array(array_map(function ($type) {
                                return Token::multi(
                                    Token::nl(),
                                    Token::text('| '),
                                    Token::text(\ucfirst($type->getName())),
                                    Token::text('Dep '),
                                    Token::text(\ucfirst($type->getName())),
                                );
                            }, $depends[$type->getName()])),
                            Token::textnlpop(''),
                        )
                        : Token::text(''),
                    count($static) > 0
                        ? Token::multi(
                            Token::nl(),
                            Token::text('type alias '),
                            Token::text(\ucfirst($type->getName())),
                            Token::textnlpush('Static ='),
                            Token::text('{ '),
                            Token::array($this->intersperce(
                                $static,
                                Token::multi(
                                    Token::nl(),
                                    Token::text(', '),
                                )
                            )),
                            Token::nl(),
                            Token::textnlpop('}'),
                        )
                        : Token::text(''),
                );
                $typeList = array_merge(
                    $typeList,
                    array(
                        \ucfirst($type->getName()) . 'Id(..)',
                        \lcfirst($type->getName()) . 'Id',
                        \ucfirst($type->getName())
                    )
                );
                if (isset($depends[$type->getName()]))
                    $typeList []= \ucfirst($type->getName()) . 'SubType(..)';
                if (count($static) > 0) {
                    $typeList []= \ucfirst($type->getName()) . 'Static';
                    $statics []= $type->getName();
                }
                return $result;
            }, $data->getTypes())),
        );
        return Token::multi(
            Token::text('module '),
            Token::text($data->getEnvironment()->getBuild()->getNamespace()),
            Token::textnlpush(' exposing'),
            Token::textnl('( ApiIterator'),
            $data->getEnvironment()->getBuild()->getContainer() === null 
                ? Token::text('')
                : Token::multi(
                    Token::text(', '),
                    Token::textnl($data->getEnvironment()->getBuild()->getContainer()),
                    Token::text(', '),
                    Token::textnl(\lcfirst($data->getEnvironment()->getBuild()->getContainer())),
                ),
            Token::array(array_map(function ($type) {
                return Token::multi(
                    Token::text(', '),
                    Token::textnl($type),
                );
            }, $typeList)),
            Token::textnlpop(')'),
            Token::nl(),
            Token::textnl('import Time'),
            Token::textnl('import Json.Encode'),
            Token::textnl('import Dict'),
            Token::textnl('import AssocList'),
            Token::nl(),
            Token::textnlpush('type alias ApiIterator id ='),
            Token::textnl('{ last: Maybe String'),
            Token::textnl(', list: List id'),
            Token::textnl(', more: Bool'),
            Token::textnlpop('}'),
            Token::nl(),
            $data->getEnvironment()->getBuild()->getContainer() !== null
                ? Token::multi(
                    Token::text('type alias '),
                    Token::text($data->getEnvironment()->getBuild()->getContainer()),
                    Token::textnlpush(' ='),
                    Token::textnlpush('{ data:'),
                    Token::text('{ '),
                    Token::array($this->intersperce(
                        array_filter(
                            array_map(function ($type) {
                                if ($type->getBase() !== null)
                                    return null;
                                return Token::multi(
                                    Token::text(\lcfirst($type->getName())),
                                    Token::text(': AssocList.Dict '),
                                    Token::text(\ucfirst($type->getName())),
                                    Token::text('Id '),
                                    Token::text(\ucfirst($type->getName()))
                                );
                            }, $data->getTypes()),
                            function ($token) {
                                return $token !== null;
                            },
                        ),
                        Token::multi(
                            Token::nl(),
                            Token::text(', '),
                        ),
                    )),
                    Token::nl(),
                    Token::textnlpop('}'),
                    Token::textnlpush(', static:'),
                    Token::text('{ '),
                    Token::array($this->intersperce(
                        array_map(function ($type) {
                            return Token::multi(
                                Token::text(\lcfirst($type)),
                                Token::text(': '),
                                Token::text(\ucfirst($type)),
                                Token::text('Static'),
                            );
                        }, $statics),
                        Token::multi(
                            Token::nl(),
                            Token::text(', '),
                        ),
                    )),
                    Token::nl(),
                    Token::textnlpop('}'),
                    Token::textnlpop('}'),
                    Token::nl(),
                    Token::text(\lcfirst($data->getEnvironment()->getBuild()->getContainer())),
                    Token::text(' : '),
                    Token::textnl($data->getEnvironment()->getBuild()->getContainer()),
                    Token::text(\lcfirst($data->getEnvironment()->getBuild()->getContainer())),
                    Token::textnlpush(' ='),
                    Token::textnlpush('{ data = '),
                    Token::text('{ '),
                    Token::array($this->intersperce(
                        array_filter(
                            array_map(function ($type) {
                                if ($type->getBase() !== null)
                                    return null;
                                return Token::multi(
                                    Token::text(\lcfirst($type->getName())),
                                    Token::text(' = AssocList.empty'),
                                );
                            }, $data->getTypes()),
                            function ($token) {
                                return $token !== null;
                            },
                        ),
                        Token::multi(
                            Token::nl(),
                            Token::text(', '),
                        ),
                    )),
                    Token::nl(),
                    Token::textnlpop('}'),
                    Token::textnlpush(', static = '),
                    Token::text('{ '),
                    Token::array($this->intersperce(
                        array_map(function ($type) use ($data) {
                            $build = $data->getEnvironment()->getBuild();
                            return Token::multi(
                                Token::text(\lcfirst($type)),
                                Token::textnlpush(' = '),
                                Token::text('{ '),
                                Token::array($this->intersperce(
                                    array_filter(
                                        array_map(function ($query) use ($build) {
                                            if (!$query->isSearchQuery())
                                                return null;
                                            if ($query->getSecurity()->isExclude($build, 'elm'))
                                                return null;
                                            $type = null;
                                            foreach ($query->getInputVarNames() as $name) {
                                                $type = Token::multi(
                                                    Token::text($this->getDict($query->getInputVarType($name))),
                                                    Token::text('.empty'),
                                                );
                                                break;
                                            }
                                            if ($type === null)
                                                foreach ($query->getInputObjNames() as $name) {
                                                    $type = Token::text('AssocList.empty');
                                                    break;
                                                }
                                            if ($type === null)
                                                $type = Token::text('Nothing');
                                            return Token::multi(
                                                Token::text(\lcfirst($query->getName())),
                                                Token::text(' = '),
                                                $type
                                            );
                                        }, $data->getType($type)->getAccess()),
                                        function ($token) {
                                            return $token !== null;
                                        }
                                    ),
                                    Token::multi(
                                        Token::nl(),
                                        Token::text(', '),
                                    )
                                )),
                                Token::nl(),
                                Token::text('}'),
                                Token::pop(),
                            );
                        }, $statics),
                        Token::multi(
                            Token::nl(),
                            Token::text(', '),
                        ),
                    )),
                    Token::nl(),
                    Token::textnlpop('}'),
                    Token::textnlpop('}'),
                )
                : Token::text(''),
            $typeDef,
        );
    }

    private static function getRootType(DataDef $data, string $name): string {
        do {
            $type = $data->getType($name);
            $name = $type->getBase();
        }
        while ($name != null);
        return $type->getName();
    }

    private static function getTypeDependency(DataDef $data): array {
        $list = array();
        foreach ($data->getTypes() as $t)
            if ($t->getBase() !== null)
                $list[$t->getBase()] []= $t;
        return $list;
    }

    private static function getElmValueType(string $type, bool $optional): Token { 
        $token;
        switch ($type) {
            case 'bool': 
                    $token = Token::text('Bool');
                break;
            case 'byte':
            case 'short':
            case 'int':
            case 'long':
            case 'sbyte':
            case 'ushort':
            case 'uint':
            case 'ulong':
                $token = Token::text('Int');
                break;
            case 'float':
            case 'double':
                $token = Token::text('Float');
                break;
            case 'string':
            case 'bytes':
                $token = Token::text('String');
                break;
            case 'date':
                $token = Token::text('Time.Posix');
                break;
            case 'json':
                $token = Token::text('Json.Encode.Value');
                break;
            default:
                $token = Token::multi(
                    Token::text('Never {-unknown type: '),
                    Token::text($type),
                    Token::text('-}'),
                );
                break;
        }
        if ($optional)
            return Token::multi(
                Token::text('Maybe '),
                $token
            );
        else return $token;
    }
    
    private static function getDict(string $type): string { 
        switch ($type) {
            case 'bool': 
            case 'byte':
            case 'short':
            case 'int':
            case 'long':
            case 'sbyte':
            case 'ushort':
            case 'uint':
            case 'ulong':
            case 'float':
            case 'double':
            case 'string':
            case 'bytes':
                return 'Dict';
            case 'date':
            case 'json':
                return 'AssocList';
            default:
                return 'Dict';
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
}