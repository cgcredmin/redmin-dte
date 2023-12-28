<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use sasco\LibreDTE\Sii\Dte\Base\DteImpreso;
use App\Http\Traits\DteDatosTrait;
use Google\Client;
use Google\Service\Gmail;

use App\Models\Config;

class ConfigController extends Controller
{
  use DteImpreso;
  use DteDatosTrait;

  public function upload(Request $request)
  {
    $tipos = implode(
      ',',
      collect($this->tipos)
        ->except(0)
        ->keys()
        ->toArray(),
    );

    $rules = [
      'archivo' => 'required|file',
      'password' => 'string|min:6',
      'rut' => 'string|digits_between:9,12',
      'nombre' => 'string|max:150',
    ];

    $this->validate($request, $rules);

    $file = $request->file('archivo');
    $mime = $file->getMimeType();
    $ext = $file->getClientOriginalExtension();

    if ($ext === 'xml' && $mime === 'text/xml') {
      //read xml file and get TD item
      try {
        $xml = simplexml_load_file($file->getRealPath());
        $tipo_folio = (string) $xml->CAF->DA->TD;

        // store file in '/var/www/html/storage/app/xml/folios'
        $path = $file->storeAs('xml/folios', $tipo_folio . '.xml');
        return response()->json(
          [
            'message' => 'Folio ' . $tipo_folio . ' subido correctamente. ',
          ],
          200,
        );
      } catch (\Exception $e) {
        return response()->json(['error' => 'Error al leer archivo XML'], 400);
      }
    } elseif (in_array($ext, ['p12', 'pfx'])) {
      $mimes = [
        'application/octet-stream',
        'application/x-pem-file',
        'application/x-x509-ca-cert',
        'text/plain',
      ];
      if (!in_array($mime, $mimes)) {
        return response()->json(
          ['error' => 'Tipo de archivo no permitido'],
          400,
        );
      }

      $password = $request->input('password');
      if ($password === '') {
        return response()->json(
          ['error' => 'Contraseña no puede estar vacía'],
          400,
        );
      }

      $certs = [];
      $filecontent = file_get_contents($file->getRealPath());
      //check if password is correct
      if (openssl_pkcs12_read($filecontent, $certs, $password) === false) {
        return response()->json(
          [
            'error' =>
            'La contraseña propocionada no corresponde al certificado',
          ],
          400,
        );
      }

      //store file in /var/www/html/storage/app/certs/
      $path = $file->storeAs('certs', 'cert.' . $ext);

      //export file to pem
      $pem = $certs['pkey'] . $certs['cert'];

      //if file has .pfx extension, then export to p12
      if ($ext === 'pfx') {
        $p12 = $filecontent;
        file_put_contents(storage_path('app/certs/cert.p12'), $p12);
      }
      file_put_contents(storage_path('app/certs/cert.pem'), $pem);

      //update config
      $config = Config::first();
      if (!$config) {
        $config = new Config();
      }
      $certinfo = openssl_x509_parse($certs['cert']);

      $config->CERT_PASS = $password;
      if (isset($certinfo['subject']['UID'])) {
        $config->DTE_RUT_CERT = $certinfo['subject']['UID'];
      } else {
        if ($request->has('rut')) {
          $config->DTE_RUT_CERT = $request->input('rut');
        }
      }
      if (isset($certinfo['subject']['CN'])) {
        $config->DTE_NOMBRE_CERT = $certinfo['subject']['CN'];
      } else {
        if ($request->has('nombre')) {
          $config->DTE_NOMBRE_CERT = $request->input('nombre');
        }
      }
      $config->save();

      return response()->json(
        [
          'message' => 'Certificado ' . $ext . ' subido correctamente. ',
        ],
        200,
      );
    }
    return response()->json(['error' => 'Error al subir archivo'], 400);
  }

  public function setConfig(Request $request)
  {
    $rules = [
      'SII_USER' => 'string|min:9|max:12',
      'SII_PASS' => 'string|min:6',
      'SII_SERVER' => 'string|in:maullin,palena',
      'SII_ENV' => 'string|in:certificacion,produccion',
      'CERT_PASS' => 'string|min:6',
      'DTE_RUT_CERT' => 'string|min:9|max:12',
      'DTE_NOMBRE_CERT' => 'string|min:6',
      'DTE_RUT_EMPRESA' => 'string|min:9|max:12',
      'DTE_NOMBRE_EMPRESA' => 'string|min:6',
      'DTE_GIRO' => 'string|min:6',
      'DTE_DIRECCION' => 'string|min:6',
      'DTE_COMUNA' => 'string|min:3',
      'DTE_ACT_ECONOMICA' => 'numeric|min:4',
    ];

    $this->validate($request, $rules);

    $config = Config::first();

    if (!$config) {
      $config = new Config();
    }

    if ($request->input('SII_USER')) {
      $config->update(['SII_USER' => $request->input('SII_USER')]);
    }
    if ($request->input('SII_PASS')) {
      $config->update(['SII_PASS' => $request->input('SII_PASS')]);
    }
    if ($request->input('SII_SERVER')) {
      $config->update(['SII_SERVER' => $request->input('SII_SERVER')]);
    }
    if ($request->input('SII_ENV')) {
      $config->update(['SII_ENV' => $request->input('SII_ENV')]);
    }
    if ($request->input('CERT_PASS')) {
      $config->update(['CERT_PASS' => $request->input('CERT_PASS')]);
    }
    if ($request->input('DTE_RUT_CERT')) {
      $config->update(['DTE_RUT_CERT' => $request->input('DTE_RUT_CERT')]);
    }
    if ($request->input('DTE_NOMBRE_CERT')) {
      $config->update([
        'DTE_NOMBRE_CERT' => $request->input('DTE_NOMBRE_CERT'),
      ]);
    }
    if ($request->input('DTE_RUT_EMPRESA')) {
      $config->update([
        'DTE_RUT_EMPRESA' => $request->input('DTE_RUT_EMPRESA'),
      ]);
    }
    if ($request->input('DTE_NOMBRE_EMPRESA')) {
      $config->update([
        'DTE_NOMBRE_EMPRESA' => $request->input('DTE_NOMBRE_EMPRESA'),
      ]);
    }

    if ($request->input('DTE_GIRO')) {
      $config->update(['DTE_GIRO' => $request->input('DTE_GIRO')]);
    }
    if ($request->input('DTE_DIRECCION')) {
      $config->update(['DTE_DIRECCION' => $request->input('DTE_DIRECCION')]);
    }
    if ($request->input('DTE_COMUNA')) {
      $config->update(['DTE_COMUNA' => $request->input('DTE_COMUNA')]);
    }
    if ($request->input('DTE_ACT_ECONOMICA')) {
      $config->update([
        'DTE_ACT_ECONOMICA' => $request->input('DTE_ACT_ECONOMICA'),
      ]);
    }

    $config->save();

    return response()->json($config, 200);
  }

  public function getConfig()
  {
    $config = Config::first();

    return response()->json($config, 200);
  }

  public function gmail()
  {
    // Set up the API client
    $client = new Client();
    $client->setApplicationName('My App');
    $client->setScopes(Gmail::GMAIL_READONLY);
    $client->setAuthConfig(env('GOOGLE_APPLICATION_CREDENTIALS'));
    $client->setAccessType('offline');

    // Prompt the user to authorize the app
    $authUrl = $client->createAuthUrl();
    print "Please visit this URL to authorize this app: $authUrl\n";
    print 'Enter the authorization code: ';
    $authCode = trim(fgets(STDIN));

    // Exchange the authorization code for an access token
    $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
    $client->setAccessToken($accessToken);

    // Check if the access token has expired
    if ($client->isAccessTokenExpired()) {
      // Refresh the access token if it has expired
      $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
      $client->setAccessToken($client->getAccessToken());
    }
  }
}
