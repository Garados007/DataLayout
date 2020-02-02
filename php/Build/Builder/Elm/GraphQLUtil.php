<?php namespace Build\Builder\Elm;

use \Build\BuildConfig as Config;
use \Build\Token as Token;
use \Data\DataDefinition as DataDef;
use \Data\Type;
use \Data\Build;

class GraphQLUtil {
    public function buildUtilCode(Config $config, DataDef $data): Token {
        $typeDep = $this->getTypeDependency($data);
        return Token::multi(
            Token::text('module '),
            Token::text($data->getEnvironment()->getBuild()->getGraphQL()),
            Token::textnlpush(' exposing'),
            Token::textnl('( Pagination'),
            Token::array(
                (function () use ($data, $typeDep) {
                    $result = [];
                    foreach ($data->getTypes() as $type) {
                        $result []= Token::multi(
                            Token::text(', '),
                            Token::text(\lcfirst($type->getName())),
                            Token::textnl('IdDecoder')
                        );
                        $result []= Token::multi(
                            Token::text(', '),
                            Token::text(\lcfirst($type->getName())),
                            Token::textnl('Decoder')
                        );
                        $result []= Token::multi(
                            Token::text(', '),
                            Token::text(\lcfirst($type->getName())),
                            Token::textnl('Pagination')
                        );
                    }
                    return $result;
                })()
            ),
            Token::textnlpop(')'),
            Token::nl(),
            Token::text('import '),
            Token::text($data->getEnvironment()->getBuild()->getNamespace()),
            Token::textnlpush(' exposing'),
            Token::text('( '),
            Token::array($this->intersperce(
                (function () use ($data, $typeDep) {
                    $result = [];
                    foreach ($data->getTypes() as $type) {
                        $result []= Token::text(\ucfirst($type->getName()));
                        $result []= Token::multi(
                            Token::text(\ucfirst($type->getName())),
                            Token::text('Id(..)'),
                        );
                        if (isset($typeDep[$type->getName()]))
                            $result []= Token::multi(
                                Token::text(\ucfirst($type->getName())),
                                Token::text('SubType(..)'),
                            );
                    }
                    return $result;
                })(),
                Token::multi(
                    Token::nl(),
                    Token::text(', '),
                ),
            )),
            Token::nl(),
            Token::textnlpop(')'),
            Token::textnl('import Api.Interface'),
            Token::array(array_map(function ($type) {
                return Token::multi(
                    Token::text('import Api.Interface.'),
                    Token::text(\ucfirst($type->getName())),
                    Token::text(' as '),
                    Token::textnl(\ucfirst($type->getName())),
                );
            }, $data->getTypes())),
            Token::textnl('import Api.Object'),
            Token::array(array_map(function ($type) {
                return Token::multi(
                    Token::text('import Api.Object.'),
                    Token::text(\ucfirst($type->getName())),
                    Token::text('_Type as '),
                    Token::text(\ucfirst($type->getName())),
                    Token::textnl('_Type'),
                    Token::text('import Api.Object.'),
                    Token::text(\ucfirst($type->getName())),
                    Token::text('_Mutator as '),
                    Token::text(\ucfirst($type->getName())),
                    Token::textnl('_Mutator'),
                    Token::text('import Api.Object.'),
                    Token::text(\ucfirst($type->getName())),
                    Token::text('_Pagination as '),
                    Token::text(\ucfirst($type->getName())),
                    Token::textnl('_Pagination'),
                    Token::text('import Api.Object.'),
                    Token::text(\ucfirst($type->getName())),
                    Token::text('_Edge as '),
                    Token::text(\ucfirst($type->getName())),
                    Token::textnl('_Edge'),
                );
            }, $data->getTypes())),
            Token::textnl('import Api.Object.PageInfo'),
            Token::textnl('import Api.Scalar as ApiScalar'),
            Token::textnl('import Graphql.SelectionSet as SelectionSet exposing (with, hardcoded)'),
            Token::textnl('import Iso8601'),
            Token::nl(),
            Token::textnlpush('type alias Pagination id data ='),
            Token::textnl('{ list: List data'),
            Token::textnl(', lastId: Maybe id'),
            Token::textnl(', hasMore: Bool'),
            Token::textnlpop('}'),
            Token::array(array_map(function ($type) use ($data, $typeDep) {
                $build = $data->getEnvironment()->getBuild();
                $root = $this->getRootType($type, $data)->getName();
                $result = array();
                // *IdDecoder : SelectionSet.SelectionSet *Id Api.Interface.*
                $result []= Token::multi(
                    Token::nl(),
                    Token::text(\lcfirst($type->getName())),
                    Token::text('IdDecoder : SelectionSet.SelectionSet '),
                    Token::text(\ucfirst($type->getName())),
                    Token::text('Id Api.Interface.'),
                    Token::textnl(\ucfirst($type->getName())),
                    Token::text(\lcfirst($type->getName())),
                    Token::textnlpush('IdDecoder ='),
                    Token::textnlpush('SelectionSet.map'),
                    Token::textnlpush('(\ (ApiScalar.Id id) ->'),
                    Token::text(\ucfirst($type->getName())),
                    Token::textnlpop('Id id'),
                    Token::textnl(')'),
                    Token::text(\ucfirst($type->getName())),
                    Token::textnlpop('.id'),
                    Token::pop(),
                );
                // *Decoder : SelectionSet.SelectionSet * Api.Object.*
                $result []= Token::multi(
                    $this->buildBasicTypeParser(
                        $type->getName() . '_Type',
                        $type,
                        $data, 
                        $build,
                        $typeDep
                    ),
                    $this->buildBasicTypeParser(
                        $type->getName() . '_Mutator',
                        $type,
                        $data, 
                        $build,
                        $typeDep
                    ),
                );
                // *Decoder : SelectionSet.SelectionSet * Api.Interface.*
                $result []= Token::multi(
                    Token::nl(),
                    Token::text(\lcfirst($type->getName())),
                    Token::text('Decoder : SelectionSet.SelectionSet '),
                    Token::text(\ucfirst($root)),
                    Token::text(' Api.Interface.'),
                    Token::textnl(\ucfirst($type->getName())),
                    Token::text(\lcfirst($type->getName())),
                    Token::textnlpush('Decoder ='),
                    Token::text(\ucfirst($type->getName())),
                    Token::textnlpush('.fragments'),
                    Token::text('{ on'),
                    Token::text(\ucfirst($type->getName())),
                    Token::text('_Type = '),
                    Token::text(\lcfirst($type->getName())),
                    Token::textnl('_TypeDecoder'),
                    Token::text(', on'),
                    Token::text(\ucfirst($type->getName())),
                    Token::text('_Mutator = '),
                    Token::text(\lcfirst($type->getName())),
                    Token::textnl('_MutatorDecoder'),
                    isset($typeDep[$type->getName()])
                        ? Token::array(array_map(function ($type) {
                            return Token::multi(
                                Token::text(', on'),
                                Token::text(\ucfirst($type->getName())),
                                Token::text('_Type = '),
                                Token::text(\lcfirst($type->getName())),
                                Token::textnl('_TypeDecoder'),
                                Token::text(', on'),
                                Token::text(\ucfirst($type->getName())),
                                Token::text('_Mutator = '),
                                Token::text(\lcfirst($type->getName())),
                                Token::textnl('_MutatorDecoder'),
                            );
                        }, $typeDep[$type->getName()]))
                        : Token::text(''),
                    Token::textnlpop('}'),
                    Token::pop(),
                );
                // *Pagination : SelectionSet.SelectionSet (Pagination *Id *) Api.Object.*_Pagination 
                $result []= Token::multi(
                    Token::nl(),
                    Token::text(\lcfirst($type->getName())),
                    Token::text('Pagination : SelectionSet.SelectionSet (Pagination '),
                    Token::text(\ucfirst($root)),
                    Token::text('Id '),
                    Token::text(\ucfirst($root)),
                    Token::text(') Api.Object.'),
                    Token::text(\ucfirst($type->getName())),
                    Token::textnl('_Pagination'),
                    Token::text(\lcfirst($type->getName())),
                    Token::textnlpush('Pagination ='),
                    Token::textnlpush('SelectionSet.map3 Pagination'),
                    Token::text('( '),
                    Token::text(\ucfirst($type->getName())),
                    Token::textnlpush('_Pagination.edges'),
                    Token::text('( '),
                    Token::text(\ucfirst($type->getName())),
                    Token::textnlpush('_Edge.node'),
                    Token::text(\lcfirst($type->getName())),
                    Token::textnlpop('Decoder'),
                    Token::textnlpop(')'),
                    Token::textnl(')'),
                    Token::text('( '),
                    Token::text(\ucfirst($type->getName())),
                    Token::textnlpush('_Pagination.pageInfo'),
                    Token::textnlpush('( SelectionSet.map'),
                    Token::text('( Maybe.map '),
                    Token::text(\ucfirst($root)),
                    Token::textnl('Id )'),
                    Token::textnlpop('Api.Object.PageInfo.endCursor'),
                    Token::textnlpop(')'),
                    Token::textnl(')'),
                    Token::text('( '),
                    Token::text(\ucfirst($type->getName())),
                    Token::textnlpush('_Pagination.pageInfo'),
                    Token::textnlpop('Api.Object.PageInfo.hasNextPage'),
                    Token::textnlpop(')'),
                    Token::pop(),
                );
                return Token::array($result);
            }, $data->getTypes())),
        );
    }
    
    private function buildBasicTypeParser(string $viewname, Type $type, DataDef $data, Build $build, array $typeDep): Token {
        $types = array( $type );
        while ($type->getBase() !== null)
            $types []= ($type = $data->getType($type->getBase()));
        $tokens = array();
        $tokens []= Token::multi(
            Token::nl(),
            Token::text(\lcfirst($viewname)),
            Token::text('Decoder : SelectionSet.SelectionSet '),
            Token::text(\ucfirst($types[count($types) - 1]->getName())),
            Token::text(' Api.Object.'),
            Token::textnl(\ucfirst($viewname)),
            Token::text(\lcfirst($viewname)),
            Token::textnlpush('Decoder ='),
            Token::text('SelectionSet.succeed '),
            Token::textnlpush(\ucfirst($types[count($types) - 1]->getName())),
        );
        for ($i = count($types) - 1; $i >= 0; $i--) {
            $type = $types[$i];
            if ($type->getBase() === null)
                $tokens []= Token::multi(
                    Token::textnlpush('|> with '),
                    Token::textnlpush('( SelectionSet.map'),
                    Token::textnlpush('(\ (ApiScalar.Id id) -> '),
                    Token::text(\ucfirst($type->getName())),
                    Token::textnlpop('Id id'),
                    Token::textnl(')'),
                    Token::text(\ucfirst($viewname)),
                    Token::textnlpop('.id'),
                    Token::textnlpop(')'),
                );
            $tokens []= Token::multi(
                Token::array(array_map(function ($attr) use ($type, $build, $viewname) {
                    if ($attr->getSecurity()->isExclude($build, 'elm'))
                        return null;
                    return Token::multi(
                        Token::text('|> with '),
                        $this->buildTypeChanger(
                            $attr->getType(),
                            $attr->getOptional(),
                            Token::multi(
                                Token::text(\ucfirst($viewname)),
                                Token::text('.'),
                                Token::text(\lcfirst($attr->getName())),
                            ),
                        ),
                        Token::nl(),
                    );
                }, $type->getAttributes())),
                Token::array(array_map(function ($joint) use ($build, $type, $viewname) {
                    if ($joint->getSecurity()->isExclude($build, 'elm'))
                        return null;
                    return Token::multi(
                        Token::text('|> with ('),
                        Token::text(\ucfirst($viewname)),
                        Token::text('.'),
                        Token::text(\lcfirst($joint->getName())),
                        Token::text(' '),
                        Token::text(\lcfirst($joint->getTarget())),
                        Token::textnl('IdDecoder)'),
                    );
                }, $type->getJoints())),
                (function () use ($data, $type, $build) {
                    $list = array();
                    foreach ($data->getTypes() as $other) 
                        foreach ($other->getJoints() as $joint)
                            if ($joint->getTarget() == $type->getName()) {
                                if ($joint->getSecurity()->isExclude($build, 'elm'))
                                    continue;
                                $list []= Token::textnl('|> hardcoded Nothing');
                            }
                    return Token::array($list);
                })(),
            );
            if (!isset($typeDep[$type->getName()])) {}
            elseif ($i == 0) {
                $tokens []= Token::multi(
                    Token::text('|> hardcoded '),
                    Token::text(\ucfirst($type->getName())),
                    Token::textnl('Base'),
                );
            }
            else {
                $tokens []= Token::multi(
                    Token::textnlpush('|> with '),
                    Token::textnlpush('( SelectionSet.map'),
                    Token::text(\ucfirst($types[$i - 1]->getName())),
                    Token::textnl('Dep'),
                    Token::text('( SelectionSet.succeed '),
                    Token::textnlpush(\ucfirst($types[$i - 1]->getName())),
                );
            }
        }
        for ($i = 1; $i < count($types); $i++) {
            $tokens []= Token::multi(
                Token::pop(),
                Token::textnlpop(')'),
                Token::textnlpop(')'),
            );
        }
        $tokens []= Token::multi(
            Token::pop(),
            Token::pop(),
        );
        return Token::array($tokens);
    }


    private static function buildTypeChanger(string $type, bool $optional, Token $source): Token {
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
                return $source;
            case 'bytes':
                return $source;
            case 'date':
                if ($optional)
                    return Token::multi(
                        Token::textnlpush(''),
                        Token::textnlpush('( SelectionSet.map'),
                        Token::textnlpush('( Maybe.andThen'),
                        Token::textnlpush('( Iso8601.toTime'),
                        Token::textnlpop('>> Result.toMaybe'),
                        Token::textnlpop(')'),
                        Token::textnl(')'),
                        $source,
                        Token::textnlpop(''),
                        Token::text(')'),
                        Token::pop(),
                    );
                else 
                    return Token::multi(
                        Token::textnlpush(''),
                        Token::textnlpush('( SelectionSet.mapOrFail'),
                        Token::textnlpush('( Iso8601.toTime'),
                        Token::textnlpush('>> Result.mapError'),
                        Token::textnlpop('(always "fail to parse time")'),
                        Token::pop(),
                        Token::textnl(')'),
                        $source,
                        Token::textnlpop(''),
                        Token::text(')'),
                        Token::pop(),
                    );
            case 'json':
                return $source;
            default:
                return $source;
        }
    }

    private static function getRootType(Type $type, DataDef $data): Type {
        while ($type->getBase() !== null)
            $type = $data->getType($type->getBase());
        return $type;
    }
    
    private static function getTypeDependency(DataDef $data): array {
        $list = array();
        foreach ($data->getTypes() as $t)
            if ($t->getBase() !== null)
                $list[$t->getBase()] []= $t;
        return $list;
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