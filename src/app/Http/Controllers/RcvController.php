<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\Log;

use App\Models\RegistroCompraVenta;

class RcvController extends Controller
{
  public function upload(Request $request)
  {
    $rules = [
      "data" => "required",
    ];

    $this->validate($request, $rules);

    try {
      $decoded = base64_decode(trim($request->data));
      $json = json_decode($decoded);

      //if there is no compra or venta, return
      if (!isset($json->COMPRA) && !isset($json->VENTA)) {
        return response()->json(["message" => "No hay datos"], 400);
      }

      $compras = $json->COMPRA ?? [];
      $ventas = $json->VENTA ?? [];

      $creados = 0;
      $actualizados = 0;
      // COMPRAS
      foreach ($compras as $value) {
        self::parseDates($value);
        $value->registro = "compra";

        $x = RegistroCompraVenta::updateOrCreate(
          [
            "dhdrCodigo" => $value->dhdrCodigo,
            "detCodigo" => $value->detCodigo,
            "detNroDoc" => $value->detNroDoc,
            "registro" => $value->registro,
          ],
          collect($value)->toArray()
        );

        self::updateCounters($creados, $actualizados, $x);
      }

      // VENTAS
      foreach ($ventas as $value) {
        self::parseDates($value);
        $value->registro = "venta";

        $x = RegistroCompraVenta::updateOrCreate(
          [
            "dhdrCodigo" => $value->dhdrCodigo,
            "detCodigo" => $value->detCodigo,
            "detNroDoc" => $value->detNroDoc,
            "registro" => $value->registro,
          ],
          collect($value)->toArray()
        );

        self::updateCounters($creados, $actualizados, $x);
      }

      $total = $creados + $actualizados;

      $data = [
        "Creados" => $creados,
        "Actualizados" => $actualizados,
        "Total" => $total . " de " . count($compras) . " recibidos",
      ];
      Log::create([
        "message" => "Registro de Compra y Venta recibido: " . implode(", ", $data),
      ]);

      return response()->json($data, 200);
    } catch (\Exception $e) {
      Log::create([
        "message" =>
          "Error al recibir el registro de compra y venta: " . $e->getMessage(),
      ]);
      return response()->json($e->getMessage(), 400);
    }
  }

  private function parseDates(&$value)
  {
    $value->detFchDoc = $value->detFchDoc
      ? Carbon::createFromFormat("d/m/Y", $value->detFchDoc)->format("Y-m-d H:i:s")
      : null;
    $value->detFecAcuse = $value->detFecAcuse
      ? Carbon::createFromFormat("d/m/Y H:i:s", $value->detFecAcuse)->format(
        "Y-m-d H:i:s"
      )
      : null;
    $value->detFecReclamado = $value->detFecReclamado
      ? Carbon::createFromFormat("d/m/Y H:i:s", $value->detFecReclamado)->format(
        "Y-m-d H:i:s"
      )
      : null;
    $value->detFecRecepcion = $value->detFecRecepcion
      ? Carbon::createFromFormat("d/m/Y H:i:s", $value->detFecRecepcion)->format(
        "Y-m-d H:i:s"
      )
      : null;
    $value->fechaActivacionAnotacion = $value->fechaActivacionAnotacion
      ? Carbon::createFromFormat("d/m/Y H:i:s", $value->fechaActivacionAnotacion)->format(
        "Y-m-d H:i:s"
      )
      : null;
  }

  private function updateCounters(&$creados, &$actualizados, $x)
  {
    if ($x->wasRecentlyCreated) {
      $creados++;
    } else {
      $actualizados++;
    }
  }
}
