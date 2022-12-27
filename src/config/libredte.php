<?php

return [
  'firma' => [
    'file' => '/var/www/html/storage/app/certs/cert.p12',
    //'data' => '', // contenido del archivo certificado.p12
    // 'pass' => env('CERT_PASS', 'my_password'),
    'pem' => '/var/www/html/storage/app/certs/cert.pem',
  ],
  'servidor' => env('SII_SERVER', 'maullin'),
  'ambiente' => env('SII_ENV', 'certificacion'),
  //Storage paths
  'archivos_xml' => '/var/www/html/storage/app/xml/',
  'archivos_tmp' => '/var/www/html/storage/app/tmp/',
  'archivos_certificacion' => '/var/www/html/storage/app/certificacion/',
];
