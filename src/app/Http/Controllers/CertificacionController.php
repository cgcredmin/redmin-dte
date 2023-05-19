<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use sasco\LibreDTE\Sii\Dte\Base\DteImpreso;
use App\Http\Traits\DteAuthTrait;

class CertificacionController extends Controller
{
  use DteImpreso;
  use DteAuthTrait;

  protected $Emisor;
  protected $Receptor;

  // {'29', '30', '32', '33', '34', '35', '38', '39', '40', '41', '43', '45', '46', '48', '53', '55', '56', '60', '61', '101', '102', '103', '104', '105', '106', '108', '109', '110', '111', '112', '175', '180', '185', '900', '901', '902', '903', '904', '905', '906', '907', '909', '910', '911', '914', '918', '919', '920', '921', '922', '924', '500', '501'}
  protected $tiposDTEPermitidos = [
    '29',
    '30',
    '32',
    '33',
    '34',
    '35',
    '38',
    '39',
    '40',
    '41',
    '43',
    '45',
    '46',
    '48',
    '53',
    '55',
    '56',
    '60',
    '61',
    '101',
    '102',
    '103',
    '104',
    '105',
    '106',
    '108',
    '109',
    '110',
    '111',
    '112',
    '175',
    '180',
    '185',
    '900',
    '901',
    '902',
    '903',
    '904',
    '905',
    '906',
    '907',
    '909',
    '910',
    '911',
    '914',
    '918',
    '919',
    '920',
    '921',
    '922',
    '924',
    '500',
    '501',
  ];

  public function __construct()
  {
    parent::__construct();

    // datos del emisor
    $this->Emisor = [
      'RUTEmisor' => $this->rutEmpresa,
      'RznSoc' => $this->nombreEmpresa,
      'GiroEmis' => $this->giro,
      'Acteco' => $this->actividad_economica,
      'DirOrigen' => $this->direccion,
      'CmnaOrigen' => trim($this->comuna),
    ];

    // datos del receptor
    $this->Receptor = [
      'RUTRecep' => '60803000-K',
      'RznSocRecep' => 'Servicio de Impuestos Internos',
      'GiroRecep' => 'Gobierno',
      'DirRecep' => 'Alonso Ovalle 680',
      'CmnaRecep' => 'Santiago',
    ];
  }

  private function updateFoliosConfig($folios)
  {
    //save new folios to file
    file_put_contents(
      $this->rutas->certificacion . 'folios.json',
      json_encode($folios),
    );
  }

  public function sendSetPruebas(Request $request)
  {
    // dd([$this->servidor, $this->ambiente]);
    $rules = [
      'set' => 'required|file|mimes:txt',
      'folios.*' => 'required|numeric|min:1',
      'nombre' => 'required|string|in:BASICO,GUIA_DESPACHO,COMPRAS',
    ];
    $this->validate($request, $rules);

    $set = $request->file('set');

    $folios = json_decode($request->folios, true, 512, JSON_THROW_ON_ERROR);

    // caratula para el envío de los dte
    $caratula = [
      'RutEnvia' => $this->rutCert,
      'RutReceptor' => $this->Receptor['RUTRecep'],
      'FchResol' => $this->FchResol,
      'NroResol' => 0,
    ];
    // dd($caratula);

    $set_pruebas = json_decode(
      \sasco\LibreDTE\Sii\Certificacion\SetPruebas::getJSON(
        file_get_contents($set->getRealPath()),
        $folios,
      ),
      true,
    );
    // $lines = file($set->getRealPath());
    // $set_pruebas = $this->parseTXT($lines);
    $set_pruebas = $this->changeItemEncoding($set_pruebas);
    // dd($set_pruebas);

    // Objetos de Firma, Folios y EnvioDTE
    $Firma = new \sasco\LibreDTE\FirmaElectronica($this->dteconfig);
    $Folios = [];
    foreach ($folios as $tipo => $cantidad) {
      $Folios[$tipo] = new \sasco\LibreDTE\Sii\Folios(
        file_get_contents($this->rutas->folios . $tipo . '.xml'),
      );
    }
    $EnvioDTE = new \sasco\LibreDTE\Sii\EnvioDte();
    $TasaImp = (float) \sasco\LibreDTE\Sii::getIVA();

    // generar cada DTE, timbrar, firmar y agregar al sobre de EnvioDTE
    foreach ($set_pruebas as $documento) {
      // $documento = collect($documento)->values();
      // dd($documento);
      $documento['Encabezado']['Emisor'] = $this->Emisor;
      $documento['Encabezado']['Receptor'] = $this->Receptor;
      $documento['Encabezado']['Totales'] = [
        // estos valores serán calculados automáticamente
        'MntNeto' => 0,
        'MntExe' => 0,
        'TasaIVA' => $TasaImp,
        'IVA' => 0,
        'MntTotal' => 0,
      ];

      try {
        $DTE = new \sasco\LibreDTE\Sii\Dte($documento);
        // dd($DTE);
        if (!$DTE->timbrar($Folios[$DTE->getTipo()])) {
          break;
        }
        if (!$DTE->firmar($Firma)) {
          break;
        }
        $EnvioDTE->agregar($DTE);
      } catch (\Exception $e) {
        dd([$e->getMessage(), $documento]);
      }
    }

    file_put_contents(
      $this->rutas->certificacion .
        'SetPruebas-' .
        strtoupper($request->nombre) .
        '.json',
      json_encode(collect($this->encodeObjectToUTF8($set_pruebas))->values()),
    );
    // return response()->json($set_pruebas);

    // enviar dtes y mostrar resultado del envío: track id o bien =false si hubo error
    $EnvioDTE->setCaratula($caratula);
    $EnvioDTE->setFirma($Firma);
    $xml = $EnvioDTE->generar();
    // dd($xml);
    file_put_contents(
      $this->rutas->certificacion .
        'EnvioDTE-SetPruebas-' .
        strtoupper($request->nombre) .
        '.xml',
      $xml,
    );
    $track_id = $EnvioDTE->enviar();

    // si hubo errores mostrar
    $errors = $this->getErrors($track_id);

    foreach ($folios as $key => $folio) {
      $doc_count = collect($set_pruebas)
        ->where('Encabezado.IdDoc.TipoDTE', $key)
        ->count();
      $folios[$key] = $folio + $doc_count;
    }
    $folios['fch_resol'] = $caratula['FchResol'];
    $folios['exito_set_pruebas'] = $track_id ? true : false;
    $this->updateFoliosConfig($folios);

    return response()->json(
      [
        'errors' => $errors,
        'track_id' => $track_id,
        'set_pruebas' => $this->encodeObjectToUTF8($set_pruebas),
        'folios' => $folios,
      ],
      count($errors) > 0 ? 400 : 200,
    );
  }
  public function sendLibroGuias(Request $request)
  {
    $folios = json_decode(
      file_get_contents($this->rutas->certificacion . 'folios.json'),
      true,
    );

    if (!isset($folios['exito_set_pruebas'])) {
      return response()->json(
        [
          'error' => [
            'No se puede enviar la simulación si no se ha enviado el set de pruebas',
          ],
        ],
        400,
      );
    }
    if ($folios['exito_set_pruebas'] == false) {
      return response()->json(
        [
          'error' => [
            'No se puede enviar la simulación si el envío del set de pruebas falló',
          ],
        ],
        400,
      );
    }

    // caratula del libro
    $caratula = [
      'RutEmisorLibro' => $this->rutEmpresa,
      'FchResol' => $this->FchResol,
      'NroResol' => 0,
      'FolioNotificacion' => 1,
    ];

    $set_pruebas = json_decode(
      file_get_contents(
        $this->rutas->certificacion . 'SetPruebas-GUIA_DESPACHO.json',
      ),
      true,
    );
    // dd($set_pruebas[0]);

    $param = false;
    // Objetos de Firma y LibroGuia
    $Firma = new \sasco\LibreDTE\FirmaElectronica($this->dteconfig);
    $LibroGuias = new \sasco\LibreDTE\Sii\LibroGuia();

    $Folios = [];
    // generar cada DTE y agregar su resumen al detalle del libro
    foreach ($set_pruebas as $k => $documento) {
      // dont load if tipoDTE is not tiposDTEPermitidos
      $tipoDoc = $documento['Encabezado']['IdDoc']['TipoDTE'];
      if (!in_array($tipoDoc, $this->tiposDTEPermitidos)) {
        continue;
      }

      // dd($documento->Encabezado->IdDoc->TipoDTE);
      if (!isset($Folios[$tipoDoc])) {
        $Folios[$tipoDoc] = new \sasco\LibreDTE\Sii\Folios(
          file_get_contents($this->rutas->folios . $tipoDoc . '.xml'),
        );
      }

      try {
        $DTE = new \sasco\LibreDTE\Sii\Dte($documento);
        $resumen = (array) $DTE->getResumen();
        $resumen['RUTDoc'] = $this->rutEmpresa;
        $resumen['RznSoc'] = $this->nombreEmpresa;

        if ($k == 1) {
          $resumen['FchDocRef'] = date('Y-m-d');
        } else {
          $resumen['Anulado'] = 2;
        }
        // dd($resumen);
        $LibroGuias->agregar($resumen); // agregar detalle sin normalizar
      } catch (\Exception $e) {
        dd($documento);
      }
    }

    // enviar libro de ventas y mostrar resultado del envío: track id o bien =false si hubo error
    $LibroGuias->setFirma($Firma);
    $LibroGuias->setCaratula($caratula);
    $LibroGuias->generar($param); // generar XML sin firma y sin detalle
    if ($LibroGuias->schemaValidate()) {
      $xml = $LibroGuias->saveXML();
      // save xml file to certificacion folder
      file_put_contents($this->rutas->certificacion . 'LibroGuias.xml', $xml);
      // dd($xml);
      $track_id = $LibroGuias->enviar(); // enviar XML generado en línea anterior
    } else {
      dd($LibroGuias->schemaValidate(), \sasco\LibreDTE\Log::readAll());
    }

    // si hubo errores mostrar
    $errors = $this->getErrors($track_id);

    $folios['fch_resol'] = $caratula['FchResol'];
    $folios['exito_ventas'] = $track_id ? true : false;
    $this->updateFoliosConfig($folios);

    return response()->json([
      'errors' => $errors,
      'track_id' => $track_id,
      'folios' => $folios,
    ]);
  }
  public function sendLibroVentas(Request $request)
  {
    $folios = json_decode(
      file_get_contents($this->rutas->certificacion . 'folios.json'),
      true,
    );

    if (!isset($folios['exito_set_pruebas'])) {
      return response()->json(
        [
          'error' => [
            'No se puede enviar la simulación si no se ha enviado el set de pruebas',
          ],
        ],
        400,
      );
    }
    if ($folios['exito_set_pruebas'] == false) {
      return response()->json(
        [
          'error' => [
            'No se puede enviar la simulación si el envío del set de pruebas falló',
          ],
        ],
        400,
      );
    }

    $periodo = '2023-03';
    $poriginal = '2023-03';

    // caratula del libro
    $caratula = [
      'RutEnvia' => $this->rutCert,
      'FchResol' => $this->FchResol,
      'NroResol' => 102006,
      'RutEmisorLibro' => $this->rutEmpresa,
      'PeriodoTributario' => $periodo,
      'TipoOperacion' => 'VENTA',
      'TipoLibro' => 'ESPECIAL',
      'TipoEnvio' => 'TOTAL',
      'FolioNotificacion' => 102006,
    ];

    $set_pruebas = json_decode(
      file_get_contents($this->rutas->certificacion . 'SetPruebas-BASICO.json'),
      true,
    );
    // dd($set_pruebas[0]);

    // Objetos de Firma y LibroCompraVenta
    $Firma = new \sasco\LibreDTE\FirmaElectronica($this->dteconfig);
    $LibroCompraVenta = new \sasco\LibreDTE\Sii\LibroCompraVenta();

    $Folios = [];
    // generar cada DTE y agregar su resumen al detalle del libro
    foreach ($set_pruebas as $documento) {
      // dont load if tipoDTE is not tiposDTEPermitidos
      $tipoDoc = $documento['Encabezado']['IdDoc']['TipoDTE'];
      if (!in_array($tipoDoc, $this->tiposDTEPermitidos)) {
        continue;
      }

      // dd($documento->Encabezado->IdDoc->TipoDTE);
      if (!isset($Folios[$tipoDoc])) {
        $Folios[$tipoDoc] = new \sasco\LibreDTE\Sii\Folios(
          file_get_contents($this->rutas->folios . $tipoDoc . '.xml'),
        );
      }

      try {
        $DTE = new \sasco\LibreDTE\Sii\Dte($documento);
        $resumen = (array) $DTE->getResumen();
        $resumen['RUTDoc'] = $this->rutEmpresa;
        $resumen['RznSoc'] = $this->nombreEmpresa;
        // dd($resumen);
        $LibroCompraVenta->agregar($resumen, false); // agregar detalle sin normalizar
      } catch (\Exception $e) {
        dd($documento);
      }
    }

    // enviar libro de ventas y mostrar resultado del envío: track id o bien =false si hubo error
    $LibroCompraVenta->setCaratula($caratula);
    $LibroCompraVenta->generar(true); // generar XML sin firma y sin detalle
    $LibroCompraVenta->setFirma($Firma);
    $xml = $LibroCompraVenta->saveXML();
    // save xml file to certificacion folder
    file_put_contents(
      $this->rutas->certificacion . 'LibroCompraVenta-VENTAS.xml',
      $xml,
    );
    $csv_path = $this->createCSV(
      $this->rutas->certificacion . 'LibroCompraVenta-VENTAS.xml',
      $poriginal,
    );
    // dd($csv_path);

    $simplificado = false;
    $LibroCompraVenta = new \sasco\LibreDTE\Sii\LibroCompraVenta(
      !$simplificado,
    ); // se genera libro simplificado (solicitado así en certificación)

    // agregar detalle desde un archivo CSV con ; como separador
    $LibroCompraVenta->agregarVentasCSV($csv_path);

    // enviar libro de compras y mostrar resultado del envío: track id o bien =false si hubo error
    $LibroCompraVenta->setCaratula($caratula);
    $LibroCompraVenta->generar($simplificado); // generar XML sin firma y sin detalle
    $LibroCompraVenta->setFirma($Firma);

    $track_id = $LibroCompraVenta->enviar(); // enviar XML generado en línea anterior

    $xml = $LibroCompraVenta->saveXML();
    file_put_contents(
      $this->rutas->certificacion . 'LibroCompraVenta-VENTAS2.xml',
      $xml,
    );

    // si hubo errores mostrar
    $errors = $this->getErrors($track_id);

    $folios['fch_resol'] = $caratula['FchResol'];
    $folios['exito_ventas'] = $track_id ? true : false;
    $this->updateFoliosConfig($folios);

    return response()->json([
      'errors' => $errors,
      'track_id' => $track_id,
      'folios' => $folios,
      'xml' => $xml,
    ]);
  }
  public function sendLibroCompras(Request $request)
  {
    $folios = json_decode(
      file_get_contents($this->rutas->certificacion . 'folios.json'),
      true,
    );

    if (!isset($folios['exito_set_pruebas'])) {
      return response()->json(
        [
          'error' => [
            'No se puede enviar la simulación si no se ha enviado el set de pruebas',
          ],
        ],
        400,
      );
    }
    if ($folios['exito_set_pruebas'] == false) {
      return response()->json(
        [
          'error' => [
            'No se puede enviar la simulación si el envío del set de pruebas falló',
          ],
        ],
        400,
      );
    }

    $periodo = '2023-01';
    $poriginal = '2023-03';

    // caratula del libro
    $caratula = [
      'RutEnvia' => $this->rutCert,
      'FchResol' => $this->FchResol,
      'RutEmisorLibro' => $this->rutEmpresa,
      'PeriodoTributario' => $periodo,
      'NroResol' => 102006,
      'TipoOperacion' => 'COMPRA',
      'TipoLibro' => 'ESPECIAL',
      'TipoEnvio' => 'TOTAL',
      'FolioNotificacion' => 102006,
    ];

    // EN FACTURA CON IVA USO COMUN CONSIDERE QUE EL FACTOR DE PROPORCIONALIDAD
    // DEL IVA ES DE 0.60
    $factor_proporcionalidad_iva = 60; // se divide por 100 al agregar al resumen del período
    $TasaImp = (float) \sasco\LibreDTE\Sii::getIVA();

    /*     
      SET LIBRO DE COMPRAS - NUMERO DE ATENCION: 3576504
    */
    $detalles = [
      // FACTURA					234
      // FACTURA DEL GIRO CON DERECHO A CREDITO
      //     35798

      [
        'TpoDoc' => 30,
        'NroDoc' => 234,
        'TasaImp' => $TasaImp,
        'FchDoc' => $caratula['PeriodoTributario'] . '-01',
        'RUTDoc' => $this->rutEmpresa,
        'MntNeto' => 35798,
      ],
      // FACTURA ELECTRONICA 			 32
      // FACTURA DEL GIRO CON DERECHO A CREDITO
      // 10616		  11427
      [
        'TpoDoc' => 33,
        'NroDoc' => 32,
        'TasaImp' => $TasaImp,
        'FchDoc' => $caratula['PeriodoTributario'] . '-01',
        'RUTDoc' => $this->rutEmpresa,
        'MntExe' => 9656,
        'MntNeto' => 8772,
      ],
      // FACTURA					781
      // FACTURA CON IVA USO COMUN
      //     30167
      [
        'TpoDoc' => 30,
        'NroDoc' => 781,
        'TasaImp' => $TasaImp,
        'FchDoc' => $caratula['PeriodoTributario'] . '-02',
        'RUTDoc' => $this->rutEmpresa,
        'MntNeto' => 29960,
        // Al existir factor de proporcionalidad se calculará el IVAUsoComun.
        // Se calculará como MntNeto * (TasaImp/100) y se añadirá a MntIVA.
        // Se quitará del detalle al armar los totales, ya que no es nodo del detalle en el XML.
        'FctProp' => $factor_proporcionalidad_iva,
      ],
      // NOTA DE CREDITO				451
      // NOTA DE CREDITO POR DESCUENTO A FACTURA 234
      //     2926
      [
        'TpoDoc' => 60,
        'NroDoc' => 451,
        'TasaImp' => $TasaImp,
        'FchDoc' => $caratula['PeriodoTributario'] . '-03',
        'RUTDoc' => $this->rutEmpresa,
        'MntNeto' => 2814,
        'TpoDocRef' => 30,
        'FolioDocRef' => 234,
      ],
      // FACTURA ELECTRONICA			 67
      // ENTREGA GRATUITA DEL PROVEEDOR
      //     12115
      [
        'TpoDoc' => 33,
        'NroDoc' => 67,
        'TasaImp' => $TasaImp,
        'FchDoc' => $caratula['PeriodoTributario'] . '-04',
        'RUTDoc' => $this->rutEmpresa,
        'MntNeto' => 10983,
        'IVANoRec' => [
          'CodIVANoRec' => 4,
          'MntIVANoRec' => round(10983 * ($TasaImp / 100)),
        ],
      ],
      // FACTURA DE COMPRA ELECTRONICA		  9
      // COMPRA CON RETENCION TOTAL DEL IVA
      //     10622
      [
        'TpoDoc' => 46,
        'NroDoc' => 9,
        'TasaImp' => $TasaImp,
        'FchDoc' => $caratula['PeriodoTributario'] . '-05',
        'RUTDoc' => $this->rutEmpresa,
        'MntNeto' => 10054,
        'OtrosImp' => [
          'CodImp' => 15,
          'TasaImp' => $TasaImp,
          'MntImp' => round(10054 * ($TasaImp / 100)),
        ],
      ],
      // NOTA DE CREDITO				211
      // NOTA DE CREDITO POR DESCUENTO FACTURA ELECTRONICA 32
      //     9010
      [
        'TpoDoc' => 60,
        'NroDoc' => 211,
        'TasaImp' => $TasaImp,
        'FchDoc' => $caratula['PeriodoTributario'] . '-06',
        'RUTDoc' => $this->rutEmpresa,
        'MntNeto' => 6547,
        'TpoDocRef' => 33,
        'FolioDocRef' => 32,
      ],
    ];

    // Objetos de Firma y LibroCompraVenta
    $Firma = new \sasco\LibreDTE\FirmaElectronica($this->dteconfig);
    $LibroCompraVenta = new \sasco\LibreDTE\Sii\LibroCompraVenta(false);

    // agregar cada uno de los detalles al libro
    foreach ($detalles as $detalle) {
      $LibroCompraVenta->agregar($detalle);
    }

    // enviar libro de compras y mostrar resultado del envío: track id o bien =false si hubo error
    $LibroCompraVenta->setCaratula($caratula);
    $xml = $LibroCompraVenta->generar(true); // generar XML sin firma
    $LibroCompraVenta->setFirma($Firma);

    // save XML to file
    file_put_contents(
      $this->rutas->certificacion . 'LibroCompraVenta-COMPRAS.xml',
      $xml,
    );

    $csv_path = $this->createCSVCompras(
      $this->rutas->certificacion . 'LibroCompraVenta-COMPRAS.xml',
      $poriginal,
    );

    $x = false;
    $LibroCompraVenta = new \sasco\LibreDTE\Sii\LibroCompraVenta(!$x); // se genera libro simplificado (solicitado así en certificación)

    // agregar detalle desde un archivo CSV con ; como separador
    $LibroCompraVenta->agregarVentasCSV($csv_path);

    // enviar libro de compras y mostrar resultado del envío: track id o bien =false si hubo error
    $LibroCompraVenta->setCaratula($caratula);
    $LibroCompraVenta->generar($x); // generar XML sin firma y sin detalle
    $LibroCompraVenta->setFirma($Firma);

    $track_id = $LibroCompraVenta->enviar(); // enviar XML generado en línea anterior

    $xml = $LibroCompraVenta->saveXML();
    file_put_contents(
      $this->rutas->certificacion . 'LibroCompraVenta-COMPRAS2.xml',
      $xml,
    );

    // si hubo errores mostrar
    $errors = $this->getErrors($track_id);

    $folios['fch_resol'] = $caratula['FchResol'];
    $folios['exito_ventas'] = $track_id ? true : false;
    $this->updateFoliosConfig($folios);

    return response()->json([
      'errors' => $errors,
      'track_id' => $track_id,
      'folios' => $folios,
    ]);
  }
  public function sendSimulacion()
  {
    $folios = json_decode(
      file_get_contents($this->rutas->certificacion . 'folios.json'),
      true,
    );

    if (!isset($folios['exito_set_pruebas'])) {
      return response()->json(
        [
          'error' => [
            'No se puede enviar la simulación si no se ha enviado el set de pruebas',
          ],
        ],
        400,
      );
    }
    if ($folios['exito_set_pruebas'] == false) {
      return response()->json(
        [
          'error' => [
            'No se puede enviar la simulación si el envío del set de pruebas falló',
          ],
        ],
        400,
      );
    }

    // caratula para el envío de los dte
    $caratula = [
      'RutEnvia' => $this->rutSiiUser,
      'RutReceptor' => '60803000-K',
      'FchResol' => $folios['fch_resol'],
      'NroResol' => 0,
    ];

    unset($folios['fch_resol']);
    unset($folios['exito_set_pruebas']);

    // datos de los DTE (cada elemento del arreglo $documentos es un DTE)
    $documentos = json_decode(
      file_get_contents(
        $this->rutas->certificacion . 'simulacion/documentos.json',
      ),
      true,
    );

    foreach ($documentos as $key => $documento) {
      $_folio = 0;
      $_tipo = $documento['Encabezado']['IdDoc']['TipoDTE'];
      if (isset($folios[$_tipo])) {
        $_folio = ++$folios[$_tipo];
        $folios[$_tipo] = $_folio;
      }
      $documentos[$key]['Encabezado']['Emisor'] = $this->Emisor;
      $documentos[$key]['Encabezado']['Receptor'] = $this->Receptor;
      $documentos[$key]['Encabezado']['IdDoc']['Folio'] = $_folio;
    }

    // Objetos de Firma, Folios y EnvioDTE
    $Firma = new \sasco\LibreDTE\FirmaElectronica($this->dteconfig);
    $Folios = [];
    foreach ($folios as $tipo => $cantidad) {
      $Folios[$tipo] = new \sasco\LibreDTE\Sii\Folios(
        file_get_contents($this->rutas->folios . $tipo . '.xml'),
      );
    }
    $EnvioDTE = new \sasco\LibreDTE\Sii\EnvioDte();

    // generar cada DTE, timbrar, firmar y agregar al sobre de EnvioDTE
    foreach ($documentos as $documento) {
      $DTE = new \sasco\LibreDTE\Sii\Dte($documento);
      if (!$DTE->timbrar($Folios[$DTE->getTipo()])) {
        break;
      }
      if (!$DTE->firmar($Firma)) {
        break;
      }
      $EnvioDTE->agregar($DTE);
    }

    // enviar dtes y mostrar resultado del envío: track id o bien =false si hubo error
    $EnvioDTE->setCaratula($caratula);
    $EnvioDTE->setFirma($Firma);
    $xml = $EnvioDTE->generar();
    // dd($xml);
    file_put_contents(
      $this->rutas->certificacion . 'EnvioDTE-Simulacion.xml',
      $xml,
    );
    $track_id = $EnvioDTE->enviar();

    // si hubo errores mostrar
    $errors = [];
    foreach (\sasco\LibreDTE\Log::readAll() as $error) {
      $errors[] = collect($error)
        ->only(['code', 'msg'])
        ->toArray();
    }

    $folios['fch_resol'] = $caratula['FchResol'];
    $folios['exito_simulacion'] = $track_id ? true : false;
    $this->updateFoliosConfig($folios);

    return response()->json([
      'errors' => $errors,
      'track_id' => $track_id,
      'folios' => $folios,
    ]);
  }

  private function parseTXT($lines)
  {
    $caso = '';
    $TasaImp = (float) \sasco\LibreDTE\Sii::getIVA();
    $set_pruebas = [];

    foreach ($lines as $line) {
      $line = trim($line);

      if (empty($line)) {
        continue;
      }

      if (stripos($line, 'CASO') === 0) {
        $_c = trim(str_replace('CASO', '', $line));
        if (!isset($set_pruebas[$_c])) {
          $set_pruebas[$_c] = [
            'Encabezado' => [
              'IdDoc' => [
                'TipoDTE' => '',
                'Folio' => '',
              ],
              'Emisor' => $this->Emisor,
              'Receptor' => $this->Receptor,
              'Totales' => [
                // estos valores serán calculados automáticamente
                'MntNeto' => 0,
                'MntExe' => 0,
                'TasaIVA' => $TasaImp,
                'IVA' => 0,
                'MntTotal' => 0,
              ],
            ],
            'Detalle' => [],
            // 'DscRcgGlobal' => [],
            'Referencia' => [
              'TpoDocRef' => 'SET',
              'FolioRef' => '',
              'RazonRef' => "CASO $_c",
            ],
          ];
        }
        $caso = $_c;
      } elseif (stripos($line, 'DOCUMENTO') === 0) {
        $_c = trim(str_replace('DOCUMENTO', '', $line));
        $_c = str_replace(
          ['Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ'],
          ['A', 'E', 'I', 'O', 'U', 'N'],
          $_c,
        );
        foreach ($this->tipos as $key => $value) {
          $_v = str_replace(
            ['Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ'],
            ['A', 'E', 'I', 'O', 'U', 'N'],
            $value,
          );
          if ($_v == $_c) {
            $_folio = isset($folios[$key]) ? ++$folios[$key] : null;
            $set_pruebas[$caso]['Encabezado']['IdDoc'] = [
              'TipoDTE' => $key,
              'Folio' => $_folio,
            ];
            $set_pruebas[$caso]['Referencia']['FolioRef'] = $_folio;
          }
        }
      } elseif (stripos($line, 'REFERENCIA') === 0) {
        dd($line);
        $referencia = trim(str_replace('REFERENCIA', '', $line));
        $ref = $set_pruebas[$caso]['Referencia'];
        $c_ref = trim(substr($referencia, stripos($referencia, 'CASO') + 4));
        $doc_ref = isset($set_pruebas[$c_ref]['Encabezado']['IdDoc'])
          ? $set_pruebas[$c_ref]['Encabezado']['IdDoc']
          : null;
        if ($doc_ref) {
          $set_pruebas[$caso]['Referencia'] = [
            $ref,
            [
              'TpoDocRef' => $doc_ref['TipoDTE'],
              'FolioRef' => $doc_ref['Folio'],
              'CodRef' => 0,
              'RazonRef' => '',
            ],
          ];
        }
      } elseif (stripos($line, 'RAZON REFERENCIA') === 0) {
        $razon = trim(str_replace('RAZON REFERENCIA', '', $line));
        $cod_ref = 0;
        if (
          stripos($razon, 'ANULACION') === 0 ||
          stripos($razon, 'ANULA') === 0
        ) {
          $cod_ref = 1;
        } elseif (
          stripos($razon, 'DEVOLUCION') === 0 ||
          stripos($razon, 'DEVUELVE') === 0
        ) {
          $cod_ref = 3;
        } elseif (
          stripos($razon, 'CORRECCION') === 0 ||
          stripos($razon, 'CORRIGE') === 0
        ) {
          $cod_ref = 2;
        }
        $set_pruebas[$caso]['Referencia'][1]['RazonRef'] = $razon;
        $set_pruebas[$caso]['Referencia'][1]['CodRef'] = $cod_ref;
      } elseif (stripos($line, 'DESCUENTO GLOBAL ITEMES AFECTOS') === 0) {
        if (!isset($set_pruebas[$caso]['DscRcgGlobal'])) {
          $set_pruebas[$caso]['DscRcgGlobal'] = [];
        }
        $set_pruebas[$caso]['DscRcgGlobal'][] = [
          'TpoMov' => 'D',
          'TpoValor' => '%',
          'ValorDR' => floatval(
            trim(str_replace('DESCUENTO GLOBAL ITEMES AFECTOS', '', $line)),
          ),
        ];
      } elseif (stripos($line, 'DESCUENTO GLOBAL ITEMES EXENTOS') === 0) {
        $set_pruebas[$caso]['DscRcgGlobal'][] = [
          'NroLinDR' => 2,
          'TpoMov' => 'D',
          'TpoValor' => '%',
          'ValorDR' => floatval(
            trim(str_replace('DESCUENTO GLOBAL ITEMES EXENTOS', '', $line)),
          ),
        ];
      } elseif (
        stripos($line, 'MOTIVO') === 0 ||
        stripos($line, 'TRASLADO') === 0
      ) {
      } else {
        if (stripos($line, '==') !== 0) {
          $line = str_replace(["\t\t", "\t\t\t"], "\t", $line);
          $line = str_replace(["\t\t", "\t\t\t"], "\t", $line);
          // dd($line);
          if (stripos($line, "ITEM\tCANTIDAD") !== 0) {
            $line = explode("\t", $line);
            // dd($line);
            $exento = stripos($line[0], 'EXENTO') !== false;
            $detalle = [
              'IndExe' => $exento,
              'NmbItem' => mb_convert_encoding(
                trim($line[0]),
                'ISO-8859-1',
                'UTF-8',
              ),
              'QtyItem' => isset($line[1]) ? floatval(trim($line[1])) : null,
              'PrcItem' => isset($line[2]) ? floatval(trim($line[2])) : null,
              'DescuentoPct' => isset($line[3])
                ? floatval(trim($line[3]))
                : null,
            ];

            if (substr_count($detalle['NmbItem'], '-') > 3) {
              $detalle = null;
            }

            if ($detalle) {
              $items_to_delete = [
                'IndExe',
                'QtyItem',
                'PrcItem',
                'DescuentoPct',
              ];
              foreach ($items_to_delete as $item_to_delete) {
                if (
                  $detalle[$item_to_delete] == null ||
                  $detalle[$item_to_delete] == 0
                ) {
                  unset($detalle[$item_to_delete]);
                }
              }

              if (!isset($detalle['QtyItem']) && !isset($detalle['PrcItem'])) {
                $detalle = null;
              }
            }

            if ($detalle) {
              $set_pruebas[$caso]['Detalle'][] = $detalle;
            }
          }
        }
      }
    }

    return $set_pruebas;
  }

  private function changeItemEncoding($set_pruebas)
  {
    foreach ($set_pruebas as $key => $value) {
      foreach ($value['Detalle'] as $key2 => $value2) {
        // try detect encoding
        $encoding = mb_detect_encoding(
          $set_pruebas[$key]['Detalle'][$key2]['NmbItem'],
        );
        // dd($encoding);
        // change to utf-8
        $set_pruebas[$key]['Detalle'][$key2]['NmbItem'] = mb_convert_encoding(
          $value2['NmbItem'],
          'UTF-8',
          $encoding,
        );
      }
    }
    return $set_pruebas;
  }

  private function createCSV($file, $periodo)
  {
    //check if $periodo is null or is not YYYY-MM
    if (!$periodo || !preg_match('/^\d{4}-\d{2}$/', $periodo)) {
      $periodo = date('Y-m');
    }

    $xml = simplexml_load_file($file);
    $f = stripos($file, '-VENTA');
    $nombre = 'LibroVentas.csv';
    if ($f === false) {
      $nombre = 'LibroCompras.csv';
    }
    $csv = fopen($this->rutas->certificacion . $nombre, 'w');

    $header = [
      'TpoDoc',
      'NroDoc',
      'RUTDoc',
      'TasaImp',
      'RznSoc',
      'FchDoc',
      'Anulado',
      'MntExe',
      'MntNeto',
      'MntIVA',
      'IVAFueraPlazo',
      'CodImp',
      'TasaImp',
      'MntImp',
      'IVAPropio',
      'IVATerceros',
      'IVARetTotal',
      'IVARetParcial',
      'IVANoRetenido',
      'Ley18211',
      'CredEC',
      'TpoDocRef',
      'FolioDocRef',
      'DepEnvase',
      'MntNoFact',
      'MntPeriodo',
      'PsjNac',
      'PsjInt',
      'NumId',
      'Nacionalidad',
      'IndServicio',
      'IndSinCosto',
      'RutEmisor',
      'ValComNeto',
      'ValComExe',
      'ValComIVA',
      'CdgSIISucur',
      'NumInt',
      'Emisor',
      'MntTotal',
    ];

    fputcsv($csv, $header, ';');

    foreach ($xml->EnvioLibro->Detalle as $detalle) {
      $row = [];
      foreach ($header as $h) {
        $row[$h] = '';
        //check if exists
        if (isset($detalle->$h)) {
          if (!in_array($h, ['Emisor', 'MntIVA', 'MntTotal'])) {
            $row[$h] = (string) $detalle->$h;
          }
          if ($h == 'FchDoc') {
            // replace YYYY-MM with $periodo
            $row[$h] = str_replace(date('Y-m'), $periodo, $row[$h]);
          }
        }
      }
      fputcsv($csv, $row, ';');
    }

    fclose($csv);

    return $this->rutas->certificacion . 'LibroVentas.csv';
  }
  private function createCSVCompras($file, $periodo)
  {
    //check if $periodo is null or is not YYYY-MM
    if (!$periodo || !preg_match('/^\d{4}-\d{2}$/', $periodo)) {
      $periodo = date('Y-m');
    }

    $xml = simplexml_load_file($file);

    $nombre = 'LibroCompras.csv';

    $csv = fopen($this->rutas->certificacion . $nombre, 'w');

    $header =
      'Tipo Doc;Folio;Rut Contraparte;Tasa Impuesto;Razón Social Contraparte;Tipo Impuesto[1=IVA:2=LEY 18211];Fecha Emisión;Anulado[A];Monto Exento;Monto Neto;Monto IVA (Recuperable);Cod IVA no Rec;Monto IVA no Rec;IVA Uso Común;Cod Otro Imp (Con Crédito);Tasa Otro Imp (Con Crédito);Monto Otro Imp (Con Crédito);Monto Otro Imp (Sin Crédito);Neto Activo Fijo;IVA Activo Fijo;IVA No Retenido;Monto Imp Cigarrros Puros;Monto Imp Cigarrillos;Monto Imp Tabaco Elaborado;Monto Imp Vehículos y Autos.;Sucursal SII;Número Interno;NC o ND de FC [1];Monto Total;Factor IVA Uso Comun';

    // fputcsv($csv, $header, ';');
    fputs($csv, $header . "\n");

    $rnumber = 0;
    foreach ($xml->EnvioLibro->Detalle as $detalle) {
      $row = [];
      for ($k = 0; $k < 30; $k++) {
        $row[$k] = '';

        if ($k == 0) {
          $row[$k] = $detalle->TpoDoc;
        }
        if ($k == 1) {
          $row[$k] = $detalle->NroDoc;
        }
        if ($k == 2) {
          $row[$k] = $detalle->RUTDoc;
        }
        if ($k == 3) {
          $row[$k] = 19;
        }
        if ($k == 6) {
          $row[$k] = $detalle->FchDoc;
        }
        if ($k == 8) {
          $row[$k] = $detalle->MntExe;
        }
        if ($k == 9) {
          $row[$k] = $detalle->MntNeto;
        }
        if ($k == 11) {
          if (isset($detalle->IVANoRec)) {
            $CodIVANoRec = $detalle->IVANoRec->CodIVANoRec;
            $row[$k] = $CodIVANoRec;
          }
        }
        if ($k == 14) {
          if (isset($detalle->OtrosImp)) {
            $CodImp = $detalle->OtrosImp->CodImp;
            $row[$k] = $CodImp;
          }
        }
        if ($k == 15) {
          if (isset($detalle->OtrosImp)) {
            $TasaImp = $detalle->OtrosImp->TasaImp;
            $row[$k] = $TasaImp;
          }
        }
        if ($k == 29 && $rnumber == 2) {
          $row[$k] = '60';
        }
      }
      fputs($csv, implode(';', $row) . "\n");
      $rnumber++;
    }

    fclose($csv);

    return $this->rutas->certificacion . 'LibroVentas.csv';
  }

  private function getErrors($track_id = null)
  {
    $errors = [];
    foreach (\sasco\LibreDTE\Log::readAll() as $error) {
      if ($error->code == 61 && $track_id) {
        continue;
      }

      $errors[] = collect($error)
        ->only(['code', 'msg'])
        ->toArray();
    }

    return $errors;
  }
}
