<?php namespace Data;

class Extension {
    private $file;
    private $prefix;
    private $use;
    private $env;

    public function getFile(): string {
        return $this->file;
    }

    public function getPrefix(): string {
        return $this->prefix;
    }

    public function getUse(): string {
        return $this->use;
    }

    public function isRequired(): bool {
        return $this->use == 'required';
    }

    public function isOptional(): bool {
        return $this->use == 'optional';
    }

    public function getEnvironment(): string {
        return $this->env;
    }

    public function isEnvIgnore(): bool {
        return $this->env == 'ignore';
    }

    public function isEnvAddPrefix(): bool {
        return $this->env == 'addPrefix';
    }

    public static function loadFromXml(\SimpleXMLElement $element): ?Extension {
        if ($element->getName() != "Extension")
            return null;

        $ext = new Extension();

        $ext->file = (string)$element->attributes()->file;
        if (isset($element->attributes()->prefix))
            $ext->prefix = (string)$element->attributes()->prefix;
        else $ext->prefix = '';
        if (isset($element->attributes()->use))
            $ext->use = (string)$element->attributes()->use;
        else $ext->use = 'required';
        if (isset($element->attributes()->environment))
            $ext->env = (string)$element->attributes()->environment;
        else $ext->env = 'ignore';

        return $ext;
    }
}