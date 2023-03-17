<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contribuyentes extends Model
{
  protected $table = 'contribuyentes';

  protected $fillable = [
    'rut',
    'dv',
    'razon_social',
    'nro_resolucion',
    'fecha_resolucion',
    'direccion_regional',
    'correo',
  ];

  protected $hidden = ['created_at', 'updated_at'];

  public function registrocompraventa()
  {
    return $this->hasMany(RegistroCompraVenta::class, 'detRutDoc', 'rut');
  }
}
