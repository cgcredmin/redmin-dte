<?php
if (!function_exists('config_path')) {
  /**
   * Get the configuration path.
   *
   * @param  string $path
   * @return string
   */
  function config_path($path = '')
  {
    return app()->basePath() . '/config' . ($path ? '/' . $path : $path);
  }
}

if (!function_exists('public_path')) {
  /**
   * Return the path to public dir
   *
   * @param null $path
   *
   * @return string
   */
  function public_path($path = null)
  {
    return rtrim(app()->basePath('public/' . $path), '/');
  }
}

if (!function_exists('str_random')) {
  /**
   * Generate a "random" alpha-numeric string.
   *
   * Should not be considered sufficient for cryptography, etc.
   *
   * @param  int $length
   * @return string
   */
  function str_random($length = 16)
  {
    $string = '';

    while (($len = strlen($string)) < $length) {
      $size = $length - $len;

      $bytes = random_bytes($size);

      $string .= substr(
        str_replace(['/', '+', '='], '', base64_encode($bytes)),
        0,
        $size,
      );
    }

    return $string;
  }
}

function setDirectories()
{
  $stringified = json_encode([
    'folios' => config('libredte.archivos_xml') . 'folios/',
    'dte' => config('libredte.archivos_xml') . 'dte/',
    'dte_ci' => config('libredte.archivos_xml') . 'dte/correointercambio/',
    'certificacion' => config('libredte.archivos_certificacion'),
    'intercambio' => config('libredte.archivos_certificacion') . 'intercambio/',
    'tmp' => config('libredte.archivos_tmp'),
    'pdf' => '/var/www/html/storage/app/pdf/',
    'db' => '/var/www/html/storage/app/db/',
    'tempfiles' => '/var/www/html/storage/app/tempfiles/',
  ]);

  return json_decode($stringified, false);
}
