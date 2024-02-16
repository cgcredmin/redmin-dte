<?php

namespace App\Console\Commands;

use App\Console\Commands\ComandoBase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;

use App\Models\Compras;
use App\Models\Contribuyentes;

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
    // dd($attachment->getMessage());
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
        $nroResolucion = (int) $caratula->NroResol;
        $IVA = (int) $doc->Totales->IVA;
        $MontoTotal = (int) $doc->Totales->MntTotal;

        //calcular monto exento (MntExe)
        $MntExe = (int) $doc->Totales->MntExe;

        $direccionEmisor = [
          'direccion' => (string) $doc->Emisor->DirOrigen,
          'comuna' => (string) $doc->Emisor->CmnaOrigen,
          'ciudad' => (string) $doc->Emisor->CiudadOrigen,
        ];
        [$rutSinDv, $dv] = explode('-', $rutEmisor);

        $detalle = json_encode($xml->SetDTE->DTE->Documento->Detalle);

        //update contribuyente
        $contribuyente = Contribuyentes::where('rut', $rutSinDv)->first();
        if (!$contribuyente) {
          $contribuyente = Contribuyentes::create([
            'rut' => $rutSinDv,
            'dv' => $dv,
            'correo' => (string) $doc->Emisor->CorreoEmisor ?? '',
            'direccion_regional' => json_encode($direccionEmisor),
            'razon_social' => (string) $doc->Emisor->RznSoc ?? '',
            'nro_resolucion' => $nroResolucion,
            'fecha_resolucion' => $fechaResolucion,
          ]);
        } else {
          if ($contribuyente->correo == '' || $contribuyente->correo == null) {
            $contribuyente->correo = (string) $doc->Emisor->CorreoEmisor ?? '';
          }
          if (
            $contribuyente->direccion_regional == '' ||
            $contribuyente->direccion_regional == null
          ) {
            $contribuyente->direccion_regional = json_encode($direccionEmisor);
          }
          if (
            $contribuyente->razon_social == null ||
            $contribuyente->razon_social == ''
          ) {
            $contribuyente->razon_social = (string) $doc->Emisor->RznSoc ?? '';
          }
          if (
            $contribuyente->nro_resolucion == '' ||
            $contribuyente->nro_resolucion == null
          ) {
            $contribuyente->nro_resolucion = $nroResolucion;
          }
          $contribuyente->save();
        }

        $data = [
          'rut_emisor' => $rutEmisor,
          'razon_social_emisor' => (string) $doc->Emisor->RznSoc ?? '',
          'rut_receptor' => $rutReceptor,
          'fecha_emision' => $fechaEmision,
          'monto_neto' => $montoNeto,
          'monto_exento' => $MntExe,
          'tipo_dte' => $tipoDTE,
          'folio' => $folio,
          'fecha_resolucion' => $fechaResolucion,
          'iva' => $IVA,
          'monto_total' => $MontoTotal,
          'detalle' => $detalle,
        ];

        //find registro_compra_venta with the given data
        $compra = Compras::where($data)->first();
        // dd($compra);
        if (!$compra) {
          $data['fecha_recepcion'] = date('Y-m-d H:i:s');
          $compra = Compras::create($data);
        }

        // $make_pdf = $compra->pdf == '' || $compra->pdf == null;
        $make_pdf = true;

        if ($make_pdf) {
          //make pdf from xml
          $pdfname = $this->generar_pdf($attachment->content);
          //hash pdf name
          $hashed_pdfname =
            md5($compra->id . '//' . $compra->fecha_emision) . '.pdf';

          // rename pdf
          rename($pdfname, $this->rutas->pdf . $hashed_pdfname);
          $pdfname = $this->rutas->pdf . $hashed_pdfname;

          $compra->pdf = str_replace([$this->rutas->pdf, '.pdf'], '', $pdfname);
          $compra->xml = str_replace(
            [$this->rutas->dte_ci, '.xml'],
            '',
            $filename,
          );
          $compra->save();
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
