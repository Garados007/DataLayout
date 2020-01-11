<?php namespace Build\Builder\PhpGraphQL;

use \Build\BuildConfig as Config;
use \Build\Token as Token;
use \Data\DataDefinition as DataDef;

class ResolveAttacher {
    public function buildResolver(Config $config, DataDef $data): Token {
        $mutate = $data->getEnvironment()->getBuild()->getSeparateMutation();
        return Token::multi(
            Token::text('<?php'),
            $data->getEnvironment()->getBuild()->getClassNamespace() === null 
                ? Token::nl()
                : Token::multi(
                    Token::text(' namespace '),
                    Token::text($data->getEnvironment()->getBuild()->getClassNamespace()),
                    Token::textnl(';')
                ),
            Token::nl(),
            Token::textnlpush('class TypeResolver {'),
            Token::textnl('private static $saveBuffer = array();'),
            Token::nl(),
            Token::textnlpush('public static function flushSaveBuffer() {'),
            Token::textnlpush('foreach (self::$saveBuffer as $typename => $list) '),
            Token::textnlpush('foreach ($list as $id => $entrys)'),
            Token::textnlpush('if ($id === null) {'),
            Token::textnlpush('foreach ($entrys as $entry)'),
            Token::textnlpop('$entry->save();'),
            Token::pop(),
            Token::textnl('}'),
            Token::textnlpush('else {'),
            Token::text('$value = ${!${\'\'} = \''),
            $data->getEnvironment()->getBuild()->getDbClassNamespace() === null
                ? Token::text('')
                : Token::multi(
                    Token::text('\\\\'),
                    Token::text(addslashes($data->getEnvironment()->getBuild()->getDbClassNamespace())),
                    Token::text('\\\\'),
                ),
            Token::textnl('\' . $typename}::load($id);'),
            Token::textnlpush('foreach ($entrys as $key => $entry)'),
            Token::textnlpop('$value->{!${\'\'} = \'set\' . \\ucfirst($key)}($entry);'),
            Token::textnlpop('$value->save();'),
            Token::textnlpop('}'),
            Token::pop(),
            Token::textnlpop('self::$saveBuffer = array();'),
            Token::textnl('}'),
            Token::nl(),
            $data->getEnvironment()->getBuild()->getInternalPermissionChecks()
                ? Token::multi(
                    Token::textnl('private static $permission = null;'),
                    Token::nl(),
                    Token::text('public static function setPermission(?'),
                    $data->getEnvironment()->getBuild()->getClassNamespace()
                        ? Token::multi(
                            Token::text('\\'),
                            Token::text($data->getEnvironment()->getBuild()->getClassNamespace()),
                            Token::text('\\'),
                        )
                        : Token::text(''),
                    Token::textnlpush('Permission $permission) {'),
                    Token::textnlpop('self::$permission = $permission;'),
                    Token::textnl('}'),
                    Token::nl(),
                    Token::textnlpush('private static function verify($object, bool $throw = true) {'),
                    Token::textnlpush('if ($object === null || self::$permission === null)'),
                    Token::textnlpop('return $object;'),
                    Token::textnl('$method = \'check\' . \\ucfirst($object->get_Type());'),
                    Token::textnlpush('if (self::$permission->{$method}($object))'),
                    Token::textnlpop('return $object;'),
                    Token::textnlpush('elseif ($throw)'),
                    Token::textnlpop('throw \\Exception(\'no permission to access this object\');'),
                    Token::textnlpop('else return null;'),
                    Token::textnl('}'),
                    Token::nl()
                )
                : Token::multi(
                    Token::textnlpush('private static function verify($object, bool $throw = true) {'),
                    Token::textnlpop('return $object;'),
                    Token::textnl('}'),
                    Token::nl()
                ),
            Token::textnl('/**'),
            Token::textnl(' * Attach the field resolvers if possible.'),
            Token::textnl(' * The return is null if no resolvers could be attached to this type.'),
            Token::textnl(' * @param mixed[] $config'),
            Token::textnl(' * @param \\GraphQL\\Language\\AST\\Node $typeNode'),
            Token::textnl(' * @param \\GraphQL\\Language\\AST\\Node[] $typeMap'),
            Token::textnl(' * @return mixed[]|null'),
            Token::textnl(' */'),
            Token::textnlpush('public static function attachResolver(array $config, \\GraphQL\\Language\\AST\\Node $typeNode, array $typeMap): ?array {'),
            Token::textnlpush('switch ($config[\'name\']) {'),
            Token::array(array_map(function ($type) use ($config, $data, $mutate) {
                return Token::multi(
                    Token::text('case \''),
                    Token::text($this->getQlTypeName($data, $type->getName())),
                    Token::text('\': return self::resolve'),
                    Token::text($this->getQlTypeName($data, $type->getName())),
                    Token::textnl('($config, $typeNode, $typeMap);'),
                    Token::text('case \''),
                    Token::text($this->getQlTypeName($data, $type->getName())),
                    Token::text('_Type\': return self::resolve'),
                    Token::text($this->getQlTypeName($data, $type->getName())),
                    Token::textnl('_Type($config, $typeNode, $typeMap);'),
                    Token::text('case \''),
                    Token::text($this->getQlTypeName($data, $type->getName())),
                    Token::text('_Static\': return self::resolve'),
                    Token::text($this->getQlTypeName($data, $type->getName())),
                    Token::textnl('_Static($config, $typeNode, $typeMap);'),
                    $mutate
                        ? Token::multi(
                            Token::text('case \''),
                            Token::text($this->getQlTypeName($data, $type->getName())),
                            Token::text('_Mutatable\': return self::resolve'),
                            Token::text($this->getQlTypeName($data, $type->getName())),
                            Token::textnl('_Mutatable($config, $typeNode, $typeMap);'),
                            Token::text('case \''),
                            Token::text($this->getQlTypeName($data, $type->getName())),
                            Token::text('_Mutator\': return self::resolve'),
                            Token::text($this->getQlTypeName($data, $type->getName())),
                            Token::textnl('_Mutator($config, $typeNode, $typeMap);'),
                            Token::text('case \''),
                            Token::text($this->getQlTypeName($data, $type->getName())),
                            Token::text('_StaticMutator\': return self::resolve'),
                            Token::text($this->getQlTypeName($data, $type->getName())),
                            Token::textnl('_StaticMutator($config, $typeNode, $typeMap);'),
                        )
                        : Token::text(''),
                    Token::text('case \''),
                    Token::text($this->getQlTypeName($data, $type->getName())),
                    Token::text('_Pagination\': return self::resolve'),
                    Token::text($this->getQlTypeName($data, $type->getName())),
                    Token::textnl('_Pagination($config, $typeNode, $typeMap);'),
                    Token::text('case \''),
                    Token::text($this->getQlTypeName($data, $type->getName())),
                    Token::text('_Edge\': return self::resolve'),
                    Token::text($this->getQlTypeName($data, $type->getName())),
                    Token::textnl('_Edge($config, $typeNode, $typeMap);'),
                );
            }, $data->getTypes())),
            Token::textnl('case \'PageInfo\': return self::resolvePageInfo($config, $typeNode, $typeMap);'),
            $data->getEnvironment()->getBuild()->getClassLoaderType() !== null 
                || $data->getEnvironment()->getBuild()->getStandalone()
                ? Token::multi(
                    Token::multi(
                        Token::text('case \''),
                        Token::text($this->getQlTypeName($data, $data->getEnvironment()->getBuild()->getClassLoaderType() ?: 'Query')),
                        Token::textnl('\': return self::resolve_Query($config, $typeNode, $typeMap);'),
                    ),
                    $mutate
                        ? Token::multi(
                            Token::text('case \''),
                            Token::text($this->getQlTypeName($data, $data->getEnvironment()->getBuild()->getClassLoaderType() ?: 'Query')),
                            Token::textnl('_Mutator\': return self::resolve_QueryMutator($config, $typeNode, $typeMap);'),
                        )
                        : Token::text('')
                )
                : Token::text(''),
            Token::textnlpop('default: return null;'),
            Token::textnlpop('}'),
            Token::textnl('}'),
            Token::array(array_map(function ($type) use ($config, $data, $mutate) {
                return Token::multi(
                    $this->buildTypeResolverInterface($data, $type, false, !$mutate),
                    $this->buildTypeResolverType($data, $type, false, !$mutate),
                    $this->buildTypeResolverStatic($data, $type, false, !$mutate),
                    $mutate 
                        ? Token::multi(
                            $this->buildTypeResolverInterface($data, $type, true, true),
                            $this->buildTypeResolverType($data, $type, true, true),
                            $this->buildTypeResolverStatic($data, $type, true, true),
                        )
                        : Token::text(''),
                    $this->buildIdResolver($data, $type),
                    $this->buildTypePagination($data, $type),
                    $this->buildTypeEdge($data, $type),
                );
            }, $data->getTypes())),
            $this->buildTypePageInfo($data),
            $data->getEnvironment()->getBuild()->getClassLoaderType() !== null || $data->getEnvironment()->getBuild()->getStandalone()
                ? Token::multi(
                    $this->buildQuery($data, false),
                    $mutate
                        ? $this->buildQuery($data, true)
                        : Token::text('')
                )
                : Token::text(''),
            Token::pop(),
            Token::textnl('}'),
        );
    }

    private function getQlTypeName(DataDef $data, string $name): string {
        $name = $data->getEnvironment()->getBuild()->getClassPrefix() . $name;
        return \ucfirst($name);
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

    private function buildTypeResolverInterface(DataDef $data, \Data\Type $type, bool $mutator, bool $mutate): Token {
        return Token::multi(
            Token::nl(),
            Token::text('private static function resolve'),
            Token::text($this->getQlTypeName($data, $type->getName())),
            Token::text($mutator ? '_Mutatable' : ''),
            Token::textnlpush('(array $config, \\GraphQL\\Language\\AST\\Node $typeNode, array $typeMap): array {'),
            $mutator 
                ? Token::multi(
                    Token::text('self::resolve'),
                    Token::text($this->getQlTypeName($data, $type->getName())),
                    Token::textnl('($config, $typeNode, $typeMap);'),
                )
                : $this->buildReadonlyTypeInstance($data, $type),
            $mutate 
                ? $this->buildWriteTypeInstance($data, $type)
                : Token::text(''),
            Token::pop(),
            Token::textnl('}')
        );
    }

    private function buildTypeResolverType(DataDef $data, \Data\Type $type, bool $mutator, bool $mutate): Token {
        return Token::multi(
            Token::nl(),
            Token::text('private static function resolve'),
            Token::text($this->getQlTypeName($data, $type->getName())),
            Token::text($mutator ? '_Mutator' : '_Type'),
            Token::textnlpush('(array $config, \\GraphQL\\Language\\AST\\Node $typeNode, array $typeMap): array {'),
            Token::array(array_map(function ($type) use ($data, $mutator) {
                return Token::multi(
                    Token::text('self::resolve'),
                    Token::text($this->getQlTypeName($data, $type->getName())),
                    Token::text($mutator ? '_Mutatable' : ''),
                    Token::textnl('($config, $typeNode, $typeMap);'),
                );
            }, $this->getTypesPath($data, $type))),
            Token::pop(),
            Token::textnl('}')
        );
    }

    private function buildTypeResolverStatic(DataDef $data, \Data\Type $type, bool $mutator, bool $mutate): Token {
        return Token::multi(
            Token::nl(),
            Token::text('private static function resolve'),
            Token::text($this->getQlTypeName($data, $type->getName())),
            Token::text($mutator ? '_StaticMutator' : '_Static'),
            Token::textnlpush('(array $config, \\GraphQL\\Language\\AST\\Node $typeNode, array $typeMap): array {'),
            $mutator 
                ? Token::multi(
                    Token::text('self::resolve'),
                    Token::text($this->getQlTypeName($data, $type->getName())),
                    Token::textnl('_Static($config, $typeNode, $typeMap);'),
                )
                : $this->buildReadonlyTypeStatic($data, $type),
            $mutate 
                ? $this->buildWriteTypeStatic($data, $type)
                : Token::text(''),
            Token::pop(),
            Token::textnl('}')
        );
    }

    private function buildTypePagination(DataDef $data, \Data\Type $type): Token {
        return Token::multi(
            Token::nl(),
            Token::text('private static function resolve'),
            Token::text($this->getQlTypeName($data, $type->getName())),
            Token::text('_Pagination'),
            Token::textnlpush('(array $config, \\GraphQL\\Language\\AST\\Node $typeNode, array $typeMap): array {'),
            Token::textnlpush('$config[\'fields\'][\'edges\'][\'resolve\'] = function ($value, $args) {'),
            Token::textnlpop('return $value[\'result\'];'),
            Token::textnl('};'),
            Token::textnlpush('$config[\'fields\'][\'pageInfo\'][\'resolve\'] = function ($value, $args) {'),
            Token::textnlpop('return $value;'),
            Token::textnl('};'),
            Token::pop(),
            Token::textnl('}')
        );
    }

    private function buildTypeEdge(DataDef $data, \Data\Type $type): Token {
        return Token::multi(
            Token::nl(),
            Token::text('private static function resolve'),
            Token::text($this->getQlTypeName($data, $type->getName())),
            Token::text('_Edge'),
            Token::textnlpush('(array $config, \\GraphQL\\Language\\AST\\Node $typeNode, array $typeMap): array {'),
            Token::textnlpush('$config[\'fields\'][\'node\'][\'resolve\'] = function ($value, $args) {'),
            Token::textnlpop('return $value;'),
            Token::textnl('};'),
            Token::textnlpush('$config[\'fields\'][\'cursor\'][\'resolve\'] = function ($value, $args) {'),
            Token::textnlpop('return $value->get_Type() . \',\' . (string)$value->getId();'),
            Token::textnl('};'),
            Token::pop(),
            Token::textnl('}')
        );
    }

    private function buildTypePageInfo(DataDef $data): Token {
        return Token::multi(
            Token::nl(),
            Token::text('private static function resolvePageInfo'),
            Token::textnlpush('(array $config, \\GraphQL\\Language\\AST\\Node $typeNode, array $typeMap): array {'),
            Token::textnlpush('$config[\'fields\'][\'node\'][\'resolve\'] = function ($value, $args) {'),
            Token::textnl('$key = array_key_last($value[\'result\']);'),
            Token::textnlpush('if ($key === null)'),
            Token::textnlpop('return null;'),
            Token::textnl('$item = $value[\'result\'][$key];'),
            Token::textnlpop('return $item->get_Type() . \',\' . (string)$item->getId();'),
            Token::textnl('};'),
            Token::textnlpush('$config[\'fields\'][\'hasNextPage\'][\'resolve\'] = function ($value, $args) {'),
            Token::textnlpop('return $value[\'more\'];'),
            Token::textnl('};'),
            Token::pop(),
            Token::textnl('}')
        );
    }

    private function buildQuery(DataDef $data, bool $mutate): Token {
        return Token::multi(
            Token::nl(),
            Token::text('private static function resolve_Query'),
            $mutate
                ? Token::text('Mutator')
                : Token::text(''),
            Token::textnlpush('(array $config, \\GraphQL\\Language\\AST\\Node $typeNode, array $typeMap): array {'),
            Token::array(array_map(function ($type) use ($data, $mutate) {
                return Token::multi(
                    Token::text('$config[\'fields\'][\''),
                    Token::text(\addslashes(\lcfirst($type->getName()))),
                    Token::textnlpush('\'][\'resolve\'] = function ($value, $args) {'),
                    Token::textnlpop('return true;'),
                    Token::textnl('};'),
                );
            }, $data->getTypes())),
            Token::pop(),
            Token::textnl('}')
        );
    }

    private function buildReadonlyTypeInstance(DataDef $data, \Data\Type $type): Token {
        return Token::multi(
            Token::textnl('//readonly instance'),
            $type->getBase() === null 
                ? Token::multi(
                    Token::textnlpush('$config[\'fields\'][\'id\'][\'resolve\'] = function ($value, $args) {'),
                    Token::textnlpush('return $value->getId() === null'),
                    Token::textnl('? null'),
                    Token::text(': \''),
                    Token::text(\addslashes($type->getName())),
                    Token::textnlpop(',\' . $value->getId();'),
                    Token::pop(),
                    Token::textnl('};'),
                )
                : Token::text(''),
            Token::array(array_map(function ($attr) use ($data, $type) {
                return Token::multi(
                    Token::text('$config[\'fields\'][\''),
                    Token::text(\lcfirst(\addslashes($attr->getName()))),
                    Token::textnlpush('\'][\'resolve\'] = function ($value, $args) {'),
                    Token::text('return '),
                    $this->buildOutputConverter(
                        $attr->getType(),
                        Token::multi(
                            Token::text('$value->get'),
                            Token::text(\ucfirst($attr->getName())),
                            Token::text('()'),
                        ),
                        $attr->getOptional(),
                    ),
                    Token::textnlpop(';'),
                    Token::textnl('};'),
                );
            }, $type->getAttributes())),
            Token::array(array_map(function ($joint) use ($data, $type) {
                return Token::multi(
                    Token::text('$config[\'fields\'][\''),
                    Token::text(\lcfirst(\addslashes($joint->getName()))),
                    Token::textnlpush('\'][\'resolve\'] = function ($value, $args) {'),
                    Token::text('return self::verify($value->get'),
                    Token::text(\ucfirst($joint->getName())),
                    Token::textnlpop('());'),
                    Token::textnl('};'),
                );
            }, $type->getJoints())),
            (function () use ($data, $type) {
                $result = array();
                foreach ($data->getTypes() as $t) 
                    foreach ($t->getJoints() as $joint)
                        if ($joint->getTarget() == $type->getName()) 
                            $result []= array(
                                Token::text('$config[\'fields\'][\'from'),
                                Token::text(\ucfirst($t->getName())),
                                Token::text(\ucfirst($joint->getName())),
                                Token::textnlpush('\'][\'resolve\'] = function ($value, $args) {'),
                                \in_array($data->getEnvironment()->getBuild()->getPagination(), ['types', 'exceptQuery', 'full'])
                                    ? Token::multi(
                                        Token::text('$result = $value->from'),
                                        Token::text(\ucfirst($t->getName())),
                                        Token::text(\ucfirst($joint->getName())),
                                        Token::textnlpush('('),
                                        Token::textnl('$args[\'first\'] === null ? null : $args[\'first\'] + 1,'),
                                        Token::textnlpop('$args[\'after\'] === null ? null : (int)$args[\'after\']'),
                                        Token::textnl(');'),
                                        Token::textnlpush('$result = array_reduce($result, function ($carry, $item) {'),
                                        Token::textnlpush('if (self::verify($item, false) !== null)'),
                                        Token::textnlpop('$carry []= $item;'),
                                        Token::textnlpop('return $carry;'),
                                        Token::textnl('}, array());'),
                                        Token::text('$more = $args[\'first\'] !== null && '),
                                        Token::textnl('count($result) > $args[\'first\'];'),
                                        Token::textnl('if ($more) array_pop($result);'),
                                        Token::textnlpush('return array('),
                                        Token::textnl('\'result\' => $result,'),
                                        Token::textnlpop('\'more\' => $more'),
                                        Token::textnlpop(');'),
                                    )
                                    : Token::multi(
                                        Token::textnlpush('return array_reduce('),
                                        Token::text('$value->from'),
                                        Token::text(\ucfirst($t->getName())),
                                        Token::text(\ucfirst($joint->getName())),
                                        Token::textnlpush('('),
                                        Token::textnl('$args[\'first\'],'),
                                        Token::textnlpop('$args[\'after\'] === null ? null : (int)$args[\'after\']'),
                                        Token::textnl('),'),
                                        Token::textnlpush('function ($carry, $item) {'),
                                        Token::textnlpush('if (self::verify($item, false) !== null)'),
                                        Token::textnlpop('$carry []= $item;'),
                                        Token::textnlpop('return $carry;'),
                                        Token::textnl('}, '),
                                        Token::textnlpop('array()'),
                                        Token::textnlpop(');'),
                                    ),
                                Token::textnl('};'),
                            );
                return Token::array($result);
            })()
        );
    }

    private function buildWriteTypeInstance(DataDef $data, \Data\Type $type): Token {
        return Token::multi(
            Token::textnl('//write instance'),
            $type->getBase() === null
                ? Token::multi(
                    Token::textnlpush('$config[\'fields\'][\'delete\'][\'resolve\'] = function ($value, $args) {'),
                    Token::textnlpush('if ($value->getId() === null)'),
                    Token::textnlpop('return false;'),
                    Token::textnl('else $value->delete();'),
                    Token::textnlpop('return true;'),
                    Token::textnl('};'),
                )
                : Token::text(''),
            Token::array(array_map(function ($attr) use ($data, $type) {
                return Token::multi(
                    Token::text('$config[\'fields\'][\'set'),
                    Token::text(\ucfirst(\addslashes($attr->getName()))),
                    Token::textnlpush('\'][\'resolve\'] = function ($value, $args) {'),
                    Token::text('$value->set'),
                    Token::text(\ucfirst($attr->getName())),
                    Token::text('('),
                    $this->buildInputConverter(
                        $attr->getType(),
                        Token::multi(
                            Token::text('$args[\''),
                            Token::text(\addslashes($attr->getName())),
                            Token::text('\']'),
                        ),
                        $attr->getOptional(),
                    ),
                    Token::textnl(');'),
                    Token::textnlpush('if ($value->getId() !== null)'),
                    Token::text('self::$saveBuffer[\''),
                    Token::text(\addslashes($type->getName())),
                    Token::text('\'][$value->getId()][\''),
                    Token::text(addslashes($attr->getName())),
                    Token::text('\'] = $value->get'),
                    Token::text(\ucfirst($attr->getName())),
                    Token::textnlpop('();'),
                    Token::text('elseif (!isset(self::$saveBuffer[\''),
                    Token::text(\addslashes($type->getName())),
                    Token::text('\'][null]) || !in_array($value, self::$saveBuffer[\''),
                    Token::text(\addslashes($type->getName())),
                    Token::textnlpush('\'][null]))'),
                    Token::text('self::$saveBuffer[\''),
                    Token::text(\addslashes($type->getName())),
                    Token::textnlpop('\'][null] []= $value;'),
                    Token::text('return '),
                    $this->buildOutputConverter(
                        $attr->getType(),
                        Token::multi(
                            Token::text('$value->get'),
                            Token::text(\ucfirst($attr->getName())),
                            Token::text('()'),
                        ),
                        $attr->getOptional(),
                    ),
                    Token::textnlpop(';'),
                    Token::textnl('};'),
                );
            }, $type->getAttributes())),
            Token::array(array_map(function ($joint) use ($data, $type) {
                $value = Token::multi(
                    Token::text('$args[\''),
                    Token::text(\addslashes($joint->getName())),
                    Token::text('\']')
                );
                $obj = Token::multi(
                    Token::text('self::idResolve'),
                    Token::text(\ucfirst($joint->getTarget())),
                    Token::text('('),
                    $value,
                    Token::text(')')
                );
                $null = $joint->getRequired()
                    ? $obj
                    : Token::multi(
                        $value,
                        Token::textnlpush(' === null'),
                        Token::textnl('? null'),
                        Token::text(': '),
                        $obj,
                        Token::pop(),
                        Token::nl(),
                    );
                return Token::multi(
                    Token::text('$config[\'fields\'][\''),
                    Token::text(\lcfirst(\addslashes($joint->getName()))),
                    Token::textnlpush('\'][\'resolve\'] = function ($value, $args) {'),
                    Token::text('$value->set'),
                    Token::text(\ucfirst($joint->getName())),
                    Token::text('('),
                    $null,
                    Token::textnl(');'),
                    Token::textnlpush('if ($value->getId() !== null)'),
                    Token::text('self::$saveBuffer[\''),
                    Token::text(\addslashes($type->getName())),
                    Token::text('\'][$value->getId()][\''),
                    Token::text(addslashes($joint->getName())),
                    Token::text('\'] = $value->get'),
                    Token::text(\ucfirst($joint->getName())),
                    Token::textnlpop('();'),
                    Token::text('elseif (!isset(self::$saveBuffer[\''),
                    Token::text(\addslashes($type->getName())),
                    Token::text('\'][null]) || !in_array($value, self::$saveBuffer[\''),
                    Token::text(\addslashes($type->getName())),
                    Token::textnlpush('\'][null]))'),
                    Token::text('self::$saveBuffer[\''),
                    Token::text(\addslashes($type->getName())),
                    Token::textnlpop('\'][null] []= $value;'),
                    Token::text('return $value->get'),
                    Token::text(\ucfirst($joint->getName())),
                    Token::textnlpop('();'),
                    Token::textnl('};'),
                );
            }, $type->getJoints())),
        );
    }
    
    private function buildReadonlyTypeStatic(DataDef $data, \Data\Type $type): Token {
        $ns = $data->getEnvironment()->getBuild()->getDbClassNamespace();
        $ns = $ns === null ? '' : '\\' . $ns . '\\';
        return Token::multi(
            Token::textnl('//readonly static'),
            $type->getBase() === null 
                ? Token::multi(
                    Token::textnlpush('$config[\'fields\'][\'load\'][\'resolve\'] = function ($value, $args) {'),
                    Token::text('return self::verify('),
                    Token::text($ns),
                    Token::text($type->getName()),
                    Token::textnlpush('::load('),
                    Token::text('self::idResolve'),
                    Token::text($type->getName()),
                    Token::textnlpop('($args[\'id\'])'),
                    Token::textnlpop('));'),
                    Token::textnl('};')
                )
                : Token::text(''),
            Token::array(array_map(function ($query) use ($data, $type, $ns) {
                if (!$query->isSearchQuery())
                    return Token::text('');
                if ($query->isLimitFirst())
                    return Token::multi(
                        Token::text('$config[\'fields\'][\''),
                        Token::text(\lcfirst($query->getName())),
                        Token::textnlpush('\'][\'resolve\'] = function ($value, $args) {'),
                        Token::text('return self::verify('),
                        Token::text($ns),
                        Token::text($type->getName()),
                        Token::text('::'),
                        Token::text($query->getName()),
                        Token::text('('),
                        $this->buildQueryArgs($type, $query, false, false),
                        Token::textnlpop('));'),
                        Token::textnl('};'),
                    );
                return Token::multi(
                    Token::text('$config[\'fields\'][\''),
                    Token::text(\lcfirst($query->getName())),
                    Token::textnlpush('\'][\'resolve\'] = function ($value, $args) {'),
                    $data->getEnvironment()->getBuild()->getPagination() == 'full'
                        ? Token::multi(
                            Token::text('$result = '),
                            Token::text($ns),
                            Token::text($type->getName()),
                            Token::text('::'),
                            Token::text($query->getName()),
                            Token::text('('),
                            $this->buildQueryArgs($type, $query, true, true),
                            Token::textnl(');'),
                            Token::textnlpush('$result = array_reduce($result, function ($carry, $item) {'),
                            Token::textnlpush('if (self::verify($item, false) !== null)'),
                            Token::textnlpop('$carry []= $item;'),
                            Token::textnlpop('return $carry;'),
                            Token::textnl('}, array());'),
                            Token::textnl('$more = $args[\'first\'] !== null && count($result) > $args[\'first\'];'),
                            Token::textnl('if ($more) array_pop($result);'),
                            Token::textnlpush('return array('),
                            Token::textnl('\'result\' => $result,'),
                            Token::textnlpop('\'more\' => $more'),
                            Token::textnl(');'),
                        )
                        : Token::multi(
                            Token::textnlpush('return array_reduce('),
                            Token::text($ns),
                            Token::text($type->getName()),
                            Token::text('::'),
                            Token::text($query->getName()),
                            Token::text('('),
                            $this->buildQueryArgs($type, $query, false, false),
                            Token::textnl('),'),
                            Token::textnlpush('function ($carry, $item) {'),
                            Token::textnlpush('if (self::verify($item, false) !== null)'),
                            Token::textnlpop('$carry []= $item;'),
                            Token::textnlpop('return $carry;'),
                            Token::textnl('}, '),
                            Token::textnlpop('array()'),
                            Token::textnl(');'),
                        ),
                    Token::pop(),
                    Token::textnl('};'),
                );
            }, $type->getAccess()))
        );
    }

    private function buildWriteTypeStatic(DataDef $data, \Data\Type $type): Token {
        $ns = $data->getEnvironment()->getBuild()->getDbClassNamespace();
        $ns = $ns === null ? '' : '\\' . $ns . '\\';
        return Token::multi(
            Token::textnl('//write static'),
            Token::array(array_map(function ($query) use ($data, $type, $ns) {
                if (!$query->isDeleteQuery())
                    return Token::text('');
                return Token::multi(
                    Token::text('$config[\'fields\'][\''),
                    Token::text(\lcfirst($query->getName())),
                    Token::textnlpush('\'][\'resolve\'] = function ($value, $args) {'),
                    Token::multi(
                        Token::text($ns),
                        Token::text($type->getName()),
                        Token::text('::'),
                        Token::text($query->getName()),
                        Token::text('('),
                        $this->buildQueryArgs($type, $query, false, false),
                        Token::textnl(');'),
                        Token::textnl('return true;'),
                    ),
                    Token::pop(),
                    Token::textnl('};'),
                );
            }, $type->getAccess()))
        );
    }

    private function buildIdResolver(DataDef $data, \Data\Type $type) : Token {
        $ns = $data->getEnvironment()->getBuild()->getDbClassNamespace();
        $ns = $ns === null ? '' : '\\' . $ns . '\\';
        return Token::multi(
            Token::nl(),
            Token::text('private static function idResolve'),
            Token::text(\ucfirst($type->getName())),
            Token::text('(string $id): '),
            Token::text($ns),
            Token::text($type->getName()),
            Token::textnlpush(' {'),
            Token::textnl('$parts = explode(\',\', $id, 2);'),
            Token::textnlpush('if (count($parts) != 2)'),
            Token::textnlpop('throw new \\Exception(\'invalid id format\');'),
            Token::text('if ($parts[0] != \''),
            Token::text($type->getName()),
            Token::textnlpush('\')'),
            Token::textnlpop('throw new \\Exception(\'unexcepted id source\');'),
            Token::text('$obj = '),
            Token::text($ns),
            Token::text($type->getName()),
            Token::textnl('::load((int)$parts[1]);'),
            Token::textnlpush('if ($obj === null)'),
            Token::textnlpop('throw new \\Exception(\'entry with this id not found\');'),
            Token::textnlpop('return $obj;'),
            Token::textnl('}'),
            );
    }

    private function buildQueryArgs(\Data\Type $type, \Data\Query $query, bool $more, bool $paginate): Token {
        if (count($query->getInputVarNames()) + count($query->getInputObjNames()) == 0)
            return Token::text('');
        return Token::multi(
            Token::textnlpush(''),
            Token::array($this->intersperce(
                array_merge(
                    array_map(function ($name) use ($query) {
                        if ($query->isInputVarArray($name))
                            return Token::multi(
                                Token::textnlpush('array_map(function ($input) {'),
                                Token::text('return '),
                                $this->buildInputConverter(
                                    $query->getInputVarType($name), 
                                    Token::text('$input'),
                                    false
                                ),
                                Token::textnlpop(';'),
                                Token::text('}, $args[\''),
                                Token::text(\addslashes($name)),
                                Token::text('\'])')
                            );
                        else 
                            return $this->buildInputConverter(
                                $query->getInputVarType($name), 
                                Token::multi(
                                    Token::text('$args[\''),
                                    Token::text(\addslashes($name)),
                                    Token::text('\']')
                                ),
                                false
                            );
                    }, $query->getInputVarNames()),
                    array_map(function ($name) use ($query) {
                        if ($query->isInputObjArray($name))
                            return Token::multi(
                                Token::textnlpush('array_map(function ($input) {'),
                                Token::text('return self::idResolve'),
                                Token::text($query->getInputObjTarget($name)),
                                Token::textnlpop('($input);'),
                                Token::text('}, $args[\''),
                                Token::text(\addslashes($name)),
                                Token::text('\'])'),
                            );
                        else
                            return Token::multi(
                                Token::text('self::idResolve'),
                                Token::text($query->getInputObjTarget($name)),
                                Token::text('($args[\''),
                                Token::text(\addslashes($name)),
                                Token::text('\'])'),
                            );
                    }, $query->getInputObjNames()),
                    $query->isSearchQuery() && !$query->isLimitFirst() && $paginate
                        ? array(
                            $more 
                                ? Token::text('$args[\'first\'] === null ? null : $args[\'first\'] + 1')
                                : Token::text('$args[\'first\']'),
                            Token::multi(
                                Token::text('self::idResolve'),
                                Token::text(\ucfirst($type->getName())),
                                Token::text('($args[\'after\'])->getId()')
                            )
                        )
                        : array(),
                ),
                Token::textnl(','),
            )),
            Token::textnlpop(''),
        );
    }

    public function buildOutputConverter(string $type, Token $value, bool $nullable): Token {
        $null = function ($tokens) use ($value, $nullable) {
            return Token::multi(
                $value,
                Token::textnlpush(' === null'),
                Token::textnl('? null'),
                Token::text(': '),
                $tokens,
                Token::pop(),
            );
        };
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
            case 'string': return $value;
            case 'bytes': return $null(Token::multi(
                Token::text('base64_encode('),
                $value,
                Token::text(')')
            ));
            case 'date': return $null(Token::multi(
                Token::text('date('),
                $value,
                Token::text(', \'c\')'),
            ));
            case 'json': return $null(Token::multi(
                Token::text('json_encode('),
                $value,
                Token::text(', JSON_UNESCAPED_UNICODE '),
                Token::text('| JSON_UNESCAPED_SLASHES '),
                Token::text('| JSON_NUMERIC_CHECK)')
            ));
            default: return $value;
        }
    }

    public function buildInputConverter(string $type, Token $value, bool $nullable): Token {
        $null = function ($tokens) use ($value, $nullable) {
            return Token::multi(
                $value,
                Token::textnlpush(' === null'),
                Token::textnl('? null'),
                Token::text(': '),
                $tokens,
                Token::pop(),
            );
        };
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
            case 'string': return $value;
            case 'bytes': return $null(Token::multi(
                Token::text('base64_decode('),
                $value,
                Token::text(')')
            ));
            case 'date': return $null(Token::multi(
                Token::text('strtotime('),
                $value,
                Token::text(')'),
            ));
            case 'json': return $null(Token::multi(
                Token::text('json_decode('),
                $value,
                Token::text(', true)')
            ));
            default: return $value;
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