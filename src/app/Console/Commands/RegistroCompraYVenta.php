<?php

namespace App\Console\Commands;

use App\Console\Commands\ComandoBase;
use App\Http\Traits\DteAuthTrait;
use App\Models\RegistroCompraVenta;

use Illuminate\Support\Carbon;

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
  protected $signature = "redmin:rcv {--overwrite} {--year=} {--month=}";

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

    if ($this->sii_user == null || $this->sii_pass == null) {
      $this->error("SII_USER o SII_PASS no definidos");
      return;
    }

    $env_params = "UN={$this->sii_pass} UP={$this->sii_user} MO=$month YE=$year OVERWRITE=$overwrite";
    $this->info($env_params);

    //url for microservice to send data
    $endpoint_url = env("APP_URL") . "/api/upload/rcv";
    //scrapper microservice url
    $scrapper_url = env("SII_SCRAPPER_URL");

    //make a request to sii-scrapper microservice
    $client = new Client();
    $headers = [
      "Content-Type" => "application/json",
    ];
    $body = json_encode([
      "passord" => $this->sii_pass,
      "username" => $this->sii_user,
      "month" => $month,
      "year" => $year,
      "endpoint" => [
        "url" => $endpoint_url,
        "method" => "POST",
        "name" => "data",
      ],
    ]);
    $request = new Request("POST", $scrapper_url, $headers, $body);
    $res = $client->sendAsync($request)->wait();
    $this->info($res->getBody());
  }
}
