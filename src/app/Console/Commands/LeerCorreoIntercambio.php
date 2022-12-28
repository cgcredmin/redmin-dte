<?php

namespace App\Console\Commands;

use App\Console\Commands\ComandoBase;
use Illuminate\Support\Facades\Log;
use Webklex\IMAP\Facades\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;

use App\Models\RegistroCompraVenta;
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
  protected $description = 'Lee el correo de intercambio y guarda los XML en la carpeta de intercambio y genera los pdf de los dte.';

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

    /** @var \Webklex\PHPIMAP\Client $client */
    $client = Client::account('default');

    //Connect to the IMAP Server
    $client->connect();

    //Get all Mailboxes
    /** @var \Webklex\PHPIMAP\Support\FolderCollection $folders */
    $folders = $client->getFolders();

    //Loop through every Mailbox
    /** @var \Webklex\PHPIMAP\Folder $folder */
    foreach ($folders as $folder) {
      //Get all Messages of the current Mailbox $folder
      /** @var \Webklex\PHPIMAP\Support\MessageCollection $messages */
      $messages = $folder
        ->query()
        ->text('Content-Type: text/xml')
        ->get();

      /** @var \Webklex\PHPIMAP\Message $message */
      foreach ($messages as $message) {
        //get the xml attachment
        $attachments = $message->getAttachments();
        foreach ($attachments as $attachment) {
          // dd($attachment);
          if ($attachment->content_type == 'text/xml') {
            file_put_contents(
              $this->rutas->dte_ci . $attachment->name,
              $attachment->content,
            );

            try {
              //read xml and get Caratula
              $xml = simplexml_load_string($attachment->content);

              $doc = $xml->SetDTE->DTE->Documento->Encabezado;
              // dd($caratula);
              $rutEmisor = (string) $doc->Emisor->RUTEmisor;
              $rutEmisor = substr($rutEmisor, 0, -2);
              $rutReceptor = (string) $doc->Receptor->RUTRecep;
              $rutReceptor = substr($rutReceptor, 0, -2);
              $fechaEmision = (string) $doc->IdDoc->FchEmis;
              $montoNeto = (int) $doc->Totales->MntNeto;
              $tipoDTE = (int) $doc->IdDoc->TipoDTE;
              $folio = (int) $doc->IdDoc->Folio;

              //find registro_compra_venta with the given data
              $rcv = DB::table('registro_compra_venta')
                ->whereRaw(
                  "detRutDoc='$rutEmisor' AND 
                  detFchDoc='$fechaEmision' AND 
                  detMntNeto='$montoNeto' AND 
                  detTipoDoc='$tipoDTE' AND 
                  detNroDoc='$folio' ",
                )
                ->first();

              if ($rcv) {
                $rcv = RegistroCompraVenta::find($rcv->id);
                $make_pdf = true;
                if ($rcv->comprobacion_sii) {
                  if (
                    $rcv->comprobacion_sii->xml &&
                    $rcv->comprobacion_sii->pdf
                  ) {
                    $make_pdf = false;
                  }
                  if (!$rcv->comprobacion_sii->xml) {
                    $make_pdf = true;
                  }
                }

                if (!$make_pdf) {
                  continue;
                }
                //make pdf from xml
                $xml_hash = Crypt::encryptString(
                  $this->rutas->dte_ci . $attachment->name,
                );
                $pdf_hash = $this->generar_pdf($attachment->content);
                if ($pdf_hash !== null) {
                  $pdf_hash = Crypt::encryptString($pdf_hash);
                }
                // dd($pdf_hash, $xml_hash);
                if ($rcv->comprobacion_sii) {
                  $rcv->comprobacion_sii->xml = $xml_hash;
                  $rcv->comprobacion_sii->pdf = $pdf_hash;
                  $rcv->comprobacion_sii->save();
                } else {
                  $rcv->comprobacion_sii()->create([
                    'xml' => $xml_hash,
                    'pdf' => $pdf_hash,
                  ]);
                }
              }
            } catch (\Exception $e) {
              Log::error($e->getMessage());
            }
          }
        }
      }
    }

    $this->info('GenerarPDFCompras >> Fin');
  }

  private function generar_pdf($xml)
  {
    $EnvioDte = new \sasco\LibreDTE\Sii\EnvioDte();
    $EnvioDte->loadXML($xml);
    $Caratula = $EnvioDte->getCaratula();
    $Documentos = $EnvioDte->getDocumentos();

    if (!$Documentos) {
      die('No se pudieron obtener los documentos del envío');
      return null;
    }

    $DTE = $Documentos[0];

    // procesar cada DTEs e ir agregándolo al PDF
    $pdf_name =
      '/var/www/html/storage/app/pdf/dte_' .
      $Caratula['RutEmisor'] .
      '_' .
      $DTE->getID() .
      '.pdf';

    if (!$DTE->getDatos()) {
      die('No se pudieron obtener los datos del DTE');
    }
    $pdf = new \sasco\LibreDTE\Sii\Dte\PDF\Dte(false); // =false hoja carta, =true papel contínuo (false por defecto si no se pasa)
    $pdf->setFooterText();

    // $pdf->setLogo('/var/www/html/public/dist/images/redmin-dte.png'); // debe ser PNG!
    $pdf->setResolucion([
      'FchResol' => $Caratula['FchResol'],
      'NroResol' => $Caratula['NroResol'],
    ]);
    //$pdf->setCedible(true);
    $pdf->agregar($DTE->getDatos(), $DTE->getTED());
    $pdf->Output($pdf_name, 'F');

    return $pdf_name;
  }
}
