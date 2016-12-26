<?php
class Loader
{
    static function autoload(string $class)
    {
        $class = str_replace('Maple', '', $class);
        require ROOT_PATH . 'classes/src' . str_replace('\\', '/', $class) . '.php';
    }
}