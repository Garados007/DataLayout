<?php

require_once __DIR__ . '/../vendor/autoload.php';

(function ()  {
    $start = microtime(true);
    $opts = getopt('b:f:o:r::p:', array(
        'build:',
        'output:',
        'db-output:',
        'setup-output:',
        'db-script:',
        'file:',
        'relative::',
        'profile:'
    ));

    $correctArgs = '  php build.php ((-b | --build) (php))'
        . ' ((-f | --file) [build-file.php])'
        . ' ((-o | --output) [output-dir])'
        . ' (--db-output [db-output-dir])'
        . ' (--setup-output [setup-output-dir])'
        . ' (--db-script [db-script-path.php])' 
        . ' (-r | --relative)'
        . ' ((-p | --profile) [profile-name])'
        . PHP_EOL;

    if ($opts === false) {
        echo 'invalid args. expect:' . PHP_EOL;
        echo $correctArgs;
        exit();
        return;
    }

    $file = isset($opts['f']) 
        ? $opts['f'] 
        : ( isset($opts['file']) 
            ? $opts['file'] 
            : null
        );
    $config = new \Build\BuildConfig();
    $config->buildMode = isset($opts['b']) ? $opts['b']
        : (isset($opts['build']) ? $opts['build'] : 'php');
    $config->outputRoot = isset($opts['o']) 
        ? $opts['o'] 
        : ( isset($opts['output']) 
            ? $opts['output'] 
            : realpath(getcwd())
        );
    $config->dbOutputDir = isset($opts['db-output']) 
        ? $opts['db-output'] 
        : $config->outputRoot . '/DB';
    $config->setupOutputDir = isset($opts['setup-output']) 
        ? $opts['setup-output'] 
        : $config->outputRoot . '/Setup';
    $config->dbScriptPath = isset($opts['db-script'])
        ? $opts['db-script']
        : null;
    $config->useRelativePaths = isset($opts['r']) || isset($opts['relative']);
    $profile = isset($opts['p']) ? $opts['p']
        : (isset($opts['profile']) ? $opts['profile'] : null);
    \Data\Environment::setProfile($profile);
    
    if ($file === null) {
        echo 'expect a target file (with -f or --file)' . PHP_EOL;
        echo $correctArgs;
        exit();
        return;
    }
    
    if ($config->dbScriptPath === null) {
        echo 'expect a db script file (with --db-script)' . PHP_EOL;
        echo $correctArgs;
        exit();
        return;
    }

    echo 'Prepare builder:' . PHP_EOL;
    echo '  Profile:              ' . ($profile ?: 'default') . PHP_EOL;
    echo '  Type definition file: ' . \Build\BuildManager::preparePath($file) . PHP_EOL;
    echo '  Output root dir:      ' . \Build\BuildManager::preparePath($config->outputRoot) . PHP_EOL;
    echo '  DB Output dir:        ' . \Build\BuildManager::preparePath($config->dbOutputDir) . PHP_EOL;
    echo '  Setup Output dir:     ' . \Build\BuildManager::preparePath($config->setupOutputDir) . PHP_EOL;
    echo '  DB Script file:       ' . \Build\BuildManager::preparePath($config->dbScriptPath) . PHP_EOL;
    echo '  Use relative paths:   ' . ($config->useRelativePaths ? 'true' : 'false') . PHP_EOL;

    echo 'Init builder job...' . PHP_EOL;
    $builder = \Build\BuildManager::load($config);
    if ($builder === null) {
        echo 'unsupported build mode ' . $config->buildMode;
        exit();
        return;
    }
    if (!$builder->init($file)) {
        echo 'Cannot initialize builder!' . PHP_EOL;
        echo 'cancel process.' . PHP_EOL;
        exit();
        return;
    }

    echo 'Build job...' . PHP_EOL;
    $builder->build();

    $memory = memory_get_peak_usage(true);

    function getNiceFileSize($bytes) {
        $unit=array('B','KiB','MiB','GiB','TiB','PiB');
        if ($bytes==0) return '0 ' . $unit[0];
        return 
            @round($bytes/pow(1024,($i=floor(log($bytes,1024)))),2) 
            .' '.
             (isset($unit[$i]) ? $unit[$i] : 'B');
    }

    echo 'finished in ' . (microtime(true) - $start) . 
        ' seconds and consumed ' . getNiceFileSize($memory) .
        ' RAM' . PHP_EOL;

})();
