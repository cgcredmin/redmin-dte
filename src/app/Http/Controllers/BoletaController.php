<?php

namespace App\Http\Controllers;

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

use App\Http\Traits\DteEnvioBoletaTrait;

use Illuminate\Http\Request;


class BoletaController extends Controller
{
  use DteEnvioBoletaTrait;

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

      'Detalle' => 'required',
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
    $boleta = [
      'Encabezado' => $request->input('Encabezado'),
      'Detalle' => $request->input('Detalle'),
    ];
    if ($request->input('Referencia')) {
      $boleta['Referencia'] = $request->input('Referencia');
    }

    // $caratula = $request->input('Caratula');
    if ($this->FchResol == null || $this->FchResol == '') {
      $config = \App\Models\Config::first();
      $this->FchResol = $config->DTE_FECHA_RESOL;
      $this->NroResol = $config->DTE_NUM_RESOL;
    }
    $caratula = [
      'RutEnvia' => $this->rutCert,
      'RutReceptor' => $request->input('Encabezado.Receptor.RUTRecep'),
      'FchResol' => $this->FchResol,
      'NroResol' => $this->NroResol,
    ];

    if ($this->ambiente === 'certificacion') {
      $boleta['Encabezado']['Receptor'] = [
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

    $DTE = new Dte($boleta);
    $DTE->timbrar($Folios);
    $DTE->firmar($this->firma);

    // generar sobre con el envío del DTE y enviar al SII
    $EnvioDTE = new Sii\EnvioDte();
    $EnvioDTE->agregar($DTE);
    $EnvioDTE->setFirma($this->firma);
    $EnvioDTE->setCaratula($caratula);
    $EnvioDTE->generar();

    if ($EnvioDTE->schemaValidate()) {
      $xml = (string) $EnvioDTE->generar();
      $result = $this->sendTicket($xml);

      $track_id = $result['trackid'];
      $doc = $EnvioDTE->getDocumentos()[0];
      $ted = $doc ? $doc->getTED() : '';

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
}
