<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Traits\DteAuthTrait;
use App\Models\Config;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Storage;

class InitController extends Controller
{
  use DteAuthTrait;

  public function getCrucialData(Request $request)
  {
    $config = Config::first();

    if (!$config) {
      return response()->json(['error' => 'No se ha configurado el sistema'], 400);
    }

    if ($config->SII_USER !== '' && $config->SII_PASS !== '') {
      // check if json file exists
      if (Storage::disk('local')->exists($config->SII_USER . '.json')) {
        // if file exists, return the json content
        $json = Storage::disk('local')->get($config->SII_USER . '.json');
        $data = json_decode($json, true);
        return response()->json($data, 200);
      }

      // execute functions to gather the data from sii page on multiple urls that will be provided later
      $urls = [
        'https://palena.sii.cl/cvc_cgi/dte/ee_empresa_rut', // get company data
      ];

      [$client, $cookies] = $this->loginWithCert();

      [$rut, $dv] = explode('-', $config->SII_USER);

      $client = new Client();
      $query = [
        'RUT_EMP' => $rut,
        'DV_EMP' => $dv,
        'ACEPTAR' => 'Consultar',
      ];

      $clientConfig = [
        'headers' => [
          'content-type' => 'application/json',
          'Accept' => 'application/json, text/plain, */*',
          'Accept-Encoding' => 'gzip, deflate, br',
        ],
        'base_uri' => 'https://palena.sii.cl',
        'cookies' => $cookies,
        'defaults' => [
          'exceptions' => false,
          'allow_redirects' => false,
        ],
        'query' => $query,
      ];

      $response = $client->request('GET', $urls[0], $clientConfig);

      if ($response->getStatusCode() === 200) {
        // get the html content
        $html = $response->getBody()->getContents();
        // store html in a file for debugging
        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();

        $rut = $this->get_cell_content(2, 0, 1, $dom);
        $razon_social = $this->get_cell_content(2, 1, 1, $dom);
        $numero_resolucion = $this->get_cell_content(2, 2, 1, $dom);
        $fecha_resolucion = $this->get_cell_content(2, 3, 1, $dom);

        $data = array(
          "razon_social" => trim($razon_social),
          "numero_resolucion" => trim($numero_resolucion),
          "fecha_resolucion" => trim($fecha_resolucion)
        );

        // store json in file.
        $json = json_encode($data);
        Storage::disk('local')->put($config->SII_USER . '.json', $json);

        return response()->json($data, 200);
      }
    }

    return response()->json(['error' => 'Debe ingresar configurar datos de acceso a página del SII primero.'], 400);
  }

  function get_cell_content($table_index, $row_index, $cell_index, $dom)
  {
    $tables = $dom->getElementsByTagName('table');
    $rows = $tables->item($table_index)->getElementsByTagName('tr');
    $cells = $rows->item($row_index)->getElementsByTagName('td');

    $content = trim($cells->item($cell_index)->nodeValue);
    $content = str_replace("\u00a0", '', $content);
    $content = preg_replace('/\xc2\xa0/', '', $content);

    return trim($content);
  }
}
