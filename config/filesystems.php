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
         | Public media.
         |
         | The fallback is public_path('storage'), NOT storage_path('app/public'),
         | because it has to agree with the 'url' below in both layouts:
         |
         |   distribution — public_path() is rebound in bootstrap/app.php to the real
         |     webroot (app in core/, index.php one level up), so this resolves to the
         |     web-served storage/ directory that APP_URL/storage/... actually maps to.
         |     storage_path('app/public') would put media inside core/, which .htaccess
         |     denies outright: uploads would succeed and then serve 403 forever.
         |   standard checkout — resolves to public/storage, which is the target of
         |     `artisan storage:link`. Writes pass through that symlink to
         |     storage/app/public exactly as before.
         |
         | The installer still writes PUBLIC_DISK_ROOT explicitly, so this is now an
         | override rather than a requirement. That matters: an .env that loses the key
         | (hand-edited, restored from an old copy, half-migrated between hosts) used to
         | fall back to a path the web server refuses to serve, with nothing in the logs.
         */
        'public' => [
            'driver' => 'local',
            'root' => env('PUBLIC_DISK_ROOT', public_path('storage')),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        // Stable reference to the on-server public media root. The `public` disk
        // above may be rebound to a cloud bucket at runtime (Settings → Storage);
        // this one never is, so storage migration can always reach local files.
        'local_public_media' => [
            'driver' => 'local',
            'root' => env('PUBLIC_DISK_ROOT', public_path('storage')),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],



];
