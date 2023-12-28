<?php

namespace App\Jobs;

use App\Http\Traits\DteAuthTrait;
use Illuminate\Support\Facades\Mail;

use App\Models\Log;

class SendDTE extends Job
{
  use DteAuthTrait;

  private $email;
  private $xml;
  private $folio;
  private $tipo;

  // Set your API credentials and message data
  protected $api_key_public = '83fe454ee222eb5d2de7779aae5f7f3e';
  protected $api_key_private = 'c6b761be929e9d1a3b9b3ddfff333fb5';
  protected $url = 'https://api.mailjet.com/v3.1/send';
  /**
   * Create a new job instance.
   *
   * @return void
   */
  public function __construct($email, $folio, $tipo, $xml)
  {
    $this->email = $email;
    $this->xml = $xml;
    $this->folio = $folio;
    $this->tipo = $tipo;
  }

  /**
   * Execute the job.
   *
   * @return void
   */
  public function handle()
  {
    try {
      $mail = Mail::to(['cj.guajardo@gmail.com'])->send(
        new \App\Mail\DteGenerado($this->xml, null, $this->folio, $this->tipo),
      );
      Log::create([
        'message' => 'Correo enviado a ' . $this->email,
        'type' => 'info',
        'user_id' => 1,
      ]);
    } catch (\Exception $e) {
      Log::error('Error al enviar correo: ' . $e->getMessage());
    }
  }
}
