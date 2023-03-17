<?php

namespace App\Http\Controllers;
session_start();

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Tempfiles;
use App\Models\Compras;

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

  public function getFile($hash, Request $request)
  {
    if ($request->has('key')) {
      $file = TempFiles::where('hash', trim($request->key))->first();
    } else {
      $file = TempFiles::where('hash', trim($hash))->first();
    }

    if (!$file) {
      return $this->returnError('No se encontró el archivo');
    }

    if ($file->expires_at < date('Y-m-d H:i:s')) {
      return $this->returnError('El link ha expirado');
    }

    if ($file->nombre == 'LINK_VENTAS') {
      $ext = $request->ext ?? 'none';

      if (!in_array($ext, ['pdf', 'xml'])) {
        return $this->returnError(
          'El tipo de archivo no es válido o el link ya expiró',
        );
      }

      $compra = Compras::where($ext, $hash)->first();
      if (!$compra) {
        return $this->returnError(
          'No se encontró el ocumento PDF, asegúrese de que el link sea correcto',
        );
      }

      if ($ext === 'pdf') {
        $content = file_get_contents($this->rutas->pdf . $compra->pdf . '.pdf');
        return response($content, 200)->header(
          'Content-Type',
          'application/pdf',
        );
      } else {
        $content = file_get_contents(
          $this->rutas->dte_ci . $compra->xml . '.xml',
        );
        return response($content, 200)->header(
          'Content-Type',
          'application/xml',
        );
      }
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
