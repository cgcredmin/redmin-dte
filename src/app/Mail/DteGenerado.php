<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
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
      $attachments[] = Attachment::fromStorage(
        $this->pdf,
        'DTE_' . $this->folio . '.pdf',
      )->withMime('application/pdf');
    }

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

  private function getTipoDocNombre()
  {
    return $this->getTipo($this->tipo);
  }
}
