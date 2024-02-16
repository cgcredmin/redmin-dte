<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use sasco\LibreDTE\FirmaElectronica;
use App\Models\Config;
use Illuminate\Support\Facades\Schema;

class ComandoBase extends Command
{
  protected $dteconfig = [];
  protected $firma = null;
  protected $rutCert = '';
  protected $nombreCert = '';
  protected $rutEmpresa = '';
  protected $nombreEmpresa = '';
  protected $servidor = 'maullin';
  protected $ambiente = 'certificacion';
  protected $rutas = [];

  protected $sii_user = '';
  protected $sii_pass = '';
  /**
   * Create a new command instance.
   *
   * @return void
   */
  public function __construct()
  {
    parent::__construct();

    //check if table exists
    if (Schema::hasTable('config')) {
      $config = Config::first();
      if ($config) {
        $this->dteconfig = config('libredte.firma');
        $this->dteconfig['pass'] = $config->CERT_PASS;

        $this->firma = new FirmaElectronica($this->dteconfig);

        $this->rutCert = $config->DTE_RUT_CERT;
        $this->nombreCert = $config->DTE_NOMBRE_CERT;
        $this->rutEmpresa = $config->DTE_RUT_EMPRESA;
        $this->nombreEmpresa = $config->DTE_NOMBRE_EMPRESA;
        $this->servidor = $config->SII_SERVER;
        $this->ambiente = $config->SII_ENV;

        $this->sii_user = $config->SII_USER;
        $this->sii_pass = $config->SII_PASS;

        $this->rutas = setDirectories();

        //check if all paths exists, and if not, create them
        foreach ($this->rutas as $ruta) {
          if (!file_exists($ruta)) {
            mkdir($ruta, 0777, true);
          }
        }
      }
    }
  }

  function getToken(): string
  {
    $token_path = config('libredte.archivos_tmp') . 'token.txt';
    $now = date('Ymd');
    $token = '';

    if (file_exists($token_path)) {
      $token_data = file_get_contents($token_path);
      $token_data = explode(';', $token_data);
      $token_date = '';

      if (count($token_data) === 2) {
        $token = $token_data[0];
        $token_date = $token_data[1];
      }
      //check if token is valid
      if ($token_date !== $now || $token == '') {
        return '';
      }

      return $token;
    } else {
      return '';
    }
  }
}
