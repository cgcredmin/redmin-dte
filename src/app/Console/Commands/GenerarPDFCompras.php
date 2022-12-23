<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerarPDFCompras extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'redmin:generarpdfcompras';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Lee los XML enviados al correo de intercambio y genera los PDF de las compras.';

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
    $this->info('GenerarPDFCompras >> Inicio');
  }
}
