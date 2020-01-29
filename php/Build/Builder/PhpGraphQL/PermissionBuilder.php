<?php namespace Build\Builder\PhpGraphQL;

use \Build\BuildConfig as Config;
use \Build\Token as Token;
use \Data\DataDefinition as DataDef;

class PermissionBuilder {
    public function buildPermission(Config $config, DataDef $data): Token {
        $ns = $data->getEnvironment()->getBuild()->getDbClassNamespace();
        $ns = $ns === null ? '\\Data\\' : '\\' . $ns . '\\Data\\';
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
            Token::array(array_map(function ($type) use ($ns) {
                if ($type->getName() == 'Permission')
                    return null;
                return Token::multi(
                    Token::text('use '),
                    Token::text($ns),
                    Token::text($type->getName()),
                    Token::textnl(';'),
                );
            }, $data->getTypes())),
            Token::nl(),
            Token::textnl('/**'),
            Token::textnl(' * This abstract class defines the default permission checker.'),
            Token::textnl(' * If you want to restrict the access of members or methods '),
            Token::textnl(' * derive this class and override the handlers.'),
            Token::textnl(' */'),
            Token::text('abstract class Permission {'),
            Token::push(),
            Token::array(array_map(function ($type) use ($config, $data, $ns) {
                $name = $type->getName();
                if ($name == 'Permission')
                    $name = $ns . $name;
                return Token::multi(
                    Token::nl(),
                    Token::textnl('/**'),
                    Token::text(' * Check if the access to an instance of '),
                    Token::text($type->getName()),
                    Token::textnl(' is allowed for the current user.'),
                    Token::text(' * @param '),
                    Token::text($name),
                    Token::textnl(' $value The instance of the object'),
                    Token::textnl(' * @return bool True if the access is granted.'),
                    Token::textnl(' */'),
                    Token::text('public function check'),
                    Token::text(\ucfirst($type->getName())),
                    Token::text('('),
                    Token::text($name),
                    Token::textnlpush(' $value): bool {'),
                    Token::textnlpop('return true;'),
                    Token::textnl('}'),
                    $this->buildCreate($type->getName()),
                    $this->buildDelete($type->getName(), $name),
                    Token::array(array_map(function ($attr) use ($type, $name) {
                        return Token::multi(
                            $this->buildMember($type->getName(), $name, $attr->getName()),
                        );
                    }, $type->getAttributes())),
                    Token::array(array_map(function ($joint) use ($type, $name) {
                        return Token::multi(
                            $this->buildMember($type->getName(), $name, $joint->getName()),
                        );
                    }, $type->getJoints())),
                    Token::array(array_map(function ($query) use ($type) {
                        return $this->buildQuery($type->getName(), $query->getName());
                    }, $type->getAccess())),
                );
            }, $data->getTypes())),
            Token::pop(),
            Token::textnl('}'),
        );
    }
    
    private function buildCreate(string $type): Token {
        return Token::multi(
            Token::nl(),
            Token::textnl('/**'),
            Token::text(' * Check if the current user is permited to create an instance of '),
            Token::text($type),
            Token::textnl(' with the specified arguments.'),
            Token::textnl(' * @param array $value The given arguments'),
            Token::textnl(' * @return bool True if the access is granted.'),
            Token::textnl(' */'),
            Token::text('public function check'),
            Token::text(\ucfirst($type)),
            Token::textnlpush('__Create(array $args): bool {'),
            Token::textnlpop('return true;'),
            Token::textnl('}'),
        );
    }
    
    private function buildDelete(string $type, string $nsType): Token {
        return Token::multi(
            Token::nl(),
            Token::textnl('/**'),
            Token::text(' * Check if the current user is permited to delete an instance of '),
            Token::text($type),
            Token::textnl('.'),
            Token::text(' * @param '),
            Token::text($nsType),
            Token::textnl(' $value The instance of the object'),
            Token::textnl(' * @return bool True if the access is granted.'),
            Token::textnl(' */'),
            Token::text('public function check'),
            Token::text(\ucfirst($type)),
            Token::text('__Delete('),
            Token::text($nsType),
            Token::textnlpush(' $value): bool {'),
            Token::textnlpop('return true;'),
            Token::textnl('}'),
        );
    }

    private function buildMember(string $type, string $nsType, string $member): Token {
        return Token::multi(
            Token::nl(),
            Token::textnl('/**'),
            Token::text(' * Check if the current user is permited to access the member '),
            Token::text($member),
            Token::text(' of the class '),
            Token::text($type),
            Token::textnl('.'),
            Token::text(' * @param '),
            Token::text($nsType),
            Token::textnl(' $value The instance of the object'),
            Token::textnl(' * @param bool $modify True if this call will modify this member'),
            Token::textnl(' * @return bool True if the access is granted.'),
            Token::textnl(' */'),
            Token::text('public function check'),
            Token::text(\ucfirst($type)),
            Token::text('__'),
            Token::text(\ucfirst($member)),
            Token::text('('),
            Token::text($nsType),
            Token::textnlpush(' $value, bool $modify): bool {'),
            Token::textnlpop('return true;'),
            Token::textnl('}'),
        );
    }

    private function buildQuery(string $type, string $member): Token {
        return Token::multi(
            Token::nl(),
            Token::textnl('/**'),
            Token::text(' * Check if the current user is permited to query '),
            Token::text($member),
            Token::text(' of the class '),
            Token::text($type),
            Token::textnl('.'),
            Token::textnl(' * @return bool True if the access is granted.'),
            Token::textnl(' */'),
            Token::text('public function check'),
            Token::text(\ucfirst($type)),
            Token::text('__'),
            Token::text(\ucfirst($member)),
            Token::textnlpush('(): bool {'),
            Token::textnlpop('return true;'),
            Token::textnl('}'),
        );
    }
}