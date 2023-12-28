<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegistroCompraVenta extends Model
{
  protected $table = "registro_compra_venta";

  protected $fillable = [
    "dhdrCodigo",
    "dcvCodigo",
    "dcvEstadoContab",
    "detCodigo",
    "detTipoDoc",
    "detRutDoc",
    "detDvDoc",
    "detRznSoc",
    "detNroDoc",
    "detFchDoc",
    "detFecAcuse",
    "detFecReclamado",
    "detFecRecepcion",
    "detMntExe",
    "detMntNeto",
    "detMntActFijo",
    "detMntIVAActFijo",
    "detMntIVANoRec",
    "detMntCodNoRec",
    "detMntSinCredito",
    "detMntIVA",
    "detMntTotal",
    "detTasaImp",
    "detAnulado",
    "detIVARetTotal",
    "detIVARetParcial",
    "detIVANoRetenido",
    "detIVAPropio",
    "detIVATerceros",
    "detIVAUsoComun",
    "detLiqRutEmisor",
    "detLiqDvEmisor",
    "detLiqValComNeto",
    "detLiqValComExe",
    "detLiqValComIVA",
    "detIVAFueraPlazo",
    "detTipoDocRef",
    "detFolioDocRef",
    "detExpNumId",
    "detExpNacionalidad",
    "detCredEc",
    "detLey18211",
    "detDepEnvase",
    "detIndSinCosto",
    "detIndServicio",
    "detMntNoFact",
    "detMntPeriodo",
    "detPsjNac",
    "detPsjInt",
    "detNumInt",
    "detCdgSIISucur",
    "detEmisorNota",
    "detTabPuros",
    "detTabCigarrillos",
    "detTabElaborado",
    "detImpVehiculo",
    "detTpoImp",
    "detTipoTransaccion",
    "detEventoReceptor",
    "detEventoReceptorLeyenda",
    "cambiarTipoTran",
    "detPcarga",
    "descTipoTransaccion",
    "totalDtoiMontoImp",
    "totalDinrMontoIVANoR",
    "emisorAgresivo",
    "fechaActivacionAnotacion",
    "registro",
    "tipo",
  ];

  //parse dates with format d/m/Y H:i:s
  protected $dates = [
    "detFchDoc",
    "detFecAcuse",
    "detFecReclamado",
    "detFecRecepcion",
    "fechaActivacionAnotacion",
  ];

  //casts dates to Carbon with given format
  protected $casts = [
    "detFchDoc" => "datetime:d/m/Y",
    "detFecAcuse" => "datetime:d/m/Y H:i:s",
    "detFecReclamado" => "datetime:d/m/Y H:i:s",
    "detFecRecepcion" => "datetime:d/m/Y H:i:s",
    "fechaActivacionAnotacion" => "datetime:d/m/Y H:i:s",
  ];

  public function comprobacion_sii()
  {
    return $this->hasOne("App\Models\ComprobacionSii");
  }

  public function contribuyente()
  {
    return $this->belongsTo("App\Models\Contribuyentes", "detRutDoc", "rut");
  }
}
