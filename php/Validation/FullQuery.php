<?php namespace Validation;

class FullQuery {
    public function check(\Data\DataDefinition $data): ?string {
        switch (true) {
            case $data->getEnvironment()->getBuild() instanceof \Data\PhpBuild:
                $fullQuery = $data->getEnvironment()->getBuild()->isFullQueryAuto()
                    ? null 
                    : $data->getEnvironment()->getBuild()->isFullQueryAll();
                break;
            default: return null;
        }
        foreach ($data->getTypes() as $type) {
            if ($type->getBase() !== null && $type->getFullQuery())
                return 'type ' . $type->getName() 
                    . ': only base types can set define full querys';
            if ($type->getBase() !== null)
                continue;
            $fq = $fullQuery ?: $type->getFullQuery();
            if (!$fq) continue;
            $bucket = new \Data\TypeBucket(
                $data->getEnvironment()->getBuild(), 
                $type, 
                $data->getTypes()
            );
            foreach ($bucket->getTypes() as $t)
                $t->setBucket($bucket);
        }
        return null;
    }
}