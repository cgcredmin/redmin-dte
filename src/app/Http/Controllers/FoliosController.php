<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Storage;
use App\Http\Traits\DteAuthTrait;

class FoliosController extends Controller
{
  use DteAuthTrait;

  public function getFolios(Request $request)
  {
    $rules = [
      'folioInicial' => 'required|numeric',
      'tipoDte' => 'required|numeric',
      'folioFinal' => 'required|numeric',
    ];

    $this->validate($request, $rules);

    $cookies = new CookieJar();

    $credentials = [$this->dteconfig['pem'], $this->dteconfig['pass']];

    $client = new Client();
    $client->request(
      'GET',
      'https://herculesr.sii.cl/cgi_AUT2000/CAutInicio.cgi?http://www.sii.cl',
      [
        [0 => 'path/cert.pem', 1 => 'passwordcert'],
        'cert' => $credentials,
        'query' => [
          'rutcntr' => $this->rutCert,
          'rut' => explode('-', $this->rutCert)[0],
          'referencia' => 'https://www.sii.cl',
          'dv' => explode('-', $this->rutCert)[1],
        ],
        'cookies' => $cookies,
      ],
    );

    [$rutEmpresa, $dvEmpresa] = explode(
      '-',
      str_replace('.', '', $this->rutEmpresa),
    );
    try {
      /**
       * se envia solicitud de folios
       */
      $url = '/cvc_cgi/dte/of_solicita_folios';

      $query = [
        'RUT_EMP' => $rutEmpresa,
        'DV_EMP' => $dvEmpresa,
        'ACEPTAR' => 'Continuar',
      ];

      $client = new Client([
        'headers' => [
          'content-type' => 'application/json',
          'Accept' => 'application/json, text/plain, */*',
          'Accept-Encoding' => 'gzip, deflate, br',
        ],
        'base_uri' => 'https://' . $this->servidor . '.sii.cl',
        'cookies' => $cookies,
        'defaults' => [
          'exceptions' => false,
          'allow_redirects' => false,
        ],
        'query' => $query,
      ]);

      $response = $client->request('POST', $url, [
        'form_params' => $query,
      ]);

      // realizar consulta curl
      $response_code = $response->getStatusCode();

      if ($response_code != 200) {
        return false;
      }

      /**
       * Se envia confirmacion de solicitud de folios
       */
      $url = '/cvc_cgi/dte/of_confirma_folio';

      $query = [
        'RUT_EMP' => $rutEmpresa,
        'DV_EMP' => $dvEmpresa,
        'FOLIO_INICIAL' => $request->folioInicial,
        'COD_DOCTO' => $request->tipoDte,
        'AFECTO_IVA' => 'S',
        'CON_CREDITO' => '0',
        'CON_AJUSTE' => '0',
        'FACTOR' => null,
        'CANT_DOCTOS' => $request->folioFinal - $request->folioInicial + 1,
        'ACEPTAR' => '(unable to decode value)',
      ];

      $client = new Client([
        'headers' => [
          'content-type' => 'application/json',
          'Accept' => 'application/json, text/plain, */*',
          'Accept-Encoding' => 'gzip, deflate, br',
        ],
        'base_uri' => 'https://' . $this->servidor . '.sii.cl',
        'cookies' => $cookies,
        'defaults' => [
          'exceptions' => false,
          'allow_redirects' => false,
        ],
        'query' => $query,
      ]);

      $response = $client->request('POST', $url, [
        'form_params' => $query,
      ]);

      // realizar consulta curl
      $response_code = $response->getStatusCode();
      if ($response_code != 200) {
        return false;
      }

      /**
       * Se genera archivo folios
       */
      $url = '/cvc_cgi/dte/of_genera_folio';

      $query = [
        'NOMUSU' => strtoupper($this->nombreCert),
        'CON_CREDITO' => 0,
        'CON_AJUSTE' => 0,
        'FOLIO_INI' => $request->folioInicial,
        'FOLIO_FIN' => $request->folioFinal,
        'DIA' => date('d'),
        'MES' => date('m'),
        'ANO' => date('Y'),
        'HORA' => date('H'),
        'MINUTO' => date('i'),
        'RUT_EMP' => $rutEmpresa,
        'DV_EMP' => $dvEmpresa,
        'COD_DOCTO' => $request->tipoDte,
        'CANT_DOCTOS' => $request->folioFinal - $request->folioInicial + 1,
        'ACEPTAR' => 'Obtener Folios',
      ];

      $client = new Client([
        'headers' => [
          'content-type' => 'application/json',
          'Accept' => 'application/json, text/plain, */*',
          'Accept-Encoding' => 'gzip, deflate, br',
        ],
        'base_uri' => 'https://' . $this->servidor . '.sii.cl',
        'cookies' => $cookies,
        'defaults' => [
          'exceptions' => false,
          'allow_redirects' => false,
        ],
        'query' => $query,
      ]);

      $response = $client->request('POST', $url, [
        'form_params' => $query,
      ]);

      // realizar consulta curl
      $response_code = $response->getStatusCode();
      if ($response_code != 200) {
        return false;
      }

      /**
       * Se genera el archivo de folios solicitado
       */
      $url = '/cvc_cgi/dte/of_genera_archivo';

      $query = [
        'RUT_EMP' => $rutEmpresa,
        'DV_EMP' => $dvEmpresa,
        'COD_DOCTO' => $request->tipoDte,
        'FOLIO_INI' => $request->folioInicial,
        'FOLIO_FIN' => $request->folioFinal,
        'FECHA' => date('Y-m-d'),
        'ACEPTAR' => 'AQUI',
      ];

      $client = new Client([
        'headers' => [
          'content-type' => 'application/x-www-form-urlencoded',
          'Accept' =>
          'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
          'Accept-Encoding' => 'gzip, deflate, br',
        ],
        'base_uri' => 'https://' . $this->servidor . '.sii.cl',
        'cookies' => $cookies,
        'defaults' => [
          'exceptions' => false,
          'allow_redirects' => false,
        ],
        'query' => $query,
      ]);

      $response = $client->request('POST', $url, [
        'form_params' => $query,
      ]);

      // realizar consulta curl
      $response_code = $response->getStatusCode();
      if ($response_code != 200) {
        return $response_code;
      }

      // store the file in xml/dte/folios
      $content = $response->getBody()->getContents();
      $filename = $this->rutas->folios . $request->tipoDte . '.xml';
      Storage::disk('local')->put($filename, $content);

      return $content;
    } catch (\Throwable $th) {
      return response()->json(
        [
          'message' => 'Error al solicitar folios',
          'error' => $th->getMessage(),
        ],
        400,
      );
    }
  }
}
