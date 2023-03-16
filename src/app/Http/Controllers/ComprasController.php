<?php

namespace App\Http\Controllers;

use App\Http\Traits\DteDatosTrait;
use Illuminate\Http\Request;
use App\Models\Compras;
use Carbon\Carbon;
use sasco\LibreDTE\Sii\Dte\Base\DteImpreso;

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

  public function getCompras(Request $request)
  {
    $this->validate($request, $this->getRules());

    $compras = Compras::where('id', '>', 0);

    $this->tableFilters($request, $compras);

    $compras = $compras->orderBy('fecha_emision')->get();

    return response()->json($compras);
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
  }
}
