<?php

use Group\App\App;

if (!function_exists('app')) {
    /**
     * Get the available container instance.
     *
     * @param  string  $abstract
     * @return mixed|\Group\App\App
     */
    function app($abstract = null)
    {
        if (is_null($abstract)) {
            return App::getInstance();
        }

        return App::getInstance()->make($abstract);
    }
}