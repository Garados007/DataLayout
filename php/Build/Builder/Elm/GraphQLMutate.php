<?php namespace Build\Builder\Elm;

use \Build\BuildConfig as Config;
use \Build\Token as Token;
use \Data\DataDefinition as DataDef;
use \Data\Type;
use \Data\Build;

class GraphQLMutate {
    public function buildUtilCode(Config $config, DataDef $data): Token {
        return Token::multi(
            Token::text('module '),
            Token::text($data->getEnvironment()->getBuild()->getGraphQLMutate()),
            Token::textnlpush(' exposing'),
            Token::textnlpop('(..)'),
            Token::nl(),
            Token::text('import '),
            Token::text($data->getEnvironment()->getBuild()->getNamespace()),
            Token::textnl(' as Types'),
            Token::text('import '),
            Token::text($data->getEnvironment()->getBuild()->getGraphQL()),
            Token::textnl(' as Decoder'),
            Token::textnl('import Api.Interface'),
            Token::array(array_map(function ($type) {
                // if ($type->getBase() !== null)
                //     return null;
                return Token::multi(
                    Token::text('import Api.Interface.'),
                    Token::text(\ucfirst($type->getName())),
                    Token::text('_Mutatable as Api'),
                    Token::text(\ucfirst($type->getName())),
                    Token::textnl('_Mutatable'),
                );
            }, $data->getTypes())),
            Token::textnl('import Api.Object'),
            Token::array(array_map(function ($type) {
                return Token::multi(
                    Token::text('import Api.Object.'),
                    Token::text(\ucfirst($type->getName())),
                    Token::text('_StaticMutator as Api'),
                    Token::text(\ucfirst($type->getName())),
                    Token::textnl('_StaticMutator'),
                    Token::text('import Api.Object.'),
                    Token::text(\ucfirst($type->getName())),
                    Token::text('_Mutator as Api'),
                    Token::text(\ucfirst($type->getName())),
                    Token::textnl('_Mutator'),
                );
            }, $data->getTypes())),
            Token::array(array_map(function ($entry) {
                if ($entry['root'])
                    return null;
                return Token::multi(
                    Token::text('import Api.Object.'),
                    Token::textnl(\ucfirst($entry['type'])),
                );
            }, $data->getEnvironment()->getBuild()->getGraphQLNode() ?: array())),
            $data->getEnvironment()->getBuild()->getGraphQLNode() === null 
                ? Token::text('')
                : Token::textnl('import Api.Mutation'),
            Token::textnl('import Api.Scalar'),
            Token::textnl('import AssocList'),
            Token::textnl('import Dict'),
            Token::textnl('import Graphql.SelectionSet as SelectionSet exposing (SelectionSet)'),
            Token::textnl('import Graphql.Operation exposing (RootMutation)'),
            Token::textnl('import Graphql.OptionalArgument exposing (OptionalArgument (..))'),
            Token::textnl('import Iso8601'),
            Token::textnl('import Json.Decode'),
            Token::textnl('import Json.Encode'),
            Token::textnl('import Time'),
            Token::nl(),

        );
    }

    private function getRootType(Type $type, DataDef $data): Type {
        while ($type->getBase() !== null)
            $type = $data->getType($type->getBase());
        return $type;
    }

    private function getBaseTypes(Type $type, DataDef $data): array {
        $result = array();
        while ($type !== null) {
            $result []= $type->getName();
            if ($type->getBase() !== null)
                $type = $data->getType($type->getBase());
            else $type = null;
        }
        return $result;
    }

    private function getHigherTypes(Type $type, DataDef $data, bool $includeCurrent = true): array {
        $result = array();
        if ($includeCurrent)
            $result []= $type->getName();
        foreach ($data->getTypes() as $other) 
            if ($other->getBase() == $type->getName()) {
                $result = array_merge(
                    $result,
                    $this->getHigherTypes($other, $data, true),
                );
            }
        return $result;
    }
    
    private function getElmValueType(string $type, bool $list, $brackets = true): Token { 
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
        if ($list) {
            if ($brackets)
                return Token::multi(
                    Token::text('(List '),
                    $token,
                    Token::text(')'),
                );
            else 
                return Token::multi(
                    Token::text('List '),
                    $token,
                );
        }
        else return $token;
    }

    private function getGraphqlType(string $type, bool $list, Token $value): Token { 
        $converter = null;
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
                $converter = null;
                break;
            case 'date':
                $converter = Token::text('Iso8601.fromTime');
                break;
            case 'json':
                $converter = Token::text('Json.Encode.encode 0');
                break;
            default:
                break;
        }
        if ($list) {
            return Token::multi(
                Token::text('List.map ('),
                $converter ?: Token::text('\v -> v'),
                Token::text(') '),
                $value
            );
        }
        else return Token::multi(
            $converter === null
                ? Token::text('')
                : Token::multi(
                    $converter,
                    Token::text(' '),
                ),
            $value
        );
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

    private function intersperce(array $array, $element): array {
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