<?php

namespace App\Http\Controllers;

use App\Http\Traits\DteDatosTrait;
use Illuminate\Http\Request;
use App\Models\RegistroCompraVenta;
use Carbon\Carbon;
use sasco\LibreDTE\Sii\Dte\Base\DteImpreso;

class LibroCompraVentaController extends Controller
{
  use DteImpreso;
  use DteDatosTrait;

  public function getCompras(Request $request)
  {
    $tipos = implode(
      ",",
      collect($this->tipos)
        ->except(0)
        ->keys()
        ->toArray()
    );
    $rules = [
      "rut" => "numeric|exists:registro_compra_venta,detRutDoc",
      "folio" => "numeric|exists:registro_compra_venta,detNroDoc",
      "tipo" => "numeric|in:" . $tipos,
      "estado" => "string|in:" . implode(",", $this->getKeysDteEstados()),
      "desde" => "date",
      "hasta" => "date",
      "periodo" => "date_format:Y-m",
    ];

    $this->validate($request, $rules);

    $compras = RegistroCompraVenta::whereRaw("registro = 'compra'");
    $hasFilters = false;

    $this->filterTable($request, $compras, $hasFilters);

    if (!$hasFilters) {
      // if no filter, then return the last 50 records
      $compras = $compras->take(50);
    }

    $compras = $compras->orderBy("detFchDoc")->get();
    // dd($compras);
    // parse some columns
    foreach ($compras as $compra) {
      $compra->detTipoDoc = $this->tipos[$compra->detTipoDoc];
    }

    return response()->json($compras);
  }

  public function getVentas(Request $request)
  {
    $tipos = implode(
      ",",
      collect($this->tipos)
        ->except(0)
        ->keys()
        ->toArray()
    );
    $rules = [
      "rut" => "numeric|exists:registro_compra_venta,detRutDoc",
      "folio" => "numeric|exists:registro_compra_venta,detNroDoc",
      "tipo" => "numeric|in:" . $tipos,
      "estado" => "string|in:" . implode(",", $this->getKeysDteEstados()),
      "desde" => "date",
      "hasta" => "date",
      "periodo" => "date_format:Y-m",
    ];

    $this->validate($request, $rules);

    $ventas = RegistroCompraVenta::whereRaw("registro = 'venta'");
    $hasFilters = false;

    $this->filterTable($request, $ventas, $hasFilters);

    if (!$hasFilters) {
      // if no filter, then return the last 50 records
      $ventas = $ventas->take(50);
    }

    $ventas = $ventas->orderBy("detFchDoc", "ASC")->get();
    return response()->json($ventas);
  }

  private function filterTable($request, &$table, &$hasFilters)
  {
    //filters rut,folio,tipo,estado,desde,hasta
    if ($request->has("rut") && $request->rut != "") {
      $table = $table->where("detRutDoc", $request->rut);
      $hasFilters = true;
    }

    if ($request->has("folio") && $request->folio != "") {
      $table = $table->where("detNroDoc", $request->folio);
      $hasFilters = true;
    }

    if ($request->has("tipo") && $request->tipo != "") {
      $table = $table->where("detTipoDoc", $request->tipo);
      $hasFilters = true;
    }

    if ($request->has("desde") && $request->desde != "") {
      // format input date to YYYY-MM-DD using carbon
      $desde = Carbon::parse($request->desde)->format("Y-m-d");
      $table = $table->whereRaw("DATE(detFchDoc) >= '$desde'");
      $hasFilters = true;
    }

    if ($request->has("hasta") && $request->hasta != "") {
      // format input date to YYYY-MM-DD
      $hasta = Carbon::parse($request->hasta)->format("Y-m-d");
      $table = $table->whereRaw("DATE(detFchDoc) <= '$hasta'");
      $hasFilters = true;
    }

    if ($request->has("estado") && $request->estado != "") {
      $estado = $request->estado;
      $table = $table->with([
        "comprobacion_sii" => function ($q) use ($estado) {
          $q->where("estado", $estado);
        },
      ]);
      $hasFilters = true;
    } else {
      $table = $table->with("comprobacion_sii");
    }

    if ($request->has("periodo")) {
      $table = $table->whereRaw("strftime('%Y-%m', detFchDoc) = '{$request->periodo}'");
      $hasFilters = true;
    }
  }
}
