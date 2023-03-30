<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use sasco\LibreDTE\Sii;
use sasco\LibreDTE\Sii\Dte;
use sasco\LibreDTE\Estado;

use Illuminate\Support\Facades\Storage;

use App\Models\RegistroCompraVenta;
use App\Models\ComprobacionSii;
use Milon\Barcode\DNS2D;

use App\Models\Tempfiles;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

class DteController extends Controller
{
  public function comprobarDocumento(Request $request)
  {
    $rules = [
      'rutEmisor' => 'required|string',
      // 'rutReceptor'=>'required|string',
      'tipoDoc' => 'required|numeric',
      'folioDoc' => 'required|numeric',
      'fechaEmision' => 'required|date',
      'montoTotal' => 'required|numeric',
    ];

    $this->validate($request, $rules);

    //get the dv from rutEmisor
    $dvEmisor = substr($request->input('rutEmisor'), -1);
    $rutEmisor = substr($request->input('rutEmisor'), 0, -2);

    //get rutEmpresa without dv
    $dvEmpresa = substr($this->rutEmpresa, -1);
    $rutEmpresa = substr($this->rutEmpresa, 0, -2);

    //get rutConsultante without dv
    $dvContultante = substr($this->rutCert, -1);
    $rutConsultante = substr($this->rutCert, 0, -2);

    // dd($this->dteconfig);

    try {
      $token = $this->token(true);

      if (!$token) {
        return response()->json([
          'status' => 'error',
          'message' => 'No se pudo obtener el token',
        ]);
      }

      //convert $request->fechaEmision to format dmY
      $fechaEmision = date('dmY', strtotime($request->fechaEmision));

      $xml = Sii::request('QueryEstDte', 'getEstDte', [
        'RutConsultante' => $rutConsultante,
        'DvConsultante' => $dvContultante,
        'RutCompania' => $rutEmisor,
        'DvCompania' => $dvEmisor,
        'RutReceptor' => $rutEmpresa,
        'DvReceptor' => $dvEmpresa,
        'TipoDte' => $request->tipoDoc,
        'FolioDte' => $request->folioDoc,
        'FechaEmisionDte' => $fechaEmision,
        'MontoDte' => $request->montoTotal,
        'token' => $token,
      ]);

      // si el estado se pudo recuperar se muestra
      if ($xml !== false) {
        $result = $xml->xpath('/SII:RESPUESTA/SII:RESP_HDR')[0];

        if ($result->ERR_CODE > 15 || $result->ERR_CODE < 0) {
          return response()->json($result, 400);
        }

        $rcv = RegistroCompraVenta::where([
          ['detTipoDoc', '=', $request->tipoDoc],
          ['detNroDoc', '=', $request->folioDoc],
          ['detFchDoc', '=', $request->fechaEmision],
          ['detMntNeto', '=', $request->montoTotal],
          ['detRutDoc', '=', $rutEmisor],
        ])->first();

        if ($rcv) {
          $comprobacion = ComprobacionSii::where(
            'registro_compra_venta_id',
            '=',
            $rcv->id,
          )->first();
          if ($comprobacion) {
            $comprobacion->estado = $result->ESTADO;
            $comprobacion->glosa_estado = $result->GLOSA_ESTADO;
            $comprobacion->error = $result->ERR_CODE;
            $comprobacion->glosa_error = $result->GLOSA_ERR;
            $comprobacion->fecha_consulta = date('Y-m-d H:i:s');
            // $comprobacion->xml = $xml->saveXML();
          } else {
            $comprobacion = new ComprobacionSii();
            $comprobacion->registro_compra_venta_id = $rcv->id;
            $comprobacion->estado = $result->ESTADO;
            $comprobacion->glosa_estado = $result->GLOSA_ESTADO;
            $comprobacion->error = $result->ERR_CODE;
            $comprobacion->glosa_error = $result->GLOSA_ERR;
            $comprobacion->fecha_consulta = date('Y-m-d H:i:s');
            // $comprobacion->xml = $xml->saveXML();
          }
          $comprobacion->save();
        }

        return response()->json($result, 200);
      }

      // si hubo errores se muestran
      if (!$xml) {
        return response()->json(\sasco\LibreDTE\Log::readAll(), 400);
      }
    } catch (\Exception $e) {
      return response()->json($e->getMessage(), 400);
    }
  }

  public function comprobarDocumentoAv(Request $request)
  {
    $rules = [
      'rutEmisor' => 'required|string',
      // 'rutReceptor'=>'required|string',
      'tipoDoc' => 'required|numeric',
      'folioDoc' => 'required|numeric',
      'fechaEmision' => 'required|date',
      'montoTotal' => 'required|numeric',
    ];

    $this->validate($request, $rules);

    //get the dv from rutEmisor
    $dvEmisor = substr($request->input('rutEmisor'), -1);
    $rutEmisor = substr($request->input('rutEmisor'), 0, -2);

    //get rutEmpresa without dv
    $dvEmpresa = substr($this->rutEmpresa, -1);
    $rutEmpresa = substr($this->rutEmpresa, 0, -2);

    //get rutConsultante without dv
    $dvContultante = substr($this->rutCert, -1);
    $rutConsultante = substr($this->rutCert, 0, -2);

    // dd($this->dteconfig);

    try {
      $token = $this->token(true);

      if (!$token) {
        return response()->json([
          'status' => 'error',
          'message' => 'No se pudo obtener el token',
        ]);
      }

      //convert $request->fechaEmision to format dmY
      $fechaEmision = date('dmY', strtotime($request->fechaEmision));

      $xml = Sii::request('QueryEstDteAv', 'getEstDteAv', [
        'RutEmpresa' => $rutEmisor,
        'DvEmpresa' => $dvEmisor,
        'RutReceptor' => $rutEmpresa,
        'DvReceptor' => $dvEmpresa,
        'TipoDte' => $request->tipoDoc,
        'FolioDte' => $request->folioDoc,
        'FechaEmisionDte' => $fechaEmision,
        'MontoDte' => $request->montoTotal,
        'FirmaDte' => $this->firma, //TODO: obtener firma del xml del documento
        'token' => $token,
      ]);

      // si el estado se pudo recuperar se muestra
      if ($xml !== false) {
        $result = $xml->xpath('/SII:RESPUESTA/SII:RESP_HDR')[0];

        if ($result->ERR_CODE > 15 || $result->ERR_CODE < 0) {
          return response()->json($result, 400);
        }

        /* $rcv = RegistroCompraVenta::where([
          ['detTipoDoc', '=', $request->tipoDoc],
          ['detNroDoc', '=', $request->folioDoc],
          ['detFchDoc', '=', $request->fechaEmision],
          ['detMntNeto', '=', $request->montoTotal],
          ['detRutDoc', '=', $rutEmisor],
        ])->first();

        if ($rcv) {
          $comprobacion = ComprobacionSii::where(
            'registro_compra_venta_id',
            '=',
            $rcv->id,
          )->first();
          if ($comprobacion) {
            $comprobacion->estado = $result->ESTADO;
            $comprobacion->glosa_estado = $result->GLOSA_ESTADO;
            $comprobacion->error = $result->ERR_CODE;
            $comprobacion->glosa_error = $result->GLOSA_ERR;
            $comprobacion->fecha_consulta = date('Y-m-d H:i:s');
            $comprobacion->xml = $xml->saveXML();
            $comprobacion->save();
          } else {
            $comprobacion = new ComprobacionSii();
            $comprobacion->registro_compra_venta_id = $rcv->id;
            $comprobacion->estado = $result->ESTADO;
            $comprobacion->glosa_estado = $result->GLOSA_ESTADO;
            $comprobacion->error = $result->ERR_CODE;
            $comprobacion->glosa_error = $result->GLOSA_ERR;
            $comprobacion->fecha_consulta = date('Y-m-d H:i:s');
            // $comprobacion->xml = $xml->saveXML();
            $comprobacion->save();
          }
        } */

        return response()->json($result, 200);
      }

      // si hubo errores se muestran
      if (!$xml) {
        return response()->json(\sasco\LibreDTE\Log::readAll(), 400);
      }
    } catch (\Exception $e) {
      return response()->json($e->getMessage(), 400);
    }
  }

  public function enviaDocumento(Request $request)
  {
    $rules = [
      'Encabezado.IdDoc.TipoDTE' => 'required',
      'Encabezado.IdDoc.Folio' => 'required',

      'Encabezado.Emisor.RUTEmisor' => 'required',
      'Encabezado.Emisor.RznSoc' => 'required',
      'Encabezado.Emisor.GiroEmis' => 'required',
      'Encabezado.Emisor.Acteco' => 'required',
      'Encabezado.Emisor.DirOrigen' => 'required',
      'Encabezado.Emisor.CmnaOrigen' => 'required',

      'Encabezado.Receptor.RUTRecep' => 'required',
      'Encabezado.Receptor.RznSocRecep' => 'required',
      'Encabezado.Receptor.GiroRecep' => 'required',
      'Encabezado.Receptor.DirRecep' => 'required',
      'Encabezado.Receptor.CmnaRecep' => 'required',

      'Detalle' => 'required_if:Encabezado.IdDoc.TipoDTE,33',
      'Detalle.*.NmbItem' => 'required',
      'Detalle.*.QtyItem' => 'required',
      'Detalle.*.PrcItem' => 'required',

      // 'Caratula.RutReceptor' => 'required',
      // 'Caratula.FchResol' => 'required',
      // 'Caratula.NroResol' => 'required',
    ];

    $this->validate($request, $rules);

    $tipo_dte = $request->input('Encabezado.IdDoc.TipoDTE');

    //check if file exists
    if (!file_exists($this->rutas->folios . "$tipo_dte.xml")) {
      return response()->json(
        ['error' => 'No existen folios para este tipo de documento'],
        400,
      );
    }

    // Objetos de Firma y Folios
    $xmlFolios = file_get_contents($this->rutas->folios . "$tipo_dte.xml");

    $Folios = new Sii\Folios($xmlFolios);

    // generar XML del DTE timbrado y firmado
    $factura = [
      'Encabezado' => $request->input('Encabezado'),
      'Detalle' => $request->input('Detalle'),
    ];
    if ($request->input('Referencia')) {
      $factura['Referencia'] = $request->input('Referencia');
    }
    // $caratula = $request->input('Caratula');
    $caratula = [
      'RutEnvia' => $this->rutSiiUser,
      'RutReceptor' => $request->input('Encabezado.Receptor.RUTRecep'),
      'FchResol' => $this->FchResol,
      'NroResol' => $this->NroResol,
    ];

    // cuando está en ambiente de certificación se inyecta datos de receptor
    if ($this->ambiente === 'certificacion') {
      $factura['Encabezado']['Receptor'] = [
        'RUTRecep' => '60803000-K',
        'RznSocRecep' => 'Servicio de Impuestos Internos',
        'GiroRecep' => 'Gobierno',
        'DirRecep' => 'Alonso Ovalle 680',
        'CmnaRecep' => 'Santiago',
      ];

      //cambiar rutCeptor en caratula
      $caratula['RutReceptor'] = '60803000-K';
      // cambiar NroResol en encabezado
      $caratula['NroResol'] = 0;
    }

    $DTE = new Dte($factura);
    $DTE->timbrar($Folios);
    $DTE->firmar($this->firma);

    // generar sobre con el envío del DTE y enviar al SII
    $EnvioDTE = new Sii\EnvioDte();
    $EnvioDTE->agregar($DTE);
    $EnvioDTE->setFirma($this->firma);
    $EnvioDTE->setCaratula($caratula);
    $EnvioDTE->generar();

    if ($EnvioDTE->schemaValidate()) {
      $xml = $EnvioDTE->generar();
      $track_id = $EnvioDTE->enviar();
      $doc = $EnvioDTE->getDocumentos()[0];
      $ted = $doc ? $doc->getTED() : '';
      // return $ted;

      //Generar Timbre en PNG
      $code = new DNS2D();
      $timbre =
        $ted != '' && $track_id !== false
          ? $code->getBarcodePNG($ted, 'PDF417')
          : '';

      $filename = 'xml/dte/' . $DTE->getID() . ' - TI' . $track_id . '.xml';
      Storage::put($filename, $xml);
      $xmlstring = Storage::get($filename);

      $stringXml = $track_id !== false ? base64_encode($xmlstring) : '';
      // dd([
      //   'trackId' => $track_id,
      //   'xml' => $stringXml,
      //   'timbre' => $timbre,
      //   'filename' => $filename,
      // ]);

      dispatch(
        new \App\Jobs\SendDTE(
          'cj.guajardo@gmail.com',
          $request->input('Encabezado.IdDoc.Folio'),
          $tipo_dte,
          $filename,
        ),
      );

      return response()->json(
        [
          'trackId' => $track_id,
          'xml' => $stringXml,
          'timbre' => "$timbre",
        ],
        200,
      );
    } else {
      $error = $this->parsearErrores([
        Estado::DTE_ERROR_GETDATOS,
        Estado::DTE_ERROR_TIPO,
        Estado::DTE_ERROR_RANGO_FOLIO,
        Estado::DTE_FALTA_FCHEMIS,
        Estado::DTE_FALTA_MNTTOTAL,
        Estado::DTE_ERROR_TIMBRE,
        Estado::DTE_ERROR_FIRMA,
        Estado::DTE_ERROR_LOADXML,
        Estado::ENVIO_OK,
        Estado::ENVIO_USUARIO_INCORRECTO,
        Estado::ENVIO_TAMANIO_ARCHIVO,
        Estado::ENVIO_ARCHIVO_CORTADO,
        Estado::ENVIO_NO_AUTENTICADO,
        Estado::ENVIO_EMPRESA_NO_AUTORIZADA,
        Estado::ENVIO_ESQUEMA_INVALIDO,
        Estado::ENVIO_ERROR_FIRMA,
        Estado::ENVIO_SISTEMA_BLOQUEADO,
        Estado::ENVIO_SSL_SIN_VERIFICAR,
        Estado::ENVIO_ERROR_CURL,
        Estado::ENVIO_ERROR_500,
        Estado::ENVIO_ERROR_XML,
        Estado::ENVIO_ERROR_GZIP,
        EStado::DOCUMENTO_FALTA_XML,
        Estado::DOCUMENTO_FALTA_SCHEMA,
        Estado::DOCUMENTO_ERROR_SCHEMA,
        Estado::DOCUMENTO_ERROR_GENERAR_XML,
      ]);
      return response()->json(['error' => $error], 400);
    }
  }

  public function getTempLink(Request $request)
  {
    $hash = $request->hash ?? '-1';
    // dd($hash);
    $compo = ComprobacionSii::where('pdf', $hash)->first();

    if ($compo) {
      $fileName = Crypt::decryptString($compo->pdf);
      $fileName = str_replace($this->rutas->pdf, '', $fileName);
      // dd($fileName);

      if (Storage::disk('pdfs')->exists($fileName) == false) {
        return response()->json(
          ['error' => 'No se encontró el documento'],
          400,
        );
      }

      $file = Storage::disk('pdfs')->get($fileName);

      if ($request->has('formato')) {
        if ($request->formato == 'base64') {
          //get mime type
          $finfo = finfo_open(FILEINFO_MIME_TYPE);
          $mime = finfo_buffer($finfo, $file);
          finfo_close($finfo);

          return response(
            'data:' . $mime . ';base64,' . base64_encode($file),
            200,
          );
        } elseif ($request->formato == 'url') {
          $extension = pathinfo($fileName, PATHINFO_EXTENSION);
          $base64 = base64_encode($file);
          $name = md5($base64) . ".$extension";
          $hash = encrypt($name);

          $url = env('APP_URL') . "/api/file/$hash";

          //set expiration date to 1 hour
          $expira = Carbon::now()->addHour();

          //store to temp directory
          $path = Storage::disk('temp')->put($name, $file);

          $tf = TempFiles::create([
            'nombre' => $fileName,
            'hash' => $hash,
            'ext' => $extension,
            'ruta' => $path,
            'expires_at' => $expira,
          ]);

          return response()->json(['data' => $url], 200);
        } elseif ($request->formato == 'download') {
          return response()->download($file, $fileName);
        } elseif ($request->formato == 'raw') {
          return response($file, 200)->header(
            'Content-Type',
            'application/pdf',
          );
        }
      } else {
        return response()->json(['error' => 'Formato no válido'], 400);
      }
    }

    return response()->json(['error' => 'No se encontró el documento'], 400);
  }
}
