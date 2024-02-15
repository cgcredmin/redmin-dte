<?php

namespace App\Console;

use App\Console\Commands\RegistroCompraYVenta;
use App\Console\Commands\BorraArchivosTemporales;
use App\Console\Commands\ConsultaEstadoDte;
use App\Console\Commands\RenovarToken;
use App\Console\Commands\ResetApiSecret;
use App\Console\Commands\ExtraerXML;
use App\Console\Commands\DescargaContribuyentes;
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
    ResetApiSecret::class,
    ExtraerXML::class,
    DescargaContribuyentes::class,
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
    $schedule->command('redmin:borratemporales')->everyFiveMinutes();
    $schedule->command('redmin:rcv', ['--wts' => 'C'])->twiceDailyAt(0, 12, 0);
    $schedule->command('redmin:estadodte')->twiceDailyAt(1, 13, 0);
    $schedule->command('redmin:correo')->everyTenMinutes();
    // $schedule->command('redmin:contribuyentes')->twiceDailyAt(1, 13, 0);

    // TODO: Add a command for checking 'folios' depletion and a some
    // sort of global process/trait or something that allows to check if
    // the 'folios' are depleted and then send an email to the admin
  }
}
