<?php namespace Test\TestEnvironment;

// Environment class used by the date system for static 
// access of variables. The date classes will only read 
// the values of the environment variables. The system  
// outside is required to set or bind these before      
// usage.                                               
class Environment {
    private static $userId;

    public static function getUserId(): int {
        if (is_callable(self::$userId))
            return self::$userId();
        else return self::$userId;
    }

    public static function setUserId(int $userId) {
        self::$userId = $userId;
    }

    public static function bindUserId(callable $userId) {
        self::$userId = $userId;
    }

    // This function will bind or set multiple at once. 
    // The key has to be exactly the name of the        
    // variable.
    public static function multiSet(array $list) {
        if (array_key_exists('userId', $list)) {
            if (is_callable($list['userId']))
                self::$userId = $list['userId'];
            else self::$userId = (int)$list['userId'];
        }
    }
}
