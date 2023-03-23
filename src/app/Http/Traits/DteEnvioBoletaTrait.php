<?php
namespace App\Http\Traits;

use sasco\LibreDTE\Estado;
use sasco\LibreDTE\FirmaElectronica;
use App\Models\Config;
use CURLFile;
use Illuminate\Support\Facades\Log;

trait DteEnvioBoletaTrait
{
  protected $dteconfig = [];
  protected $firma = null;
  protected $rutCert = '';
  protected $nombreCert = '';
  protected $rutEmpresa = '';
  protected $nombreEmpresa = '';
  protected $servidor = 'maullin';
  protected $ambiente = 'certificacion';
  protected $rutas = [];
  protected $seedRetries = 0; // intentos de semilla
  protected $retry = 10; ///< Veces que se reintentará conectar a SII al usar el servicio web
  protected $verificar_ssl = true; ///< Indica si se deberá verificar o no el certificado SSL del SII

  public function __construct()
  {
    $config = Config::first();
    if ($config) {
      $this->dteconfig = config('libredte.firma');
      $this->dteconfig['pass'] = $config->CERT_PASS;

      $this->firma = new FirmaElectronica($this->dteconfig);

      $this->rutCert = $config->DTE_RUT_CERT;
      $this->nombreCert = $config->DTE_NOMBRE_CERT;
      $this->rutEmpresa = $config->DTE_RUT_EMPRESA;
      $this->nombreEmpresa = $config->DTE_NOMBRE_EMPRESA;
      $this->giro = $config->DTE_GIRO;
      $this->direccion = $config->DTE_DIRECCION;
      $this->comuna = $config->DTE_COMUNA;
      $this->actividad_economica = $config->DTE_ACT_ECONOMICA;
      $this->ambiente = $config->SII_ENV;
      $this->servidor = $config->SII_ENV === 'produccion' ? 'api' : 'apicert';

      $this->rutas = setDirectories();
      //check if all paths exists, and if not, create them
      foreach ($this->rutas as $ruta) {
        if (!file_exists($ruta)) {
          mkdir($ruta, 0777, true);
        }
      }
    }
  }

  /**
   * Método que envía un XML de EnvioBoleta al SII y entrega el Track ID del envío
   * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
   * @author IdFour.cl
   * @version 2020-11-19
   */
  public function sendTicket($xml, $gzip = false)
  {
    ///OBTENCION DEL TOKEN
    $token = $this->getTicketToken($this->firma);

    ////ENVIO DEL DOCUMENTO
    $respuesta = $this->sendTicketRequest($xml, $token, $gzip);

    return $respuesta;
  }

  /**
   * Método que entrega el estado normalizado del envío e la boleta al SII
   * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
   * @author IdFour.cl
   * @version 2020-11-19
   */
  public function estado_normalizado($rut, $dv, $track_id, $Firma, $dte, $folio)
  {
    //La consulta del estado aun se encuentra en desarrollo.
  }

  /**
   * Método para solicitar la semilla para la autenticación automática.
   * Nota: la semilla tiene una validez de 2 minutos.
   *
   * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
   * @author IdFour.cl
   * @version 2020-11-19
   */
  private function getTicketSeed()
  {
    $resp = null;
    try {
      $ch = curl_init(
        "https://{$this->servidor}.sii.cl/recursos/v1/boleta.electronica.semilla",
      );
      $header = [
        'User-Agent: Mozilla/4.0 (compatible; PROG 1.0; LibreDTE)',
        'Referer: https://libredte.cl',
        'Accept: application/xml, text/xml, */*',
      ];
      curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      $resp = curl_exec($ch);
      // dd($resp);

      $respon = new \SimpleXMLElement($resp, LIBXML_COMPACT);

      if (
        $resp === false or
        (string) $respon->xpath('/SII:RESPUESTA/SII:RESP_HDR/ESTADO')[0] !==
          '00'
      ) {
        \sasco\LibreDTE\Log::write(
          \sasco\LibreDTE\Estado::AUTH_ERROR_SEMILLA,
          \sasco\LibreDTE\Estado::get(
            \sasco\LibreDTE\Estado::AUTH_ERROR_SEMILLA,
          ),
        );
        return false;
      }
      $semilla = (string) $respon->xpath(
        '/SII:RESPUESTA/SII:RESP_BODY/SEMILLA',
      )[0];
      Log::info("SEMILLA: $semilla");
      return $semilla;
    } catch (\Exception $e) {
      // dd($e->getMessage(), $resp);
      if ($e->getMessage() == 'String could not be parsed as XML') {
        if (stripos($resp, '<SEMILLA>')) {
          $semilla = explode('<SEMILLA>', $resp);
          $semilla = explode('</SEMILLA>', $semilla[1]);
          // dd(['semilla' => $semilla]);
          return $semilla[0];
        }
        $this->seedRetries++;
        if ($this->seedRetries <= 10) {
          Log::info(
            'SEMILLA: Error al obtener semilla, reintentando...' .
              $this->seedRetries,
          );
          $semilla = $this->getTicketSeed();
          Log::info("SEMILLA ({$this->seedRetries}): [$semilla]");
          return $semilla;
        }
      }
      return false;
    }
  }

  /**
   * Método que firma una semilla previamente obtenida
   * @param seed Semilla obtenida desde SII
   * @param Firma objeto de la Firma electrónica o arreglo con configuración de la misma
   * @return Solicitud de token con la semilla incorporada y firmada
   * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
   * @author IdFour.cl
   * @version 2020-11-19
   */
  private function signTokenRequest($seed, $Firma = [])
  {
    if (is_array($Firma)) {
      $Firma = new \sasco\LibreDTE\FirmaElectronica($Firma);
    }
    $seedSigned = $Firma->signXML(
      (new \sasco\LibreDTE\XML())
        ->generate([
          'getToken' => [
            'item' => [
              'Semilla' => $seed,
            ],
          ],
        ])
        ->saveXML(),
    );
    if (!$seedSigned) {
      \sasco\LibreDTE\Log::write(
        \sasco\LibreDTE\Estado::AUTH_ERROR_FIRMA_SOLICITUD_TOKEN,
        \sasco\LibreDTE\Estado::get(
          \sasco\LibreDTE\Estado::AUTH_ERROR_FIRMA_SOLICITUD_TOKEN,
        ),
      );
      return false;
    }
    return $seedSigned;
  }

  /**
   * Método para obtener el token de la sesión a través de una semilla
   * previamente firmada
   *
   * WSDL producción: https://palena.sii.cl/DTEWS/GetTokenFromSeed.jws?WSDL
   * WSDL certificación: https://maullin.sii.cl/DTEWS/GetTokenFromSeed.jws?WSDL
   *
   * @param Firma objeto de la Firma electrónica o arreglo con configuración de la misma
   * @return Token para autenticación en SII o =false si no se pudo obtener
   * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
   * @author IdFour.cl
   * @version 2020-11-19
   */
  public function getTicketToken($Firma = [])
  {
    $resp = null;
    try {
      if (!$Firma) {
        return false;
      }
      $semilla = $this->getTicketSeed();
      Log::info("Semilla obtenida: [$semilla]");
      // dd($semilla);
      if (!$semilla) {
        return false;
      }
      $requestFirmado = $this->signTokenRequest($semilla, $Firma);
      // dd($requestFirmado);
      if (!$requestFirmado) {
        return false;
      }

      $header = [
        'User-Agent: Mozilla/4.0 (compatible; PROG 1.0; LibreDTE)',
        'Referer: https://libredte.cl',
        'Content-type: application/xml',
        'Accept: application/xml',
      ];
      $ch = curl_init(
        "https://{$this->servidor}.sii.cl/recursos/v1/boleta.electronica.token",
      );
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $requestFirmado);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      $resp = curl_exec($ch);
      // dd(['getTicketToken::resp' => $resp]);
      if (stripos($resp, '<TOKEN>')) {
        $token = explode('<TOKEN>', $resp);
        $token = explode('</TOKEN>', $token[1]);
        // dd(['getTicketToken::token' => $token]);
        Log::info('Token obtenido: ' . $token[0]);
        return $token[0];
      } else {
        \sasco\LibreDTE\Log::write(
          \sasco\LibreDTE\Estado::AUTH_ERROR_TOKEN,
          \sasco\LibreDTE\Estado::get(\sasco\LibreDTE\Estado::AUTH_ERROR_TOKEN),
        );

        return false;
      }
    } catch (\Exception $e) {
      dd($e);

      return false;
    }
  }

  /**
   * Método que realiza el envío de un DTE al SII
   * Referencia: http://www.sii.cl/factura_electronica/factura_mercado/envio.pdf
   * @param usuario RUN del usuario que envía el DTE
   * @param empresa RUT de la empresa emisora del DTE
   * @param dte Documento XML con el DTE que se desea enviar a SII
   * @param token Token de autenticación automática ante el SII
   * @param gzip Permite enviar el archivo XML comprimido al servidor
   * @param retry Intentos que se realizarán como máximo para obtener respuesta
   * @return Respuesta XML desde SII o bien null si no se pudo obtener respuesta
   * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
   * @author IdFour.cl
   * @version 2020-11-19
   */
  public function sendTicketRequest($dte, $token, $gzip = false, $retry = null)
  {
    // definir datos que se usarán en el envío
    [$rutSender, $dvSender] = explode(
      '-',
      str_replace('.', '', $this->rutCert),
    );
    [$rutCompany, $dvCompany] = explode(
      '-',
      str_replace('.', '', $this->rutEmpresa),
    );
    if (strpos($dte, '<?xml') === false) {
      $dte = '<?xml version="1.0" encoding="ISO-8859-1"?>' . "\n" . $dte;
    }
    do {
      $file =
        sys_get_temp_dir() .
        '/dte_' .
        md5(microtime() . $token . $dte) .
        '.' .
        ($gzip ? 'gz' : 'xml');
    } while (file_exists($file));
    if ($gzip) {
      $dte = gzencode($dte);
      if ($dte === false) {
        \sasco\LibreDTE\Log::write(
          Estado::ENVIO_ERROR_GZIP,
          Estado::get(Estado::ENVIO_ERROR_GZIP),
        );
        return false;
      }
    }
    file_put_contents($file, $dte);
    $data = [
      'rutSender' => $rutSender,
      'dvSender' => $dvSender,
      'rutCompany' => $rutCompany,
      'dvCompany' => $dvCompany,
      'archivo' => new CURLFile(
        $file,
        $gzip ? 'application/gzip' : 'application/xml',
        basename($file),
      ),
    ];
    // Generar el boundary
    $boundary = uniqid();

    // Construir el cuerpo de la solicitud
    $body = '';
    foreach ($data as $key => $value) {
      $body .= "--$boundary\r\n";
      if ($key == 'archivo') {
        $body .= "Content-Disposition: form-data; name=\"$key\"; filename=\"{$value->getPostFilename()}\"\r\n";
        $body .= "Content-Type: {$value->getMimeType()}\r\n\r\n";
        $body .= file_get_contents($value->getFilename()) . "\r\n";
      } else {
        $body .= "Content-Disposition: form-data; name=\"$key\"\r\n\r\n";
        $body .= "$value\r\n";
      }
    }
    $body .= "--$boundary--\r\n";
    // dd(['sendTicketRequest::data' => $data]);
    // definir reintentos si no se pasaron
    if (!$retry) {
      $retry = $this->retry;
    }
    // crear sesión curl con sus opciones
    $curl = curl_init();
    $header = [
      'User-Agent: Mozilla/4.0 ( compatible; PROG 1.0; Windows NT)',
      // 'Referer: https://libredte.cl',
      'Cookie: TOKEN=' . $token,
      'Accept: application/json',
      "Content-Type: multipart/form-data; boundary=$boundary",
    ];

    $server = $this->ambiente === 'produccion' ? 'rahue' : 'pangal';
    $url = "https://$server.sii.cl/recursos/v1/boleta.electronica.envio";
    // $url = "https://{$this->servidor}.sii.cl/recursos/v1/boleta.electronica.envio";
    // dd(['sendTicketRequest::url' => $url]);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    // si no se debe verificar el SSL se asigna opción a curl, además si
    // se está en el ambiente de producción y no se verifica SSL se
    // generará una entrada en el log
    /*
        if (!$this->verificar_ssl) {
            if ($this->ambiente==='produccion') {
                $msg = Estado::get(Estado::ENVIO_SSL_SIN_VERIFICAR);
                \sasco\LibreDTE\Log::write(Estado::ENVIO_SSL_SIN_VERIFICAR, $msg, LOG_WARNING);
            }
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        }
        */
    // enviar XML al SII
    for ($i = 0; $i < $retry; $i++) {
      $response = curl_exec($curl);
      if ($response and $response != 'Error 500') {
        break;
      }
    }
    unlink($file);
    // verificar respuesta del envío y entregar error en caso que haya uno
    if (!$response or $response == 'Error 500') {
      if (!$response) {
        \sasco\LibreDTE\Log::write(
          Estado::ENVIO_ERROR_CURL,
          Estado::get(Estado::ENVIO_ERROR_CURL, curl_error($curl)),
        );
      }
      if ($response == 'Error 500') {
        \sasco\LibreDTE\Log::write(
          Estado::ENVIO_ERROR_500,
          Estado::get(Estado::ENVIO_ERROR_500),
        );
      }
      return false;
    }
    // cerrar sesión curl
    curl_close($curl);

    // dd(['sendTicketRequest::response' => $response]);

    return json_decode($response, true);
  }
}
