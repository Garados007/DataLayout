<?php namespace Validation;

use \Data\Build;
use \Data\Security as DSec;
use \Data\DataDefinition;

class Security {
    public function check(DataDefinition $data): ?string {
        $build = $data->getEnvironment()->getBuild();
        foreach ($data->getTypes() as $type) {
            $type->getCreateSecurity()->setBase($data->getCreateSecurity());
            $type->getLoadSecurity()->setBase($data->getLoadSecurity());
            $type->getDeleteSecurity()->setBase($data->getDeleteSecurity());
            $type->getDefaultAttributeSecurity()->setBase($data->getDefaultAttributeSecurity());
            $type->getDefaultJointSecurity()->setBase($data->getDefaultJointSecurity());

            if (($error = $this->checkValidModes($build, $type->getCreateSecurity())) !== null)
                return 'type ' . $type->getName() . ' create: ' . $error;
            if (($error = $this->checkValidModes($build, $type->getLoadSecurity())) !== null)
                return 'type ' . $type->getName() . ' load: ' . $error;
            if (($error = $this->checkValidModes($build, $type->getDeleteSecurity())) !== null)
                return 'type ' . $type->getName() . ' delete: ' . $error;
            
            foreach ($type->getAttributes() as $attr) {
                $attr->getSecurity()->setBase($type->getDefaultAttributeSecurity());
                if (($error = $this->checkValidModes($build, $attr->getSecurity(), DSec::GET)) !== null)
                    return 'type ' . $type->getName() . ' attribute ' 
                        . $attr->getName() . ': ' . $error;
                if (($error = $this->checkValidModes($build, $attr->getSecurity(), DSec::SET)) !== null)
                    return 'type ' . $type->getName() . ' attribute ' 
                        . $attr->getName() . ': ' . $error;
                if (!$attr->getSecurity()->isInclude($build, 'php', DSec::SET)) {
                    if (!$attr->getOptional() && !$attr->hasDefault())
                        return 'type ' . $type->getName() . ' attribute ' 
                        . $attr->getName() . ': a required field without a default connot be excluded in php';
                }
            }
            foreach ($type->getJoints() as $joint) {
                $joint->getSecurity()->setBase($type->getDefaultJointSecurity());
                if (($error = $this->checkValidModes($build, $joint->getSecurity(), DSec::GET)) !== null)
                    return 'type ' . $type->getName() . ' joint ' 
                        . $joint->getName() . ': ' . $error;
                if (($error = $this->checkValidModes($build, $joint->getSecurity(), DSec::SET)) !== null)
                    return 'type ' . $type->getName() . ' joint ' 
                        . $joint->getName() . ': ' . $error;
                if (!$joint->getSecurity()->isInclude($build, 'php', DSec::SET)
                    // || !$joint->getSecurity()->isInclude($build, 'php-graphql', DSec::SET)
                ) {
                    if ($joint->getRequired())
                        return 'type ' . $type->getName() . ' joint ' 
                        . $joint->getName() . ': a required joint connot be excluded';
                }
            }
            foreach ($type->getAccess() as $query) {
                if (($error = $this->checkValidModes($build, $query->getSecurity())) !== null)
                    return 'type ' . $type->getName() . ' query ' 
                        . $query->getName() . ': ' . $error;
            }
        }
        return null;
    }

    private function checkValidModes(Build $build, DSec $sec, int $rule = DSec::ANY): ?string {
        $php = $sec->isInclude($build, 'php', $rule);
        $phpQl = $sec->isInclude($build, 'php-graphql', $rule);

        switch ($rule) {
            case DSec::GET: $access = '(access: get) '; break;
            case DSec::SET: $access = '(access: set) '; break;
            case DSec::CREATE: $access = '(access: create) '; break;
            default: $access = ''; break;
        }
        if ($phpQl && !$php)
            return 'invalid security ' . $access . '- php-graphql requires the inclusion of php';

        return null;
    }
}