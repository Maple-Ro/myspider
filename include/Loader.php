<?php

class Loader
{
    static function autoload(string $class)
    {
        require ROOT_PATH . '/classes/src/' . str_replace('\\', '/', $class) . '.php';
    }
}