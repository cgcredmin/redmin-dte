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
    Schema::create('tempfiles', function (Blueprint $table) {
      $table->id()->autoIncrement();
      $table->string('nombre');
      $table->mediumText('hash');
      $table->string('ext');
      $table->string('ruta');
      $table->dateTime('expires_at')->nullable(true);
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
    Schema::dropIfExists('tempfiles');
  }
};
