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
    ];

    $this->validate($request, $rules);

    // dd($request->all());
    $compras = RegistroCompraVenta::whereRaw("registro = 'compra'");

    $hasFilters = false;

    //filters rut,folio,tipo,estado,desde,hasta
    if ($request->has("rut") && $request->rut != "") {
      $compras = $compras->where("detRutDoc", $request->input("rut"));
      $hasFilters = true;
    }
    if ($request->has("folio") && $request->folio != "") {
      $compras = $compras->where("detNroDoc", $request->input("folio"));
      $hasFilters = true;
    }
    if ($request->has("tipo") && $request->tipo != "") {
      $compras = $compras->where("detTipoDoc", $request->input("tipo"));
      $hasFilters = true;
    }
    if ($request->has("desde") && $request->desde != "") {
      // format input date to YYYY-MM-DD using carbon
      $desde = Carbon::parse($request->input("desde"))->format("Y-m-d");
      $compras = $compras->whereRaw(
        "DATE(detFchDoc) >= DATE('$desde 00:00:00                                                                                                               ')"
      );
      $hasFilters = true;
    }
    if ($request->has("hasta") && $request->hasta != "") {
      // format input date to YYYY-MM-DD
      $hasta = Carbon::parse($request->input("hasta"))->format("Y-m-d");
      $compras = $compras->whereRaw("DATE(detFchDoc) <= DATE('$hasta')");
      $hasFilters = true;
    }

    if ($request->has("estado") && $request->estado != "") {
      $estado = $request->estado;
      $compras = $compras->with([
        "comprobacion_sii" => function ($q) use ($estado) {
          $q->where("estado", $estado);
        },
      ]);
      $hasFilters = true;
    } else {
      $compras = $compras->with("comprobacion_sii");
    }

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
    $ventas = RegistroCompraVenta::where("registro", "venta");
    $hasFilter = false;
    if ($request->has("dcvRutEmisor")) {
      $ventas = $ventas->where("dcvRutEmisor", $request->input("dcvRutEmisor"));
      $hasFilter = true;
    }
    if ($request->has("rsmnTipoDocInteger")) {
      $ventas = $ventas->where(
        "rsmnTipoDocInteger",
        $request->input("rsmnTipoDocInteger")
      );
      $hasFilter = true;
    }
    if ($request->has("dcvFecCreacion")) {
      $ventas = $ventas->where("dcvFecCreacion", ">=", $request->input("dcvFecCreacion"));
      $hasFilter = true;
    }
    if ($request->has("periodo")) {
      // validate periodo has the format YYYY-MM
      $this->validate($request, [
        "periodo" => "required|date_format:Y-m",
      ]);
      $fecha = $request->periodo . "-01 00:00:00";
      $ventas = $ventas->where("dcvFecCreacion", ">=", $fecha);
      $hasFilter = true;
    }

    if (!$hasFilter) {
      // if no filter, then return the last 50 records
      $ventas = $ventas->take(50);
    }

    $ventas = $ventas->orderBy("dcvFecCreacion", "ASC")->get();
    return response()->json($ventas);
  }
}
