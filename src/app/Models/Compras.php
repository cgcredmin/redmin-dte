<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Compras extends Model
{
  protected $table = 'compras';

  protected $fillable = [
    'rut_emisor',
    'razon_social_emisor',
    'rut_receptor',
    'fecha_emision',
    'fecha_recepcion',
    'monto_neto',
    'iva',
    'monto_total',
    'monto_exento',
    'detalle',
    'tipo_dte',
    'folio',
    'fecha_resolucion',
    'xml',
    'pdf',
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
