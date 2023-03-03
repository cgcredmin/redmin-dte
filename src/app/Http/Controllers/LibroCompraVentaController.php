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

  private $tipos = "";

  public function __construct()
  {
    $this->tipos = implode(
      ",",
      collect($this->tipos)
        ->except(0)
        ->keys()
        ->toArray()
    );
  }

  public function getCompras(Request $request)
  {
    $rules = [
      "rut" => "numeric|exists:registro_compra_venta,detRutDoc",
      "folio" => "numeric|exists:registro_compra_venta,detNroDoc",
      "tipo" => "numeric|in:" . $this->tipos,
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
    $rules = [
      "rut" => "numeric|exists:registro_compra_venta,detRutDoc",
      "folio" => "numeric|exists:registro_compra_venta,detNroDoc",
      "tipo" => "numeric|in:" . $this->tipos,
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
      $table = $table->where("detRutDoc", $request->input("rut"));
      $hasFilters = true;
    }

    if ($request->has("folio") && $request->folio != "") {
      $table = $table->where("detNroDoc", $request->input("folio"));
      $hasFilters = true;
    }

    if ($request->has("tipo") && $request->tipo != "") {
      $table = $table->where("detTipoDoc", $request->input("tipo"));
      $hasFilters = true;
    }

    if ($request->has("desde") && $request->desde != "") {
      // format input date to YYYY-MM-DD using carbon
      $desde = Carbon::parse($request->input("desde"))->format("Y-m-d");
      $table = $table->whereRaw(
        "DATE(detFchDoc) >= DATE('$desde 00:00:00                                                                                                               ')"
      );
      $hasFilters = true;
    }

    if ($request->has("hasta") && $request->hasta != "") {
      // format input date to YYYY-MM-DD
      $hasta = Carbon::parse($request->input("hasta"))->format("Y-m-d");
      $table = $table->whereRaw("DATE(detFchDoc) <= DATE('$hasta')");
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
      // validate periodo has the format YYYY-MM
      $this->validate($request, [
        "periodo" => "required|date_format:Y-m",
      ]);
      $startDate = $request->periodo . "-01 00:00:00";
      $endDate = $request->periodo . "-31 23:59:59";

      $table = $table->whereRaw(
        "DATE(detFchDoc) >= DATE('$startDate') AND DATE(detFchDoc) <= DATE('$endDate)"
      );
      $hasFilters = true;
    }
  }
}
