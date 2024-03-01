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
    private $pdf;
    private $folio;
    private $tipo;

    // Set your API credentials and message data
   /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($email, $folio, $tipo, $xml, $pdf = null)
    {
        $this->email = $email;
        $this->xml = $xml;
        $this->pdf = $pdf;
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
        // get the fallbacl email from env param IMAP_USERNAME

        // try {
        //     Mail::to([
        //         $this->email, $fallbackEmail
        //     ])->send(
        //         new \App\Mail\DteGenerado($this->xml, $this->pdf, $this->folio, $this->tipo),
        //     );
        //     Log::create([
        //         'message' => 'Correo enviado a ' . $this->email,
        //         'type' => 'info',
        //         'user_id' => 1,
        //     ]);
        // } catch (\Exception $e) {
        //     Log::create(['message' => 'Error al enviar correo: ' . $e->getMessage(), 'type' => 'error', 'user_id' => 1]);
        // }

        // try {
        //
        //     $tipo_doc = $this->getTipo($this->tipo);
        //
        //     $content = view('emails.dte')
        //         ->with([
        //             'folio' => $this->folio,
        //             'tipo_doc_nombre' => $tipo_doc,
        //         ])
        //         ->render();
        //
        //     $result = $this->send_mj($content);
        //
        //     Log::create(['message' => json_encode($result), 'type' => 'info', 'user_id' => 1]);
        // } catch (\Exception $e) {
        //     Log::create(['message' => 'Error al enviar correo: ' . $e->getMessage(), 'type' => 'error', 'user_id' => 1]);
        // }
    }

}
