<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Testing
    |--------------------------------------------------------------------------
    |
    | The starter kit keeps page components in lowercase `resources/js/pages`,
    | so point the assertInertia page-exists check at that path (the package
    | default is `js/Pages`).
    |
    */

    'testing' => [
        'ensure_pages_exist' => true,
        'page_paths' => [resource_path('js/pages')],
        'page_extensions' => ['js', 'jsx', 'ts', 'tsx', 'vue', 'svelte'],
    ],

];
