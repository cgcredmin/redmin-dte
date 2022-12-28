<?php

namespace App\Console\Commands;

use App\Console\Commands\ComandoBase;
use Illuminate\Support\Facades\Log;
use sasco\LibreDTE\Sii\Autenticacion;

class LeerCorreoIntercambio extends ComandoBase
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'redmin:correointercambio';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Lee el correo de intercambio y guarda los XML en la carpeta de intercambio.';

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

    // Connect to the email server
    $inbox = imap_open(
      '{imap.example.com:993/imap/ssl}INBOX',
      'cguajardo@redmin.cl',
      'q2wer5ty7*',
    );

    // Search for emails with XML attachments
    $emails = imap_search($inbox, 'BODY "Content-Type: text/xml"');

    // Loop through the emails
    foreach ($emails as $email) {
      // Retrieve the email details
      $overview = imap_fetch_overview($inbox, $email, 0);
      // Retrieve the email body
      $body = imap_fetchbody($inbox, $email, 1);
      // Search the email body for links to XML attachments
      preg_match_all('/<a href="(.*\.xml)">/', $body, $matches);
      // Loop through the XML attachments
      foreach ($matches[1] as $xml_link) {
        // Download the XML attachment
        $xml = file_get_contents($xml_link);
        // Parse the XML into an object
        $xml_obj = simplexml_load_string($xml);
        // Do something with the XML object
        // ...
      }
    }

    // Close the connection to the email server
    imap_close($inbox);

    $this->info('GenerarPDFCompras >> Fin');
  }
}
