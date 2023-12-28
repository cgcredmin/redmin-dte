<?php

namespace App\Console\Commands;

use App\Console\Commands\ComandoBase;
use App\Http\Traits\DteAuthTrait;

use App\Models\Log;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

class RegistroCompraYVenta extends ComandoBase
{
  use DteAuthTrait;
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = "redmin:rcv {--overwrite} {--year=} {--month=} {--wts=all}";

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = "Scraping Registro de Compra y Venta";

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
    $year = $this->option("year") ?? date("Y");
    $month = str_pad($this->option("month") ?? date("m"), 2, "0", STR_PAD_LEFT);
    $overwrite = $this->option("overwrite") ?? false;
    $overwrite = $overwrite ? "true" : "false";
    $wts = strtoupper($this->option("wts") ?? "all");
    // check if wts is valid, must have any value: all, venta, compra
    if (!in_array($wts, ["ALL", "VENTAS", "COMPRAS", "C", "V"])) {
      $this->error("wts debe ser: all, venta o compra");
      Log::create(["message" => "RCV::Error::wts debe ser: all, venta o compra"]);
      return;
    }

    $starttime = date("H:i:s");
    $startday = date("Y-m-d");
    $message = "RCV::{$startday}/{$starttime}/{{END}}::Scraping RCV finalizado";

    if ($this->sii_user == null || $this->sii_pass == null) {
      $this->error("SII_USER o SII_PASS no definidos");
      Log::create(["message" => "RCV::Error::SII_USER o SII_PASS no definidos"]);
      return;
    }

    try {
      $env_params = "UN={$this->sii_pass} UP={$this->sii_user} MO=$month YE=$year OVERWRITE=$overwrite";
      $this->info($env_params);

      //url for microservice to send data
      $endpoint_url = env("APP_URL") . "/api/upload/rcv";
      //scrapper microservice url
      $scrapper_url = env("SII_SCRAPPER_URL") . "/getRCV";

      //make a request to sii-scrapper microservice
      $client = new Client();
      $headers = [
        "Content-Type" => "application/json",
      ];
      $body = json_encode([
        "password" => $this->sii_pass,
        "username" => $this->sii_user,
        "month" => $month,
        "year" => $year,
        "wts" => $wts,
        "endpoint" => [
          "url" => $endpoint_url,
          "method" => "POST",
          "name" => "data",
        ],
      ]);
      $request = new Request("POST", $scrapper_url, $headers, $body);
      $res = $client->sendAsync($request)->wait();
      $body = $res->getBody();
      $message = str_replace("{{END}}", date("H:i:s"), $message);
      Log::create([
        "message" => $message . " " . $body,
      ]);
      $this->info($message);
    } catch (\Exception $e) {
      $this->error($e->getMessage());
      Log::create([
        "message" => "RCV::Error::" . $e->getMessage(),
      ]);
    }
  }
}
