<?php namespace Build;

require_once __DIR__ . '/BuildConfig.php';
require_once __DIR__ . '/Token.php';
require_once __DIR__ . '/../Data/DataDefinition.php';
require_once __DIR__ . '/../Validation/Validator.php';
require_once __DIR__ . '/../Data/Build.php';

use \Build\Token as Token;

abstract class BuildManager {
    protected $config;
    protected $data;

    public static function load(\Build\BuildConfig $config): ?BuildManager {
        switch ($config->buildMode) {
            case 'php':
                require_once __DIR__ . '/Builder/Php/BuildManager.php';
                return new \Build\Builder\Php\BuildManager($config);
            case 'php-graphql':
                require_once __DIR__ . '/Builder/PhpGraphQL/BuildManager.php';
                return new \Build\Builder\PhpGraphQL\BuildManager($config);
            default:
                return null;
        }
    }

    public function __construct(\Build\BuildConfig $config) {
        $this->config = $config;
        \Data\Build::setBuildMode($config->buildMode);
    }

    protected function validateConfig(): bool {
        try {
            //putput root
            $this->config->outputRoot = self::preparePath($this->config->outputRoot);
            if (!is_dir($this->config->outputRoot)) {
                mkdir($this->config->outputRoot, 0777, true);
            }
            //db output dir
            $this->config->dbOutputDir = self::preparePath($this->config->dbOutputDir);
            if (!is_dir($this->config->dbOutputDir)) {
                mkdir($this->config->dbOutputDir, 0777, true);
            }
            //setup output dir
            $this->config->setupOutputDir = self::preparePath($this->config->setupOutputDir);
            if (!is_dir($this->config->setupOutputDir)) {
                mkdir($this->config->setupOutputDir, 0777, true);
            }
            //db script path
            $this->config->dbScriptPath = self::preparePath($this->config->dbScriptPath);
            if (!is_file($this->config->dbScriptPath))
                throw new \Exception('db script not found in: ' . $this->config->dbScriptPath);

            //if relative paths
            if ($this->config->useRelativePaths) {
                $this->config->dbScriptPath = (function ($target) {
                    return function ($source) use ($target) {
                        return self::getRelativePath($source, $target);
                    };
                })($this->config->dbScriptPath);
            }
        }
        catch (\Exception $e) {
            echo 'error: ' . $e . PHP_EOL;
            return false;
        }
        return true;
    }

    public static function getRelativePath(string $startPath, string $target): string {
        if (self::getRoot($startPath) !== self::getRoot($target))
            return $target;
        $sp = \preg_split('/[\\\\\/]/', $startPath);
        $tp = \preg_split('/[\\\\\/]/', $target);
        $ind = 0;
        for (; $ind < count($sp) && $ind < count($tp); ++$ind)
            if ($sp[$ind] != $tp[$ind])
                break;
        $result = '';
        for ($i = $ind + 1; $i < count($sp); ++$i)
            $result .= '../';
        for ($i = $ind; $i < count($tp); ++$i)
            $result .= ($i != $ind ? '/' : '') . $tp[$i];
        return $result;
    }

    protected static function getRoot(string $path): ?string {
        $matches = array();
        if (\preg_match('/^(\/|[a-zA-z]:\\\\)/', $path, $matches) === 1)
            return $matches[0];
        else return null;
    }

    public static function preparePath(string $path, ?string $cwd = null): string {
        $path = self::getAbsolute($path);
        $is_root = preg_match('/^(\/|[A-Za-z]\:\/)/', $path);
        if (!$is_root) {
            $path = ($cwd === null ? getcwd() : $cwd) . '/' . $path;
            $path = self::getAbsolute($path);
        }
        return $path;
    }

    //source: https://www.php.net/manual/de/function.self::preparePath.php#124254
    protected static function getAbsolute(string $path): string {
        // Cleaning path regarding OS
        $path = mb_ereg_replace('\\\\|/', DIRECTORY_SEPARATOR, $path, 'msr');
        // Check if path start with a separator (UNIX)
        $startWithSeparator = $path[0] === DIRECTORY_SEPARATOR;
        // Check if start with drive letter
        preg_match('/^[a-z]:/', $path, $matches);
        $startWithLetterDir = isset($matches[0]) ? $matches[0] : false;
        // Get and filter empty sub paths
        $subPaths = array_filter(explode(DIRECTORY_SEPARATOR, $path), 'mb_strlen');

        $absolutes = [];
        foreach ($subPaths as $subPath) {
            if ('.' === $subPath) {
                continue;
            }
            // if $startWithSeparator is false
            // and $startWithLetterDir
            // and (absolutes is empty or all previous values are ..)
            // save absolute cause that's a relative and we can't deal with that and just forget that we want go up
            if ('..' === $subPath
                && !$startWithSeparator
                && !$startWithLetterDir
                && empty(array_filter($absolutes, function ($value) { return !('..' === $value); }))
            ) {
                $absolutes[] = $subPath;
                continue;
            }
            if ('..' === $subPath) {
                array_pop($absolutes);
                continue;
            }
            $absolutes[] = $subPath;
        }

        return
            (($startWithSeparator ? DIRECTORY_SEPARATOR : $startWithLetterDir) ?
                $startWithLetterDir.DIRECTORY_SEPARATOR : ''
            ).implode(DIRECTORY_SEPARATOR, $absolutes);
    }

    protected function preInit(string $dataPath, array $ignoreExtensions = array()) {
        $this->loadData($dataPath);
        //load extensions
        foreach ($this->data->getExtensions() as $ext) {
            $path = self::preparePath($ext->getFile(), dirname($dataPath));
            if (\in_array($path, $ignoreExtensions)) {
                if ($ext->isRequired())
                    throw new \Exception('extension cannot loaded: ' . $path);
                else continue;
            }
            $ignoreExtensions []= $path;
            //load extension
            $client = new BuildManager($this->config);
            $client->preInit(
                $path,
                $ignoreExtensions
            );
            //import extension
            $this->data->import(
                $client->data, 
                $ext->isEnvAddPrefix(),
                $ext->getPrefix(), 
                $path
            );
        }
    }

    public function init(string $dataPath): bool {
        try {
            if (!$this->validateConfig($this->config)) 
                throw new \Exception('Invalid config');
            $this->preInit($dataPath, array($dataPath));
            //validate self
            $this->validateData();
        }
        catch (\Exception $e) {
            echo 'error: ' . $e->getMessage() . PHP_EOL;
            return false;
        }
        return true;
    }

    protected function loadData(string $dataPath) {
        if (!is_file($dataPath))
            throw new \Exception('data file doesn\'t exists.' . self::preparePath($dataPath));
        $content = file_get_contents($dataPath);

        //validate
        $reader = new \XmlReader();
        $reader->xml($content);
        if (!$reader->setSchema(__DIR__ . '/../../data-layout.xsd'))
            throw new \Exception('xsd validation error - invalid content');
        $reader->close();

        //load
        $xml = \simplexml_load_string($content);
        $this->data = \Data\DataDefinition::loadFromXml($xml);
    }

    protected function validateData() {
        $validator = new \Validation\Validator();
        $error = $validator->check($this->data);
        if ($error !== null)
            throw new \Exception($error);

        if (!$this->data->getEnvironment()->getBuild()->getSupported())
            throw new \Exception('the build is not supported by the type definition itself.');
    }

    abstract public function build();

    protected function output(Token $token, string $path) {
        $text = $token->build();
        $dir = dirname($path);
        if (!\is_dir($dir))
            mkdir($dir);
        \file_put_contents($path, $text);
    }

    public static function execute(\Build\BuildConfig $config, string $dataPath): bool {
        $builder = self::load($config);
        if ($builder === null) {
            echo 'error: unsupported build mode ' . $config->buildMode;
            return false;
        }
        if (!$builder->init($dataPath))
            return false;
        $builder->build();
        return true;
    }
}
