<?php namespace Data;

class DataDefinition {
    private $environment;
    private $extensions;
    private $types;
    private $createSecurity;
    private $loadSecurity;
    private $deleteSecurity;
    private $defaultAttributeSecurity;
    private $defaultJointSecurity;
    
    private function __construct() {}

    public function getEnvironment(): \Data\Environment {
        return $this->environment;
    }

    public function getExtensions(): array {
        return $this->extensions;
    }

    public function getTypes(): array {
        return $this->types;
    }

    public function getType(string $name): ?\Data\Type {
        foreach ($this->types as $type)
            if ($type->getName() == $name)
                return $type;
        return null;
    }
    
    public function getCreateSecurity(): Security {
        return $this->createSecurity;
    }

    public function getLoadSecurity(): Security {
        return $this->loadSecurity;
    }

    public function getDeleteSecurity(): Security {
        return $this->deleteSecurity;
    }

    public function getDefaultAttributeSecurity(): Security {
        return $this->defaultAttributeSecurity;
    }
    
    public function getDefaultJointSecurity(): Security {
        return $this->defaultJointSecurity;
    }

    public static function loadFromXml(\SimpleXMLElement $element): ?DataDefinition {
        if ($element->getName() != "DataDefinition")
            return null;

        $def = new \Data\DataDefinition();

        if (isset($element->Environment))
            $def->environment = \Data\Environment::loadFromXml($element->Environment);
        else $def->environment = null;

        $def->extensions = array();
        if (isset($element->Extensions))
            foreach ($element->Extensions->children() as $child) {
                $ext = \Data\Extension::loadFromXml($child);
                if ($ext !== null)
                    $def->extensions []= $ext;
            }

        $def->types = array();
        if (isset($element->Types)) 
            foreach ($element->Types->children() as $child) {
                $type = \Data\Type::loadFromXml($child);
                if ($type !== null)
                    $def->types []= $type;
            }
            
        $def->createSecurity = isset($element->DefaultTypeSecurity->Create)
            ? Security::loadFromXml($element->DefaultTypeSecurity->Create)
            : new Security();
        $def->loadSecurity = isset($element->DefaultTypeSecurity->Load)
            ? Security::loadFromXml($element->DefaultTypeSecurity->Load)
            : new Security();
        $def->deleteSecurity = isset($element->DefaultTypeSecurity->Delete)
            ? Security::loadFromXml($element->DefaultTypeSecurity->Delete)
            : new Security();
        $def->defaultAttributeSecurity = isset($element->DefaultTypeSecurity->DefaultAttribute)
            ? Security::loadFromXml($element->DefaultTypeSecurity->DefaultAttribute)
            : new Security();
        $def->defaultJointSecurity = isset($element->DefaultTypeSecurity->DefaultJoint)
            ? Security::loadFromXml($element->DefaultTypeSecurity->DefaultJoint)
            : new Security();
        return $def;
    }

    public function import(DataDefinition $data, bool $enableEnv, string $prefix, string $extension) {
        $types = $data->getTypes();
        $envVars = $enableEnv
            ? array_map(
                function ($a) { return $a->getName(); },
                $data->getEnvironment()->getEnvVars()
            )
            : array();
        $importTypes = array_map(function ($t) { return $t->getName(); }, $types);
        $env = function ($name) use ($envVars, $prefix) {
            if (\in_array($name, $envVars))
                return $prefix . $name;
            return $name;
        };
        $renamer = function ($name) use ($importTypes, $prefix) {
            if (\in_array($name, $importTypes))
                return $prefix . $name;
            return $name;
        };
        if ($enableEnv)
            $this->getEnvironment()->import($data->getEnvironment()->getEnvVars(), $env);
        foreach ($types as $type) {
            $type->import($renamer, $env);
            $type->setExtension($extension);
            $this->types []= $type;
        }
    }
}
