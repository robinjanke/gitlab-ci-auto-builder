<?php

namespace RobinJanke\GitlabCiAutoBuilder\Models;

class Singleton
{
    public static function instance()
    {
        static $instance = false;
        if( $instance === false )
        {
            $instance = new static();
        }

        return $instance;
    }

    private function __construct() {}

    private function __clone() {}

    private function __sleep() {}

    private function __wakeup() {}

}
