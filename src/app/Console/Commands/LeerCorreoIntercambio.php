<?php
namespace App\Console\Commands;
ini_set('memory_limit', '1G');

use App\Console\Commands\ComandoBase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;

use Webklex\IMAP\Facades\Client;

use App\Models\RegistroCompraVenta;
class LeerCorreoIntercambio extends ComandoBase
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
      $this->info('GenerarPDFCompras >> Leyendo todo el correo');
    } else {
      $this->info('GenerarPDFCompras >> Leyendo solo correos nuevos');
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

    $this->info(
      'GenerarPDFCompras >> Procesando ' . $messages->count() . ' mensajes',
    );

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
        $this->processXMLAttachment($attachment);
        $this->processPDFAttachment($attachment);
      }
    }

    $this->info('GenerarPDFCompras >> Fin');

    $this->info(
      "GenerarPDFCompras >> Total de archivos: $this->attachment_total",
    );
    $this->info(
      "GenerarPDFCompras >> Total de archivos PDF: $this->attachment_pdf",
    );
    $this->info(
      "GenerarPDFCompras >> Total de archivos XML: $this->attachment_xml",
    );
    $this->info(
      "GenerarPDFCompras >> Total de archivos que coinciden con una compra o venta en DB: $this->attachment_match",
    );
  }

  private function processXMLAttachment($attachment)
  {
    if ($attachment->getMimeType() == 'text/xml') {
      $this->attachment_xml++;
      $filename = $this->rutas->dte_ci . $attachment->name;
      if (!file_exists($filename)) {
        file_put_contents($filename, $attachment->content);
      }

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
          $this->attachment_match++;
          $rcv = RegistroCompraVenta::find($rcv->id);
          $make_pdf = true;
          if ($rcv->comprobacion_sii) {
            if ($rcv->comprobacion_sii->xml && $rcv->comprobacion_sii->pdf) {
              $make_pdf = false;
            }
            if (!$rcv->comprobacion_sii->xml) {
              $make_pdf = true;
            }
          }

          if (!$make_pdf) {
            return;
          }
          //make pdf from xml
          $xml_hash = Crypt::encryptString($filename);
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

  private function processPDFAttachment($attachment)
  {
    // dd($attachment);
    if ($attachment->getMimeType() == 'application/pdf') {
      $this->attachment_pdf++;
      $filePath = $this->rutas->pdf . $attachment->name;

      if (!file_exists($filePath)) {
        file_put_contents($filePath, $attachment->content);
      }

      //get data from pdf filename
      $data = $this->getDataFromFilename($attachment->name);

      $folios[] = $data['folio'];
      $csv[] = $data;

      try {
        //find registro_compra_venta with the given data
        $rcv = DB::table('registro_compra_venta')
          ->whereRaw(
            "detRutDoc='$data[rutEmisor]' AND 
                  detTipoDoc='$data[tipoDte]' AND 
                  detNroDoc='$data[folio]'",
          )
          ->first();
        if ($rcv) {
          $this->attachment_match++;
          $rcv = RegistroCompraVenta::find($rcv->id);
          $hash = Crypt::encryptString($this->rutas->pdf . $attachment->name);
          // dd($pdf_hash, $xml_hash);
          if ($rcv->comprobacion_sii) {
            $rcv->comprobacion_sii->xml = '';
            $rcv->comprobacion_sii->pdf = $hash;
            $rcv->comprobacion_sii->save();
          } else {
            $rcv->comprobacion_sii()->create([
              'xml' => '',
              'pdf' => $hash,
            ]);
          }
        }
      } catch (\Exception $e) {
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

  private function getDataFromFilename($fileName)
  {
    $fileName = str_replace(['.pdf', '.xml', '.txt'], '', $fileName);

    $rutReceptor = substr($fileName, 0, strpos($fileName, '-') + 2);
    $rutEmisor = substr(
      $fileName,
      strlen($rutReceptor),
      strpos($fileName, '-') + 2,
    );
    $tipoDte = intval(
      substr($fileName, strlen($rutEmisor) + strlen($rutReceptor), 2),
    );
    $folio = intval(
      str_replace($rutReceptor . $rutEmisor . strval($tipoDte), '', $fileName),
    );

    //remove Dv from rut
    $rutReceptor = substr($rutReceptor, 0, -2);
    $rutEmisor = substr($rutEmisor, 0, -2);

    return [
      'rutReceptor' => $rutReceptor,
      'rutEmisor' => $rutEmisor,
      'tipoDte' => $tipoDte,
      'folio' => $folio,
    ];
  }

  private function getAccessToken($PARAMS)
  {
    try {
      // create /var/www/html/.env.outlook-token file with the $PARAMS content
      $env = '';
      foreach ($PARAMS as $key => $value) {
        $env .= "$key=$value\r\n";
      }
      file_put_contents('/var/www/html/.env.outlook-token', $env);

      $command = 'node ./node_apps/src/outlook-token/';
      $this->info('Command: ' . $command);

      //exec storage/node_apps/bin/outlook-token and receive the token data as base64
      $token = exec($command);
      $token = base64_decode($token);

      if (stripos($token, 'ERROR') !== false) {
        $this->info('Error: ' . $token);
        return '';
      }

      $token = json_decode($token);

      if ($token) {
        return $token->accessToken;
      }
    } catch (\Exception $e) {
      return '';
    }
  }

  private function getOfficeToken3652($tenantID, $clientid, $clientSecret)
  {
    try {
      $url =
        'https://login.microsoftonline.com/' . $tenantID . '/oauth2/v2.0/token';
      $curl = curl_init($url);
      curl_setopt($curl, CURLOPT_URL, $url);
      curl_setopt($curl, CURLOPT_POST, true);
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
      $headers = ['Content-Type: application/x-www-form-urlencoded'];
      curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
      $data =
        'client_id=' .
        $clientid .
        '&scope=https%3A%2F%2Foutlook.office365.com%2F.default&client_secret=' .
        $clientSecret .
        '&grant_type=client_credentials';
      curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
      //for debug only!
      curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
      curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
      $resp = json_decode(curl_exec($curl));
      curl_close($curl);
      return $resp->access_token;
    } catch (\Exception $e) {
      return '';
    }
  }
}
