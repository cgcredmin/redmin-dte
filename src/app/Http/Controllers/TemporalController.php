<?php

namespace App\Http\Controllers;
session_start();

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Tempfiles;

class TemporalController extends Controller
{
  private function returnError($m = '')
  {
    return response()->json(
      [
        'error' => $m . ': No se pudo obtener el archivo. ',
      ],
      400,
    );
  }

  public function getFile($hash)
  {
    $file = TempFiles::where('hash', trim($hash))->first();

    if (!$file) {
      return $this->returnError('No se encontró el archivo');
    }

    if ($file->expires_at < date('Y-m-d H:i:s')) {
      return $this->returnError('El link ha expirado');
    }

    if (Storage::disk('temp')->exists($file->ruta)) {
      $file = Storage::disk('temp')->get($file->ruta);
      $mime = Storage::disk('temp')->mimeType($file->ruta);

      return response($file, 200)->header('Content-Type', $mime);
    } else {
      return $this->returnError('No se encontró el archivo');
    }
  }
}
