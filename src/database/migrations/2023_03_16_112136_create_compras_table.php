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
    Schema::create('compras', function (Blueprint $table) {
      $table->id();
      $table->string('rut_emisor')->comment('RUT del emisor del DTE');
      $table
        ->string('razon_social_emisor')
        ->comment('Razón social del emisor del DTE');
      $table->string('rut_receptor')->comment('RUT del receptor del DTE');
      $table->date('fecha_emision')->comment('Fecha de emisión del DTE');
      $table
        ->datetime('fecha_recepcion')
        ->comment('Fecha de recepción del DTE');
      $table->double('monto_neto')->comment('Monto neto del DTE');
      $table->double('iva')->comment('IVA del DTE');
      $table->double('monto_exento')->comment('Monto exento del DTE');
      $table->double('monto_total')->comment('Monto total del DTE');
      $table
        ->mediumText('detalle')
        ->nullable()
        ->comment('Detalle del DTE en JSON');
      $table->integer('tipo_dte')->comment('Tipo de DTE');
      $table->integer('folio')->comment('Folio del DTE');
      $table->date('fecha_resolucion')->comment('Fecha de resolución del SII');
      $table
        ->mediumText('xml')
        ->nullable()
        ->comment('Ruta del XML');
      $table
        ->mediumText('pdf')
        ->nullable()
        ->comment('Ruta del pdf');
      $table
        ->unsignedInteger('comprobacion_sii_id')
        ->nullable()
        ->comment('ID de la comprobación del SII');
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
    Schema::dropIfExists('compras');
  }
};
