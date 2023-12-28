<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Log extends Model
{
  protected $table = "log";

  protected $fillable = ["message"];

  protected $dates = ["created_at", "updated_at"];

  //format dates as Y-m-d H:i
  public function getCreatedAtAttribute($value)
  {
    return date("Y-m-d H:i", strtotime($value));
  }

  public function getUpdatedAtAttribute($value)
  {
    return date("Y-m-d H:i", strtotime($value));
  }
}
