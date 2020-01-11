<?php namespace Build\Builder\PhpGraphQL;

use \Build\BuildConfig as Config;
use \Build\Token as Token;
use \Data\DataDefinition as DataDef;

class PermissionBuilder {
    public function buildPermission(Config $config, DataDef $data): Token {
        $ns = $data->getEnvironment()->getBuild()->getDbClassNamespace();
        $ns = $ns === null ? '' : '\\' . $ns . '\\';
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
            Token::text('abstract class Permission {'),
            Token::push(),
            Token::array(array_map(function ($type) use ($config, $data, $ns) {
                return Token::multi(
                    Token::nl(),
                    Token::text('public function check'),
                    Token::text(\ucfirst($type->getName())),
                    Token::text('('),
                    Token::text($ns),
                    Token::text($type->getName()),
                    Token::textnlpush(' $value): bool {'),
                    Token::textnlpop('return true;'),
                    Token::textnl('}'),
                );
            }, $data->getTypes())),
            Token::pop(),
            Token::textnl('}'),
        );
    }
}