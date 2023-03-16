<?php

namespace App\Console\Commands;

use App\Console\Commands\ComandoBase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;

use App\Models\Compras;

use Webklex\IMAP\Facades\Client;

class ExtraerXML extends ComandoBase
{
  protected $attachment_total = 0;
  protected $attachment_pdf = 0;
  protected $attachment_xml = 0;
  protected $attachment_match = 0;
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'redmin:correo {--read-all}';

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
    $read_all = $this->option('read-all');
    if ($read_all) {
      $this->info('ExtraerXML >> Leyendo todo el correo');
    } else {
      $this->info('ExtraerXML >> Leyendo solo correos nuevos');
    }

    /** @var \Webklex\PHPIMAP\Client $client */
    $client = Client::account('default');
    // dd($client);

    //Connect to the IMAP Server
    $client->connect();

    //Get all Mailboxes
    /** @var \Webklex\PHPIMAP\Support\FolderCollection $folders */

    if (!$read_all) {
      $messages = $client
        ->getFolder('INBOX')
        ->query()
        ->unseen()
        ->get();
    } else {
      $messages = $client
        ->getFolder('INBOX')
        ->query()
        ->all()
        ->setFetchOrder('desc')
        ->setFetchOrderDesc()
        ->fetchOrderDesc()
        ->get();
    }

    $this->info('ExtraerXML >> Procesando ' . $messages->count() . ' mensajes');

    foreach ($messages as $message) {
      $attachments = $message->getAttachments();
      $this->attachment_total += $attachments->count();
      foreach ($attachments as $attachment) {
        // if ($attachments->count() >= 2) {
        //   dd([
        //     'content_type' => $attachment->content_type,
        //     'mime_type' => $attachment->getMimeType(),
        //     'name' => $attachment->getName(),
        //   ]);
        // }
        $success = $this->processXMLAttachment($attachment);
        if ($success) {
          $message->setFlag('Seen');
        }
      }
    }

    $this->info('ExtraerXML >> Fin');
    $this->info("ExtraerXML >> Total de archivos: $this->attachment_total");
    $this->info("ExtraerXML >> Total de archivos XML: $this->attachment_xml");
  }

  private function processXMLAttachment($attachment)
  {
    // dd($attachment->getMimeType());
    if ($attachment->getMimeType() == 'text/xml') {
      $this->attachment_xml++;
      $hashedName =
        md5($attachment->getName() . '//' . $attachment->content) . '.xml';

      $filename = $this->rutas->dte_ci . $hashedName;

      file_put_contents($filename, $attachment->content);

      try {
        //read xml and get Caratula
        $xml = simplexml_load_string($attachment->content);

        $doc = $xml->SetDTE->DTE->Documento->Encabezado;
        $caratula = $xml->SetDTE->Caratula;
        // dd($caratula);
        $rutEmisor = (string) $doc->Emisor->RUTEmisor;
        $rutReceptor = (string) $doc->Receptor->RUTRecep;
        $fechaEmision = (string) $doc->IdDoc->FchEmis;
        $montoNeto = (int) $doc->Totales->MntNeto;
        $tipoDTE = (int) $doc->IdDoc->TipoDTE;
        $folio = (int) $doc->IdDoc->Folio;
        $fechaResolucion = (string) $caratula->FchResol;
        $IVA = (int) $doc->Totales->IVA;
        $MontoTotal = (int) $doc->Totales->MntTotal;

        //find registro_compra_venta with the given data
        $compra = Compras::where([
          'rut_emisor' => $rutEmisor,
          'rut_receptor' => $rutReceptor,
          'fecha_emision' => $fechaEmision,
          'monto_neto' => $montoNeto,
          'tipo_dte' => $tipoDTE,
          'folio' => $folio,
          'fecha_resolucion' => $fechaResolucion,
          'iva' => $IVA,
          'monto_total' => $MontoTotal,
        ])->first();

        // dd($compra);
        if (!$compra) {
          $compra = Compras::create([
            'rut_emisor' => $rutEmisor,
            'rut_receptor' => $rutReceptor,
            'fecha_emision' => $fechaEmision,
            'monto_neto' => $montoNeto,
            'tipo_dte' => $tipoDTE,
            'folio' => $folio,
            'fecha_resolucion' => $fechaResolucion,
            'iva' => $IVA,
            'monto_total' => $MontoTotal,
          ]);

          // dd($compra);
        }

        $make_pdf = false;
        if ($compra->xml == '' || $compra->xml == null) {
          $compra->xml = $filename;
          $compra->save();

          if ($compra->comprobacion_sii) {
            if (
              $compra->comprobacion_sii->pdf == '' ||
              $compra->comprobacion_sii->pdf == null
            ) {
              $make_pdf = true;
            }
          } else {
            $make_pdf = true;
          }
        }

        if ($make_pdf) {
          //make pdf from xml
          $xml_hash = Crypt::encryptString($filename);
          $pdf_hash = $this->generar_pdf($attachment->content);
          if ($pdf_hash !== null) {
            $pdf_hash = Crypt::encryptString($pdf_hash);
          }
          // dd($pdf_hash, $xml_hash);
          if ($compra->comprobacion_sii) {
            $compra->comprobacion_sii->xml = $xml_hash;
            $compra->comprobacion_sii->pdf = $pdf_hash;
            $compra->comprobacion_sii->save();
          } else {
            $compra->comprobacion_sii()->create([
              'xml' => $xml_hash,
              'pdf' => $pdf_hash,
            ]);
          }
        }

        return true;
      } catch (\Exception $e) {
        $this->error(
          'ExtraerXML >> Error al procesar archivo: ' . $e->getMessage(),
        );
        Log::error($e->getMessage());
      }
    }
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
