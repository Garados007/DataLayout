<?php namespace Build\Builder\PhpGraphQL;

require_once __DIR__ . '/../../BuildManager.php';
require_once __DIR__ . '/GraphSchemeBuilder.php';

use \Build\Token as Token;

class BuildManager extends \Build\BuildManager {

    public function build() {
        $this->buildScheme();
    }

    protected function buildScheme() {
        $builder = new \Build\Builder\PhpGraphQL\GraphSchemeBuilder();
        $token = $builder->buildSchema($this->config, $this->data);
        $this->output(
            $token,
            $this->config->outputRoot . '/GraphQL/db-schema.part.graphql'
        );
    }

    // protected function buildEnv() {
    //     $builder = new \Build\Builder\Php\EnvBuilder();
    //     $token = $builder->buildEnvCode($this->config, $this->data);
    //     $this->output(
    //         $token,
    //         $this->config->dbOutputDir . '/Environment.php'
    //     );
    // }
}