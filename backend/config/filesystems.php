<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        /*
         * Central private files read and written from both central and tenant context
         * (request conversation attachments): same root as the central `local` disk but not
         * in tenancy.filesystem.disks, so a store request never re-roots it.
         */
        'central' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            // Group-writable: the web server and CLI queue workers / artisan run as different users of one group.
            'permissions' => [
                'file' => ['public' => 0664, 'private' => 0660],
                'dir' => ['public' => 0775, 'private' => 0770],
            ],
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],

        /*
         * Custom Twig theme packages (decision #121): extracted files of every uploaded
         * version ({tenant}/{version}/…) and the compiled template cache (compiled/…).
         * Private, central (not re-rooted per store); assets are streamed by the
         * storefront `theme-assets` route, never served from here directly.
         */
        'custom-themes' => [
            'driver' => 'local',
            'root' => storage_path('app/private/custom-themes'),
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            // Unused: object storage would be configured in Admin › Integrations (decision #69), never .env.
            'driver' => 's3',
            'key' => null,
            'secret' => null,
            'region' => null,
            'bucket' => null,
            'url' => null,
            'endpoint' => null,
            'use_path_style_endpoint' => false,
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
