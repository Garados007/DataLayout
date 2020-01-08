<?php namespace Build\Builder\Php;

require_once __DIR__ . '/../../../Data/DataDefinition.php';
require_once __DIR__ . '/../../BuildConfig.php';
require_once __DIR__ . '/../../Token.php';

use \Build\BuildConfig as Config;
use \Build\Token as Token;
use \Data\DataDefinition as DataDef;

class EnvBuilder {
    public function buildEnvCode(Config $config, DataDef $data): Token {#
        $env = $data->getEnvironment();
        return Token::multi(
            Token::text('<?php'),
            $env->getBuild()->getClassNamespace() == ''
                ? Token::nl()
                : Token::multi(
                    Token::text(' namespace '),
                    Token::text(substr($data->getEnvironment()->getBuild()->getClassNamespace(), 1)),
                    Token::textnl(';'),
                ),
            Token::nl(),
            Token::textnl('// Environment class used by the date system for static '),
            Token::textnl('// access of variables. The date classes will only read '),
            Token::textnl('// the values of the environment variables. The system  '),
            Token::textnl('// outside is required to set or bind these before      '),
            Token::textnl('// usage.                                               '),
            Token::textnlpush('class Environment {'),
            //Attribute list
            Token::array(array_map(function ($attr) {
                return Token::multi(
                    Token::text('private static $'),
                    Token::text($attr->getName()),
                    Token::textnl(';')
                );
            }, $data->getEnvironment()->getEnvVars())),
            //Attribute access
            Token::array(array_map(function ($attr) { //is_callable
                $type = self::getCallTypeOfAttribute($attr);
                return Token::multi(
                    //getter
                    Token::multi(
                        Token::nl(),
                        Token::text('public static function get'),
                        Token::text(\ucfirst($attr->getName())),
                        Token::text('()'),
                        $type === null
                            ? Token::text('')
                            : Token::multi(
                                Token::text(': '),
                                Token::text($type)
                            ),
                        Token::textnlpush(' {'),
                        Token::text('if (is_callable(self::$'),
                        Token::text($attr->getName()),
                        Token::textnlpush('))'),
                        Token::text('return self::$'),
                        Token::text($attr->getName()),
                        Token::textnlpop('();'),
                        Token::text('else return self::$'),
                        Token::text($attr->getName()),
                        Token::textnlpop(';'),
                        Token::textnl('}')
                    ),
                    //setter
                    Token::multi(
                        Token::nl(),
                        Token::text('public static function set'),
                        Token::text(\ucfirst($attr->getName())),
                        Token::text('('),
                        $type === null 
                            ? Token::text('')
                            : Token::multi(
                                Token::text($type),
                                Token::text(' ')
                            ),
                        Token::text('$'),
                        Token::text($attr->getName()),
                        Token::textnlpush(') {'),
                        Token::text('self::$'),
                        Token::text($attr->getName()),
                        Token::text(' = $'),
                        Token::text($attr->getName()),
                        Token::textnlpop(';'),
                        Token::textnl('}')
                    ),
                    //binder
                    Token::multi(
                        Token::nl(),
                        Token::text('public static function bind'),
                        Token::text(\ucfirst($attr->getName())),
                        Token::text('(callable $'),
                        Token::text($attr->getName()),
                        Token::textnlpush(') {'),
                        Token::text('self::$'),
                        Token::text($attr->getName()),
                        Token::text(' = $'),
                        Token::text($attr->getName()),
                        Token::textnlpop(';'),
                        Token::textnl('}')
                    ),
                );
            }, $data->getEnvironment()->getEnvVars())),
            //Multi set/ bind
            Token::multi(
                Token::nl(),
                Token::textnl('// This function will bind or set multiple at once. '),
                Token::textnl('// The key has to be exactly the name of the        '),
                Token::textnl('// variable.'),
                Token::textnlpush('public static function multiSet(array $list) {'),
                Token::array(array_map(function ($attr) { //array_key_exists
                    $type = self::getCallTypeOfAttribute($attr);
                    return Token::multi(
                        Token::text('if (array_key_exists(\''),
                        Token::text(\addslashes($attr->getName())),
                        Token::textnlpush('\', $list)) {'),
                        Token::text('if (is_callable($list[\''),
                        Token::text(\addslashes($attr->getName())),
                        Token::textnlpush('\']))'),
                        Token::text('self::$'),
                        Token::text($attr->getName()),
                        Token::text(' = $list[\''),
                        Token::text(\addslashes($attr->getName())),
                        Token::textnlpop('\'];'),
                        Token::text('else self::$'),
                        Token::text($attr->getName()),
                        Token::text(' = '),
                        $type === null 
                            ? Token::text('')
                            : Token::multi(
                                Token::text('('),
                                Token::text($type),
                                Token::text(')')
                            ),
                        Token::text('$list[\''),
                        Token::text(\addslashes($attr->getName())),
                        Token::textnlpop('\'];'),
                        Token::textnl('}'),
                    );
                }, $data->getEnvironment()->getEnvVars())),
                Token::pop(),
                Token::textnl('}'),
            ),
            Token::pop(),
            Token::textnl('}'),
        );
    }

    private static function getCallTypeOfAttribute(\Data\Attribute $attr): ?string {
        switch ($attr->getType()) {
            case 'bool': return 'bool';
            case 'byte':
            case 'short':
            case 'int':
            case 'long':
            case 'sbyte':
            case 'ushort':
            case 'uint':
            case 'ulong': return 'int';
            case 'float':
            case 'double': return 'float';
            case 'string': return 'string';
            case 'bytes': return 'string';
            case 'date': return 'int';
            case 'json': return null;
            default: return null;
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