<?php

echo '<pre>'.PHP_EOL;
echo 'init test...'.PHP_EOL;

require_once __DIR__ . '/php/Data/DataDefinition.php';

$content = file_get_contents(__DIR__ . '/test.xml');

//validate
$reader = new \XmlReader();
$reader->xml($content);
if ($reader->setSchema(__DIR__ . '/data-layout.xsd'))
    echo 'valid document'.PHP_EOL;
else echo 'invalid document'.PHP_EOL;
$reader->close();

//load
$xml = simplexml_load_string($content);
$data = \Data\DataDefinition::loadFromXml($xml);

//extra validation

require_once __DIR__ . '/php/Validation/Validator.php';

$error = (new \Validation\Validator())->check($data);
if ($error !== null)
    echo $error . PHP_EOL;

//export loaded result
if ($error === null) {
    require_once __DIR__ . '/php/Build/Builder/TypeBuilder.php';
    $bc = new \Build\BuildConfig();
    $bc->dbScriptPath = 'db.php';

    $builder = new \Build\Builder\TypeBuilder();
    foreach ($data->getTypes() as $type) {
        echo '>>>>>> ' . $type->getName() . PHP_EOL;
        echo $builder->buildTypeCode($bc, $data, $type)->build();
        echo PHP_EOL;
    }
    // echo $setup->buildTokens($bc, $data)->build();
}

echo '</pre>'.PHP_EOL;
