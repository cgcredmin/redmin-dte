<?php

namespace App\Console;

use App\Console\Commands\RegistroCompraYVenta;
use App\Console\Commands\BorraArchivosTemporales;
use App\Console\Commands\ConsultaEstadoDte;
use App\Console\Commands\RenovarToken;
use App\Console\Commands\LeerCorreoIntercambio;

use Illuminate\Console\Scheduling\Schedule;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
  /**
   * The Artisan commands provided by your application.
   *
   * @var array
   */
  protected $commands = [
    RegistroCompraYVenta::class,
    ConsultaEstadoDte::class,
    RenovarToken::class,
    BorraArchivosTemporales::class,
    LeerCorreoIntercambio::class,
  ];

  /**
   * Define the application's command schedule.
   *
   * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
   * @return void
   */
  protected function schedule(Schedule $schedule)
  {
    $schedule->command('redmin:token')->everyTwoHours();

    $schedule->command('redmin:borratemporales')->hourly();

    $schedule->command('redmin:rcv')->twiceDailyAt(0, 12, 0);
    $schedule->command('redmin:estadodte')->twiceDailyAt(1, 13, 0);
    $schedule->command('redmin:correointercambio')->twiceDailyAt(2, 14, 0);
    // $schedule->command('redmin:generarpdfcompras')->twiceDailyAt(2, 14, 0);
  }
}
