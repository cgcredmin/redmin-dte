<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;
use sasco\LibreDTE\Sii\Autenticacion;
use App\Http\Traits\DteAuthTrait;
use App\Http\Traits\DteParsearErroresTrait;

class Controller extends BaseController
{
  use DteAuthTrait;
  use DteParsearErroresTrait;

  public function renewToken()
  {
    try {
      $token = $this->token(true);

      if (is_string($token)) {
        return response()->json(['token' => $token], 200);
      }

      return $token;
    } catch (\Exception $e) {
      // si hubo errores se muestran
      return response()->json(\sasco\LibreDTE\Log::readAll(), 400);
    }
    return response()->json(['error' => ''], 400);
  }

  public function encodeObjectToUTF8($object)
  {
    return $object;
    foreach ($object as $key => $value) {
      if (is_array($value)) {
        $object[$key] = $this->encodeObjectToUTF8($value);
      } elseif (is_string($value)) {
        $object[$key] = utf8_encode($value);
      }
    }
    return $object;
  }
}
