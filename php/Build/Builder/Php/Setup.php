<?php namespace Build\Builder\Php;

use \Build\BuildConfig as Config;
use \Build\Token as Token;
use \Data\DataDefinition as DataDef;

class Setup {
    public function build(Config $config, DataDef $data) {
        if (!is_dir($config->setupOutputDir))
            mkdir($config->setupOutputDir);
        $text = $this->buildTokens($config, $data)->build();
        \file_put_contents($config->setupOutputDir . '/data-setup.php', $text);
    }

    public function buildTokens(Config $config, DataDef $data): Token {
        $build = $data->getEnvironment()->getBuild();
        return Token::multi(
            Token::text('<?php namespace '),
            Token::text(substr($build->getClassNamespace(), 1)),
            Token::textnl('\\Setup;'),
            Token::nl(),
            Token::textnl('//create tables and add foreign keys'),
            Token::textnlpush('\\DB::getMultiResult( \'\''),
            Token::frame(Token::array(array(
                array_map(function ($type) use($build, $data) {
                    return $type->buildSqlCreateTable($build);
                }, $data->getTypes()),
                array_map(function ($type) use($build, $data) {
                    return $type->buildSqlAddForeignKeys($data, $build);
                }, $data->getTypes())
            )),
                '. \'', '\' . PHP_EOL', function ($str) { return \addslashes($str); }
            ),
            Token::pop(),
            Token::textnl(')->executeAll();')
        );
    }
}