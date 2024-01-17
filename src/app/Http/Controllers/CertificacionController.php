<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use sasco\LibreDTE\Sii\Dte\Base\DteImpreso;
use App\Http\Traits\DteAuthTrait;
use App\Http\Traits\StrangeCharsTrait;

class CertificacionController extends Controller
{
  use DteImpreso;
  use DteAuthTrait;
  use StrangeCharsTrait;

  protected $Emisor;
  protected $Receptor;
  protected $fchResol;

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
    $receptor2 = [
      'RUTRecep' => '60803000-K',
      'RznSocRecep' => 'Empresa S.A.',
      'GiroRecep' => 'Servicios jurídicos',
      'DirRecep' => 'Santiago',
      'CmnaRecep' => 'Santiago',
    ];

    // datos del receptor
    $this->Receptor = $receptor2;

    $this->fchResol = '2023-03-28';
  }

  private function updateFoliosConfig($folios)
  {
    //save new folios to file
    file_put_contents(
      $this->rutas->certificacion . 'folios.json',
      json_encode($folios),
    );
  }

  private function sendDtes($folios, $set_pruebas, &$errors, $name = null)
  {
    foreach ($set_pruebas as $key => $documento) {
      $set_pruebas[$key]['Encabezado']['Emisor'] = $this->Emisor;
      $set_pruebas[$key]['Encabezado']['Receptor'] = $this->Receptor;
    }

    $key = implode('_', array_keys($folios));

    if ($name == null) {
      $name = $key;
    }

    // caratula para el envío de los dte
    $caratula = [
      'RutEnvia' => $this->rutCert,
      'RutReceptor' => $this->Receptor['RUTRecep'],
      'FchResol' => $this->fchResol,
      'NroResol' => 0,
    ];

    /* Objetos de Firma, Folios y EnvioDTE */
    $firma = new \sasco\LibreDTE\FirmaElectronica($this->dteconfig);
    $folios = [];
    foreach ($folios as $tipo => $cantidad) {
      $folios[$tipo] = new \sasco\LibreDTE\Sii\Folios(
        file_get_contents($this->rutas->folios . $tipo . '.xml'),
      );
    }
    $envioDte = new \sasco\LibreDTE\Sii\EnvioDte();

    /* generar cada DTE, timbrar, firmar y agregar al sobre de EnvioDTE */
    foreach ($set_pruebas as $documento) {
      $dte = new \sasco\LibreDTE\Sii\Dte($documento);

      foreach (\sasco\LibreDTE\Log::readAll() as $error) {
        $errors[] = collect($error)
          ->only(['code', 'msg'])
          ->toArray();
      }
      // dd($dte, $documento, $errors);
      if (!$dte->timbrar($folios[$dte->getTipo()])) {
        break;
      }
      if (!$dte->firmar($firma)) {
        break;
      }
      $envioDte->agregar($dte);
    }

    /* enviar dtes y mostrar resultado del envío: track id o bien =false si hubo error */
    $envioDte->setCaratula($caratula);
    $envioDte->setFirma($firma);
    $xml = $envioDte->generar();

    /* Crea EnvioDTE-SetPruebas.xml */
    file_put_contents(
      $this->rutas->certificacion . 'EnvioDTE-SetPruebas-' . $name . '.xml',
      $xml,
    );
    $track_id = null;
    $track_id = $envioDte->enviar();

    return $track_id;
  }

  private function errorCollector(&$errors)
  {
    foreach (\sasco\LibreDTE\Log::readAll() as $error) {
      $errors[] = collect($error)
        ->only(['code', 'msg'])
        ->toArray();
    }
  }

  public function sendSetBasico(Request $request)
  {
    $folios = [
      33 => 236, // consumo 5 => 241
      56 => 174, // consumo 2 => 176
      61 => 194, // consumo 1 => 195
    ];

    $caso = '3791491';

    $track_id = null;
    $errors = [];
    $send = true;

    // $set_pruebas = \sasco\LibreDTE\Sii\Certificacion\SetPruebas::getJSON(
    //   file_get_contents($this->rutas->certificacion . '001-basico.txt'),
    //   $folios
    // );
    // return response()->json(json_decode($set_pruebas));

    $set_pruebas = [
      [
        "Encabezado" => [
          "IdDoc" => [
            "TipoDTE" => 33,
            "Folio" => $folios[33]
          ]
        ],
        "Detalle" => [
          [
            "NmbItem" => "Cajón AFECTO",
            "QtyItem" => 126,
            "PrcItem" => 1047
          ],
          [
            "NmbItem" => "Relleno AFECTO",
            "QtyItem" => 54,
            "PrcItem" => 1683
          ]
        ],
        "Referencia" => [
          [
            "TpoDocRef" => "SET",
            "FolioRef" => $folios[33],
            "RazonRef" => "CASO {$caso}-1"
          ]
        ]
      ],
      [
        "Encabezado" => [
          "IdDoc" => [
            "TipoDTE" => 33,
            "Folio" => $folios[33] + 1
          ]
        ],
        "Detalle" => [
          [
            "NmbItem" => "Pañuelo AFECTO",
            "QtyItem" => 260,
            "PrcItem" => 2117,
            "DescuentoPct" => 4
          ],
          [
            "NmbItem" => "ITEM 2 AFECTO",
            "QtyItem" => 187,
            "PrcItem" => 1180,
            "DescuentoPct" => 6
          ]
        ],
        "Referencia" => [
          [
            "TpoDocRef" => "SET",
            "FolioRef" => $folios[33] + 1,
            "RazonRef" => "CASO {$caso}-2"
          ]
        ]
      ],
      [
        "Encabezado" => [
          "IdDoc" => [
            "TipoDTE" => 33,
            "Folio" => $folios[33] + 2
          ]
        ],
        "Detalle" => [
          [
            "NmbItem" => "Pintura B&W AFECTO",
            "QtyItem" => 25,
            "PrcItem" => 2202
          ],
          [
            "NmbItem" => "ITEM 2 AFECTO",
            "QtyItem" => 153,
            "PrcItem" => 3005
          ],
          [
            "NmbItem" => "ITEM 3 SERVICIO EXENTO",
            "QtyItem" => 1,
            "PrcItem" => 34733,
            "IndExe" => 1
          ]
        ],
        "Referencia" => [
          [
            "TpoDocRef" => "SET",
            "FolioRef" => $folios[33] + 2,
            "RazonRef" => "CASO {$caso}-3"
          ]
        ]
      ],
      [
        "Encabezado" => [
          "IdDoc" => [
            "TipoDTE" => 33,
            "Folio" => $folios[33] + 3
          ]
        ],
        "Detalle" => [
          [
            "NmbItem" => "ITEM 1 AFECTO",
            "QtyItem" => 97,
            "PrcItem" => 1877
          ],
          [
            "NmbItem" => "ITEM 2 AFECTO",
            "QtyItem" => 42,
            "PrcItem" => 1685
          ],
          [
            "NmbItem" => "ITEM 3 SERVICIO EXENTO",
            "QtyItem" => 2,
            "PrcItem" => 6771,
            "IndExe" => 1
          ]
        ],
        "DscRcgGlobal" => [
          [
            "TpoMov" => "D",
            "TpoValor" => "%",
            "ValorDR" => 7
          ]
        ],
        "Referencia" => [
          [
            "TpoDocRef" => "SET",
            "FolioRef" => $folios[33] + 3,
            "RazonRef" => "CASO {$caso}-4"
          ]
        ]
      ],
      [
        "Encabezado" => [
          "IdDoc" => [
            "TipoDTE" => 61,
            "Folio" => $folios[61]
          ],
          "Totales" => [
            "MntTotal" => 0
          ]
        ],
        "Detalle" => [
          [
            "NmbItem" => "DONDE DICE Servicios integrales de informática DEBE DECIR Informática"
          ]
        ],
        "Referencia" => [
          [
            "TpoDocRef" => "SET",
            "FolioRef" => $folios[61],
            "RazonRef" => "CASO {$caso}-5"
          ],
          [
            "TpoDocRef" => 33,
            "FolioRef" => $folios[33],
            "CodRef" => 2,
            "RazonRef" => "CORRIGE GIRO DEL RECEPTOR"
          ]
        ]
      ],
      [
        "Encabezado" => [
          "IdDoc" => [
            "TipoDTE" => 61,
            "Folio" => $folios[61] + 1
          ],
          "Totales" => [
            "MntNeto" => 0,
            "TasaIVA" => 19,
            "IVA" => 0,
            "MntTotal" => 0
          ]
        ],
        "Detalle" => [
          [
            "NmbItem" => "Pañuelo AFECTO",
            "QtyItem" => 95,
            "PrcItem" => 2117,
            "DescuentoPct" => 4
          ],
          [
            "NmbItem" => "ITEM 2 AFECTO",
            "QtyItem" => 127,
            "PrcItem" => 1180,
            "DescuentoPct" => 6
          ]
        ],
        "Referencia" => [
          [
            "TpoDocRef" => "SET",
            "FolioRef" => $folios[61] + 1,
            "RazonRef" => "CASO {$caso}-6"
          ],
          [
            "TpoDocRef" => 33,
            "FolioRef" => $folios[33] + 1,
            "CodRef" => 3,
            "RazonRef" => "DEVOLUCION DE MERCADERIAS"
          ]
        ]
      ],
      [
        "Encabezado" => [
          "IdDoc" => [
            "TipoDTE" => 61,
            "Folio" => $folios[61] + 2
          ],
          "Totales" => [
            "MntNeto" => 0,
            "TasaIVA" => 19,
            "IVA" => 0,
            "MntTotal" => 0
          ]
        ],
        "Detalle" => [
          [
            "NmbItem" => "Pintura B&W AFECTO",
            "QtyItem" => 25,
            "PrcItem" => 2202
          ],
          [
            "NmbItem" => "ITEM 2 AFECTO",
            "QtyItem" => 153,
            "PrcItem" => 3005
          ],
          [
            "NmbItem" => "ITEM 3 SERVICIO EXENTO",
            "QtyItem" => 1,
            "PrcItem" => 34733,
            "IndExe" => 1
          ]
        ],
        "Referencia" => [
          [
            "TpoDocRef" => "SET",
            "FolioRef" => $folios[61] + 2,
            "RazonRef" => "CASO {$caso}-7"
          ],
          [
            "TpoDocRef" => 33,
            "FolioRef" => $folios[33] + 2,
            "CodRef" => 1,
            "RazonRef" => "ANULA FACTURA"
          ]
        ]
      ],
      [
        "Encabezado" => [
          "IdDoc" => [
            "TipoDTE" => 56,
            "Folio" => $folios[56]
          ],
          "Totales" => [
            "MntTotal" => 0
          ]
        ],
        "Detalle" => [
          [
            "NmbItem" => "DONDE DICE Servicios integrales de informática DEBE DECIR Informática"
          ]
        ],
        "Referencia" => [
          [
            "TpoDocRef" => "SET",
            "FolioRef" => $folios[56],
            "RazonRef" => "CASO {$caso}-8"
          ],
          [
            "TpoDocRef" => 61,
            "FolioRef" => $folios[61],
            "CodRef" => 1,
            "RazonRef" => "ANULA NOTA DE CREDITO ELECTRONICA"
          ]
        ]
      ]
    ];

    if ($send) {
      $track_id = $this->sendDtes($folios, $set_pruebas, $errors, 'set_basico');
    }

    /* si hubo errores mostrar */
    $this->errorCollector($errors);

    foreach ($folios as $key => $folio) {
      $count  = collect($set_pruebas)
        ->where('Encabezado.IdDoc.TipoDTE', $key)
        ->count();
      $folios[$key] = $folio + $count;
    }
    $nuevos_folios = $folios;
    $folios['exito_set_pruebas'] = $track_id ? true : false;
    $this->updateFoliosConfig($folios);

    file_put_contents(
      $this->rutas->certificacion . 'EnvioDTE-SetPruebas-Basico.json',
      json_encode($set_pruebas),
    );

    return response()->json(
      [
        'exito' => $track_id ? true : false,
        'errors' => $errors,
        'track_id' => $track_id,
        'set_pruebas' => $this->encodeObjectToUTF8($set_pruebas),
        'updated_values' => $nuevos_folios,
      ],
      count($errors) > 0 ? 400 : 200,
    );
  }
  public function sendSetGuias(Request $request)
  {
    $folios = [
      52 => 221,
    ];

    $caso = '3791492';

    // $set_pruebas = \sasco\LibreDTE\Sii\Certificacion\SetPruebas::getJSON(
    //   file_get_contents($this->rutas->certificacion . '005-guia_despacho.txt'),
    //   $folios
    // );
    // return response()->json(json_decode($set_pruebas));

    // caratula para el envío de los dte
    $caratula = [
      'RutEnvia' => $this->rutCert,
      'RutReceptor' => $this->Receptor['RUTRecep'],
      'FchResol' => $this->fchResol,
      'NroResol' => 0,
    ];

    $track_id = null;
    $errors = [];
    $send = true;
    $set_pruebas = [
      [
        "Encabezado" => [
          "IdDoc" => [
            "TipoDTE" => 52,
            "Folio" => $folios[52],
            "IndTraslado" => 5
          ],
          'Emisor' => $this->Emisor,
          'Receptor' => $this->Receptor,
        ],
        "Detalle" => [
          [
            "NmbItem" => "ITEM 1",
            "QtyItem" => 67
          ],
          [
            "NmbItem" => "ITEM 2",
            "QtyItem" => 89
          ],
          [
            "NmbItem" => "ITEM 3",
            "QtyItem" => 54
          ]
        ],
        "Referencia" => [
          [
            "TpoDocRef" => "SET",
            "FolioRef" => $folios[52],
            "RazonRef" => 'CASO ' . $caso . '-1'
          ]
        ]
      ],
      [
        "Encabezado" => [
          "IdDoc" => [
            "TipoDTE" => 52,
            "Folio" => $folios[52] + 1,
            "TipoDespacho" => 2,
            "IndTraslado" => 1
          ],
          'Emisor' => $this->Emisor,
          'Receptor' => $this->Receptor,
        ],
        "Detalle" => [
          [
            "NmbItem" => "ITEM 1",
            "QtyItem" => 211,
            "PrcItem" => 4589
          ],
          [
            "NmbItem" => "ITEM 2",
            "QtyItem" => 402,
            "PrcItem" => 1286
          ]
        ],
        "Referencia" => [
          [
            "TpoDocRef" => "SET",
            "FolioRef" => $folios[52] + 1,
            "RazonRef" => 'CASO ' . $caso . '-2'
          ]
        ]
      ],
      [
        "Encabezado" => [
          "IdDoc" => [
            "TipoDTE" => 52,
            "Folio" => $folios[52] + 2,
            "TipoDespacho" => 1,
            "IndTraslado" => 1
          ],
          'Emisor' => $this->Emisor,
          'Receptor' => $this->Receptor,
        ],
        "Detalle" => [
          [
            "NmbItem" => "ITEM 1",
            "QtyItem" => 127,
            "PrcItem" => 1535
          ],
          [
            "NmbItem" => "ITEM 2",
            "QtyItem" => 259,
            "PrcItem" => 3615
          ]
        ],
        "Referencia" => [
          [
            "TpoDocRef" => "SET",
            "FolioRef" => $folios[52] + 2,
            "RazonRef" => 'CASO ' . $caso . '-3'
          ]
        ]
      ]
    ];

    if ($send) {
      $track_id = $this->sendDtes($folios, $set_pruebas, $errors, 'set_guias');
    }

    /* si hubo errores mostrar */
    $errors = [];
    $this->errorCollector($errors);

    foreach ($folios as $key => $folio) {
      $count  = collect($set_pruebas)
        ->where('Encabezado.IdDoc.TipoDTE', $key)
        ->count();
      $folios[$key] = $folio + $count;
    }
    $nuevos_folios = $folios;
    $this->fchResol = $caratula['FchResol'];
    $folios['exito_set_pruebas'] = $track_id ? true : false;
    $this->updateFoliosConfig($folios);

    file_put_contents(
      $this->rutas->certificacion . 'EnvioDTE-SetPruebas-GuiaDespacho.json',
      json_encode($set_pruebas),
    );

    return response()->json(
      [
        'exito' => $track_id ? true : false,
        'errors' => $errors,
        'track_id' => $track_id,
        'set_pruebas' => $this->encodeObjectToUTF8($set_pruebas),
        'updated_values' => $nuevos_folios,
        'fch_resol' => $caratula['FchResol'],
      ],
      count($errors) > 0 ? 400 : 200,
    );
  }
  public function sendSetCompras(Request $request)
  {
    $folios = [
      46 => 100,
      56 => 175,
      61 => 197,
    ];

    $caso = '3791496';

    // $set_pruebas = \sasco\LibreDTE\Sii\Certificacion\SetPruebas::getJSON(
    //   file_get_contents($this->rutas->certificacion . '003-compras.txt'),
    //   $folios
    // );
    // return response()->json(json_decode($set_pruebas));

    $track_id = null;
    $errors = [];
    $send = true;
    $set_pruebas = [
      [
        "Encabezado" => [
          "IdDoc" => [
            "TipoDTE" => 46,
            "Folio" => $folios[46]
          ]
        ],
        "Detalle" => [
          [
            "NmbItem" => "Producto 1",
            "QtyItem" => 832,
            "PrcItem" => 6470,
            "CodImpAdic" => 15
          ],
          [
            "NmbItem" => "Producto 2",
            "QtyItem" => 35,
            "PrcItem" => 3549,
            "CodImpAdic" => 15
          ]
        ],
        "Referencia" => [
          [
            "TpoDocRef" => "SET",
            "FolioRef" => $folios[46],
            "RazonRef" => 'CASO ' . $caso . '-1'
          ]
        ]
      ],
      [
        "Encabezado" => [
          "IdDoc" => [
            "TipoDTE" => 61,
            "Folio" => $folios[61]
          ],
          "Totales" => [
            "MntNeto" => 0,
            "TasaIVA" => 19,
            "IVA" => 0,
            "MntTotal" => 0
          ]
        ],
        "Detalle" => [
          [
            "NmbItem" => "Producto 1",
            "QtyItem" => 277,
            "PrcItem" => 6470,
            "CodImpAdic" => 15
          ],
          [
            "NmbItem" => "Producto 2",
            "QtyItem" => 12,
            "PrcItem" => 3549,
            "CodImpAdic" => 15
          ]
        ],
        "Referencia" => [
          [
            "TpoDocRef" => "SET",
            "FolioRef" => $folios[61],
            "RazonRef" => 'CASO ' . $caso . '-2'
          ],
          [
            "TpoDocRef" => 46,
            "FolioRef" => $folios[46],
            "CodRef" => 3,
            "RazonRef" => "DEVOLUCION DE MERCADERIA ITEMS 1 Y 2"
          ]
        ]
      ],
      [
        "Encabezado" => [
          "IdDoc" => [
            "TipoDTE" => 56,
            "Folio" => $folios[56]
          ],
          "Totales" => [
            "MntNeto" => 0,
            "TasaIVA" => 19,
            "IVA" => 0,
            "MntTotal" => 0
          ]
        ],
        "Detalle" => [
          [
            "NmbItem" => "Producto 1",
            "QtyItem" => 277,
            "PrcItem" => 6470,
            "CodImpAdic" => 15
          ],
          [
            "NmbItem" => "Producto 2",
            "QtyItem" => 12,
            "PrcItem" => 3549,
            "CodImpAdic" => 15
          ]
        ],
        "Referencia" => [
          [
            "TpoDocRef" => "SET",
            "FolioRef" => $folios[56],
            "RazonRef" => 'CASO ' . $caso . '-3'
          ],
          [
            "TpoDocRef" => 61,
            "FolioRef" => $folios[61],
            "CodRef" => 1,
            "RazonRef" => "ANULA NOTA DE CREDITO ELECTRONICA"
          ]
        ]
      ]
    ];

    if ($send) {
      $track_id = $this->sendDtes($folios, $set_pruebas, $errors, 'set_compras');
    }

    /* si hubo errores mostrar */
    $errors = [];
    $this->errorCollector($errors);

    foreach ($folios as $key => $folio) {
      $count  = collect($set_pruebas)
        ->where('Encabezado.IdDoc.TipoDTE', $key)
        ->count();
      $folios[$key] = $folio + $count;
    }
    $nuevos_folios = $folios;
    $folios['exito_set_pruebas'] = $track_id ? true : false;
    $this->updateFoliosConfig($folios);

    file_put_contents(
      $this->rutas->certificacion . 'EnvioDTE-SetPruebas-Compras.json',
      json_encode($set_pruebas),
    );

    return response()->json(
      [
        'exito' => $track_id ? true : false,
        'errors' => $errors,
        'track_id' => $track_id,
        'set_pruebas' => $this->encodeObjectToUTF8($set_pruebas),
        'updated_values' => $nuevos_folios,
      ],
      count($errors) > 0 ? 400 : 200,
    );
  }
  public function sendSetPruebas(Request $request)
  {
    // BA -> BASICO
    // GD -> GUIA DESPACHO
    // LV -> LIBRO VENTA
    // LC -> LIBRO COMPRA
    // LG -> LIBRO GUIA
    // FC -> FACTURA COMPRA
    $rules = [
      'set' => 'required|in:BA,GD,LV,LC,LG,FC',
      'folios.*' => 'required|numeric|min:1',
      'fch_resol' => 'required|date_format:Y-m-d',
    ];
    $this->validate($request, $rules);

    $path = null;
    switch ($request->set) {
      case 'BA':
        $path = $this->rutas->certificacion . 'SetPruebas-Basico.json';
        break;
      case 'GD':
        $path = $this->rutas->certificacion . 'SetPruebas-GuiaDespacho.json';
        break;
      case 'LV':
        $path = $this->rutas->certificacion . 'SetPruebas-LibroVenta.json';
        break;
      case 'LC':
        $path = $this->rutas->certificacion . 'SetPruebas-LibroCompra.json';
        break;
      case 'LG':
        $path = $this->rutas->certificacion . 'SetPruebas-LibroGuia.json';
        break;
      case 'FC':
        $path = $this->rutas->certificacion . 'SetPruebas-FacturaCompra.json';
        break;
      default:
        $path = null;
    }

    if ($path == null) {
      return response()->json(
        [
          'error' => [
            'No existe el archivo para enviar el set de pruebas',
          ],
        ],
        400,
      );
    }

    $folios = json_decode($request->folios, true);

    $set_pruebas = [];
    if ($path) {
      if (file_exists($path)) {
        $set_pruebas = json_decode(file_get_contents($path));
      }
    }

    if (count($set_pruebas) == 0) {
      return response()->json(
        [
          'error' => [
            'No se pudo leer el archivo para enviar el set de pruebas',
          ],
        ],
        400,
      );
    }

    // caratula para el envío de los dte
    $caratula = [
      'RutEnvia' => $this->rutCert,
      'RutReceptor' => $this->Receptor['RUTRecep'],
      'FchResol' => $this->fchResol,
      'NroResol' => 0,
    ];

    $track_id = null;
    $errors = [];
    $send = true;

    if ($send) {
      /* Objetos de Firma, Folios y EnvioDTE */
      $firma = new \sasco\LibreDTE\FirmaElectronica($this->dteconfig);
      $folios = [];
      foreach ($folios as $tipo => $cantidad) {
        $folios[$tipo] = new \sasco\LibreDTE\Sii\Folios(
          file_get_contents($this->rutas->folios . $tipo . '.xml'),
        );
      }
      $envioDte = new \sasco\LibreDTE\Sii\EnvioDte();

      /* generar cada DTE, timbrar, firmar y agregar al sobre de EnvioDTE */
      foreach ($set_pruebas as $documento) {
        $dte = new \sasco\LibreDTE\Sii\Dte($documento);

        foreach (\sasco\LibreDTE\Log::readAll() as $error) {
          $errors[] = collect($error)
            ->only(['code', 'msg'])
            ->toArray();
        }
        dd($dte, $documento, $errors);
        if (!$dte->timbrar($folios[$dte->getTipo()])) {
          break;
        }
        if (!$dte->firmar($firma)) {
          break;
        }
        $envioDte->agregar($dte);
      }

      /* enviar dtes y mostrar resultado del envío: track id o bien =false si hubo error */
      $envioDte->setCaratula($caratula);
      $envioDte->setFirma($firma);
      $xml = $envioDte->generar();

      /* Crea EnvioDTE-SetPruebas.xml */
      file_put_contents(
        $this->rutas->certificacion . 'EnvioDTE-SetPruebas.xml',
        $xml,
      );
      $track_id = $envioDte->enviar();
    }

    /* si hubo errores mostrar */
    $errors = [];
    $this->errorCollector($errors);

    foreach ($folios as $key => $folio) {
      $folios[$key] = $folio + 1;
    }
    $nuevos_folios = $folios;
    $this->fchResol = $caratula['FchResol'];
    $folios['exito_set_pruebas'] = $track_id ? true : false;
    $this->updateFoliosConfig($folios);

    return response()->json(
      [
        'exito' => $track_id ? true : false,
        'errors' => $errors,
        'track_id' => $track_id,
        'set_pruebas' => $this->encodeObjectToUTF8($set_pruebas),
        'updated_values' => $nuevos_folios,
        'fch_resol' => $caratula['FchResol'],
      ],
      count($errors) > 0 ? 400 : 200,
    );
  }
  /*   public function generateSetPruebas(Request $request)
  {
    $rules = [
      'set' => 'required|file|mimes:txt',
      'folios.*' => 'required|numeric|min:1',
      'nombre' => 'required|string|in:BASICO,GUIA_DESPACHO,COMPRAS',
    ];
    $this->validate($request, $rules);

    $set = $request->file('set');

    $folios = json_decode($request->folios, true);
    // dd($folios);

    $set_pruebas = [];

    $lines = file($set->getRealPath());

    // caratula para el envío de los dte
    $caratula = [
      'RutEnvia' => $this->rutCert,
      'RutReceptor' => $this->Receptor['RUTRecep'],
      'FchResol' => $this->fchResol,
      'NroResol' => 0,
    ];
    // dd($caratula);

    $caso = '';
    $TasaImp = (float) \sasco\LibreDTE\Sii::getIVA();

    foreach ($lines as $line) {
      $line = trim($line);

      if (empty($line)) {
        continue;
      }

      // Pedir mas folios: dte_certificacion@sii.cl

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
        $cod_type = null;
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

          // $set_pruebas[$caso]['Detalle'] = $set_pruebas[$c_ref]['Detalle'];
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
        // $set_pruebas[$caso]['Detalle'][] = ['NmbItem' => $razon];
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
            $data = [
              'IndExe' => $exento,
              'NmbItem' => trim($line[0]),
              'QtyItem' => isset($line[1]) ? floatval(trim($line[1])) : null,
              'PrcItem' => isset($line[2]) ? floatval(trim($line[2])) : null,
              'DescuentoPct' => isset($line[3])
                ? floatval(trim($line[3]))
                : null,
            ];

            // convert Ò into ñ and \u00c3\u0092 into ñ
            // $data['NmbItem'] = $this->parse($data['NmbItem']);

            if ($data['DescuentoPct'] == null) {
              unset($data['DescuentoPct']);
            }
            if ($data['PrcItem'] == null) {
              unset($data['PrcItem']);
            }
            if ($data['NmbItem'] == null || $data['NmbItem'] == '') {
              // unset();
              $data['NmbItem'] = $set_pruebas[$caso]['Referencia'][1]['RazonRef'];
            }
            $set_pruebas[$caso]['Detalle'][] = $data;
          }
        }
      }
    }

    // Check if documents type 61 and 56 has no items, if not add the firt item on referenced document
    foreach ($set_pruebas as $case => $set) {
      if (in_array($set['Encabezado']['IdDoc']['TipoDTE'], [56, 61]) === true) {
        if (count($set['Detalle']) == 0) {
          $ref_document_num = $set['Referencia'][1]['FolioRef'];
          $ref_document = collect($set_pruebas)
            ->where('Encabezado.IdDoc.Folio', $ref_document_num)
            ->first();

          if ($ref_document) {
            $set_pruebas[$case]['Detalle'][] = [
              'NmbItem' => $ref_document['Detalle'][0]['NmbItem'],
            ];
          }
        }
      }
      foreach ($set_pruebas[$case]['Detalle'] as $key => $item) {
        if (isset($item['QtyItem'])) {
          if ($item['QtyItem'] == null || $item['QtyItem'] == '' || is_numeric($item['QtyItem']) == false) {
            dd($item['QtyItem']);
            unset($set_pruebas[$case]['Detalle'][$key]['QtyItem']);
          }
        }
      }
    }

    // crea SetPruebas.json
    file_put_contents(
      $this->rutas->certificacion . 'SetPruebas.json',
      json_encode(collect($this->encodeObjectToUTF8($set_pruebas))->values()),
    );

    return response()->json(
      [
        'set_pruebas' => $this->encodeObjectToUTF8($set_pruebas),
      ],
      200,
    );
  } */

  public function sendLibroGuias()
  {
    $caratula = [
      'RutEmisorLibro' => $this->Emisor['RUTEmisor'],
      'FchResol' => $this->fchResol,
      'NroResol' => 0,
      'FolioNotificacion' => 2,
    ];

    $folios = [
      52 => 224,
    ];

    $set_pruebas = [
      [
        "Folio" => $folios[52],
        "TpoOper" => 5,
        'RUTDoc' => $caratula['RutEmisorLibro'],
        'TasaImp' => \sasco\LibreDTE\Sii::getIVA(),
      ],
      // CASO 2 CORRESPONDE A UNA GUIA QUE SE FACTURO EN EL PERIODO
      [
        "Folio" => $folios[52] + 1,
        "TpoOper" => 1,
        'RUTDoc' => $this->Receptor['RUTRecep'],
        'MntNeto' => 222804,
        'TasaImp' => \sasco\LibreDTE\Sii::getIVA(),
        'TpoDocRef' => 33,
        'FolioDocRef' => 236,
        'FchDocRef' => date('Y-m-d'),
      ],
      // CASO 3 CORRESPONDE A UNA GUIA ANULADA
      [
        "Folio" => $folios[52] + 2,
        "Anulado" => 2,
        'TpoOper' => 1,
        'RUTDoc' => $this->Receptor['RUTRecep'],
        'MntNeto' => 158409,
        'TasaImp' => \sasco\LibreDTE\Sii::getIVA()
      ]
    ];

    // Objetos de Firma y LibroGuia
    $firma = new \sasco\LibreDTE\FirmaElectronica($this->dteconfig);
    $LibroGuia = new \sasco\LibreDTE\Sii\LibroGuia();

    // agregar cada uno de los detalles al libro
    foreach ($set_pruebas as $detalle) {
      $LibroGuia->agregar($detalle);
    }

    // enviar libro de guías y mostrar resultado del envío: track id o bien =false si hubo error
    $LibroGuia->setFirma($firma);
    $LibroGuia->setCaratula($caratula);
    $LibroGuia->generar();
    $track_id = null;
    if ($LibroGuia->schemaValidate()) {
      $xml = $LibroGuia->generar();

      /* Crea EnvioDTE-SetPruebas.xml */
      file_put_contents(
        $this->rutas->certificacion . 'EnvioDTE-SetPruebas-LibroGuias.xml',
        $xml,
      );
      $track_id = $LibroGuia->enviar();
    }

    /* si hubo errores mostrar */
    $errors = [];
    $this->errorCollector($errors);

    $folios[52] += 3;

    return response()->json([
      'errors' => $errors,
      'track_id' => $track_id,
      'folios' => $folios
    ]);
  }
  public function sendLibroVentas(Request $request)
  {
    $caratula = [
      'RutEmisorLibro' => $this->Emisor['RUTEmisor'],
      'RutEnvia' => $this->rutCert,
      'PeriodoTributario' => '1980-04',
      // 'PeriodoTributario' => date('Y-m'),
      // 'FchResol' => $this->fchResol,
      'FchResol' => '2006-01-20',
      'NroResol' => 102006,
      'TipoOperacion' => 'VENTA',
      'TipoLibro' => 'ESPECIAL',
      'TipoEnvio' => 'TOTAL',
      'FolioNotificacion' => 102006,
    ];

    // $set_pruebas = json_decode(
    //   file_get_contents($this->rutas->certificacion . 'SetPruebas-Basico.json'),
    // );

    // Objetos de Firma y LibroCompraVenta
    $firma = new \sasco\LibreDTE\FirmaElectronica($this->dteconfig);
    $LibroCompraVenta = new \sasco\LibreDTE\Sii\LibroCompraVenta(true); // se genera libro simplificado (solicitado así en certificación)

    // generar cada DTE y agregar su resumen al detalle del libro
    // foreach ($set_pruebas as $documento) {
    //   $dte = new \sasco\LibreDTE\Sii\Dte($documento);
    //   $LibroCompraVenta->agregar((array) $dte->getResumen(), false); // agregar detalle sin normalizar
    // }

    // agregar detalle desde un archivo CSV con ; como separador
    $LibroCompraVenta->agregarVentasCSV($this->rutas->certificacion . 'libro-ventas.csv');

    // enviar libro de compras y mostrar resultado del envío: track id o bien =false si hubo error
    $LibroCompraVenta->setCaratula($caratula);
    $LibroCompraVenta->generar(false); // generar XML sin firma y sin detalle
    $LibroCompraVenta->setFirma($firma);
    $xml = $LibroCompraVenta->generar();

    /* Crea EnvioDTE-SetPruebas.xml */
    file_put_contents(
      $this->rutas->certificacion . 'EnvioDTE-SetPruebas-LibroVentas.xml',
      $xml,
    );

    $track_id = $LibroCompraVenta->enviar(); // enviar XML generado en línea anterior

    /* si hubo errores mostrar */
    $errors = [];
    $this->errorCollector($errors);

    return response()->json([
      'errors' => $errors,
      'track_id' => $track_id,
    ]);
  }
  public function sendLibroCompras(Request $request)
  {
    // caratula del libro
    $caratula = [
      'RutEmisorLibro' => $this->Emisor['RUTEmisor'],
      'RutEnvia' => $this->rutCert,
      // 'PeriodoTributario' => date('Y-m'),
      'PeriodoTributario' => '2000-04',
      // 'FchResol' => $this->fchResol,
      'FchResol' => '2006-01-20',
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
        'RUTDoc' => '78885550-8',
        'MntNeto' => 50812,
      ],
      // FACTURA ELECTRONICA 			 32
      // FACTURA DEL GIRO CON DERECHO A CREDITO
      // 10616		  11427
      [
        'TpoDoc' => 33,
        'NroDoc' => 32,
        'TasaImp' => $TasaImp,
        'FchDoc' => $caratula['PeriodoTributario'] . '-01',
        'RUTDoc' => '78885550-8',
        'MntExe' => 10496,
        'MntNeto' => 11096,
      ],
      // FACTURA					781
      // FACTURA CON IVA USO COMUN
      //     30167
      [
        'TpoDoc' => 30,
        'NroDoc' => 781,
        'TasaImp' => $TasaImp,
        'FchDoc' => $caratula['PeriodoTributario'] . '-02',
        'RUTDoc' => '78885550-8',
        'MntNeto' => 30141,
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
        'RUTDoc' => '78885550-8',
        'MntNeto' => 2912,
      ],
      // FACTURA ELECTRONICA			 67
      // ENTREGA GRATUITA DEL PROVEEDOR
      //     12115
      [
        'TpoDoc' => 33,
        'NroDoc' => 67,
        'TasaImp' => $TasaImp,
        'FchDoc' => $caratula['PeriodoTributario'] . '-04',
        'RUTDoc' => '78885550-8',
        'MntNeto' => 11974,
        'IVANoRec' => [
          'CodIVANoRec' => 4,
          'MntIVANoRec' => round(11974 * ($TasaImp / 100)),
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
        'RUTDoc' => '78885550-8',
        'MntNeto' => 10551,
        'OtrosImp' => [
          'CodImp' => 15,
          'TasaImp' => $TasaImp,
          'MntImp' => round(10551 * ($TasaImp / 100)),
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
        'RUTDoc' => '78885550-8',
        'MntNeto' => 8703,
      ],
    ];

    // Objetos de Firma y LibroCompraVenta
    $firma = new \sasco\LibreDTE\FirmaElectronica($this->dteconfig);
    $LibroCompraVenta = new \sasco\LibreDTE\Sii\LibroCompraVenta(true);

    // agregar cada uno de los detalles al libro
    foreach ($detalles as $detalle) {
      $LibroCompraVenta->agregar($detalle);
    }
    // $LibroCompraVenta->agregarComprasCSV($this->rutas->certificacion . 'libro-compras.csv');

    // enviar libro de compras y mostrar resultado del envío: track id o bien =false si hubo error
    $LibroCompraVenta->setCaratula($caratula);
    $xml = $LibroCompraVenta->generar(true); // generar XML sin firma
    $LibroCompraVenta->setFirma($firma);
    $xml = $LibroCompraVenta->generar();

    /* Crea EnvioDTE-SetPruebas.xml */
    file_put_contents(
      $this->rutas->certificacion . 'EnvioDTE-SetPruebas-LibroCompras.xml',
      $xml,
    );

    $track_id = $LibroCompraVenta->enviar(); // enviar XML generado en línea anterior

    $xml = $LibroCompraVenta->saveXML();
    file_put_contents(
      $this->rutas->certificacion . 'LibroCompraVenta-COMPRAS2.xml',
      $xml,
    );

    // si hubo errores mostrar
    $errors = [];
    $this->errorCollector($errors);

    return response()->json([
      'errors' => $errors,
      'track_id' => $track_id,
    ]);
  }
  public function sendSimulacion()
  {
    $folios = [
      // 33 => 240, // +11
      // 39 => 121, // +6
      46 => 101, // +1
      52 => 227, // +1
      56 => 176, // +3
      61 => 198, // +3
    ];

    // caratula para el envío de los dte
    $caratula = [
      'RutEnvia' => $this->rutCert,
      'RutReceptor' => $this->Receptor['RUTRecep'],
      'FchResol' => $this->fchResol,
      'NroResol' => 0,
    ];

    // datos de los DTE (cada elemento del arreglo $documentos es un DTE)
    $documentos = json_decode(
      file_get_contents(
        $this->rutas->certificacion . 'simulacion/documentos.json',
      ),
      true,
    );

    $oldies_for_ref = [];
    foreach ($documentos as $key => $documento) {
      $_folio = 0;
      $_tipo = $documento['Encabezado']['IdDoc']['TipoDTE'];
      if (isset($folios[$_tipo])) {
        $_folio = ++$folios[$_tipo];
        $folios[$_tipo] = $_folio;
      }
      $old_folio = $documentos[$key]['Encabezado']['IdDoc']['Folio'];
      $oldies_for_ref["{$_tipo}_{$old_folio}"] = $_folio;

      $documentos[$key]['Encabezado']['Emisor'] = $this->Emisor;
      $documentos[$key]['Encabezado']['Receptor'] = $this->Receptor;
      $documentos[$key]['Encabezado']['IdDoc']['Folio'] = $_folio;

      if (isset($documentos[$key]['Referencia'])) {
        $tipoDocRef = $documentos[$key]['Referencia']['TpoDocRef'] ?? null;
        $folioDocRef = $documentos[$key]['Referencia']['FolioRef'] ?? null;

        if ($tipoDocRef && $folioDocRef) {
          $documentos[$key]['Referencia']['FolioRef'] = $oldies_for_ref["{$tipoDocRef}_{$folioDocRef}"];
        }
      }
    }

    // Objetos de Firma, Folios y EnvioDTE
    $firma = new \sasco\LibreDTE\FirmaElectronica($this->dteconfig);
    $folios = [];
    foreach ($folios as $tipo => $cantidad) {
      $folios[$tipo] = new \sasco\LibreDTE\Sii\Folios(
        file_get_contents($this->rutas->folios . $tipo . '.xml'),
      );
    }
    $envioDte = new \sasco\LibreDTE\Sii\EnvioDte();

    // generar cada DTE, timbrar, firmar y agregar al sobre de EnvioDTE
    foreach ($documentos as $documento) {
      $dte = new \sasco\LibreDTE\Sii\Dte($documento);
      if (!$dte->timbrar($folios[$dte->getTipo()])) {
        break;
      }
      if (!$dte->firmar($firma)) {
        break;
      }
      $envioDte->agregar($dte);
    }

    // enviar dtes y mostrar resultado del envío: track id o bien =false si hubo error
    $envioDte->setCaratula($caratula);
    $envioDte->setFirma($firma);
    $xml = $envioDte->generar();
    // dd($xml);
    file_put_contents(
      $this->rutas->certificacion . 'EnvioDTE-Simulacion.xml',
      $xml,
    );
    $track_id = $envioDte->enviar();

    // si hubo errores mostrar
    $errors = [];
    $this->errorCollector($errors);

    $folios['exito_simulacion'] = $track_id ? true : false;
    $this->updateFoliosConfig($folios);

    file_put_contents(
      $this->rutas->certificacion . 'SetPruebas.json',
      json_encode($documentos),
    );

    return response()->json([
      'errors' => $errors,
      'track_id' => $track_id,
      'folios' => $folios,
    ]);
  }

  public function generatePDF()
  {

    // sin límite de tiempo para generar documentos
    set_time_limit(0);

    $xmls = [
      'EnvioDTE-SetPruebas-LibroCompras.xml',
      'EnvioDTE-SetPruebas-LibroGuias.xml',
      'EnvioDTE-SetPruebas-LibroVentas.xml',
      'EnvioDTE-SetPruebas-set_basico.xml',
      'EnvioDTE-SetPruebas-set_compras.xml',
      'EnvioDTE-SetPruebas-set_guias.xml',
      'EnvioDTE-Simulacion.xml',
    ];

    foreach ($xmls as $xmlName) {
      // archivo XML de EnvioDTE que se generará
      $archivo = $this->rutas->certificacion . $xmlName;

      // Cargar EnvioDTE y extraer arreglo con datos de carátula y DTEs
      $envioDte = new \sasco\LibreDTE\Sii\EnvioDte();
      $envioDte->loadXML(file_get_contents($archivo));
      $caratula = $envioDte->getCaratula();
      $documentos = $envioDte->getDocumentos();

      $hash = md5($xmlName);

      // directorio temporal para guardar los PDF
      $dir = $this->rutas->pdf . 'dte_' . $hash . '-' . $caratula['RutEmisor'] . '_' . $caratula['RutReceptor'] . '_' . str_replace(['-', ':', 'T'], '', $caratula['TmstFirmaEnv']);
      if (is_dir($dir)) {
        \sasco\LibreDTE\File::rmdir($dir);
      }
      if (!mkdir($dir)) {
        die('No fue posible crear directorio temporal para DTEs');
      }

      // procesar cada DTEs e ir agregándolo al PDF
      foreach ($documentos as $dte) {
        if (!$dte->getDatos())
          die('No se pudieron obtener los datos del DTE');
        $pdf = new \sasco\LibreDTE\Sii\Dte\PDF\Dte(false); // =false hoja carta, =true papel contínuo (false por defecto si no se pasa)
        $pdf->setFooterText();
        $pdf->setLogo('/var/www/html/public/dist/images/logo-sin-fondo.png'); // debe ser PNG!
        $pdf->setResolucion(['FchResol' => $caratula['FchResol'], 'NroResol' => $caratula['NroResol']]);
        $pdf->setCedible(true);
        $pdf->agregar($dte->getDatos(), $dte->getTED());
        $pdf->Output($dir . '/dte_' . $caratula['RutEmisor'] . '_' . $dte->getID() . '-CEDIBLE.pdf', 'F');
      }
      foreach ($documentos as $dte) {
        if (!$dte->getDatos())
          die('No se pudieron obtener los datos del DTE');
        $pdf = new \sasco\LibreDTE\Sii\Dte\PDF\Dte(false); // =false hoja carta, =true papel contínuo (false por defecto si no se pasa)
        $pdf->setFooterText();
        $pdf->setLogo('/var/www/html/public/dist/images/logo-sin-fondo.png'); // debe ser PNG!
        $pdf->setResolucion(['FchResol' => $caratula['FchResol'], 'NroResol' => $caratula['NroResol']]);
        $pdf->setCedible(false);
        $pdf->agregar($dte->getDatos(), $dte->getTED());
        $pdf->Output($dir . '/dte_' . $caratula['RutEmisor'] . '_' . $dte->getID() . '.pdf', 'F');
      }

      // entregar archivo comprimido que incluirá cada uno de los DTEs
      // \sasco\LibreDTE\File::compress($dir, ['format' => 'zip', 'delete' => true, 'download' => false]);
      echo "$dir creado.\n";
    }
  }
}
