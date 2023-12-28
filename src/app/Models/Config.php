<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Config extends Model
{
  protected $table = "config";

  public $incrementing = false;

  protected $fillable = [
    "SII_USER",
    "SII_PASS",
    "SII_SERVER",
    "SII_ENV",
    "CERT_PASS",
    "DTE_RUT_CERT",
    "DTE_NOMBRE_CERT",
    "DTE_RUT_EMPRESA",
    "DTE_NOMBRE_EMPRESA",
    "DTE_GIRO",
    "DTE_DIRECCION",
    "DTE_COMUNA",
    "DTE_ACT_ECONOMICA",
    "DTE_FECHA_RESOL",
    "DTE_NUM_RESOL",
  ];

  public $timestamps = true;
}
