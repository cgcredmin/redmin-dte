<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  //  Rut	  78884190-6
  // Razón Social/Nombres	  HAYDEE VIDAL SPA
  // N° Resolución	  80
  // Fecha Resolución	  22-08-2014
  // Dirección Regional	  XIII
  /**
   * Run the migrations.
   *
   * @return void
   */
  public function up()
  {
    Schema::create('contribuyentes', function (Blueprint $table) {
      $table->id()->autoIncrement();
      $table->string('rut');
      $table->string('dv');
      $table->string('razon_social');
      $table->string('nro_resolucion');
      $table->string('fecha_resolucion');
      $table->string('direccion_regional');
      $table->string('correo');
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
    Schema::dropIfExists('contribuyentes');
  }
};
