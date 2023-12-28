<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

use Illuminate\Mail\Mailables\Attachment;

use sasco\LibreDTE\Sii\Dte\Base\DteImpreso;

class DteGenerado extends Mailable
{
  use Queueable, SerializesModels, DteImpreso;

  private $xml;
  private $folio;
  private $tipo;
  private $pdf;

  /**
   * Create a new message instance.
   *
   * @return void
   */
  public function __construct($xml, $pdf, $folio, $tipo)
  {
    $this->xml = $xml;
    $this->folio = $folio;
    $this->tipo = $tipo;
    $this->pdf = $pdf;
  }

  public function attachments()
  {
    $attachments[] = Attachment::fromStorage(
      $this->xml,
      'DTE_' . $this->folio . '.xml',
    )->withMime('application/xml');

    if ($this->pdf) {
      $attachments[] = Attachment::fromData(
        $this->pdf,
        'DTE_' . $this->folio . '.pdf',
      )->withMime('application/pdf');
    }

    // 'DTE_' . $this->folio . '.xml' => [
    //   'data' => $this->xml,
    //   'mime' => 'application/xml',
    // ],
    // 'DTE_' . $this->folio . '.pdf' => [
    //   'data' => $this->pdf,
    //   'mime' => 'application/pdf',
    // ],
    // ];

    return $attachments;
  }

  /**
   * Build the message.
   *
   * @return $this
   */
  public function build()
  {
    return $this->view('emails.dte')
      ->subject('Envío de Documento Trinutario Electrónico')
      ->with([
        'folio' => $this->folio,
        'tipo_doc_nombre' => $this->getTipoDocNombre(),
      ]);
  }

  // public function content()
  // {
  //   $renderedView = $this->view('emails.dte')->render();
  //   return new Content($renderedView);
  // }

  private function getTipoDocNombre()
  {
    return $this->getTipo($this->tipo);
  }
}
