<?php namespace Validation;

require_once __DIR__ . '/../Data/DataDefinition.php';
require_once __DIR__ . '/TypeCycleReference.php';
require_once __DIR__ . '/LinkReference.php';
require_once __DIR__ . '/QueryReference.php';
require_once __DIR__ . '/TargetReference.php';

class Validator {
    public function check(\Data\DataDefinition $data): ?string {
        $error = (new TypeCycleReference())->check($data);
        if ($error !== null)
            return '[TypeCycleReference] ' . $error;

        $error = (new LinkReference())->check($data);
        if ($error !== null)
            return '[LinkReference] ' . $error;
            
        $error = (new TargetReference())->check($data);
        if ($error !== null)
            return '[TargetReference] ' . $error;

        $error = (new QueryReference())->check($data);
        if ($error !== null)
            return '[QueryReference] ' . $error;

        return null;
    }
}
