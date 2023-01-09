<?php

namespace App\Console\Commands;

use App\Console\Commands\ComandoBase;
use Illuminate\Support\Facades\Log;
use sasco\LibreDTE\Sii\Autenticacion;

class RenovarToken extends ComandoBase
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = "redmin:token";

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = "Obtiene automáticamente un nuevo token para el SII.";

  /**
   * Create a new command instance.
   *
   * @return void
   */
  public function __construct()
  {
    parent::__construct();
  }

  /**
   * Execute the console command.
   *
   * @return mixed
   */
  public function handle()
  {
    try {
      $token_path = $this->rutas->tmp . "token.txt";
      $now = date("Ymd");
      $token = "";

      $this->alert("Obteniendo token...");

      //check if the cert is valid
      $cert = [];
      //check if file exists
      if (!file_exists($this->dteconfig["file"])) {
        $this->error("No se ha encontrado el certificado en la ruta especificada");
        exit();
      }
      $this->info("Certificado encontrado");

      //check if password is correct
      $p12 = file_get_contents($this->dteconfig["file"]);
      if (openssl_pkcs12_read($p12, $cert, $this->dteconfig["pass"]) === false) {
        $this->error("La contraseña propocionada no corresponde al certificado");
        exit();
      }
      $this->info("Certificado válido");

      $token = Autenticacion::getToken($this->firma);
      if (!$token || $token == "") {
        $this->info("Token : $token");
        file_put_contents($token_path, "$token;$now");
      } else {
        $this->error("No se ha podido obtener el token.");
        $errors = collect(\sasco\LibreDTE\Log::readAll())->map(function ($e) {
          return $e->msg . "\n\n";
        });
        $this->error($errors);
        //delete file
        if (file_exists($token_path)) {
          unlink($token_path);
        }
      }
    } catch (\Exception $e) {
      Log::error("RenovarToken >> " . $e->getMessage());
    }
  }
}
