<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\Tempfiles;
use Storage;

class BorraArchivosTemporales extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'redmin:borratemporales';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Elimina los archivos temporales de la carpeta storage/app/temp';

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
    $files = Tempfiles::where('expires_at', '<', date('Y-m-d H:i:s'))->get();

    if ($files->count() > 0) {
      foreach ($files as $file) {
        if (Storage::disk('temp')->exists($file->ruta)) {
          Storage::disk('temp')->delete($file->ruta);
        }
        $file->delete();
      }
    }
  }
}
