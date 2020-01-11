<?php namespace Build\Builder\Php;

use \Build\Token as Token;

class BuildManager extends \Build\BuildManager {

    public function build() {
        $this->buildEnv();
        $this->buildSetup();
        $this->buildTypes();
    }

    protected function buildEnv() {
        $builder = new \Build\Builder\Php\EnvBuilder();
        $token = $builder->buildEnvCode($this->config, $this->data);
        $this->output(
            $token,
            $this->config->dbOutputDir . '/Environment.php'
        );
    }

    protected function buildSetup() {
        $setup = new \Build\Builder\Php\Setup();
        $token = $setup->buildTokens($this->config, $this->data);
        $this->output(
            $token, 
            $this->config->setupOutputDir . '/data-setup.php'
        );
    }

    protected function buildTypes() {
        $builder = new \Build\Builder\Php\TypeBuilder();
        foreach ($this->data->getTypes() as $type) {
            $token = $builder->buildTypeCode($this->config, $this->data, $type);
            $path = $this->config->dbOutputDir . '/Data/' . $type->getName() . '.php';
            $dir = dirname($path);
            if (!is_dir($dir))
                mkdir($dir, 0777, true);
            $this->output($token, $path);
        }
    }
}