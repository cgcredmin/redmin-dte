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
      'nombre' => 'required|string|in:BASICO,GUIA_DESPACHO',
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

      //cambia codificación de caracteres de items
      // foreach ($documento['Detalle'] as $det) {
      //   $det['NmbItem'] = mb_convert_encoding(
      //     trim($det['NmbItem']),
      //     'ISO-8859-1',
      //     'UTF-8',
      //   );
      // }
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
    $errors = [];
    foreach (\sasco\LibreDTE\Log::readAll() as $error) {
      $errors[] = collect($error)
        ->only(['code', 'msg'])
        ->toArray();
    }

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

    // caratula del libro
    $caratula = [
      'RutEmisorLibro' => $this->rutEmpresa,
      'RutEnvia' => $this->rutSiiUser,
      'PeriodoTributario' => '2023-03',
      'FchResol' => $folios['fch_resol'],
      'NroResol' => $this->NroResol,
      'TipoOperacion' => 'VENTA',
      'TipoLibro' => 'ESPECIAL',
      'TipoEnvio' => 'TOTAL',
      // 'FolioNotificacion' => $this->NroResol,
    ];

    $set_pruebas = json_decode(
      file_get_contents($this->rutas->certificacion . 'SetPruebas.json'),
      true,
    );
    // dd($set_pruebas[0]);

    // Objetos de Firma y LibroCompraVenta
    $Firma = new \sasco\LibreDTE\FirmaElectronica($this->dteconfig);
    $LibroCompraVenta = new \sasco\LibreDTE\Sii\LibroCompraVenta(true);

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
        $LibroCompraVenta->agregar((array) $DTE->getResumen(), false); // agregar detalle sin normalizar
      } catch (\Exception $e) {
        dd($documento);
      }
    }

    // enviar libro de ventas y mostrar resultado del envío: track id o bien =false si hubo error
    $LibroCompraVenta->setCaratula($caratula);
    $LibroCompraVenta->generar(); // generar XML sin firma y sin detalle
    $LibroCompraVenta->setFirma($Firma);
    $track_id = $LibroCompraVenta->enviar(); // enviar XML generado en línea anterior

    // si hubo errores mostrar
    $errors = [];
    foreach (\sasco\LibreDTE\Log::readAll() as $error) {
      $errors[] = collect($error)
        ->only(['code', 'msg'])
        ->toArray();
    }

    $folios['fch_resol'] = $caratula['FchResol'];
    $folios['exito_ventas'] = $track_id ? true : false;
    $this->updateFoliosConfig($folios);

    return response()->json([
      'errors' => $errors,
      'track_id' => $track_id,
      'folios' => $folios,
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

    $NroResol = $request->has('NroResol') ? $request->NroResol : 0;

    // caratula del libro
    $caratula = [
      'RutEmisorLibro' => $this->rutEmpresa,
      'RutEnvia' => $this->rutCert,
      'PeriodoTributario' => '2000-03',
      'FchResol' => $folios['fch_resol'],
      'NroResol' => $NroResol,
      'TipoOperacion' => 'COMPRA',
      'TipoLibro' => 'ESPECIAL',
      'TipoEnvio' => 'TOTAL',
      'FolioNotificacion' => $NroResol,
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
      //     52954
      [
        'TpoDoc' => 30,
        'NroDoc' => 234,
        'TasaImp' => $TasaImp,
        'MntNeto' => 52954,
      ],
      // FACTURA ELECTRONICA 			 32
      // FACTURA DEL GIRO CON DERECHO A CREDITO
      // 10616		  11427
      [
        'TpoDoc' => 33,
        'NroDoc' => 32,
        'MntExe' => 10616,
        'MntNeto' => 11427,
      ],
      // FACTURA					781
      // FACTURA CON IVA USO COMUN
      //     30167
      [
        'TpoDoc' => 30,
        'NroDoc' => 781,
        'MntNeto' => 30167,
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
        'MntNeto' => 2926,
        'TpoDocRef' => 30,
        'FolioDocRef' => 234,
      ],
      // FACTURA ELECTRONICA			 67
      // ENTREGA GRATUITA DEL PROVEEDOR
      //     12115
      [
        'TpoDoc' => 33,
        'NroDoc' => 67,
        'MntNeto' => 12115,
        'IVANoRec' => [
          'CodIVANoRec' => 4,
          'MntIVANoRec' => round(12115 * ($TasaImp / 100)) * -1,
        ],
      ],
      // FACTURA DE COMPRA ELECTRONICA		  9
      // COMPRA CON RETENCION TOTAL DEL IVA
      //     10622
      [
        'TpoDoc' => 46,
        'NroDoc' => 9,
        'MntNeto' => 10622,
        'OtrosImp' => [
          'CodImp' => 15,
          'TasaImp' => $TasaImp,
          'MntImp' => round(10622 * ($TasaImp / 100)) * -1,
        ],
      ],
      // NOTA DE CREDITO				211
      // NOTA DE CREDITO POR DESCUENTO FACTURA ELECTRONICA 32
      //     9010
      [
        'TpoDoc' => 60,
        'NroDoc' => 211,
        'MntNeto' => 9010,
        'TpoDocRef' => 33,
        'FolioDocRef' => 32,
      ],
    ];

    foreach ($detalles as $k => $detalle) {
      $detalle['FchDoc'] = $caratula['PeriodoTributario'] . '-01';
      $detalle['RUTDoc'] = '78885550-8';
      $detalle['TasaImp'] = $TasaImp;
    }

    // Objetos de Firma y LibroCompraVenta
    $Firma = new \sasco\LibreDTE\FirmaElectronica($this->dteconfig);
    $LibroCompraVenta = new \sasco\LibreDTE\Sii\LibroCompraVenta();

    // agregar cada uno de los detalles al libro
    foreach ($detalles as $detalle) {
      $LibroCompraVenta->agregar($detalle);
    }

    // enviar libro de compras y mostrar resultado del envío: track id o bien =false si hubo error
    $LibroCompraVenta->setCaratula($caratula);
    $LibroCompraVenta->generar(); // generar XML sin firma
    $LibroCompraVenta->setFirma($Firma);
    $track_id = $LibroCompraVenta->enviar(); // enviar XML generado en línea anterior

    // si hubo errores mostrar
    $errors = [];
    foreach (\sasco\LibreDTE\Log::readAll() as $error) {
      $errors[] = collect($error)
        ->only(['code', 'msg'])
        ->toArray();
    }

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
}
