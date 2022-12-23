<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComprobacionSii extends Model
{
  protected $table = 'comprobacion_sii';

  protected $fillable = [
    'registro_compra_venta_id',
    'estado',
    'glosa_estado',
    'error',
    'glosa_error',
    'fecha_consulta',
    'xml',
    'pdf',
  ];

  public function registro_compra_venta()
  {
    return $this->belongsTo('App\Models\RegistroCompraVenta');
  }
}
