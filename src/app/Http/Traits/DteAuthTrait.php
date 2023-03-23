<?php
namespace App\Http\Traits;

use sasco\LibreDTE\FirmaElectronica;
use sasco\LibreDTE\Sii\Autenticacion;

use App\Models\Config;

trait DteAuthTrait
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

  public function __construct()
  {
    $config = Config::first();
    if ($config) {
      $this->dteconfig = config('libredte.firma');
      $this->dteconfig['pass'] = $config->CERT_PASS;

      $this->firma = new FirmaElectronica($this->dteconfig);

      $this->rutCert = $config->DTE_RUT_CERT;
      $this->nombreCert = $config->DTE_NOMBRE_CERT;
      $this->rutEmpresa = $config->DTE_RUT_EMPRESA;
      $this->nombreEmpresa = $config->DTE_NOMBRE_EMPRESA;
      $this->giro = $config->DTE_GIRO;
      $this->direccion = $config->DTE_DIRECCION;
      $this->comuna = $config->DTE_COMUNA;
      $this->actividad_economica = $config->DTE_ACT_ECONOMICA;
      $this->servidor = $config->SII_SERVER;
      $this->ambiente = $config->SII_ENV;

      $this->rutas = setDirectories();

      //check if all paths exists, and if not, create them
      foreach ($this->rutas as $ruta) {
        if (!file_exists($ruta)) {
          mkdir($ruta, 0777, true);
        }
      }
    }

    // trabajar en ambiente de certificación
    \sasco\LibreDTE\Sii::setAmbiente(
      $this->ambiente === 'produccion'
        ? \sasco\LibreDTE\Sii::PRODUCCION
        : \sasco\LibreDTE\Sii::CERTIFICACION,
    );

    // trabajar con maullin para certificación
    \sasco\LibreDTE\Sii::setServidor($this->servidor);
  }

  public function token($forceRenew = false)
  {
    try {
      $token_path = config('libredte.archivos_tmp') . 'token.txt';
      $now = date('Ymd');
      $token = '';

      if ($forceRenew === true) {
        $token = Autenticacion::getToken($this->dteconfig);
        file_put_contents($token_path, "$token;$now");
        return $token;
      }

      //check if token exists
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
          $token = Autenticacion::getToken($this->dteconfig);
          file_put_contents($token_path, "$token;$now");
        }

        return $token;
      } else {
        $token = Autenticacion::getToken($this->dteconfig);
        file_put_contents($token_path, "$token;$now");
      }

      return $token;
    } catch (\Exception $e) {
      // si hubo errores se muestran
      return response()->json(['error' => $e->getMessage()], 400);
    }
    return null;
  }
}
