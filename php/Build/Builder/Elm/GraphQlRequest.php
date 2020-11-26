<?php namespace Build\Builder\Elm;

use \Build\BuildConfig as Config;
use \Build\Token as Token;
use \Data\DataDefinition as DataDef;
use \Data\Type;
use \Data\Build;

class GraphQLRequest {
    public function buildUtilCode(Config $config, DataDef $data): Token {
        return Token::multi(
            Token::text('module '),
            Token::text($data->getEnvironment()->getBuild()->getGraphQLRequest()),
            Token::textnlpush(' exposing'),
            Token::textnl('( Response (..)'),
            Token::textnl(', applyResponse'),
            Token::array(array_map(function ($type) use ($data) {
                $build = $data->getEnvironment()->getBuild();
                $baseTypes = $this->getBaseTypes($type, $data);
                return Token::multi(
                    Token::text(', type'),
                    Token::textnl(\ucfirst($type->getName())),
                    Token::array(
                        array_map(function ($joint) use ($type, $build) {
                            if ($joint->getSecurity()->isExclude($build, 'elm'))
                                return null;
                            return Token::multi(
                                Token::text(', joint'),
                                Token::text(\ucfirst($type->getName())),
                                Token::textnl(\ucfirst($joint->getName())),
                            );
                        }, $type->getJoints())
                    ),
                    (function () use ($type, $baseTypes, $build, $data) {
                        $result = array();
                        foreach ($data->getTypes() as $other) 
                            foreach ($other->getJoints() as $joint) 
                                if (\in_array($joint->getTarget(), $baseTypes)) {
                                    if ($joint->getSecurity()->isExclude($build, 'elm'))
                                        continue;
                                    $suffix = $joint->getTarget() == $type->getName()
                                        ? Token::nl()
                                        : Token::multi(
                                            Token::text('At'),
                                            Token::textnl(\ucfirst($type->getName())),
                                        );
                                    $result []= Token::multi(
                                        Token::text(', from'),
                                        Token::text(\ucfirst($other->getName())),
                                        Token::text(\ucfirst($joint->getName())),
                                        $suffix,
                                    );
                                    if ($build->getGraphQLNode() !== null)
                                        $result []= Token::multi(
                                            Token::text(', continueFrom'),
                                            Token::text(\ucfirst($other->getName())),
                                            Token::text(\ucfirst($joint->getName())),
                                            $suffix,
                                        );
                                }
                        return Token::array($result);
                    })(),
                    Token::text(', node'),
                    Token::textnl(\ucfirst($type->getName())),
                    Token::array(array_map(function ($query) use ($type, $build) {
                        if (!$query->isSearchQuery())
                            return null;
                        if ($query->getSecurity()->isExclude($build, 'elm'))
                            return null;
                        return Token::multi(
                            Token::text(', query'),
                            Token::text(\ucfirst($type->getName())),
                            Token::textnl(\ucfirst($query->getName())),
                            $build->getGraphQLNode() === null || $query->isLimitFirst()
                                ? Token::text('')
                                : Token::multi(
                                    Token::text(', continueQuery'),
                                    Token::text(\ucfirst($type->getName())),
                                    Token::textnl(\ucfirst($query->getName())),
                                ),
                        );
                    }, $type->getAccess())),
                    Token::text(', load'),
                    Token::textnl(\ucfirst($type->getName())),
                );
            }, $data->getTypes())),
            Token::textnlpop(')'),
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
                    Token::text(' as Api'),
                    Token::textnl(\ucfirst($type->getName())),
                );
            }, $data->getTypes())),
            Token::textnl('import Api.Object'),
            Token::array(array_map(function ($type) {
                return Token::multi(
                    Token::text('import Api.Object.'),
                    Token::text(\ucfirst($type->getName())),
                    Token::text('_Pagination as Api'),
                    Token::text(\ucfirst($type->getName())),
                    Token::textnl('_Pagination'),
                    Token::text('import Api.Object.'),
                    Token::text(\ucfirst($type->getName())),
                    Token::text('_Edge as Api'),
                    Token::text(\ucfirst($type->getName())),
                    Token::textnl('_Edge'),
                    Token::text('import Api.Object.'),
                    Token::text(\ucfirst($type->getName())),
                    Token::text('_Static as Api'),
                    Token::text(\ucfirst($type->getName())),
                    Token::textnl('_Static'),
                    $type->getBase() !== null
                        ? Token::multi(
                            Token::text('import Api.Object.'),
                            Token::text(\ucfirst($type->getName())),
                            Token::text('_Mutator as Api'),
                            Token::text(\ucfirst($type->getName())),
                            Token::textnl('_Mutator'),
                            Token::text('import Api.Object.'),
                            Token::text(\ucfirst($type->getName())),
                            Token::text('_Type as Api'),
                            Token::text(\ucfirst($type->getName())),
                            Token::textnl('_Type'),
                        )
                        : Token::text(''),
                );
            }, $data->getTypes())),
            Token::textnl('import Api.Object.PageInfo'),
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
                : Token::textnl('import Api.Query'),
            Token::textnl('import Api.Scalar'),
            Token::textnl('import AssocList'),
            Token::textnl('import Dict'),
            Token::textnl('import Graphql.SelectionSet as SelectionSet exposing (SelectionSet)'),
            Token::textnl('import Graphql.Operation exposing (RootQuery)'),
            Token::textnl('import Graphql.OptionalArgument exposing (OptionalArgument (..))'),
            Token::textnl('import Iso8601'),
            Token::textnl('import Json.Decode'),
            Token::textnl('import Json.Encode'),
            Token::textnl('import Time'),
            Token::nl(),
            Token::textnlpush('type Response '),
            Token::textnl('= NoResult'),
            Token::array(array_map(function ($type) use ($data) {
                $build = $data->getEnvironment()->getBuild();
                return Token::multi(
                    $type->getBase() === null 
                        ? Token::multi(
                            Token::text('| Type'),
                            Token::text(\ucfirst($type->getName())),
                            Token::text(' Types.'),
                            Token::textnl(\ucfirst($type->getName())),
                        )
                        : Token::text(''),
                    (function () use ($type, $data, $build) {
                        $result = array();
                        foreach ($data->getTypes() as $other)
                            foreach ($other->getJoints() as $joint) {
                                if ($joint->getSecurity()->isExclude($build, 'elm'))
                                    continue;
                                if ($joint->getTarget() == $type->getName()) {
                                    $result []= Token::multi(
                                        Token::text('| From'),
                                        Token::text(\ucfirst($other->getName())),
                                        Token::text(\ucfirst($joint->getName())),
                                        Token::text(' Types.'),
                                        Token::text(\ucfirst($type->getName())),
                                        Token::text('Id (Types.ApiIterator Types.'),
                                        Token::text(\ucfirst($this->getRootType($other, $data)->getName())),
                                        Token::textnl('Id)'),
                                    );
                                }
                            }
                        return Token::array($result);
                    })(),
                    Token::array(array_map(function ($query) use ($type, $data, $build) {
                        if (!$query->isSearchQuery())
                            return null;
                        if ($query->getSecurity()->isExclude($build, 'elm'))
                            return null;
                        return Token::multi(
                            Token::text('| Search'),
                            Token::text(\ucfirst($type->getName())),
                            Token::text(\ucfirst($query->getName())),
                            Token::array(array_map(function ($name) use ($query) {
                                return Token::multi(
                                    Token::text(' '),
                                    $this->getElmValueType(
                                        $query->getInputVarType($name),
                                        $query->isInputVarArray($name),
                                    ),
                                );
                            }, $query->getInputVarNames())),
                            Token::array(array_map(function ($name) use ($query) {
                                $type = Token::multi(
                                    Token::text('Types.'),
                                    Token::text(\ucfirst($query->getInputObjTarget($name))),
                                    Token::text('Id'),
                                );
                                return Token::multi(
                                    Token::text(' '),
                                    $query->isInputObjArray($name)
                                        ? Token::multi(
                                            Token::text('(List '),
                                            $type,
                                            Token::text(')'),
                                        )
                                        : $type,
                                );
                            }, $query->getInputObjNames())),
                            $query->isLimitFirst()
                                ? Token::text(' (Maybe Types.')
                                : Token::text(' (Types.ApiIterator Types.'),
                            Token::text(\ucfirst($type->getName())),
                            Token::textnl('Id)'),
                        );
                    }, $type->getAccess())),
                );
            }, $data->getTypes())),
            Token::pop(),
            Token::nl(),
            Token::textnl('applyResponse : Response -> Types.DataBase -> Types.DataBase'),
            Token::textnlpush('applyResponse response database ='),
            Token::textnlpush('case response of'),
            Token::textnl('NoResult -> database'),
            Token::array(array_map(function ($type) use ($data) {
                $build = $data->getEnvironment()->getBuild();
                return Token::multi(
                    $type->getBase() === null 
                        ? Token::multi(
                            Token::text('Type'),
                            Token::text(\ucfirst($type->getName())),
                            Token::textnlpush(' arg ->'),
                            Token::textnl('{ database'),
                            Token::textnlpush('| data = database.data |> \\data ->'),
                            Token::textnl('{ data'),
                            Token::text('| '),
                            Token::text(\lcfirst($type->getName())),
                            Token::textnlpush(' = AssocList.update'),
                            Token::textnl('arg.id'),
                            Token::textnlpush('(\\mv -> Just <| case mv of'),
                            Token::textnlpush('Just v ->'),
                            Token::textnl('{ v'),
                            Token::textnl('| id = arg.id'),
                            Token::array(array_map(function ($attr) use ($build) {
                                if ($attr->getSecurity()->isExclude($build, 'elm'))
                                    return null;
                                return Token::multi(
                                    Token::text(', '),
                                    Token::text(\lcfirst($attr->getName())),
                                    Token::text(' = arg.'),
                                    Token::textnl(\lcfirst($attr->getName())),
                                );
                            }, $type->getAttributes())),
                            Token::array(array_map(function ($joint) use ($build) {
                                if ($joint->getSecurity()->isExclude($build, 'elm'))
                                    return null;
                                return Token::multi(
                                    Token::text(', '),
                                    Token::text(\lcfirst($joint->getName())),
                                    Token::text(' = arg.'),
                                    Token::textnl(\lcfirst($joint->getName())),
                                );
                            }, $type->getJoints())),
                            Token::textnlpop('}'),
                            Token::textnlpop('Nothing -> arg'),
                            Token::textnl(')'),
                            Token::text('data.'),
                            Token::textnlpop(\lcfirst($type->getName())),
                            Token::textnlpop('}'),
                            Token::textnlpop('}'),
                        )
                        : Token::text(''),
                    (function () use ($type, $data, $build) {
                        $result = array();
                        foreach ($data->getTypes() as $other)
                            foreach ($other->getJoints() as $joint) {
                                if ($joint->getSecurity()->isExclude($build, 'elm'))
                                    continue;
                                if ($joint->getTarget() == $type->getName()) {
                                    $result []= Token::multi(
                                        Token::text('From'),
                                        Token::text(\ucfirst($other->getName())),
                                        Token::text(\ucfirst($joint->getName())),
                                        Token::textnlpush(' arg1 arg2 ->'),
                                        Token::textnl('{ database'),
                                        Token::textnlpush('| data = database.data |> \\data ->'),
                                        Token::textnl('{ data'),
                                        Token::text('| '),
                                        Token::text(\lcfirst($type->getName())),
                                        Token::textnlpush(' = AssocList.update'),
                                        Token::textnl('arg1'),
                                        Token::textnlpush('(Maybe.map'),
                                        Token::textnlpush('(\value ->'),
                                        Token::textnl('{ value'),
                                        Token::text('| from'),
                                        Token::text(\ucfirst($other->getName())),
                                        Token::text(\ucfirst($joint->getName())),
                                        Token::textnlpush(' ='),
                                        Token::text('value.from'),
                                        Token::text(\ucfirst($other->getName())),
                                        Token::textnlpush(\ucfirst($joint->getName())),
                                        Token::textnlpush('|> Maybe.withDefault'),
                                        Token::textnl('{ last = Nothing'),
                                        Token::textnl(', list = []'),
                                        Token::textnl(', more = True'),
                                        Token::textnlpop('}'),
                                        Token::textnlpush('|> \\apiIterator ->'),
                                        Token::textnl('{ last = arg2.last'),
                                        Token::textnlpush(', list = List.append'),
                                        Token::textnl('apiIterator.list'),
                                        Token::textnlpop('arg2.list'),
                                        Token::textnl(', more = arg2.more'),
                                        Token::textnlpop('}'),
                                        Token::textnlpop('|> Just'),
                                        Token::pop(),
                                        Token::textnlpop('}'),
                                        Token::textnlpop(')'),
                                        Token::textnl(')'),
                                        Token::text('data.'),
                                        Token::textnlpop(\lcfirst($type->getName())),
                                        Token::textnlpop('}'),
                                        Token::textnlpop('}'),
                                    );
                                }
                            }
                        return Token::array($result);
                    })(),
                    Token::array(array_map(function ($query) use ($type, $data, $build) {
                        if (!$query->isSearchQuery())
                            return null;
                        if ($query->getSecurity()->isExclude($build, 'elm'))
                            return null;
                        $result = array(
                            Token::text('Search'),
                            Token::text(\ucfirst($type->getName())),
                            Token::text(\ucfirst($query->getName())),
                            Token::array(array_map(function ($name) {
                                return Token::multi(
                                    Token::text(' arg'),
                                    Token::text(\ucfirst($name)),
                                );
                            }, $query->getInputVarNames())),
                            Token::array(array_map(function ($name) {
                                return Token::multi(
                                    Token::text(' arg'),
                                    Token::text(\ucfirst($name)),
                                );
                            }, $query->getInputObjNames())),
                            Token::textnlpush(' arg ->'),
                            Token::textnl('{ database'),
                            Token::textnlpush('| static = database.static |> \static ->'),
                            Token::textnl('{ static'),
                            Token::text('| '),
                            Token::text(\lcfirst($type->getName())),
                            Token::text(' = static.'),
                            Token::text(\lcfirst($type->getName())),
                            Token::text(' |> \\'),
                            Token::text(\lcfirst($type->getName())),
                            Token::textnlpush(' ->'),
                            Token::text('{ '),
                            Token::textnl(\lcfirst($type->getName())),
                            Token::text('| '),
                            Token::text(\lcfirst($query->getName())),
                            Token::text(' = '),
                        );
                        $arg = array();
                        foreach ($query->getInputVarNames() as $name)
                            $arg []= array(
                                'name' => $name,
                                'dict' => $this->getDict(
                                    $query->getInputVarType($name)
                                ),
                            );
                        foreach ($query->getInputObjNames() as $name)
                            $arg []= array(
                                'name' => $name,
                                'dict' => 'AssocList'
                            );
                        for ($i = 0; $i < count($arg); ++$i) 
                            $result []= Token::multi(
                                $i == 0 
                                    ? Token::text('')
                                    : Token::multi(
                                        Token::text('Maybe.withDefault '),
                                        Token::text($arg[$i]['dict']),
                                        Token::textnlpush('.empty'),
                                        Token::text('>> '),
                                    ),
                                Token::text($arg[$i]['dict']),
                                Token::textnlpush('.update'),
                                Token::text('arg'),
                                Token::textnl(\ucfirst($arg[$i]['name'])),
                                Token::text('('),
                            );
                        if (count($arg) == 0)
                            $result []= Token::multi(
                                Token::textnlpush(''),
                                Token::text('('),
                            );
                        if ($query->isLimitFirst())
                            $result []= Token::multi(
                                Token::text('always arg'),
                            );
                        else 
                            $result []= Token::multi(
                                Token::textnlpush('Maybe.withDefault'),
                                Token::textnl('{ last = Nothing'),
                                Token::textnl(', list = []'),
                                Token::textnl(', more = True'),
                                Token::textnl('}'),
                                Token::textnlpush('>> (\\iterator ->'),
                                Token::push(),
                                Token::textnl('{ iterator'),
                                Token::textnl('| last = arg.last'),
                                Token::textnl(', list = List.append iterator.list arg.list'),
                                Token::textnl(', more = arg.more'),
                                Token::textnlpop('}'),
                                Token::textnlpop(')'),
                                Token::textnlpop('>> Just'),
                            );
                        if (count($arg) == 0) 
                            $result []= Token::multi(
                                Token::textnl(')'),
                                Token::text(\lcfirst($type->getName())),
                                Token::text('.'),
                                Token::textnlpop(\lcfirst($query->getName())),
                            );
                        for ($i = count($arg) - 1; $i >= 0; --$i)
                            $result []= Token::multi(
                                Token::textnl(')'),
                                $i == 0 
                                    ? Token::multi(
                                        Token::text(\lcfirst($type->getName())),
                                        Token::text('.'),
                                        Token::textnlpop(\lcfirst($query->getName())),
                                    )
                                    : Token::multi(
                                        Token::pop(),
                                        Token::textnlpop('>> Just'),
                                    ),
                            );
                        $result []= Token::multi(
                            Token::textnlpop('}'),
                            Token::textnlpop('}'),
                            Token::textnlpop('}'),
                        );
                        return $result;
                    }, $type->getAccess())),
                );
            }, $data->getTypes())),
            Token::pop(),
            Token::pop(),
            Token::array(array_map(function ($type) use ($data) {
                $build = $data->getEnvironment()->getBuild();
                $root = $this->getRootType($type, $data);
                $result = array();
                $baseTypes = $this->getBaseTypes($type, $data);
                $higherTypes = $this->getHigherTypes($type, $data, true);
                //type* : SelectionSet (List Response) Api.Interface.*
                $result []= Token::multi(
                    Token::nl(),
                    Token::text('type'),
                    Token::text(\ucfirst($type->getName())),
                    Token::text(' : SelectionSet (List Response) Api.Interface.'),
                    Token::textnl(\ucfirst($type->getName())),
                    Token::text('type'),
                    Token::text(\ucfirst($type->getName())),
                    Token::textnlpush(' ='),
                    Token::textnlpush('SelectionSet.map'),
                    Token::text('(List.singleton << Type'),
                    Token::text(\ucfirst($root->getName())),
                    Token::textnl(')'),
                    Token::text('Decoder.'),
                    Token::text(\lcfirst($type->getName())),
                    Token::textnlpop('Decoder'),
                    Token::pop(),
                );
                //joint*<n> : List (SelectionSet (List Response) Api.Interface.<t>)
                //      -> SelectionSet (List Response) Api.Interface.*
                foreach ($baseTypes as $t)
                    foreach ($data->getType($t)->getJoints() as $joint) {
                        if ($joint->getSecurity()->isExclude($build, 'elm'))
                            continue;
                        $result []= Token::multi(
                            Token::nl(),
                            Token::text('joint'),
                            Token::text(\ucfirst($type->getName())),
                            Token::text(\ucfirst($joint->getName())),
                            Token::text(' : List (SelectionSet (List Response) Api.Interface.'),
                            Token::text(\ucfirst($joint->getTarget())),
                            Token::text(') -> SelectionSet (List Response) Api.Interface.'),
                            Token::textnl(\ucfirst($type->getName())),
                            Token::text('joint'),
                            Token::text(\ucfirst($type->getName())),
                            Token::text(\ucfirst($joint->getName())),
                            Token::textnlpush(' ='),
                            $joint->getRequired()
                                ? Token::text('')
                                : Token::multi(
                                    Token::textnlpush('SelectionSet.map (Maybe.withDefault [])'),
                                    Token::text('<< '),
                                ),
                            $t == $type->getName()
                                ? Token::multi(
                                    Token::text('Api'),
                                    Token::text(\ucfirst($type->getName())),
                                    Token::text('.'),
                                    Token::textnl(\lcfirst($joint->getName())),
                                )
                                : Token::multi(
                                    Token::textnlpush('(\\obj ->'),
                                    Token::text('Api'),
                                    Token::text(\ucfirst($type->getName())),
                                    Token::textnlpush('.fragments'),
                                    Token::text('{ '),
                                    Token::array($this->intersperce(
                                        array_map(function ($t) use ($joint) {
                                            return Token::multi(
                                                Token::text('on'),
                                                Token::text(\ucfirst($t)),
                                                Token::textnlpush('_Mutator ='),
                                                Token::text('Api'),
                                                Token::text(\ucfirst($t)),
                                                Token::text('_Mutator.'),
                                                Token::text(\lcfirst($joint->getName())),
                                                Token::textnlpop(' obj'),
                                                Token::text(', on'),
                                                Token::text(\ucfirst($t)),
                                                Token::textnlpush('_Type ='),
                                                Token::text('Api'),
                                                Token::text(\ucfirst($t)),
                                                Token::text('_Type.'),
                                                Token::text(\lcfirst($joint->getName())),
                                                Token::text(' obj'),
                                                Token::pop(),
                                            );
                                        }, $higherTypes),
                                        Token::multi(
                                            Token::nl(),
                                            Token::text(', '),
                                        ),
                                    )),
                                    Token::nl(),
                                    Token::textnlpop('}'),
                                    Token::pop(),
                                    Token::textnl(')'),
                                ),
                            $joint->getRequired()
                                ? Token::push()
                                : Token::text(''),
                            Token::textnl('<< SelectionSet.map List.concat'),
                            Token::textnlpop('<< SelectionSet.list'),
                            Token::pop(),
                        );
                    }
                // apiIterate* : SelectionSet (Types.ApiIterator *Id) Api.Object.*_Pagination 
                $result []= Token::multi(
                    Token::nl(),
                    Token::text('apiIterate'),
                    Token::text(\ucfirst($type->getName())),
                    Token::text(' : SelectionSet (Types.ApiIterator Types.'),
                    Token::text(\ucfirst($root->getName())),
                    Token::text('Id) Api.Object.'),
                    Token::text(\ucfirst($type->getName())),
                    Token::textnl('_Pagination'),
                    Token::text('apiIterate'),
                    Token::text(\ucfirst($type->getName())),
                    Token::textnlpush(' ='),
                    Token::textnlpush('SelectionSet.map3 Types.ApiIterator'),
                    Token::text('( Api'),
                    Token::text(\ucfirst($type->getName())),
                    Token::textnlpush('_Pagination.pageInfo'),
                    Token::textnlpop('Api.Object.PageInfo.endCursor'),
                    Token::textnl(')'),
                    Token::text('( Api'),
                    Token::text(\ucfirst($type->getName())),
                    Token::textnlpush('_Pagination.edges'),
                    Token::text('( Api'),
                    Token::text(\ucfirst($type->getName())),
                    Token::textnlpush('_Edge.node'),
                    Token::text('Decoder.'),
                    Token::text(\lcfirst($type->getName())),
                    Token::textnlpop('IdDecoder'),
                    Token::textnlpop(')'),
                    Token::textnl(')'),
                    Token::text('( Api'),
                    Token::text(\ucfirst($type->getName())),
                    Token::textnlpush('_Pagination.pageInfo'),
                    Token::textnlpop('Api.Object.PageInfo.hasNextPage'),
                    Token::textnlpop(')'),
                    Token::pop(),
                );
                //from<o><j> : Maybe String -> Maybe Int
                //      -> List (SelectionSet (List Response) Api.Object.<o>_Pagination)
                //      -> SelectionSet (List Response) Api.Interface.*
                foreach ($data->getTypes() as $other) 
                    foreach ($other->getJoints() as $joint) 
                        if (\in_array($joint->getTarget(), $baseTypes)) {
                            if ($joint->getSecurity()->isExclude($build, 'elm'))
                                continue;
                            $result []= Token::multi(
                                Token::nl(),
                                Token::text('from'),
                                Token::text(\ucfirst($other->getName())),
                                Token::text(\ucfirst($joint->getName())),
                                $joint->getTarget() == $type->getName()
                                    ? Token::text('')
                                    : Token::multi(
                                        Token::text('At'),
                                        Token::text(\ucfirst($type->getName())),
                                    ),
                                Token::text(' : Maybe String -> Maybe Int -> List (SelectionSet (List Response) Api.Object.'),
                                Token::text(\ucfirst($other->getName())),
                                Token::text('_Pagination) -> SelectionSet (List Response) Api.Interface.'),
                                Token::textnl(\ucfirst($type->getName())),
                                Token::text('from'),
                                Token::text(\ucfirst($other->getName())),
                                Token::text(\ucfirst($joint->getName())),
                                $joint->getTarget() == $type->getName()
                                    ? Token::text('')
                                    : Token::multi(
                                        Token::text('At'),
                                        Token::text(\ucfirst($type->getName())),
                                    ),
                                Token::textnlpush(' paginationStart paginationCount ='),
                                Token::textnlpush('SelectionSet.map'),
                                Token::textnlpush('(\\(a, (b, c)) ->'),
                                Token::text('From'),
                                Token::text(\ucfirst($other->getName())),
                                Token::text(\ucfirst($joint->getName())),
                                Token::textnlpop(' a b :: c'),
                                Token::textnl(')'),
                                Token::textnlpush('<< SelectionSet.map2 Tuple.pair'),
                                Token::text('Decoder.'),
                                Token::text(\lcfirst($type->getName())),
                                Token::textnlpop('IdDecoder'),
                                $joint->getTarget() == $type->getName()
                                    ? Token::multi(
                                        Token::text('<< Api'),
                                        Token::text(\ucfirst($type->getName())),
                                        Token::text('.from'),
                                        Token::text(\ucfirst($other->getName())),
                                        Token::textnlpush(\ucfirst($joint->getName())),
                                    )
                                    : Token::multi(
                                        Token::textnlpush('<< (\\opts obj ->'),
                                        Token::text('Api'),
                                        Token::text(\ucfirst($type->getName())),
                                        Token::textnlpush('.fragments'),
                                        Token::push(),
                                        Token::text('{ '),
                                        Token::array($this->intersperce(
                                            array_map(function($t) use ($other, $joint) {
                                                return Token::multi(
                                                    Token::text('on'),
                                                    Token::text(\ucfirst($t)),
                                                    Token::textnlpush('_Mutator ='),
                                                    Token::text('Api'),
                                                    Token::text(\ucfirst($t)),
                                                    Token::text('_Mutator.from'),
                                                    Token::text(\ucfirst($other->getName())),
                                                    Token::textnlpush(\ucfirst($joint->getName())),
                                                    Token::textnl('opts'),
                                                    Token::textnlpop('obj'),
                                                    Token::pop(),
                                                    Token::text(', on'),
                                                    Token::text(\ucfirst($t)),
                                                    Token::textnlpush('_Type ='),
                                                    Token::text('Api'),
                                                    Token::text(\ucfirst($t)),
                                                    Token::text('_Type.from'),
                                                    Token::text(\ucfirst($other->getName())),
                                                    Token::textnlpush(\ucfirst($joint->getName())),
                                                    Token::textnl('opts'),
                                                    Token::text('obj'),
                                                    Token::pop(),
                                                    Token::pop(),
                                                );
                                            }, $higherTypes),
                                            Token::multi(
                                                Token::nl(),
                                                Token::text(', '),
                                            ),
                                        )),
                                        Token::nl(),
                                        Token::textnlpop('}'),
                                        Token::pop(),
                                        Token::textnl(')'),
                                    ),
                                Token::textnlpush('(\optional ->'),
                                Token::textnl('{ optional'),
                                Token::textnlpush('| after = case paginationStart of'),
                                Token::textnl('Just value -> Present value'),
                                Token::textnlpop('Nothing -> Absent'),
                                Token::textnlpush(', first = case paginationCount of'),
                                Token::textnl('Just value -> Present value'),
                                Token::textnlpop('Nothing -> Absent'),
                                Token::textnlpop('}'),
                                Token::textnlpop(')'),
                                Token::textnlpush('<< SelectionSet.map2 Tuple.pair'),
                                Token::text('apiIterate'),
                                Token::textnlpop(\ucfirst($other->getName())),
                                Token::textnl('<< SelectionSet.map List.concat'),
                                Token::textnlpop('<< SelectionSet.list'),
                                Token::pop(),
                            );
                            if ($build->getGraphQLNode() !== null)
                                $result []= TOken::multi(
                                    Token::nl(),
                                    Token::text('continueFrom'),
                                    Token::text(\ucfirst($other->getName())),
                                    Token::text(\ucfirst($joint->getName())),
                                    $joint->getTarget() == $type->getName()
                                        ? Token::text('')
                                        : Token::multi(
                                            Token::text('At'),
                                            Token::text(\ucfirst($type->getName())),
                                        ),
                                    Token::textnl(' : Int -> Types.DataBase -> Maybe (SelectionSet (List Response) RootQuery)'),
                                    Token::text('continueFrom'),
                                    Token::text(\ucfirst($other->getName())),
                                    Token::text(\ucfirst($joint->getName())),
                                    $joint->getTarget() == $type->getName()
                                        ? Token::text('')
                                        : Token::multi(
                                            Token::text('At'),
                                            Token::text(\ucfirst($type->getName())),
                                        ),
                                    Token::textnlpush(' limit data ='),
                                    Token::text('let queryList : List (Types.'),
                                    Token::text(\ucfirst($root->getName())),
                                    Token::textnlpush('Id, Maybe String)'),
                                    Token::text('queryList = data.data.'),
                                    Token::textnlpush(\lcfirst($root->getName())),
                                    Token::textnl('|> AssocList.toList'),
                                    $root->getName() == $type->getName()
                                        ? Token::text('')
                                        : Token::multi(
                                            Token::textnlpush('|> List.filter'),
                                            Token::text('(\\(_, entry) -> '),
                                            Token::array(
                                                (function () use ($type, $baseTypes, $data) {
                                                    $result = array();
                                                    $var = 'entry';
                                                    for ($i = count($baseTypes) - 2; $i >= 0; $i--) {
                                                        $result []= Token::multi(
                                                            Token::text('case '),
                                                            Token::text($var),
                                                            Token::textnlpush('.subType of'),
                                                            Token::text('Types.'),
                                                            Token::text(\ucfirst($baseTypes[$i])),
                                                            Token::text('Dep '),
                                                            Token::text($i == 0 ? '_' : 'e' . $i),
                                                            Token::text(' -> '),
                                                        );
                                                        $var = 'e' . $i;
                                                    }
                                                    $result []= Token::textnl('True');
                                                    for ($i = count($baseTypes) - 2; $i >= 0; $i--) {
                                                        $result []= Token::textnlpop('_ -> False');
                                                    }
                                                    return $result;
                                                })(),
                                            ),
                                            Token::textnlpop(')'),
                                        ),
                                    Token::textnlpush('|> List.filterMap'),
                                    Token::textnlpush('(\\(id, entry) -> Maybe.andThen'),
                                    Token::textnlpush('(\\page ->'),
                                    Token::textnl('if page.more'),
                                    Token::textnl('then Just (id, page.last)'),
                                    Token::textnlpop('else Nothing'),
                                    Token::textnl(')'),
                                    Token::text('entry.from'),
                                    Token::text(\ucfirst($other->getName())),
                                    Token::textnlpop(\ucfirst($joint->getName())),
                                    Token::textnlpop(')'),
                                    Token::pop(),
                                    Token::text('query : List (Types.'),
                                    Token::text(\ucfirst($root->getName())),
                                    Token::textnl('Id, Maybe String) -> SelectionSet (List Response) RootQuery'),
                                    Token::textnlpush('query = SelectionSet.nonNullOrFail'),
                                    Token::text('<< Api.Query.'),
                                    Token::array(array_map(function ($entry) {
                                        if ($entry['root'])
                                            return Token::multi(
                                                isset($entry['member'])
                                                    ? Token::multi(
                                                        Token::textnl(\lcfirst($entry['member'])),
                                                        Token::text('<< '),
                                                    )
                                                    : Token::text(''),
                                            );
                                        return Token::multi(
                                            $entry['maybe']
                                                ? Token::multi(
                                                    Token::textnl('SelectionSet.map (Maybe.withDefault [])'),
                                                    Token::text('<< '),
                                                )
                                                : Token::text(''),
                                            Token::text('Api.Object.'),
                                            Token::text(\ucfirst($entry['type'])),
                                            Token::text('.'),
                                            isset($entry['member'])
                                                ? Token::multi(
                                                    Token::textnl(\lcfirst($entry['member'])),
                                                    Token::text('<< '),
                                                )
                                                : Token::text(''),
                                        );
                                    }, $build->getGraphQLNode())),
                                    Token::textnl(\lcfirst($root->getName())),
                                    Token::textnl('<< SelectionSet.map List.concat'),
                                    Token::textnl('<< SelectionSet.list'),
                                    Token::textnlpush('<< List.map'),
                                    Token::textnlpush('(\\(id, last) ->'),
                                    Token::text('load'),
                                    Token::text(\ucfirst($root->getName())),
                                    Token::textnlpush(' id'),
                                    Token::text('[ from'),
                                    Token::text(\ucfirst($other->getName())),
                                    Token::textnlpush(\ucfirst($joint->getName())),
                                    Token::textnl('last'),
                                    Token::textnl('(Just limit)'),
                                    Token::text('[ node'),
                                    Token::textnlpush(\ucfirst($other->getName())),
                                    Token::text('[ type'),
                                    Token::text(\ucfirst($other->getName())),
                                    Token::textnlpop(' ]'),
                                    Token::textnlpop(']'),
                                    Token::textnlpop(']'),
                                    Token::pop(),
                                    Token::textnlpop(')'),
                                    Token::pop(),
                                    Token::pop(),
                                    Token::textnlpush('in  if List.isEmpty queryList'),
                                    Token::textnl('then Nothing'),
                                    Token::textnlpop('else Just <| query queryList'),
                                    Token::pop(),
                                );
                        }
                //node* : List (SelectionSet (List Response) Api.Interface.*)
                //      -> SelectionSet (List Response) Api.Object.*_Pagination
                $result []= Token::multi(
                    Token::nl(),
                    Token::text('node'),
                    Token::text(\ucfirst($type->getName())),
                    Token::text(' : List (SelectionSet (List Response) Api.Interface.'),
                    Token::text(\ucfirst($type->getName())),
                    Token::text(') -> SelectionSet (List Response) Api.Object.'),
                    Token::text(\ucfirst($type->getName())),
                    Token::textnl('_Pagination'),
                    Token::text('node'),
                    Token::text(\ucfirst($type->getName())),
                    Token::textnlpush(' ='),
                    Token::textnlpush('SelectionSet.map List.concat'),
                    Token::text('<< Api'),
                    Token::text(\ucfirst($type->getName())),
                    Token::textnl('_Pagination.edges'),
                    Token::text('<< Api'),
                    Token::text(\ucfirst($type->getName())),
                    Token::textnl('_Edge.node'),
                    Token::textnl('<< SelectionSet.map List.concat'),
                    Token::textnlpop('<< SelectionSet.list'),
                    Token::pop(),
                );
                //query*<n> : <args> -> Maybe String -> Maybe Int
                //      -> List (SelectionSet (List Response) Api.Object.*_Pagination)
                //      -> SelectionSet (List Response) Api.Object.*_Static
                foreach ($type->getAccess() as $query) {
                    if (!$query->isSearchQuery())
                        continue;
                    if ($query->getSecurity()->isExclude($build, 'elm'))
                        continue;
                    $result []= Token::multi(
                        Token::nl(),
                        Token::text('query'),
                        Token::text(\ucfirst($type->getName())),
                        Token::text(\ucfirst($query->getName())),
                        Token::text(' : '),
                        Token::array(array_map(function ($name) use ($query) {
                            return Token::multi(
                                $this->getElmValueType(
                                    $query->getInputVarType($name),
                                    $query->isInputVarArray($name),
                                    false
                                ),
                                Token::text(' -> '),
                            );
                        }, $query->getInputVarNames())),
                        Token::array(array_map(function ($name) use ($query) {
                            return Token::multi(
                                $query->isInputObjArray($name)
                                    ? Token::text('List ')
                                    : Token::text(''),
                                Token::text('Types.'),
                                Token::text(\ucfirst($query->getInputObjTarget($name))),
                                Token::text('Id -> '),
                            );
                        }, $query->getInputObjNames())),
                        $query->isLimitFirst()
                            ? Token::text('')
                            : Token::text('Maybe String -> Maybe Int -> '),
                        Token::text('List (SelectionSet (List Response) Api.'),
                        $query->isLimitFirst()
                            ? Token::multi(
                                Token::text('Interface.'),
                                Token::text(\ucfirst($type->getName())),
                            )
                            : Token::multi(
                                Token::text('Object.'),
                                Token::text(\ucfirst($type->getName())),
                                Token::text('_Pagination')
                            ),
                        Token::text(') -> SelectionSet (List Response) Api.Object.'),
                        Token::text(\ucfirst($type->getName())),
                        Token::textnl('_Static'),
                        Token::text('query'),
                        Token::text(\ucfirst($type->getName())),
                        Token::text(\ucfirst($query->getName())),
                        Token::array(array_map(function ($name) {
                            return Token::multi(
                                Token::text(' '),
                                Token::text(\lcfirst($name)),
                            );
                        }, $query->getInputVarNames())),
                        Token::array(array_map(function ($name) {
                            return Token::multi(
                                Token::text(' '),
                                Token::text(\lcfirst($name)),
                            );
                        }, $query->getInputObjNames())),
                        $query->isLimitFirst()
                            ? Token::text('')
                            : Token::text(' paginationStart paginationCount'),
                        Token::textnlpush(' ='),
                        $query->isLimitFirst()
                            ? Token::multi(
                                Token::textnlpush('SelectionSet.map'),
                                Token::textnlpush('(\result -> case result of'),
                                Token::textnlpush('Just (arg1, arg2) ->'),
                                Token::text('Search'),
                                Token::text(\ucfirst($type->getName())),
                                Token::text(\ucfirst($query->getName())),
                                Token::array(array_map(function($name) {
                                    return Token::multi(
                                        Token::text(' '),
                                        Token::text(\lcfirst($name)),
                                    );
                                }, $query->getInputVarNames())),
                                Token::array(array_map(function($name) {
                                    return Token::multi(
                                        Token::text(' '),
                                        Token::text(\lcfirst($name)),
                                    );
                                }, $query->getInputObjNames())),
                                Token::textnl(' (Just arg1)'),
                                Token::textnlpop(':: arg2'),
                                Token::textnlpush('Nothing ->'),
                                Token::text('[ Search'),
                                Token::text(\ucfirst($type->getName())),
                                Token::text(\ucfirst($query->getName())),
                                Token::array(array_map(function($name) {
                                    return Token::multi(
                                        Token::text(' '),
                                        Token::text(\lcfirst($name)),
                                    );
                                }, $query->getInputVarNames())),
                                Token::array(array_map(function($name) {
                                    return Token::multi(
                                        Token::text(' '),
                                        Token::text(\lcfirst($name)),
                                    );
                                }, $query->getInputObjNames())),
                                Token::textnlpop(' Nothing ]'),
                                Token::pop(),
                                Token::textnl(')'),
                                Token::text('<< '),
                            )
                            : Token::text(''),
                        Token::text('Api'),
                        Token::text(\ucfirst($type->getName())),
                        Token::text('_Static.'),
                        Token::textnl(\lcfirst($query->getName())),
                        $query->isLimitFirst()
                            ? Token::text('')
                            : Token::push(),
                        $query->isLimitFirst()
                            ? Token::text('')
                            : Token::multi(
                                Token::textnlpush('(\optional ->'),
                                Token::textnl('{ optional'),
                                Token::textnlpush('| after = case paginationStart of'),
                                Token::textnl('Just value -> Present value'),
                                Token::textnlpop('Nothing -> Absent'),
                                Token::textnlpush(', first = case paginationCount of'),
                                Token::textnl('Just value -> Present value'),
                                Token::textnlpop('Nothing -> Absent'),
                                Token::textnlpop('}'),
                                Token::textnl(')'),
                            ),
                        count($query->getInputVarNames()) + count($query->getInputObjNames()) == 0
                            ? Token::text('')
                            : Token::multi(
                                Token::text('{ '),
                                Token::array($this->intersperce(
                                    array_merge(
                                        array_map(function ($name) use ($query) {
                                            return Token::multi(
                                                Token::text(\lcfirst($name)),
                                                Token::text(' = '),
                                                $this->getGraphqlType(
                                                    $query->getInputVarType($name),
                                                    $query->isInputVarArray($name),
                                                    Token::text(\lcfirst($name)),
                                                ),
                                            );
                                        }, $query->getInputVarNames()),
                                        array_map(function ($name) use ($query) {
                                            return Token::multi(
                                                Token::text(\lcfirst($name)),
                                                Token::text(' = Api.Scalar.Id <| Types.'),
                                                Token::text(\lcfirst(
                                                    $query->getInputObjTarget($name)
                                                )),
                                                Token::text('Id '),
                                                Token::text(\lcfirst($name)),
                                            );
                                        }, $query->getInputObjNames()),
                                    ),
                                    Token::multi(
                                        Token::nl(),
                                        Token::text(', '),
                                    ),
                                )),
                                Token::nl(),
                                Token::textnl('}'),
                            ),
                        $query->isLimitFirst()
                            ? Token::multi(
                                Token::textnlpush('<< SelectionSet.map2'),
                                Token::textnl('Tuple.pair'),
                                Token::text('Decoder.'),
                                Token::text(\lcfirst($type->getName())),
                                Token::textnlpop('IdDecoder'),
                            )
                            : Token::text(''),
                        Token::textnl('<< SelectionSet.map List.concat'),
                        Token::textnl('<< SelectionSet.list'),
                        $query->isLimitFirst()
                            ? Token::pop()
                            : Token::multi(
                                Token::textnlpush('<< (::)'),
                                Token::textnlpush('(SelectionSet.map'),
                                Token::text('(List.singleton << Search'),
                                Token::text(\ucfirst($type->getName())),
                                Token::text(\ucfirst($query->getName())),
                                Token::array(array_map(function ($name) {
                                    return Token::multi(
                                        Token::text(' '),
                                        Token::text(\lcfirst($name)),
                                    );
                                }, $query->getInputVarNames())),
                                Token::array(array_map(function ($name) {
                                    return Token::multi(
                                        Token::text(' '),
                                        Token::text(\lcfirst($name)),
                                    );
                                }, $query->getInputObjNames())),
                                Token::textnl(')'),
                                Token::text('apiIterate'),
                                Token::textnlpop(\ucfirst($type->getName())),
                                Token::textnlpop(')'),
                                Token::pop(),
                            ),
                        Token::pop(),
                    );
                    if (!$query->isLimitFirst() && $build->getGraphQLNode() !== null)
                        $result []= Token::multi(
                            Token::nl(),
                            Token::text('continueQuery'),
                            Token::text(\ucfirst($type->getName())),
                            Token::text(\ucfirst($query->getName())),
                            Token::textnl(' : Int -> Types.DataBase -> Maybe (SelectionSet (List Response) RootQuery)'),
                            Token::text('continueQuery'),
                            Token::text(\ucfirst($type->getName())),
                            Token::text(\ucfirst($query->getName())),
                            Token::textnlpush(' limit data ='),
                            count($query->getInputVarNames()) + count($query->getInputObjNames()) == 0
                                ? Token::multi(
                                    Token::text('let queryList = case data.static.'),
                                    Token::text(\lcfirst($type->getName())),
                                    Token::text('.'),
                                    Token::text(\lcfirst($query->getName())),
                                    Token::textnlpush(' of'),
                                    Token::push(),
                                    Token::textnlpush('Just v ->'),
                                    Token::textnl('if v.more'),
                                    Token::textnlpush('then case v.last of'),
                                    Token::textnl('Just e -> [ ((), e) ]'),
                                    Token::textnlpop('Nothing -> []'),
                                    Token::textnlpop('else []'),
                                    Token::textnlpop('Nothing -> []'),
                                )
                                : Token::multi(
                                    Token::text('let queryList = data.static.'),
                                    Token::text(\lcfirst($type->getName())),
                                    Token::text('.'),
                                    Token::textnlpush(\lcfirst($query->getName())),
                                    Token::push(),
                                    Token::text('|> '),
                                    Token::array(array_map(function ($name) use ($query) {
                                        return Token::multi(
                                            Token::text($this->getDict(
                                                $query->getInputVarType($name)
                                            )),
                                            Token::textnl('.toList'),
                                            Token::textnlpush('|> List.concatMap'),
                                            Token::text('(\\(arg'),
                                            Token::text(\ucfirst($name)),
                                            Token::text(', v'),
                                            Token::text(\ucfirst($name)),
                                            Token::text(') -> v'),
                                            Token::textnlpush(\ucfirst($name)),
                                            Token::text('|> '),
                                        );
                                    }, $query->getInputVarNames())),
                                    Token::array(array_map(function ($name) use ($query) {
                                        return Token::multi(
                                            Token::textnl('AssocList.toList'),
                                            Token::textnlpush('|> List.concatMap'),
                                            Token::text('(\\(arg'),
                                            Token::text(\ucfirst($name)),
                                            Token::text(', v'),
                                            Token::text(\ucfirst($name)),
                                            Token::text(') -> v'),
                                            Token::textnlpush(\ucfirst($name)),
                                            Token::text('|> '),
                                        );
                                    }, $query->getInputObjNames())),
                                    Token::textnlpush('\\v ->'),
                                    Token::textnl('if v.more'),
                                    Token::textnlpush('then List.singleton <| Tuple.pair'),
                                    Token::text('{ '),
                                    Token::array($this->intersperce(
                                        array_merge(
                                            array_map(function ($name) {
                                                return Token::multi(
                                                    Token::text(\lcfirst($name)),
                                                    Token::text(' = arg'),
                                                    Token::text(\ucfirst($name)),
                                                );
                                            }, $query->getInputVarNames()),
                                            array_map(function ($name) {
                                                return Token::multi(
                                                    Token::text(\lcfirst($name)),
                                                    Token::text(' = arg'),
                                                    Token::text(\ucfirst($name)),
                                                );
                                            }, $query->getInputObjNames()),
                                        ),
                                        Token::multi(
                                            Token::nl(),
                                            Token::text(', '),
                                        )
                                    )),
                                    Token::nl(),
                                    Token::textnl('}'),
                                    Token::textnlpop('v.last'),
                                    Token::textnlpop('else []'),
                                    Token::array(array_map(function ($name) {
                                        return Token::multi(
                                            Token::pop(),
                                            Token::textnlpop(')'),
                                        );
                                    }, $query->getInputVarNames())),
                                    Token::array(array_map(function ($name) {
                                        return Token::multi(
                                            Token::pop(),
                                            Token::textnlpop(')'),
                                        );
                                    }, $query->getInputObjNames())),
                                    Token::textnlpush('|> List.filterMap'),
                                    Token::textnlpush('(\\(k, v) -> Maybe.map'),
                                    Token::textnl('(Tuple.pair k)'),
                                    Token::textnlpop('v'),
                                    Token::textnlpop(')'),
                                    Token::pop(),
                                ),
                            Token::textnlpush('query = SelectionSet.nonNullOrFail'),
                            Token::text('<< Api.Query.'),
                            Token::array(array_map(function ($entry) {
                                if ($entry['root'])
                                    return Token::multi(
                                        isset($entry['member'])
                                            ? Token::multi(
                                                Token::textnl(\lcfirst($entry['member'])),
                                                Token::text('<< '),
                                            )
                                            : Token::text(''),
                                    );
                                return Token::multi(
                                    $entry['maybe']
                                        ? Token::multi(
                                            Token::textnl('SelectionSet.map (Maybe.withDefault [])'),
                                            Token::text('<< '),
                                        )
                                        : Token::text(''),
                                    Token::text('Api.Object.'),
                                    Token::text(\ucfirst($entry['type'])),
                                    Token::text('.'),
                                    isset($entry['member'])
                                        ? Token::multi(
                                            Token::textnl(\lcfirst($entry['member'])),
                                            Token::text('<< '),
                                        )
                                        : Token::text(''),
                                );
                            }, $build->getGraphQLNode())),
                            Token::textnl(\lcfirst($type->getName())),
                            Token::textnl('<< SelectionSet.map List.concat'),
                            Token::textnl('<< SelectionSet.list'),
                            Token::textnlpush('<< List.map'),
                            Token::textnlpush('(\\(arg, key) ->'),
                            Token::text('query'),
                            Token::text(\ucfirst($type->getName())),
                            Token::textnlpush(\ucfirst($query->getName())),
                            Token::array(array_map(function ($name) {
                                return Token::multi(
                                    Token::text('arg.'),
                                    Token::textnl(\lcfirst($name)),
                                );
                            }, $query->getInputVarNames())),
                            Token::array(array_map(function ($name) {
                                return Token::multi(
                                    Token::text('arg.'),
                                    Token::textnl(\lcfirst($name)),
                                );
                            }, $query->getInputObjNames())),
                            Token::textnl('(Just key)'),
                            Token::textnl('(Just limit)'),
                            Token::text('[ node'),
                            Token::textnlpush(\ucfirst($type->getName())),
                            Token::text('[ type'),
                            Token::text(\ucfirst($type->getName())),
                            Token::textnlpop(' ]'),
                            Token::textnlpop(']'),
                            Token::pop(),
                            Token::textnlpop(')'),
                            Token::pop(),
                            Token::pop(),
                            Token::textnlpush('in  if List.isEmpty queryList'),
                            Token::textnl('then Nothing'),
                            Token::textnlpop('else Just <| query queryList'),
                            Token::pop(),
                        );
                }
                //load* : Types.*Id 
                //      -> List (SelectionSet (List Response) Api.Interface.*)
                //      -> SelectionSet (List Response) Api.Object.*_Static
                $result []= Token::multi(
                    Token::nl(),
                    Token::text('load'),
                    Token::text(\ucfirst($type->getName())),
                    Token::text(' : Types.'),
                    Token::text(\ucfirst($type->getName())),
                    Token::text('Id -> List (SelectionSet (List Response) Api.Interface.'),
                    Token::text(\ucfirst($type->getName())),
                    Token::text(') -> SelectionSet (List Response) Api.Object.'),
                    Token::text(\ucfirst($type->getName())),
                    Token::textnl('_Static'),
                    Token::text('load'),
                    Token::text(\ucfirst($type->getName())),
                    Token::textnlpush(' id ='),
                    Token::textnlpush('SelectionSet.map (Maybe.withDefault [])'),
                    Token::text('<< Api'),
                    Token::text(\ucfirst($type->getName())),
                    Token::textnlpush('_Static.load'),
                    Token::text('{ id = Api.Scalar.Id <| Types.'),
                    Token::text(\lcfirst($type->getName())),
                    Token::textnlpop('Id id }'),
                    Token::textnl('<< SelectionSet.map List.concat'),
                    Token::textnlpop('<< SelectionSet.list'),
                    Token::pop(),
                );
                //return
                return $result;
            }, $data->getTypes())),
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
