<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Scan Directories
    |--------------------------------------------------------------------------
    |
    | Directories to scan for translation keys used in PHP/Blade files.
    | Paths are relative to the project root (base_path()).
    |
    */

    'scan_dirs' => ['app', 'config', 'routes', 'resources/views', 'modules', 'src'],

    /*
    |--------------------------------------------------------------------------
    | JavaScript Scan Directories
    |--------------------------------------------------------------------------
    |
    | Directories to scan for translation keys used in JS/TS/Vue files.
    |
    */

    'scan_js_dirs' => ['resources/js', 'modules', 'src'],

    /*
    |--------------------------------------------------------------------------
    | Exclude Paths
    |--------------------------------------------------------------------------
    |
    | Paths to exclude when scanning. These are appended to any --exclude flags.
    | Paths are relative to the project root.
    |
    */

    'exclude_paths' => [],

    /*
    |--------------------------------------------------------------------------
    | Supported Locales
    |--------------------------------------------------------------------------
    |
    | Set to an array of locale codes (e.g. ['en', 'es', 'fr']) to restrict
    | which locales are managed, or null to auto-discover from lang/*.json files.
    |
    */

    'locales' => null,

    /*
    |--------------------------------------------------------------------------
    | Default Locale
    |--------------------------------------------------------------------------
    |
    | The primary language file used as the master key source.
    |
    */

    'default_locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | PHP Translation Functions
    |--------------------------------------------------------------------------
    |
    | Function names to scan for in PHP and Blade files.
    |
    */

    'php_functions' => ['__', 'trans'],

    /*
    |--------------------------------------------------------------------------
    | JavaScript Translation Functions
    |--------------------------------------------------------------------------
    |
    | Function names to scan for in JS/TS/Vue files.
    |
    */

    'js_functions' => ['t'],

    /*
    |--------------------------------------------------------------------------
    | Translatable HTML Attributes
    |--------------------------------------------------------------------------
    |
    | Which HTML tag attributes should be automatically wrapped with
    | translation helpers when running trans:make-translatable.
    |
    | Keys: 'blade' for Blade templates, 'vue' for Vue templates.
    |
    */

    'translatable_attributes' => [
        'blade' => ['placeholder', 'alt', 'title'],
        'vue' => ['placeholder', 'alt', 'title', 'label', 'message', 'hint', 'action-label'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Wrap Translation Keys
    |--------------------------------------------------------------------------
    |
    | When running trans:make-translatable, which object keys should have
    | their string values automatically wrapped with translation helpers.
    |
    */

    'make_translatable' => [
        'php_keys' => ['error', 'success', 'message', 'warning', 'title'],
        'js_keys' => ['label', 'title', 'placeholder', 'description', 'message', 'subject'],
        'schema_keys' => ['label', 'name', 'placeholder', 'info', 'help'],
    ],

];
