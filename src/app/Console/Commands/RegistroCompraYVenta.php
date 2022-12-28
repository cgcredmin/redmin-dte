<?php

namespace App\Console\Commands;

use App\Console\Commands\ComandoBase;
use App\Http\Traits\DteAuthTrait;
use App\Models\RegistroCompraVenta;

use Illuminate\Support\Carbon;

class RegistroCompraYVenta extends ComandoBase
{
  use DteAuthTrait;
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'redmin:rcv {--overwrite} {--year=} {--month=}';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Scraping Registro de Compra y Venta';

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
    $year = $this->option('year') ?? date('Y');
    $month = str_pad($this->option('month') ?? date('m'), 2, '0', STR_PAD_LEFT);
    $overwrite = $this->option('overwrite') ?? false;
    $overwrite = $overwrite ? 'true' : 'false';

    // SII_PASS
    $userPassword = $this->sii_pass;
    // SII_USER
    $userName = $this->sii_user;
    $this->info(
      "UN=$userName UP=$userPassword MO=$month YE=$year OVERWRITE=$overwrite",
    );

    //execute nodejs script
    $output = shell_exec(
      "UN=$userName UP=$userPassword MO=$month YE=$year OVERWRITE=$overwrite /var/www/html/scrapper/RegistroCompraVentaScrapper",
    );

    if (str_starts_with($output, 'ERROR')) {
      $this->error(trim($output));
    } else {
      try {
        $decoded = base64_decode(trim($output));
        $json = json_decode($decoded);

        $compras = $json->COMPRA ?? [];
        $ventas = $json->VENTA ?? [];
        //join both arrays
        $compras = array_merge($compras, $ventas);

        $creados = 0;
        $actualizados = 0;
        foreach ($compras as $value) {
          // dd($value);
          $value->detFchDoc = $value->detFchDoc
            ? Carbon::createFromFormat('d/m/Y', $value->detFchDoc)->format(
              'Y-m-d H:i:s',
            )
            : null;
          $value->detFecAcuse = $value->detFecAcuse
            ? Carbon::createFromFormat(
              'd/m/Y H:i:s',
              $value->detFecAcuse,
            )->format('Y-m-d H:i:s')
            : null;
          $value->detFecReclamado = $value->detFecReclamado
            ? Carbon::createFromFormat(
              'd/m/Y H:i:s',
              $value->detFecReclamado,
            )->format('Y-m-d H:i:s')
            : null;
          $value->detFecRecepcion = $value->detFecRecepcion
            ? Carbon::createFromFormat(
              'd/m/Y H:i:s',
              $value->detFecRecepcion,
            )->format('Y-m-d H:i:s')
            : null;
          $value->fechaActivacionAnotacion = $value->fechaActivacionAnotacion
            ? Carbon::createFromFormat(
              'd/m/Y H:i:s',
              $value->fechaActivacionAnotacion,
            )->format('Y-m-d H:i:s')
            : null;

          $value->registro = $x = RegistroCompraVenta::updateOrCreate(
            [
              'dhdrCodigo' => $value->dhdrCodigo,
              'detCodigo' => $value->detCodigo,
              'detNroDoc' => $value->detNroDoc,
            ],
            collect($value)->toArray(),
          );
          if ($x->wasRecentlyCreated) {
            $creados++;
          } elseif ($x->wasChanged()) {
            $actualizados++;
          }
        }

        $this->info('Registro de Compra y Venta actualizado');
        $this->info("\t\tCreados: $creados");
        $this->info("\t\tActualizados: $actualizados");
        $this->info(
          "\t\tTotal: " . ($creados + $actualizados) . '/' . count($compras),
        );
      } catch (\Exception $e) {
        $this->error($e->getMessage());
      }
    }
  }
}
