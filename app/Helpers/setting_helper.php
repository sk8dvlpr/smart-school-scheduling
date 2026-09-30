<?php

use App\Libraries\SettingsService;

if (! function_exists('setting')) {
    /**
     * Read a dotted settings key (e.g. school.name).
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return SettingsService::get($key, $default);
    }
}
