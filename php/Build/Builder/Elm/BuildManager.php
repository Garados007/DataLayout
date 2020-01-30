<?php namespace Build\Builder\Elm;

use \Build\Token as Token;

class BuildManager extends \Build\BuildManager {

    public function build() {
        $this->buildTypes();
    }

    protected function buildTypes() {
        $builder = new \Build\Builder\Elm\TypeBuilder();
        $token = $builder->buildTypeCode($this->config, $this->data);
        $path = $this->config->outputRoot
             . '/'
            . str_replace(
                '.',
                '/',
                $this->data->getEnvironment()->getBuild()->getNamespace()
            ) . '.elm';
        $dir = dirname($path);
        if (!is_dir($dir))
            mkdir($dir, 0777, true);
        $this->output($token, $path);
    }
}