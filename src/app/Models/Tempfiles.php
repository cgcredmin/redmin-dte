<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tempfiles extends Model
{
  protected $table = 'tempfiles';
  protected $fillable = ['nombre', 'hash', 'ext', 'ruta', 'expires_at'];

  public $timestamps = true;

  protected $dates = ['expires_at', 'created_at', 'updated_at'];

  public function getHashAttribute($value)
  {
    return decrypt($value);
  }
}
