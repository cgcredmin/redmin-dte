<?php

namespace App\Http\Traits;

trait DteParsearErroresTrait
{
  public function parsearErrores($codigos = [])
  {
    if (count($codigos) > 0) {
      $error = '';
      $errors = collect(\sasco\LibreDTE\Log::readAll())->filter(function (
        $e
      ) use ($codigos) {
        return in_array(intval($e->code), $codigos);
      });

      if (count($errors) > 0) {
        $error = $errors
          ->map(function ($error) {
            return $error->msg;
          })
          ->implode(".\n", $errors);
      } else {
        $error = 'Error no identificado.';
      }
      return $error;
    }
    return false;
  }
}
