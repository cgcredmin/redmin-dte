<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class PdfController extends Controller
{
  public function getPdf(Request $request)
  {
    $this->validate($request, [
      'html' => 'required',
      'tipo_doc' => 'required',
    ]);
    $html_generado_b64 = $request->input('html');
    $html_generado = base64_decode($html_generado_b64);
    $tipo_doc = $request->input('tipo_doc');
    $ttt = time();
    $filename = $tipo_doc . $ttt . '.pdf';

    return Pdf::loadHTML($html_generado)->setPaper('Folio', 'portrait')
      ->setWarnings(false)->stream($filename);
  }
}
