<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use GraphQL\GraphQL;
use GraphQL\Error\Debug;
use GraphQL\Language\Parser;
use GraphQL\Type\Schema;
use GraphQL\Utils\BuildSchema;
use GraphQL\Utils\AST;
use Test\Generated\GraphQL\TypeResolver;

//get schema

$cacheFilename = __DIR__ . '/../test/cached_schema.php';
$sourceFileName = __DIR__ . '/../test/GraphQL/db-schema.part.graphql';

if (!file_exists($cacheFilename) || filemtime($cacheFilename) < filemtime($sourceFileName)) {
    $document = Parser::parse(
        file_get_contents(
            $sourceFileName
        )
    );
    file_put_contents(
        $cacheFilename,
        '<?php' . PHP_EOL . 'return '
            . \var_export(
                AST::toArray($document),
                true
            )
            . ';' . PHP_EOL
    );
}
else {
    $document = AST::fromArray(require $cacheFilename);
}

$typeConfigDecorator = function ($config, $typeNode, $typeMap) {
    $newConfig = TypeResolver::attachResolver($config, $typeNode, $typeMap);
    if ($newConfig !== null)
        return $newConfig;

    return $config;
};
$schema = BuildSchema::build($document, $typeConfigDecorator);

//handle request
if (isset($_POST['operations'])) {
    $rawInput = $_POST['operations'];
}
else {
    $rawInput = file_get_contents('php://input');
}
if (preg_match('/^\-{4,}/', $rawInput)) {
    $parts = preg_split('/[\r\n]/', $rawInput, -1, PREG_SPLIT_NO_EMPTY);
    for ($i = 0; $i < count($parts); ++$i) {
        if (preg_match('/name\=\"operations\"$/', $parts[$i])) {
            $rawInput = $parts[$i + 1];
            break;
        }
    }
}
$input = json_decode($rawInput, true);
$query = $input['query'];
$variableValues = isset($input['variables'])
    ? $input['variables'] : null;

try {
    $rootValue = [];
    $result = GraphQL::executeQuery(
        $schema,
        $query,
        $rootValue,
        null,
        $variableValues
    );
    $output = $result->toArray(Debug::INCLUDE_DEBUG_MESSAGE | Debug::INCLUDE_TRACE);
}
catch (\Exception $e) {
    $output = [
        'errors' => [
            [
                'message' => $e->getMessage(),
            ]
        ]
    ];
}

// $output['raw'] = $rawInput;

header('Content-Type: application/json');
echo json_encode($output);
