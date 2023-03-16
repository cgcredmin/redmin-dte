<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Compras extends Model
{
  protected $table = 'compras';

  protected $fillable = [
    'rut_emisor',
    'rut_receptor',
    'fecha_emision',
    'monto_neto',
    'tipo_dte',
    'folio',
    'fecha_resolucion',
    'iva',
    'monto_total',
    'xml',
    'comprobacion_sii_id',
  ];

  public function comprobacion_sii()
  {
    return $this->belongsTo(ComprobacionSii::class);
  }

  public function scopeWhereRutEmisor($query, $rutEmisor)
  {
    return $query->where('rut_emisor', $rutEmisor);
  }
}
