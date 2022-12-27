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
      'CmnaOrigen' => $this->comuna,
    ];

    // datos del receptor
    $this->Receptor = [
      'RUTRecep' => '55666777-8',
      'RznSocRecep' => 'Empresa S.A.',
      'GiroRecep' => 'Servicios jur\u00eddicos',
      'DirRecep' => 'Santiago',
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
    $rules = [
      'set' => 'required|file|mimes:txt',
      'folios.*' => 'required|numeric|min:1',
      'fch_resol' => 'required|date_format:Y-m-d',
    ];
    $this->validate($request, $rules);

    $set = $request->file('set');

    $folios = json_decode($request->folios, true);

    $set_pruebas = [];

    $lines = file($set->getRealPath());

    // caratula para el envío de los dte
    $caratula = [
      'RutEnvia' => $this->rutEmpresa,
      'RutReceptor' => '60803000-K',
      'FchResol' => $request->fch_resol,
      'NroResol' => 0,
    ];

    $caso = '';
    $TasaImp = (float) \sasco\LibreDTE\Sii::getIVA();

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
            'DscRcgGlobal' => [],
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
      } else {
        if (stripos($line, '==') !== 0) {
          $line = str_replace(["\t\t", "\t\t\t"], "\t", $line);
          $line = str_replace(["\t\t", "\t\t\t"], "\t", $line);
          // dd($line);
          if (stripos($line, "ITEM\tCANTIDAD") !== 0) {
            $line = explode("\t", $line);
            // dd($line);
            $exento = stripos($line[0], 'EXENTO') !== false;
            $set_pruebas[$caso]['Detalle'][] = [
              'IndExe' => $exento,
              'NmbItem' => trim($line[0]),
              'QtyItem' => isset($line[1]) ? floatval(trim($line[1])) : null,
              'PrcItem' => isset($line[2]) ? floatval(trim($line[2])) : null,
              'DescuentoPct' => isset($line[3])
                ? floatval(trim($line[3]))
                : null,
            ];
          }
        }
      }
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
    foreach ($set_pruebas as $documento) {
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
      $this->rutas->certificacion . 'EnvioDTE-SetPruebas.xml',
      $xml,
    );
    $track_id = $EnvioDTE->enviar();

    file_put_contents(
      $this->rutas->certificacion . 'SetPruebas.json',
      json_encode(collect($this->encodeObjectToUTF8($set_pruebas))->values()),
    );

    // si hubo errores mostrar
    $errors = [];
    foreach (\sasco\LibreDTE\Log::readAll() as $error) {
      $errors[] = collect($error)
        ->only(['code', 'msg'])
        ->toArray();
    }

    foreach ($folios as $key => $folio) {
      $folios[$key] = $folio + 1;
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

    $NroResol = $request->has('NroResol') ? $request->NroResol : 0;

    // caratula del libro
    $caratula = [
      'RutEmisorLibro' => $this->rutEmpresa,
      'RutEnvia' => $this->rutCert,
      'PeriodoTributario' => '1980-03',
      'FchResol' => $folios['fch_resol'],
      'NroResol' => $NroResol,
      'TipoOperacion' => 'VENTA',
      'TipoLibro' => 'ESPECIAL',
      'TipoEnvio' => 'TOTAL',
      'FolioNotificacion' => $NroResol,
    ];

    $set_pruebas = json_decode(
      file_get_contents($this->rutas->certificacion . 'SetPruebas.json'),
    );

    // Objetos de Firma y LibroCompraVenta
    $Firma = new \sasco\LibreDTE\FirmaElectronica($this->dteconfig);
    $LibroCompraVenta = new \sasco\LibreDTE\Sii\LibroCompraVenta();

    // generar cada DTE y agregar su resumen al detalle del libro
    foreach ($set_pruebas as $documento) {
      $DTE = new \sasco\LibreDTE\Sii\Dte($documento);
      $LibroCompraVenta->agregar((array) $DTE->getResumen(), false); // agregar detalle sin normalizar
    }

    // enviar libro de ventas y mostrar resultado del envío: track id o bien =false si hubo error
    $LibroCompraVenta->setCaratula($caratula);
    $LibroCompraVenta->generar(false); // generar XML sin firma y sin detalle
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

    // set de pruebas compras - número de atención 414177
    $detalles = [
      // FACTURA DEL GIRO CON DERECHO A CREDITO
      [
        'TpoDoc' => 30,
        'NroDoc' => 234,
        'TasaImp' => $TasaImp,
        'FchDoc' => $caratula['PeriodoTributario'] . '-01',
        'RUTDoc' => '78885550-8',
        'MntNeto' => 53253,
      ],
      // FACTURA DEL GIRO CON DERECHO A CREDITO
      [
        'TpoDoc' => 33,
        'NroDoc' => 32,
        'TasaImp' => $TasaImp,
        'FchDoc' => $caratula['PeriodoTributario'] . '-01',
        'RUTDoc' => '78885550-8',
        'MntExe' => 10633,
        'MntNeto' => 11473,
      ],
      // FACTURA CON IVA USO COMUN
      [
        'TpoDoc' => 30,
        'NroDoc' => 781,
        'TasaImp' => $TasaImp,
        'FchDoc' => $caratula['PeriodoTributario'] . '-02',
        'RUTDoc' => '78885550-8',
        'MntNeto' => 30171,
        // Al existir factor de proporcionalidad se calculará el IVAUsoComun.
        // Se calculará como MntNeto * (TasaImp/100) y se añadirá a MntIVA.
        // Se quitará del detalle al armar los totales, ya que no es nodo del detalle en el XML.
        'FctProp' => $factor_proporcionalidad_iva,
      ],
      // NOTA DE CREDITO POR DESCUENTO A FACTURA 234
      [
        'TpoDoc' => 60,
        'NroDoc' => 451,
        'TasaImp' => $TasaImp,
        'FchDoc' => $caratula['PeriodoTributario'] . '-03',
        'RUTDoc' => '78885550-8',
        'MntNeto' => 2928,
      ],
      // ENTREGA GRATUITA DEL PROVEEDOR
      [
        'TpoDoc' => 33,
        'NroDoc' => 67,
        'TasaImp' => $TasaImp,
        'FchDoc' => $caratula['PeriodoTributario'] . '-04',
        'RUTDoc' => '78885550-8',
        'MntNeto' => 12135,
        'IVANoRec' => [
          'CodIVANoRec' => 4,
          'MntIVANoRec' => round(12135 * ($TasaImp / 100)),
        ],
      ],
      // COMPRA CON RETENCION TOTAL DEL IVA
      [
        'TpoDoc' => 46,
        'NroDoc' => 9,
        'TasaImp' => $TasaImp,
        'FchDoc' => $caratula['PeriodoTributario'] . '-05',
        'RUTDoc' => '78885550-8',
        'MntNeto' => 10632,
        'OtrosImp' => [
          'CodImp' => 15,
          'TasaImp' => $TasaImp,
          'MntImp' => round(10632 * ($TasaImp / 100)),
        ],
      ],
      // NOTA DE CREDITO POR DESCUENTO FACTURA ELECTRONICA 32
      [
        'TpoDoc' => 60,
        'NroDoc' => 211,
        'TasaImp' => $TasaImp,
        'FchDoc' => $caratula['PeriodoTributario'] . '-06',
        'RUTDoc' => '78885550-8',
        'MntNeto' => 9053,
      ],
    ];

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
      'RutEnvia' => $this->rutEmpresa,
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
}
