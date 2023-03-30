<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use sasco\LibreDTE\Sii\Dte\Base\DteImpreso;
use App\Http\Traits\DteDatosTrait;

use Illuminate\Support\Carbon;

use App\Models\Log;
use App\Models\Config;
use App\Models\Tempfiles;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ConfigController extends Controller
{
  use DteImpreso;
  use DteDatosTrait;

  private $sqliteUploadErrors = [];

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

        $desde = (string) $xml->CAF->DA->RNG->D;
        $hasta = (string) $xml->CAF->DA->RNG->H;
        $fecha = (string) $xml->CAF->DA->FA;

        // store file in '/var/www/html/storage/app/xml/folios'
        $path = $file->storeAs('xml/folios', $tipo_folio . '.xml');
        return response()->json(
          [
            'message' => 'Folio ' . $tipo_folio . ' subido correctamente. ',
            'desde' => $desde,
            'hasta' => $hasta,
            'fecha' => $fecha,
            'tipo' => $tipo_folio,
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
      'DTE_FECHA_RESOL' => 'date:format("Y-m-d")',
      'DTE_NUM_RESOL' => 'numeric|min:0',
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
    if ($request->input('DTE_FECHA_RESOL')) {
      $config->update([
        'DTE_FECHA_RESOL' => $request->input('DTE_FECHA_RESOL'),
      ]);
    }
    if ($request->has('DTE_NUM_RESOL')) {
      $config->update([
        'DTE_NUM_RESOL' => $request->input('DTE_NUM_RESOL'),
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
    /* $client = new Client();
    $client->setApplicationName("RedminDTE");
    $client->setScopes(Gmail::GMAIL_READONLY);
    $client->setAuthConfig(env("GOOGLE_APPLICATION_CREDENTIALS"));
    $client->setAccessType("offline");

    // Prompt the user to authorize the app
    $authUrl = $client->createAuthUrl();
    $message = "Please visit this URL to authorize this app: $authUrl\n";
    return response()->json($message, 200); */
  }

  public function gmail_step2(Request $request)
  {
    $rules = [
      'codigo' => 'required',
    ];
    $this->validate($request, $rules);

    $authCode = trim($request->codigo);

    return response()->json($authCode, 200);

    /* $client = new Client();
    $client->setApplicationName("RedminDTE");
    $client->setScopes(Gmail::GMAIL_READONLY);
    $client->setAuthConfig(env("GOOGLE_APPLICATION_CREDENTIALS"));
    $client->setAccessType("offline");

    // Exchange the authorization code for an access token
    $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
    $client->setAccessToken($accessToken);

    // Check if the access token has expired
    if ($client->isAccessTokenExpired()) {
      // Refresh the access token if it has expired
      $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
      $client->setAccessToken($client->getAccessToken());
    } */
  }

  public function getLogs(Request $request)
  {
    $rules = [
      'desde' => 'date',
      'hasta' => 'date',
      'dia' => 'date',
      'errores' => 'boolean',
    ];

    $this->validate($request, $rules);

    $desde = $request->has('desde') ? $request->desde : null;
    $hasta = $request->has('hasta') ? $request->hasta : null;
    $dia = $request->has('dia') ? $request->dia : null;
    $errores = $request->has('errores') ? $request->errores : false;

    $logs = Log::select(['created_at', 'message'])->whereRaw("message != ''");
    if ($dia) {
      $logs = $logs->whereRaw('DATE(created_at) = ?', $dia);
    } elseif ($desde || $hasta) {
      if ($desde) {
        $logs = $logs->where('created_at', '>=', $desde);
      }
      if ($hasta) {
        $logs = $logs->where('created_at', '<=', $hasta);
      }
    }

    if ($errores) {
      $logs = $logs->whereRaw("message LIKE '%error%'");
    }

    if ($logs) {
      $logs = $logs->orderBy('created_at', 'DESC')->get();
      return response()->json($logs, 200);
    }

    return response()->json(['message' => 'No se encontraron logs'], 404);
  }

  public function uploadDataBase(Request $request)
  {
    // only if DB_CONNECTION is sqlite
    if (env('DB_CONNECTION') != 'sqlite') {
      return response()->json(
        ['errors' => 'La base de datos no es sqlite'],
        400,
      );
    }

    $rules = [
      'archivo' => 'required|file',
    ];
    $this->validate($request, $rules);

    //validate that the file is a valid sqlite database
    $file = $request->file('archivo');
    $pass = $this->checkDataBase($file);
    if (!$pass) {
      return response()->json(['errors' => $this->sqliteUploadErrors], 400);
    }

    // database is stored in storage/app/db/redmin_dte.sqlite

    $now = Carbon::now()->format('YmdHi');
    //backup current database, just move it to storage/app/db/backup-{timestamp}.sqlite
    $backup = storage_path('app/db/backup-' . $now . '.sqlite');
    $current = storage_path('app/db/redmin_dte.sqlite');
    if (file_exists($current)) {
      // copy current database to backup
      copy($current, $backup);
    }

    //copy uploaded database to storage/app/db/redmin_dte.sqlite
    copy(storage_path('app/db/uploaded.sqlite'), $current);

    // set permissions
    chmod(storage_path('app/db/redmin_dte.sqlite'), 0777);

    //delete uploaded database
    unlink(storage_path('app/db/uploaded.sqlite'));

    return response()->json(['message' => 'Base de datos actualizada'], 200);
  }

  /**
   * Download the database as a compressed zip file
   */
  public function downloadDatabase()
  {
    // only if DB_CONNECTION is sqlite
    if (env('DB_CONNECTION') != 'sqlite') {
      $database = $this->backupSQLDB();
      if (!$database) {
        return response()->json(
          ['errors' => 'No se pudo crear la copia de seguridad'],
          400,
        );
      }

      return response()->json($database);
    } else {
      $file = storage_path('app/db/redmin_dte.sqlite');

      // compress the database file
      $zip = new \ZipArchive();
      if (
        $zip->open(storage_path('app/db/redmin_dte.zip'), \ZipArchive::CREATE)
      ) {
        // generate a random password for the zip file
        $hash = md5(uniqid(rand(), true));
        $zip->addFile($file, 'redmin_dte.sqlite');
        $zip->setEncryptionName($file, \ZipArchive::EM_AES_256, $hash);
        $zip->close();

        $tempfile = Tempfiles::create([
          'nombre' => 'redmin_dte.zip',
          'ruta' => storage_path('app/db/redmin_dte.zip'),
          'ext' => 'zip',
          'hash' => $hash,
        ]);

        return response()->json(['data' => $tempfile], 200);
      }

      return response()->json(
        ['message' => 'No se pudo comprimir la base de datos'],
        400,
      );
    }
  }

  private function checkDataBase($file)
  {
    $this->sqliteUploadErrors = [];
    $file->move(storage_path('app/db/'), 'uploaded.sqlite');
    $db = new \SQLite3(storage_path('database.sqlite'));
    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table'");
    $tables = [];
    // by every model, check if the table exists
    while ($row = $result->fetchArray()) {
      $tables[] = $row[0];

      //check if the table has the same columns as the model
      $model = 'App\\' . ucfirst($row[0]);
      $model = new $model();
      $columns = $model
        ->getConnection()
        ->getSchemaBuilder()
        ->getColumnListing($row[0]);
      $table_columns = $db->query("PRAGMA table_info('" . $row[0] . "')");
      $table_columns = $table_columns->fetchArray();
      if (count($columns) != count($table_columns)) {
        $this->sqliteUploadErrors[] =
          'La tabla ' . $row[0] . ' no tiene las mismas columnas que el modelo';
      }
    }

    if (count($this->sqliteUploadErrors) > 0) {
      //delete uploaded database, if exists
      if (file_exists(storage_path('app/db/uploaded.sqlite'))) {
        unlink(storage_path('app/db/uploaded.sqlite'));
      }
      return false;
    }

    return true;
  }

  private function backupSQLDB()
  {
    // Nombre de la base de datos a respaldar
    $databaseName = DB::getDatabaseName();

    // Nombre del archivo de backup
    $backupFileName = $databaseName . '_' . date('Y-m-d_H-i-s') . '.sql';

    // Ruta del archivo de backup temporal
    $backupFilePath = storage_path('app/db/' . $backupFileName);

    // Comando para generar el backup de la base de datos
    $command = sprintf(
      'mysqldump --user=%s --password=%s --host=%s %s > %s',
      env('DB_USERNAME'),
      env('DB_PASSWORD'),
      env('DB_HOST'),
      $databaseName,
      $backupFilePath,
    );

    // Ejecutar el comando
    exec($command);

    Storage::disk('backups')->put(
      $backupFileName,
      file_get_contents($backupFilePath),
    );

    // Eliminar el archivo de backup temporal
    unlink($backupFilePath);

    //obtener link temporal desde S3
    $temp_url = Storage::disk('backups')->temporaryUrl(
      $backupFileName,
      now()->addMinutes(5),
    );

    return $temp_url;
  }
}
