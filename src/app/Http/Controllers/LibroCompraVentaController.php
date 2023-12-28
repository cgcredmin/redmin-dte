<?php

namespace App\Http\Controllers;

ini_set('memory_limit', '512M');

use App\Http\Traits\DteDatosTrait;
use Illuminate\Http\Request;
use App\Models\RegistroCompraVenta;
use Carbon\Carbon;
use sasco\LibreDTE\Sii\Dte\Base\DteImpreso;

class LibroCompraVentaController extends Controller
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

    $compras = RegistroCompraVenta::where('registro', 'compra');

    $this->tableFilters($request, $compras);

    $compras = $compras->orderBy('detFchDoc')->get();

    return response()->json($compras);
  }

  public function getVentas(Request $request)
  {
    $this->validate($request, $this->getRules());

    $ventas = RegistroCompraVenta::where('registro', 'venta');

    $this->tableFilters($request, $ventas);

    $ventas = $ventas->orderBy('detFchDoc', 'ASC')->get();

    return response()->json($ventas);
  }

  private function tableFilters($request, &$table)
  {
    $hasFilters = false;

    //filters rut,folio,tipo,estado,desde,hasta
    if ($request->has('rut') && $request->rut != '') {
      $table = $table->where('detRutDoc', $request->rut);
      $hasFilters = true;
    }

    if ($request->has('folio') && $request->folio != '') {
      $table = $table->where('detNroDoc', $request->folio);
      $hasFilters = true;
    }

    if ($request->has('tipo') && $request->tipo != '') {
      $table = $table->where('detTipoDoc', $request->tipo);
      $hasFilters = true;
    }

    if ($request->has('desde') && $request->desde != '') {
      // format input date to YYYY-MM-DD using carbon
      $desde = Carbon::parse($request->desde)->format('Y-m-d');
      $table = $table->whereDate('detFchDoc', '>=', $desde);
      $hasFilters = true;
    }

    if ($request->has('hasta') && $request->hasta != '') {
      // format input date to YYYY-MM-DD
      $hasta = Carbon::parse($request->hasta)->format('Y-m-d');
      $table = $table->whereDate('detFchDoc', '<=', $hasta);
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
        "DATE_FORMAT(detFchDoc, '%Y-%m') = '{$request->periodo}'",
      );
      $hasFilters = true;
    }

    // if no filter, then return the last 50 records
    if ($hasFilters === false) {
      $table = $table->take(20);
    }
  }
}
