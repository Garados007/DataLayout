<?php namespace Build\Builder\PhpGraphQL;

use \Build\BuildConfig as Config;
use \Build\Token as Token;
use \Data\DataDefinition as DataDef;
use \Data\Build;
use \Data\Security;

class GraphSchemeBuilder {
    public function buildSchema(Config $config, DataDef $data): Token {
        $mutate = $data->getEnvironment()->getBuild()->getSeparateMutation();
        $paginate = $data->getEnvironment()->getBuild()->getPagination();
        $emptyTypes = (function () use ($data, $mutate) {
            $list = array();
            $build = $data->getEnvironment()->getBuild();
            foreach ($data->getTypes() as $type) {
                $instRead = 0;
                $instWrite = 0;
                $statRead = 0;
                $statWrite = 0;

                if ($type->getBase() === null)
                    $instRead++;
                if ($type->getCreateSecurity()->isInclude($build, 'php-graphql'))
                    $statWrite++;
                if ($type->getLoadSecurity()->isInclude($build, 'php-graphql'))
                    $statRead++;
                if ($type->getDeleteSecurity()->isInclude($build, 'php-graphql'))
                    $instWrite++;
                foreach ($type->getAttributes() as $attr) {
                    if ($attr->getSecurity()->isInclude($build, 'php-graphql', Security::GET))
                        $instRead++;
                    if ($attr->getSecurity()->isInclude($build, 'php-graphql', Security::SET))
                        $instWrite++;
                }
                foreach ($type->getJoints() as $joint) {
                    if ($joint->getSecurity()->isInclude($build, 'php-graphql', Security::GET))
                        $instRead++;
                    if ($joint->getSecurity()->isInclude($build, 'php-graphql', Security::SET))
                        $instWrite++;
                }
                foreach ($type->getAccess() as $query) {
                    if ($query->isSearchQuery() && $query->getSecurity()->isInclude($build, 'php-graphql'))
                        $statRead++;
                    if ($query->isDeleteQuery() && $query->getSecurity()->isInclude($build, 'php-graphql'))
                        $statWrite++;
                }

                foreach ($data->getTypes() as $type2)
                    foreach ($type2->getJoints() as $joint) 
                        if ($joint->getTarget() == $type->getName()) {
                            if ($joint->getSecurity()->isInclude($build, 'php-graphql', Security::GET))
                                $instRead++;
                        }

                $name = $this->getQlTypeName($data, $type->getName());
                $types = array(
                    $name => $instRead + ($mutate ? 0 : $instWrite),
                    $name . '_Mutatable' => $instWrite,
                    $name . '_Static' => $statRead + ($mutate ? 0 : $statWrite),
                    $name . '_StaticMutator' => $statRead + $statWrite,
                );

                foreach ($types as $key => $value)
                    if ($value == 0)
                        $list []= $key;
            }
            foreach ($data->getTypes() as $type) {
                $name = $this->getQlTypeName($data, $type->getName());

                $countr = 0;
                $countw = 0;

                foreach ($this->getTypesPath($data, $type) as $parent) {
                    $pname = $this->getQlTypeName($data, $parent->getName());

                    if (!in_array($pname, $list))
                        $countr++;
                    if (!in_array($pname . '_Mutatable', $list))
                        $countw++;
                }

                if ($countr == 0)
                    $list[] = $name . '_Type';
                if ($countw + $countr == 0)
                    $list[] = $name . '_Mutator';
            }
            return $list;
        })();
        $redirect = (function () use ($data, $mutate, $emptyTypes) {
            $list = array();
            foreach ($data->getTypes() as $type) {
                $name = $this->getQlTypeName($data, $type->getName());
                $list[$name] = $name;
                $list[$name . '_Type'] = $name . '_Type';
                $list[$name . '_Static'] = 
                    in_array($name . '_Static', $emptyTypes)
                    ? null 
                    : $name . '_Static';
                $list[$name . '_Mutatable'] = 
                    in_array($name . '_Mutatable', $emptyTypes)
                    ? null 
                    : $name . '_Mutatable';
                $list[$name . '_Mutator'] = 
                    in_array($name . '_Mutator', $emptyTypes)
                    ? $name . '_Type'
                    : $name . '_Mutator';
                $list[$name . '_StaticMutator'] =
                    in_array($name . '_StaticMutator', $emptyTypes)
                    ? $list[$name . '_Static']
                    : $name . '_StaticMutator';
            }
            return $list;
        })();
        $token = Token::array(array_map(function ($type) use ($config, $data, $mutate, $paginate, $emptyTypes, $redirect) {
            $name = $this->getQlTypeName($data, $type->getName());
            return Token::multi(
                $this->printTypeDescription($type, true, $mutate),
                Token::text('interface '),
                Token::text($name),
                Token::textnlpush(' {'),
                $this->printTypeHead($type),
                $this->printTypeMember($config, $data, $type, $paginate),
                $mutate 
                    ? Token::text('') 
                    : $this->printSetTypeMember($config, $data, $type, $paginate),
                Token::pop(),
                Token::textnl('}'),
                $this->printTypeDescription($type, false, $mutate),
                Token::text('type '),
                Token::text($name),
                Token::text('_Type implements '),
                Token::array($this->intersperce(array_reduce(
                    $this->getTypesPath($data, $type),
                    function ($list, $type) use ($data, $emptyTypes) {
                        $name = $this->getQlTypeName($data, $type->getName());
                        if (in_array($name, $emptyTypes))
                            return $list;
                        $list [] = Token::text($name);
                        return $list;
                    },
                    array(),
                ), Token::text(' & '))),
                Token::textnlpush(' {'),
                $this->printTypeHead($type),
                Token::array(array_map(function ($type) use ($config, $data, $mutate, $paginate) {
                    if ($mutate)
                        return $this->printTypeMember($config, $data, $type, $paginate);
                    else 
                        return Token::multi(
                            $this->printTypeMember($config, $data, $type, $paginate),
                            $this->printSetTypeMember($config, $data, $type, $paginate)
                        );
                }, $this->getTypesPath($data, $type))),
                Token::pop(),
                Token::textnl('}'),
                in_array($name . '_Static', $emptyTypes)
                    ? Token::text('')
                    : Token::multi(
                        $this->printStaticTypeDescription($type, $mutate),
                        Token::text('type '),
                        Token::text($name),
                        Token::textnlpush('_Static {'),
                        Token::multi(
                            $type->getLoadSecurity()->isInclude($data->getEnvironment()->getBuild(), 'php-graphql')
                                ? Token::multi(
                                    $this->printSingleDescription('load an entry by its id'),
                                    Token::text('load(id: ID!): '),
                                    Token::textnl($name),
                                )
                                : Token::text(''),
                            $this->printSelectQueryDefList($data, $type, $paginate),
                            $mutate 
                                ? Token::text('')
                                : Token::multi(
                                    $this->printCreateDefList($data, $type),
                                    $this->printDeleteQueryDefList($data, $type),
                                ),
                        ),
                        Token::pop(),
                        Token::textnl('}'),
                    ),
                !$mutate
                    ? Token::text('')
                    : Token::multi(
                        in_array($name . '_Mutatable', $emptyTypes)
                            ? Token::text('')
                            : Token::multi(
                                $this->printTypeDescription($type, true, false),
                                Token::text('interface '),
                                Token::text($name),
                                Token::textnlpush('_Mutatable {'),
                                $this->printSetTypeMember($config, $data, $type, $paginate),
                                Token::pop(),
                                Token::textnl('}'),
                            ),
                        $redirect[$name . '_Mutator'] != $name . '_Mutator'
                            ? Token::text('')
                            : Token::multi(
                                $this->printTypeDescription($type, false, false),
                                Token::text('type '),
                                Token::text($name),
                                Token::text('_Mutator implements '),
                                Token::array($this->intersperce(array_reduce(
                                    $this->getTypesPath($data, $type),
                                    function ($list, $type) use ($data, $emptyTypes) {
                                        $name = $this->getQlTypeName($data, $type->getName());
                                        if (!in_array($name, $emptyTypes))
                                            $list []= Token::text($name);
                                        if (!in_array($name . '_Mutatable', $emptyTypes))
                                            $list []= Token::text($name . '_Mutatable');
                                        return $list;
                                    },
                                    array()
                                ), Token::text(' & '))),
                                Token::textnlpush(' {'),
                                $this->printTypeHead($type),
                                Token::array(array_map(function ($type) use ($config, $data, $paginate) {
                                    return Token::multi(
                                        $this->printTypeMember($config, $data, $type, $paginate),
                                        $this->printSetTypeMember($config, $data, $type, $paginate),
                                    );
                                }, $this->getTypesPath($data, $type))),
                                Token::pop(),
                                Token::textnl('}'),
                            ),
                        $redirect[$name . '_StaticMutator'] != $name . '_StaticMutator'
                            ? Token::text('')
                            : Token::multi(
                                $this->printStaticTypeDescription($type, false),
                                Token::text('type '),
                                Token::text($name),
                                Token::textnlpush('_StaticMutator {'),
                                Token::multi(
                                    $type->getLoadSecurity()->isInclude($data->getEnvironment()->getBuild(), 'php-graphql')
                                        ? Token::multi(
                                            $this->printSingleDescription('load an entry by its id'),
                                            Token::text('load(id: ID!): '),
                                            Token::textnl($name),
                                        )
                                        : Token::text(''),
                                    $this->printCreateDefList($data, $type),
                                    $this->printSelectQueryDefList($data, $type, $paginate),
                                    $this->printDeleteQueryDefList($data, $type),
                                ),
                                Token::pop(),
                                Token::textnl('}'),
                            ),
                    ),
                $paginate == 'none'
                    ? Token::text('')
                    : Token::multi(
                        Token::textnlpush('"""'),
                        Token::text('The Pagination object that return a list of '),
                        Token::text($name),
                        Token::textnlpop('.'),
                        Token::textnl('"""'),
                        Token::text('type '),
                        Token::text($name),
                        Token::textnlpush('_Pagination {'),
                        $this->printSingleDescription('A list of edges that contains the result values'),
                        Token::text('edges: ['),
                        Token::text($name),
                        Token::textnl('_Edge!]!'),
                        $this->printSingleDescription('The information about the current result page.'),
                        Token::textnlpop('pageInfo: PageInfo!'),
                        Token::textnl('}'),
                        Token::textnlpush('"""'),
                        Token::text('The Pagination Edge that contains a single entry of '),
                        Token::text($name),
                        Token::textnlpop('.'),
                        Token::textnl('"""'),
                        Token::text('type '),
                        Token::text($name),
                        Token::textnlpush('_Edge {'),
                        $this->printSingleDescription('The value of the current edge'),
                        Token::text('node: '),
                        Token::text($name),
                        Token::textnl('!'),
                        $this->printSingleDescription('The cursor to identify the edge in this pagination'),
                        Token::textnlpop('cursor: String!'),
                        Token::textnl('}'),
                    ),
            );
        }, $data->getTypes()));
        if ($paginate != 'none') 
            $token = Token::multi(
                $token,
                Token::textnlpush('"""'),
                Token::text('The Pagination info that contains information abaut '),
                Token::textnlpop('current requested page.'),
                Token::textnl('"""'),
                Token::textnlpush('type PageInfo {'),
                $this->printSingleDescription('the last returned cursor of this selection'),
                Token::textnl('endCursor: String'),
                $this->printSingleDescription('determines if more pages exists'),
                Token::textnlpop('hasNextPage: Boolean!'),
                Token::textnl('}'),
            );
        if ($data->getEnvironment()->getBuild()->getClassLoaderType() !== null || $data->getEnvironment()->getBuild()->getStandalone())
            $token = Token::multi(
                $token,
                Token::textnlpush('"""'),
                Token::textnl('Give access to all static members of the db types.'),
                $mutate 
                    ? Token::textnl('This access is readonly.')
                    : Token::textnl('Using this access you can edit members.'),
                Token::pop(),
                Token::textnl('"""'),
                Token::text('type '),
                Token::text($this->getQlTypeName($data, $data->getEnvironment()->getBuild()->getClassLoaderType() ?: 'Query')),
                Token::textnlpush(' {'),
                Token::array(array_map(function ($type) use ($data, $redirect) {
                    $name = $this->getQlTypeName($data, $type->getName());
                    $target = $redirect[$name . '_Static'];
                    if ($target === null)
                        return null;
                    return Token::multi(
                        $this->printMultiDescription(
                            Token::text('Access to the static members of the type '),
                            Token::textnl($type->getName()),
                        ),
                        Token::text(\lcfirst($type->getName())),
                        Token::text(': '),
                        Token::textnl($target),
                    );
                }, $data->getTypes())),
                Token::pop(),
                Token::textnl('}'),
                !$mutate
                    ? Token::text('')
                    : Token::multi(
                        Token::textnlpush('"""'),
                        Token::textnl('Give access to all static members of the db types.'),
                        Token::textnlpop('Using this access you can edit members.'),
                        Token::textnl('"""'),
                        Token::text('type '),
                        Token::text($this->getQlTypeName($data, $data->getEnvironment()->getBuild()->getClassLoaderType() ?: 'Query')),
                        Token::textnlpush('_Mutator {'),
                        Token::array(array_map(function ($type) use ($data, $redirect) {
                            $name = $this->getQlTypeName($data, $type->getName());
                            $target = $redirect[$name . '_StaticMutator'];
                            if ($target === null)
                                return null;
                            return Token::multi(
                                $this->printMultiDescription(
                                    Token::text('Access to the static members of the type '),
                                    Token::textnl($type->getName()),
                                ),
                                Token::text(\lcfirst($type->getName())),
                                Token::text(': '),
                                Token::textnl($target),
                            );
                        }, $data->getTypes())),
                        Token::pop(),
                        Token::textnl('}'),
                    ),
            );
        if ($data->getEnvironment()->getBuild()->getStandalone())
            $token = Token::multi(
                $token,
                Token::textnlpush('schema {'),
                Token::text('query: '),
                Token::textnl($this->getQlTypeName($data, $data->getEnvironment()->getBuild()->getClassLoaderType() ?: 'Query')),
                Token::text('mutation: '),
                Token::text($this->getQlTypeName($data, $data->getEnvironment()->getBuild()->getClassLoaderType() ?: 'Query')),
                $mutate 
                    ? Token::text('_Mutator')
                    : Token::text(''),
                Token::textnlpop(''),
                Token::textnl('}'),
            );
        return $token;
    }

    private function printMultiDescription(Token ... $tokens): Token {
        return Token::multi(
            Token::textnlpush('"""'),
            Token::array($tokens),
            Token::pop(),
            Token::textnl('"""'),
        );
    }

    private function printSingleDescription(string $comment): Token {
        return Token::multi(
            Token::textnlpush('"""'),
            Token::textnlpop($comment),
            Token::textnl('"""'),
        );
    }

    private function printTypeDescription(\Data\Type $type, bool $interface, bool $readonly): Token {
        return Token::multi(
            Token::textnlpush('"""'),
            $interface 
                ? Token::text('The autogenerated interface of the type ')
                : Token::text('The autogenerated type '),
            Token::text($type->getName()),
            Token::textnl('.'),
            $readonly
                ? Token::text('This definition is only for selecting data.')
                : Token::text('This definition can also edit the data fields.'),
            Token::textnlpop(''),
            Token::textnl('"""'),
        );
    }
    
    private function printStaticTypeDescription(\Data\Type $type, bool $readonly): Token {
        return Token::multi(
            Token::textnlpush('"""'),
            Token::text('The autogenerated type '),
            Token::text($type->getName()),
            Token::textnl(' for access of the static content.'),
            $readonly
                ? Token::text('This definition is only for selecting data.')
                : Token::text('This definition can also edit the data fields.'),
            Token::textnlpop(''),
            Token::textnl('"""'),
        );
    }

    private function printTypeHead(\Data\Type $type): Token {
        return Token::multi(
            $this->printSingleDescription('The unique id of this entry'),
            Token::textnl('id: ID!'),
        );
    }

    private function getTypesPath(DataDef $data, \Data\Type $type): array {
        $result = [];
        while ($type !== null) {
            array_unshift($result, $type);
            if ($type->getBase() !== null)
                $type = $data->getType($type->getBase());
            else $type = null;
        }
        return $result;
    }

    private function printTypeMember(Config $config, DataDef $data, \Data\Type $type, string $paginate): Token {
        return Token::multi(
            $this->printAttributeDefList($data->getEnvironment()->getBuild(), $type->getAttributes()),
            $this->printJointDefList($data, $type->getJoints()),
            $this->printReverseJointDefList($data, $type, $paginate)
        );
    }

    private function printSetTypeMember(Config $config, DataDef $data, \Data\Type $type, string $paginate): Token {
        return Token::multi(
            $type->getBase() === null && $type->getDeleteSecurity()->isInclude($data->getEnvironment()->getBuild(), 'php-graphql')
                ? Token::multi(
                    $this->printSingleDescription('deletes this entry'),
                    Token::textnl('delete: Boolean!'),
                )
                : Token::text(''),
            $this->printSetAttributeDefList($data->getEnvironment()->getBuild(), $type->getAttributes()),
            $this->printSetJointDefList($data, $type->getJoints()),
        );
    }

    private function getQlTypeName(DataDef $data, string $name): string {
        $name = $data->getEnvironment()->getBuild()->getClassPrefix() . $name;
        return \ucfirst($name);
    }

    private function printAttributeDefList(Build $build, array $attrs): Token {
        return Token::array(array_map(function ($attr) use ($build) {
            if ($attr->getSecurity()->isExclude($build, 'php-graphql', Security::GET))
                return Token::text('');
            return Token::multi(
                $this->printMultiDescription(
                    Token::text('Attribute '),
                    Token::text($attr->getName()),
                    Token::text(' of type '),
                    Token::textnl($attr->getType()),
                ),
                Token::text(\lcfirst($attr->getName())),
                Token::text(': '),
                Token::text($this->getGraphqlType($attr->getType())),
                $attr->getOptional()
                    ? Token::text('')
                    : Token::text('!'),
                Token::nl()
            );
        }, $attrs));
    }

    private function printSetAttributeDefList(Build $build, array $attrs): Token {
        return Token::array(array_map(function ($attr) use ($build) {
            if ($attr->getSecurity()->isExclude($build, 'php-graphql', Security::SET))
                return Token::text('');
            return Token::multi(
                $this->printMultiDescription(
                    Token::text('Change the value of attribute '),
                    Token::text($attr->getName()),
                    Token::text(' of type '),
                    Token::textnl($attr->getType()),
                ),
                Token::text('set'),
                Token::text(\ucfirst($attr->getName())),
                Token::text('('),
                Token::text(\lcfirst($attr->getName())),
                Token::text(': '),
                Token::text($this->getGraphqlType($attr->getType())),
                $attr->getOptional()
                    ? Token::text('')
                    : Token::text('!'),
                Token::text('): '),
                Token::text($this->getGraphqlType($attr->getType())),
                $attr->getOptional()
                    ? Token::text('')
                    : Token::text('!'),
                Token::nl()
            );
        }, $attrs));
    }

    private function printJointDefList(DataDef $data, array $joints): Token {
        return Token::array(array_map(function ($joint) use ($data) {
            if ($joint->getSecurity()->isExclude($data->getEnvironment()->getBuild(), 'php-graphql', Security::GET))
                return Token::text('');
            return Token::multi(
                $this->printMultiDescription(
                    Token::text('Joint '),
                    Token::text($joint->getName()),
                    Token::text(' pointing to '),
                    Token::textnl($joint->getTarget()),
                ),
                Token::text(\lcfirst($joint->getName())),
                Token::text(': '),
                Token::text($this->getQlTypeName($data, $joint->getTarget())),
                $joint->getRequired()
                    ? Token::text('!')
                    : Token::text(''),
                Token::nl()
            );
        }, $joints));
    }
    
    private function printSetJointDefList(DataDef $data, array $joints): Token {
        return Token::array(array_map(function ($joint) use ($data) {
            if ($joint->getSecurity()->isExclude($data->getEnvironment()->getBuild(), 'php-graphql', Security::SET))
                return Token::text('');
            return Token::multi(
                $this->printMultiDescription(
                    Token::text('Change Joint '),
                    Token::text($joint->getName()),
                    Token::text(' pointing to '),
                    Token::textnl($joint->getTarget()),
                ),
                Token::text('set'),
                Token::text(\ucfirst($joint->getName())),
                Token::text('('),
                Token::text(\lcfirst($joint->getName())),
                Token::text(': ID'),
                $joint->getRequired()
                    ? Token::text('!')
                    : Token::text(''),
                Token::text('): '),
                Token::text($this->getQlTypeName($data, $joint->getTarget())),
                $joint->getRequired()
                    ? Token::text('!')
                    : Token::text(''),
                Token::nl()
            );
        }, $joints));
    }

    private function printReverseJointDefList(DataDef $data, \Data\Type $type, string $paginate): Token {
        $tokens = array();
        foreach ($data->getTypes() as $t)
            foreach ($t->getJoints() as $joint) 
                if ($joint->getTarget() == $type->getName()) {
                    if ($joint->getSecurity()->isExclude($data->getEnvironment()->getBuild(), 'php-graphql', Security::GET))
                        continue;
                    $tokens []= Token::multi(
                        $this->printMultiDescription(
                            Token::text('Get all '),
                            Token::text($t->getName()),
                            Token::text(' which '),
                            Token::text($joint->getName()),
                            Token::textnl(' points to this entry'),
                        ),
                        Token::text('from'),
                        Token::text(\ucfirst($t->getName())),
                        Token::text(\ucfirst($joint->getName())),
                        \in_array($paginate, ['types', 'exceptQuery', 'full'])
                            ? Token::multi(
                                Token::text('(first: Int, after: String): '),
                                Token::text($this->getQlTypeName($data, $t->getName())),
                                Token::textnl('_Pagination!'),
                            )
                            : Token::multi(
                                Token::text(": ["),
                                Token::text($this->getQlTypeName($data, $t->getName())),
                                Token::textnl('!]!'),
                            ),
                    );
                }
        return Token::array($tokens);
    }

    private function printCreateDefList(DataDef $data, \Data\Type $type): Token {
        $build = $data->getEnvironment()->getBuild();
        if ($type->getCreateSecurity()->isExclude($build, 'php-graphql', Security::CREATE))
            return Token::text('');
        $args = array();
        foreach ($this->getTypesPath($data, $type) as $stype) {
            foreach ($stype->getAttributes() as $attr) {
                if ($attr->getSecurity()->isExclude($build, 'php-graphql', Security::CREATE))
                    continue;
                $args []= Token::multi(
                    Token::text(\lcfirst($attr->getName())),
                    Token::text(': '),
                    Token::text($this->getGraphqlType($attr->getType())),
                    $attr->getOptional() || $attr->hasDefault()
                        ? Token::text('')
                        : Token::text('!'),
                );
            }
            foreach ($stype->getJoints() as $joint) {
                if ($joint->getSecurity()->isExclude($build, 'php-graphql', Security::CREATE))
                    continue;
                $args []= Token::multi(
                    Token::text(\lcfirst($joint->getName())),
                    Token::text(': ID'),
                    $joint->getRequired()
                        ? Token::text('!')
                        : Token::text(''),
                );
            }
        }
        return Token::multi(
            $this->printMultiDescription(
                Token::text('Create a new '),
                Token::textnl($type->getName())
            ),
            Token::text('create'),
            count($args) == 0
                ? Token::text('')
                : Token::multi(
                    Token::text('('),
                    Token::array($this->intersperce($args, Token::text(', '))),
                    Token::text(')'),
                ),
            Token::text(': '),
            Token::text($this->getQlTypeName($data, $type->getName())),
            Token::textnl('!')
        );
    }

    private function printSelectQueryDefList(DataDef $data, \Data\Type $type, string $paginate): Token {
        return Token::array(array_map(function ($query) use ($data, $type, $paginate) {
            if (!$query->isSearchQuery())
                return null;
            if ($query->getSecurity()->isExclude($data->getEnvironment()->getBuild(), 'php-graphql'))
                return null;
            $namefy = function ($name, $array) use ($data) {
                $name = Token::text($this->getQlTypeName($data, $name));
                if ($array)
                    return Token::multi(
                        Token::text('['),
                        $name,
                        Token::text('!]!')
                    );
                else 
                    return Token::multi(
                        $name,
                        Token::text('!')
                    );
            };
            $args = $this->intersperce(array_merge(
                array_map(function ($attr) use ($query, $namefy) {
                    return Token::multi(
                        Token::text($attr),
                        Token::text(': '),
                        $namefy(
                            $this->getGraphqlType(
                                $query->getInputVarType($attr)
                            ), 
                            $query->isInputVarArray($attr)
                        ),
                    );
                }, $query->getInputVarNames()),
                array_map(function ($joint) use ($query, $namefy, $data) {
                    return Token::multi(
                        Token::text($joint),
                        Token::text(': '),
                        $namefy(
                            'ID',
                            $query->isInputObjArray($joint)
                        ),
                    );
                }, $query->getInputObjNames()),
                $paginate == 'full' && !$query->isLimitFirst()
                    ? array(
                        Token::text('first: Int'),
                        Token::text('after: String')
                    )
                    : array(),
            ), Token::text(', '));
            return Token::multi(
                $this->printSingleDescription('Select all entrys that match a specific condition'),
                Token::text(lcfirst($query->getName())),
                count($args) === 0 
                    ? Token::text('')
                    : Token::multi(
                        Token::text('('),
                        Token::array($args),
                        Token::text(')')
                    ),
                Token::text(': '),
                $query->isLimitFirst()
                    ? Token::multi(
                        Token::text($this->getQlTypeName($data, $type->getName()))
                    )
                    : ($paginate == 'full'
                        ? Token::multi(
                            Token::text($this->getQlTypeName($data, $type->getName())),
                            Token::text('_Pagination!')
                        )
                        : Token::multi(
                            Token::text('['),
                            Token::text($this->getQlTypeName($data, $type->getName())),
                            Token::text('!]!')
                        )
                    ),
                Token::nl(),
            );
        }, $type->getAccess()));
    }

    private function printDeleteQueryDefList(DataDef $data, \Data\Type $type): Token {
        return Token::array(array_map(function ($query) use ($data, $type) {
            if (!$query->isDeleteQuery())
                return null;
            if ($query->getSecurity()->isExclude($data->getEnvironment()->getBuild(), 'php-graphql'))
                return null;
            $namefy = function ($name, $array) use ($data) {
                $name = Token::text($this->getQlTypeName($data, $name));
                if ($array)
                    return Token::multi(
                        Token::text('['),
                        $name,
                        Token::text('!]!')
                    );
                else 
                    return Token::multi(
                        $name,
                        Token::text('!')
                    );
            };
            $args = $this->intersperce(array_merge(
                array_map(function ($attr) use ($query, $namefy) {
                    return Token::multi(
                        Token::text($attr),
                        Token::text(': '),
                        $namefy(
                            $this->getGraphqlType(
                                $query->getInputVarType($attr)
                            ), 
                            $query->isInputVarArray($attr)
                        ),
                    );
                }, $query->getInputVarNames()),
                array_map(function ($joint) use ($query, $namefy, $data) {
                    return Token::multi(
                        Token::text($joint),
                        Token::text(': '),
                        $namefy(
                            'ID',
                            $query->isInputObjArray($joint)
                        ),
                    );
                }, $query->getInputObjNames())
            ), Token::text(', '));
            return Token::multi(
                $this->printSingleDescription('Deletes all entrys that match a certain condition'),
                Token::text(lcfirst($query->getName())),
                count($args) === 0 
                    ? Token::text('')
                    : Token::multi(
                        Token::text('('),
                        Token::array($args),
                        Token::text(')')
                    ),
                Token::text(': Boolean!'),
                Token::nl(),
            );
        }, $type->getAccess()));
    }

    private function getGraphqlType(string $type): string {
        switch ($type) {
            case 'bool': return 'Boolean';
            case 'byte':
            case 'short':
            case 'int':
            case 'long':
            case 'sbyte':
            case 'ushort':
            case 'uint':
            case 'ulong': return 'Int';
            case 'float':
            case 'double': return 'Float';
            case 'string': return 'String';
            case 'bytes': return 'String';
            case 'date': return 'String';
            case 'json': return 'String';
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