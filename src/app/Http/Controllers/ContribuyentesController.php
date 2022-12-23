<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use sasco\LibreDTE\Sii;
use sasco\LibreDTE\FirmaElectronica;
use sasco\LibreDTE\Log;

use App\Models\Contribuyentes;

class ContribuyentesController extends Controller
{
  public function index()
  {
    
    $datos = Contribuyentes::all();
    

    return response()->json($datos);
  }
}
