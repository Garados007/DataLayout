<?php namespace Build\Builder\Elm;

use \Build\Token as Token;

class BuildManager extends \Build\BuildManager {

    public function build() {
        $this->buildTypes();
        $this->buildUtils();
        $this->buildRequestUtils();
        $this->buildMutate();
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

    protected function buildUtils() {
        if ($this->data->getEnvironment()->getBuild()->getGraphQL() !== null) {
            $builder = new \Build\Builder\Elm\GraphQLUtil();
            $token = $builder->buildUtilCode($this->config, $this->data);
            $path = $this->config->outputRoot
                 . '/'
                . str_replace(
                    '.',
                    '/',
                    $this->data->getEnvironment()->getBuild()->getGraphQL()
                ) . '.elm';
            $dir = dirname($path);
            if (!is_dir($dir))
                mkdir($dir, 0777, true);
            $this->output($token, $path);
        }
    }
    
    protected function buildRequestUtils() {
        if ($this->data->getEnvironment()->getBuild()->getGraphQLRequest() !== null) {
            $builder = new \Build\Builder\Elm\GraphQLRequest();
            $token = $builder->buildUtilCode($this->config, $this->data);
            $path = $this->config->outputRoot
                 . '/'
                . str_replace(
                    '.',
                    '/',
                    $this->data->getEnvironment()->getBuild()->getGraphQLRequest()
                ) . '.elm';
            $dir = dirname($path);
            if (!is_dir($dir))
                mkdir($dir, 0777, true);
            $this->output($token, $path);
        }
    }
    
    protected function buildMutate() {
        if ($this->data->getEnvironment()->getBuild()->getGraphQLMutate() !== null) {
            $builder = new \Build\Builder\Elm\GraphQLMutate();
            $token = $builder->buildUtilCode($this->config, $this->data);
            $path = $this->config->outputRoot
                 . '/'
                . str_replace(
                    '.',
                    '/',
                    $this->data->getEnvironment()->getBuild()->getGraphQLMutate()
                ) . '.elm';
            $dir = dirname($path);
            if (!is_dir($dir))
                mkdir($dir, 0777, true);
            $this->output($token, $path);
        }
    }
}