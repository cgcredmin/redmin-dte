<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
  /**
   * Run the migrations.
   *
   * @return void
   */
  public function up()
  {
    Schema::create('config', function (Blueprint $table) {
      $table->id()->autoIncrement();
      $table
        ->string('SII_USER')
        ->default('-')
        ->comment('Usuario SII');
      $table
        ->string('SII_PASS')
        ->default('-')
        ->comment('Password SII');
      $table
        ->string('SII_SERVER')
        ->default('-')
        ->comment('Servidor SII');
      $table
        ->string('SII_ENV')
        ->default('-')
        ->comment('Ambiente de trabajo produccion o certificacion');
      $table
        ->string('CERT_PASS')
        ->default('-')
        ->comment('Password del certificado');
      $table
        ->string('DTE_RUT_CERT')
        ->default('-')
        ->comment('Rut del certificado');
      $table
        ->string('DTE_NOMBRE_CERT')
        ->default('-')
        ->comment('Nombre del certificado');
      $table
        ->string('DTE_RUT_EMPRESA')
        ->default('-')
        ->comment('Rut de la empresa');
      $table
        ->string('DTE_NOMBRE_EMPRESA')
        ->default('-')
        ->comment('Nombre de la empresa');
      $table->timestamps();
    });

    //init table with empty values
    DB::table('config')->insert([
      'SII_USER' => '',
      'SII_PASS' => '',
      'SII_SERVER' => 'maullin',
      'SII_ENV' => 'certificacion',
      'CERT_PASS' => '',
      'DTE_RUT_CERT' => '',
      'DTE_NOMBRE_CERT' => '',
      'DTE_RUT_EMPRESA' => '',
      'DTE_NOMBRE_EMPRESA' => '',
    ]);
  }

  /**
   * Reverse the migrations.
   *
   * @return void
   */
  public function down()
  {
    Schema::dropIfExists('config');
  }
};
