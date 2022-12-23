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
    Schema::create('comprobacion_sii', function (Blueprint $table) {
      $table->id();
      $table->unsignedBigInteger('registro_compra_venta_id');
      $table
        ->string('estado')
        ->default('')
        ->comment('Estado de la comprobación');
      $table
        ->string('glosa_estado')
        ->default('')
        ->comment('Glosa de la comprobación');
      $table
        ->string('error')
        ->nullable()
        ->comment('Error de la comprobación');
      $table
        ->string('glosa_error')
        ->nullable()
        ->comment('Glosa del error');
      $table
        ->date('fecha_consulta')
        ->nullable()
        ->comment('Fecha de consulta al SII');
      $table
        ->mediumText('xml')
        ->nullable()
        ->comment('XML generado por LibreDTE');
      $table
        ->string('pdf')
        ->nullable()
        ->comment('Ruta del pdf generado por LibreDTE');
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
    Schema::dropIfExists('comprobacion_sii');
  }
};
