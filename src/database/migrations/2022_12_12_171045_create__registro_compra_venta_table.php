<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  /**
   * Run the migrations.
   *
   * @return void
   */
  public function up()
  {
    Schema::create('registro_compra_venta', function (Blueprint $table) {
      $table->id()->autoIncrement();
      $table->string('registro')->default('compra');
      $table->string('tipo')->default('registro');

      $table->string('dhdrCodigo')->nullable();
      $table->string('dcvCodigo')->nullable();
      $table->string('dcvEstadoContab')->nullable();
      $table->string('detCodigo')->nullable();
      $table->string('detTipoDoc')->nullable();
      $table->string('detRutDoc')->nullable();
      $table->string('detDvDoc')->nullable();
      $table->string('detRznSoc')->nullable();
      $table->string('detNroDoc')->nullable();
      $table->date('detFchDoc')->nullable();
      $table->datetime('detFecAcuse')->nullable();
      $table->datetime('detFecReclamado')->nullable();
      $table->datetime('detFecRecepcion')->nullable();
      $table->string('detMntExe')->nullable();
      $table->string('detMntNeto')->nullable();
      $table->string('detMntActFijo')->nullable();
      $table->string('detMntIVAActFijo')->nullable();
      $table->string('detMntIVANoRec')->nullable();
      $table->string('detMntCodNoRec')->nullable();
      $table->string('detMntSinCredito')->nullable();
      $table->string('detMntIVA')->nullable();
      $table->string('detMntTotal')->nullable();
      $table->string('detTasaImp')->nullable();
      $table->string('detAnulado')->nullable();
      $table->string('detIVARetTotal')->nullable();
      $table->string('detIVARetParcial')->nullable();
      $table->string('detIVANoRetenido')->nullable();
      $table->string('detIVAPropio')->nullable();
      $table->string('detIVATerceros')->nullable();
      $table->string('detIVAUsoComun')->nullable();
      $table->string('detLiqRutEmisor')->nullable();
      $table->string('detLiqDvEmisor')->nullable();
      $table->string('detLiqValComNeto')->nullable();
      $table->string('detLiqValComExe')->nullable();
      $table->string('detLiqValComIVA')->nullable();
      $table->string('detIVAFueraPlazo')->nullable();
      $table->string('detTipoDocRef')->nullable();
      $table->string('detFolioDocRef')->nullable();
      $table->string('detExpNumId')->nullable();
      $table->string('detExpNacionalidad')->nullable();
      $table->string('detCredEc')->nullable();
      $table->string('detLey18211')->nullable();
      $table->string('detDepEnvase')->nullable();
      $table->string('detIndSinCosto')->nullable();
      $table->string('detIndServicio')->nullable();
      $table->string('detMntNoFact')->nullable();
      $table->string('detMntPeriodo')->nullable();
      $table->string('detPsjNac')->nullable();
      $table->string('detPsjInt')->nullable();
      $table->string('detNumInt')->nullable();
      $table->string('detCdgSIISucur')->nullable();
      $table->string('detEmisorNota')->nullable();
      $table->string('detTabPuros')->nullable();
      $table->string('detTabCigarrillos')->nullable();
      $table->string('detTabElaborado')->nullable();
      $table->string('detImpVehiculo')->nullable();
      $table->string('detTpoImp')->nullable();
      $table->string('detTipoTransaccion')->nullable();
      $table->string('detEventoReceptor')->nullable();
      $table->string('detEventoReceptorLeyenda')->nullable();
      $table->string('cambiarTipoTran')->nullable();
      $table->string('detPcarga')->nullable();
      $table->string('descTipoTransaccion')->nullable();
      $table->string('totalDtoiMontoImp')->nullable();
      $table->string('totalDinrMontoIVANoR')->nullable();
      $table->string('emisorAgresivo')->nullable();
      $table->datetime('fechaActivacionAnotacion')->nullable();

      $table->timestamps();
    });
  }

  /**
   * Reverse the migrations.
   *
   * @return void
   */
  public function down()
  {
    Schema::dropIfExists('registro_compra_venta');
  }
};
