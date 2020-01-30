<?php namespace Build\Builder\Elm;

use \Build\BuildConfig as Config;
use \Build\Token as Token;
use \Data\DataDefinition as DataDef;
use \Data\Type;

class TypeBuilder {
    public function buildTypeCode(Config $config, DataDef $data): Token {
        $depends = $this->getTypeDependency($data);
        return Token::multi(
            Token::text('module '),
            Token::text($data->getEnvironment()->getBuild()->getNamespace()),
            Token::textnlpush(' exposing'),
            Token::text('( '),
            Token::array($this->intersperce(
                array_map(function ($type) use ($depends) {
                    return Token::multi(
                        Token::text(\ucfirst($type->getName())),
                        Token::textnl('Id(..)'),
                        Token::text(', '),
                        Token::text(\lcfirst($type->getName())),
                        Token::textnl('Id'),
                        Token::text(', '),
                        Token::text(\ucfirst($type->getName())),
                        isset($depends[$type->getName()])
                            ? Token::multi(
                                Token::nl(),
                                Token::text(', '),
                                Token::text(\ucfirst($type->getName())),
                                Token::text('SubType(..)'),
                            )
                            : Token::text(''),
                    );
                }, $data->getTypes()),
                Token::multi(
                    Token::nl(),
                    Token::text(', '),
                )
            )),
            Token::nl(),
            Token::textnlpop(')'),
            Token::nl(),
            Token::textnl('import Time'),
            Token::textnl('import Json.Encode'),
            Token::array(array_map(function ($type) use ($data, $depends) {
                $build = $data->getEnvironment()->getBuild();
                $first = $type->getBase() !== null;
                return Token::multi(
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
                            Token::text('Dep'),
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
                );
            }, $data->getTypes())),
        );
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