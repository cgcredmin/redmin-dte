<?php

namespace App\Http\Controllers;

use App\Http\Traits\DteDatosTrait;
use Illuminate\Http\Request;
use App\Models\Compras;
use Carbon\Carbon;
use sasco\LibreDTE\Sii\Dte\Base\DteImpreso;
use App\Models\Tempfiles;

class ComprasController extends Controller
{
  use DteImpreso;
  use DteDatosTrait;

  private function getTiposImploded()
  {
    return implode(
      ',',
      collect($this->tipos)
        ->except(0)
        ->keys()
        ->toArray(),
    );
  }
  private function getRules()
  {
    return [
      'rut' => 'numeric|exists:registro_compra_venta,detRutDoc',
      'folio' => 'numeric|exists:registro_compra_venta,detNroDoc',
      'tipo' => 'numeric|in:' . $this->getTiposImploded(),
      'estado' => 'string|in:' . implode(',', $this->getKeysDteEstados()),
      'desde' => 'date',
      'hasta' => 'date',
      'periodo' => 'date_format:Y-m',
    ];
  }

  private function getMessages()
  {
    return [
      'rut.exists' => 'El rut no existe en la base de datos',
      'folio.exists' => 'El folio no existe en la base de datos',
      'tipo.in' => 'El tipo de documento no es válido',
      'estado.in' => 'El estado no es válido',
      'desde.date' => 'El campo desde debe ser una fecha válida',
      'hasta.date' => 'El campo hasta debe ser una fecha válida',
      'periodo.date_format' => 'El campo periodo debe ser una fecha válida',
    ];
  }

  public function getCompras(Request $request)
  {
    $this->validate($request, $this->getRules(), $this->getMessages());

    $compras = Compras::where('id', '>', 0);

    $hasFilters = $this->tableFilters($request, $compras);

    if (!$hasFilters) {
      $compras = Compras::orderBy('fecha_emision', 'desc')->get();
    } else {
      $compras = $compras->orderBy('fecha_emision')->get();
    }

    if (count($compras) > 0) {
      $key = $this->getKey();
      $compras->map(function ($c) use ($key) {
        $c->pdf = "file/$c->pdf?key=$key&ext=pdf";
        $c->xml = "file/$c->xml?key=$key&ext=xml";
      });
    }

    return response()->json($compras);
  }

  private function getKey()
  {
    $linkVentas = TempFiles::where('nombre', 'LINK_VENTAS')
      ->where('expires_at', '>', Carbon::now()->addHours(2))
      ->where('ext', 'pdf;xml')
      ->first();

    if (!$linkVentas) {
      $key = str_random(64);
      $linkVentas = Tempfiles::create([
        'nombre' => 'LINK_VENTAS',
        'hash' => $key,
        'ext' => 'pdf;xml',
        'ruta' => $this->rutas->pdf . ';' . $this->rutas->dte_ci,
        'expires_at' => Carbon::now()->addDays(1),
      ]);
    } else {
      $key = $linkVentas->hash;
    }

    return $key;
  }

  private function tableFilters($request, &$table)
  {
    $hasFilters = false;

    //filters rut,folio,tipo,estado,desde,hasta
    if ($request->has('rut') && $request->rut != '') {
      $table = $table->where('rut_emisor', $request->rut);
      $hasFilters = true;
    }

    if ($request->has('folio') && $request->folio != '') {
      $table = $table->where('folio', $request->folio);
      $hasFilters = true;
    }

    if ($request->has('tipo') && $request->tipo != '') {
      $table = $table->where('tipo_dte', $request->tipo);
      $hasFilters = true;
    }

    if ($request->has('desde') && $request->desde != '') {
      // format input date to YYYY-MM-DD using carbon
      $desde = Carbon::parse($request->desde)->format('Y-m-d');
      $table = $table->whereDate('fecha_emision', '>=', $desde);
      $hasFilters = true;
    }

    if ($request->has('hasta') && $request->hasta != '') {
      // format input date to YYYY-MM-DD
      $hasta = Carbon::parse($request->hasta)->format('Y-m-d');
      $table = $table->whereDate('fecha_emision', '<=', $hasta);
      $hasFilters = true;
    }

    if ($request->has('estado') && $request->estado != '') {
      $estado = $request->estado;
      $table = $table->with([
        'comprobacion_sii' => function ($q) use ($estado) {
          $q->where('estado', $estado);
        },
      ]);
      $hasFilters = true;
    } else {
      $table = $table->with('comprobacion_sii');
    }

    if ($request->has('periodo')) {
      $table = $table->whereRaw(
        "DATE_FORMAT(fecha_emision, '%Y-%m') = '{$request->periodo}'",
      );
      $hasFilters = true;
    }

    // if no filter, then return the last 50 records
    if ($hasFilters === false) {
      $table = $table->take(20);
    }

    return $hasFilters;
  }

  public function getPdf($hash)
  {
    $compra = Compras::where('pdf', $hash)->first();
    $filePath = $this->rutas->pdf . $compra->pdf . '.pdf';

    if (!file_exists($filePath)) {
      return response()->json(['error' => 'No se encontró el archivo'], 404);
    }

    $pdf = file_get_contents($filePath);

    return response($pdf, 200, [
      'Content-Type' => 'application/pdf',
      'Content-Disposition' => 'inline; filename="dte.pdf"',
    ]);
  }
}
