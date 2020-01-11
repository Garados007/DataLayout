<?php namespace Build\Builder\PhpGraphQL;

use \Build\Token as Token;

class BuildManager extends \Build\BuildManager {

    public function build() {
        $this->buildScheme();
        $this->buildResolver();
        $this->buildPermission();
    }

    protected function buildScheme() {
        $builder = new \Build\Builder\PhpGraphQL\GraphSchemeBuilder();
        $token = $builder->buildSchema($this->config, $this->data);
        $this->output(
            $token,
            $this->config->outputRoot . '/GraphQL/db-schema.part.graphql'
        );
    }

    protected function buildResolver() {
        $builder = new \Build\Builder\PhpGraphQL\ResolveAttacher();
        $token = $builder->buildResolver($this->config, $this->data);
        $this->output(
            $token,
            $this->config->outputRoot . '/GraphQL/type-resolver.php'
        );
    }

    protected function buildPermission() {
        if (!$this->data->getEnvironment()->getBuild()->getInternalPermissionChecks())
            return;
        $builder = new \Build\Builder\PhpGraphQL\PermissionBuilder();
        $token = $builder->buildPermission($this->config, $this->data);
        $this->output(
            $token,
            $this->config->outputRoot . '/GraphQL/permission.php'
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