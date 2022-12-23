<?php

namespace App\Http\Controllers;

use App\Http\Traits\DteDatosTrait;
use Illuminate\Http\Request;
use App\Models\RegistroCompraVenta;

use sasco\LibreDTE\Sii\Dte\Base\DteImpreso;

class LibroCompraVentaController extends Controller
{
  use DteImpreso;
  use DteDatosTrait;

  public function getCompras(Request $request)
  {
    $tipos = implode(
      ',',
      collect($this->tipos)
        ->except(0)
        ->keys()
        ->toArray(),
    );
    $rules = [
      'rut' => 'numeric|exists:registro_compra_venta,detRutDoc',
      'folio' => 'numeric|exists:registro_compra_venta,detNroDoc',
      'tipo' => 'numeric|in:' . $tipos,
      'estado' => 'string|in:' . implode(',', $this->getKeysDteEstados()),
      'desde' => 'date',
      'hasta' => 'date',
    ];

    $this->validate($request, $rules);

    // dd($request->all());
    $compras = RegistroCompraVenta::where('registro', 'compra');

    //filters rut,folio,tipo,estado,desde,hasta
    if ($request->has('rut') && $request->rut != '') {
      $compras = $compras->where('detRutDoc', $request->input('rut'));
    }
    if ($request->has('folio') && $request->folio != '') {
      $compras = $compras->where('detNroDoc', $request->input('folio'));
    }
    if ($request->has('tipo') && $request->tipo != '') {
      $compras = $compras->where('detTipoDoc', $request->input('tipo'));
    }
    if ($request->has('desde') && $request->desde != '') {
      $compras = $compras->where('detFchDoc', '>=', $request->input('desde'));
    }
    if ($request->has('hasta') && $request->hasta != '') {
      $compras = $compras->where('detFchDoc', '<=', $request->input('hasta'));
    }

    if ($request->has('estado') && $request->estado != '') {
      $estado = $request->estado;
      $compras = $compras->with([
        'comprobacion_sii' => function ($q) use ($estado) {
          $q->where('estado', $estado);
        },
      ]);
    } else {
      $compras = $compras->with('comprobacion_sii');
    }

    $compras = $compras->get();
    //parse some columns
    foreach ($compras as $compra) {
      $compra->detTipoDoc = $this->tipos[$compra->detTipoDoc];
    }

    $compras = collect($compras);

    return response()->json($compras);
  }

  public function getVentas(Request $request)
  {
    $ventas = RegistroCompraVenta::where('registro', 'venta');
    if ($request->has('dcvRutEmisor')) {
      $ventas = $ventas->where('dcvRutEmisor', $request->input('dcvRutEmisor'));
    }
    if ($request->has('rsmnTipoDocInteger')) {
      $ventas = $ventas->where(
        'rsmnTipoDocInteger',
        $request->input('rsmnTipoDocInteger'),
      );
    }
    if ($request->has('dcvFecCreacion')) {
      $ventas = $ventas->where(
        'dcvFecCreacion',
        '>=',
        $request->input('dcvFecCreacion'),
      );
    }

    $ventas = $ventas->orderBy('dcvFecCreacion', 'ASC')->get();
    return response()->json($ventas);
  }
}
