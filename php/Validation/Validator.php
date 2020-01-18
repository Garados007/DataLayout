<?php namespace Validation;

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

        $error = (new Security())->check($data);
        if ($error !== null)
            return '[Security] ' . $error;

        //Optimizer
        $error = (new DbTableNames())->check($data);
        if ($error !== null)
            return '[DbTableNames] ' . $error;
        $error = (new FullQuery())->check($data);
        if ($error !== null)
            return '[FullQuery] ' . $error;

        return null;
    }
}
