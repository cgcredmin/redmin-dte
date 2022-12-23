<?php

return [
  /*
  |--------------------------------------------------------------------------
  | Default Filesystem Disk
  |--------------------------------------------------------------------------
  |
  | Here you may specify the default filesystem disk that should be used
  | by the framework. The "local" disk, as well as a variety of cloud
  | based disks are available to your application. Just store away!
  |
  */

  'default' => env('FILESYSTEM_DRIVER', 'local'),

  /*
  |--------------------------------------------------------------------------
  | Default Cloud Filesystem Disk
  |--------------------------------------------------------------------------
  |
  | Many applications store files both locally and in the cloud. For this
  | reason, you may specify a default "cloud" driver here. This driver
  | will be bound as the Cloud disk implementation in the container.
  |
  */

  'cloud' => env('FILESYSTEM_CLOUD', 's3'),

  /*
  |--------------------------------------------------------------------------
  | Filesystem Disks
  |--------------------------------------------------------------------------
  |
  | Here you may configure as many filesystem "disks" as you wish, and you
  | may even configure multiple disks of the same driver. Defaults have
  | been setup for each driver as an example of the required options.
  |
  | Supported Drivers: "local", "ftp", "sftp", "s3", "rackspace"
  |
  */

  'disks' => [
    'local' => [
      'driver' => 'local',
      'root' => storage_path('app'),
    ],
    'pdfs' => [
      'driver' => 'local',
      'root' => storage_path('app/pdf'),
      'url' => env('APP_URL') . '/dte/pdf/',
      'visibility' => 'public',
    ],
    'temp' => [
      'driver' => 'local',
      'root' => storage_path('app/tempfiles'),
      'visibility' => 'public',
    ],

    // 'pdfs' => [
    //   'driver' => 's3',
    //   'key' => env('AWS_ACCESS_KEY_ID'),
    //   'secret' => env('AWS_SECRET_ACCESS_KEY'),
    //   'region' => env('AWS_REGION', 'sa-east-1'),
    //   'bucket' => env('AWS_BUCKET'),
    //   'url' => env('AWS_URL'),
    //   // 'driver' => 'local',
    //   // 'root' => storage_path('app/public/s3'),
    //   // 'url' => env('APP_URL') . '/s3',
    //   'visibility' => 'private',
    // ],

    // 'folios' => [
    //   'driver' => 'local',
    //   // 'root' => storage_path('xml/folios'),
    //   'root' => env('APP_URL') . '/app/storage/firmas',
    //   // 'url' => env('APP_URL') . '/folios',
    //   // 'visibility' => 'public',
    // ],
  ],

  'links' => [
    // public_path('folios') => storage_path('xml/folios'),
  ],
];
