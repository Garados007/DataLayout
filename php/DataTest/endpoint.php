<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use GraphQL\GraphQL;
use GraphQL\Language\Parser;
use GraphQL\Type\Schema;
use GraphQL\Utils\BuildSchema;
use GraphQL\Utils\AST;
use Test\Generated\GraphQL\TypeResolver;

//get schema

$cacheFilename = __DIR__ . '/../test/cached_schema.php';

if (!file_exists($cacheFilename)) {
    $document = Parser::parse(
        file_get_contents(
            __DIR__ . '/../test/GraphQL/db-schema.part.graphql'
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
$rawInput = file_get_contents('php://input');
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
    $output = $result->toArray();
}
catch (\Exception $e) {
    $output = [
        'errors' => [
            [
                'message' => $e->getMessage()
            ]
        ]
    ];
}

header('Content-Type: application/json');
echo json_encode($output);
