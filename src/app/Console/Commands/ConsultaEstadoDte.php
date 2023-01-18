<?php

namespace App\Console\Commands;

use App\Console\Commands\ComandoBase;

use App\Models\RegistroCompraVenta;
use App\Models\ComprobacionSii;
use sasco\LibreDTE\Sii\Autenticacion;
use App\Http\Traits\DteAuthTrait;
use sasco\LibreDTE\Sii;

use App\Models\Log;

class ConsultaEstadoDte extends ComandoBase
{
  use DteAuthTrait;
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = "redmin:estadodte";

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = "Consulta el estado de los DTE en el SII";

  /**
   * Create a new command instance.
   *
   * @return void
   */
  public function __construct()
  {
    parent::__construct();
  }

  /**
   * Execute the console command.
   *
   * @return mixed
   */
  public function handle()
  {
    if (!$this->firma) {
      $this->error("No se ha configurado la firma electrónica");
      exit(1);
    }

    $starttime = date("H:i:s");
    $startday = date("Y-m-d");
    $message = "ESTADODTE::{$startday}/{$starttime}/{{END}}::Consulta de DTEs finalizada";

    try {
      $token_path = $this->rutas->tmp . "token.txt";
      $now = date("Ymd");
      $token = "";

      dd($this->dteconfig);
      $token = Autenticacion::getToken($this->dteconfig);
      if ($token == "" || $token == null || $token == false) {
        $this->error("No se pudo obtener el token");
        Log::create("ESTADODTE::Error::No se pudo obtener el token");
        exit(1);
      }
      file_put_contents($token_path, "$token;$now");

      $comprobaciones = collect(ComprobacionSii::all())->pluck(
        "registro_compra_venta_id"
      );
      // dd($comprobaciones);
      //Obtiene todos los DTE que no tiene registro de consulta
      $dtes = RegistroCompraVenta::whereNotIn("id", $comprobaciones)->get();
      // dd($dtes);
      $this->info("DTEs a consultar: " . $dtes->count());

      for ($i = 0; $i < $dtes->count(); $i++) {
        $this->info("Consultando DTE: " . $dtes[$i]->id);
        $estado = $this->getEstado($dtes[$i], $token);
        $this->info("Estado: " . $estado);
      }
    } catch (\Exception $e) {
      $this->error($e->getMessage());
      $errores = "";
      // si hubo errores mostrar
      foreach (\sasco\LibreDTE\Log::readAll() as $error) {
        $this->error($error->msg);
        $errores .= "- $error->msg\n";
      }

      $message .= " con errores.\n\n$errores";
    }

    $message = str_replace("{{END}}", date("H:i:s"), $message);
    $this->info($message);
    Log::create([
      "message" => $message,
    ]);
  }

  private function getEstado($rcv, $token)
  {
    //get rutEmpresa without dv
    $dvEmpresa = substr($this->rutEmpresa, -1);
    $rutEmpresa = substr($this->rutEmpresa, 0, -2);

    //get rutConsultante without dv
    $dvContultante = substr($this->rutCert, -1);
    $rutConsultante = substr($this->rutCert, 0, -2);

    $fecha_dmY = date("d/m/Y", strtotime($rcv->detFchDoc));

    $xml = Sii::request("QueryEstDte", "getEstDte", [
      "RutConsultante" => $rutConsultante,
      "DvConsultante" => $dvContultante,
      "RutCompania" => $rcv->detRutDoc,
      "DvCompania" => $rcv->detDvDoc,
      "RutReceptor" => $rutEmpresa,
      "DvReceptor" => $dvEmpresa,
      "TipoDte" => $rcv->detTipoDoc,
      "FolioDte" => $rcv->detNroDoc,
      "FechaEmisionDte" => $fecha_dmY,
      "MontoDte" => $rcv->detMntNeto,
      "token" => $token,
    ]);

    if ($xml !== false) {
      $result = $xml->xpath("/SII:RESPUESTA/SII:RESP_HDR")[0];

      if (
        $result->ERR_CODE > 15 ||
        $result->ERR_CODE < 0 ||
        intval($result->ESTADO) > 0
      ) {
        dd($result);
        return "ERR:" . $result->ERR_CODE;
      }

      if ($rcv) {
        $comprobacion = ComprobacionSii::where(
          "registro_compra_venta_id",
          $rcv->id
        )->first();
        if ($comprobacion) {
          $comprobacion->estado = $result->ESTADO;
          $comprobacion->glosa_estado = $result->GLOSA_ESTADO;
          $comprobacion->error = $result->ERR_CODE;
          $comprobacion->glosa_error = $result->GLOSA_ERR;
          $comprobacion->fecha_consulta = date("Y-m-d H:i:s");
          // $comprobacion->xml = $xml->saveXML();
          $comprobacion->save();
        } else {
          $comprobacion = new ComprobacionSii();
          $comprobacion->registro_compra_venta_id = $rcv->id;
          $comprobacion->estado = $result->ESTADO;
          $comprobacion->glosa_estado = $result->GLOSA_ESTADO;
          $comprobacion->error = $result->ERR_CODE;
          $comprobacion->glosa_error = $result->GLOSA_ERR;
          $comprobacion->fecha_consulta = date("Y-m-d H:i:s");
          // $comprobacion->xml = $xml->saveXML();
          $comprobacion->save();
        }

        return $result->ESTADO;
      }
    }
    return false;
  }
}
