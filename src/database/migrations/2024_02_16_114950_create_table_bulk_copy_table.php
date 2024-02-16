<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Run the migrations.
   *
   * @return void
   */
  public function up()
  {
    Schema::create('bulkCopyTable', function (Blueprint $table) {
      $table->string('field_1')->nullable();
      $table->string('field_2')->nullable();
      $table->string('field_3')->nullable();
      $table->string('field_4')->nullable();
      $table->string('field_5')->nullable();
      $table->string('field_6')->nullable();
      $table->string('field_7')->nullable();
      $table->string('field_8')->nullable();
      $table->string('field_9')->nullable();
      $table->string('field_10')->nullable();
      $table->id();
    });
  }

  /**
   * Reverse the migrations.
   *
   * @return void
   */
  public function down()
  {
    Schema::dropIfExists('bulkCopyTable');
  }
};
