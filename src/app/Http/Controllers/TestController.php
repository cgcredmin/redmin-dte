<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use sasco\LibreDTE\Sii\Dte\Base\DteImpreso;
use App\Http\Traits\DteAuthTrait;

class TestController extends Controller
{
  use DteImpreso;
  use DteAuthTrait;

  public function test(Request $request)
  {
    $rules = [
      'set' => 'required|file|mimes:txt',
      'folios.*' => 'required|numeric|min:1',
    ];
    $this->validate($request, $rules);

    $set = $request->file('set');

    $folios = json_decode($request->folios, true);

    $set_pruebas = [];

    $lines = file($set->getRealPath());

    // caratula para el envío de los dte
    $caratula = [
      'RutEnvia' => '11222333-4',
      'RutReceptor' => '60803000-K',
      'FchResol' => '2014-12-05',
      'NroResol' => 0,
    ];

    // datos del emisor
    $Emisor = [
      'RUTEmisor' => $this->rutEmpresa,
      'RznSoc' => $this->nombreEmpresa,
      'GiroEmis' => $this->giro,
      'Acteco' => $this->actividad_economica,
      'DirOrigen' => $this->direccion,
      'CmnaOrigen' => $this->comuna,
    ];

    $caso = '';
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
              'Emisor' => $Emisor,
              'Receptor' => [
                'RUTRecep' => '60803000-K',
                'RznSocRecep' => 'Empresa S.A.',
                'GiroRecep' => 'Servicios jurídicos',
                'DirRecep' => 'Santiago',
                'CmnaRecep' => 'Santiago',
              ],
              'Totales' => [
                // estos valores serán calculados automáticamente
                'MntNeto' => 0,
                'MntExe' => 0,
                'TasaIVA' => \sasco\LibreDTE\Sii::getIVA(),
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
            $_folio = isset($folios[$key]) ? $folios[$key] + 1 : null;
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

    /* // Objetos de Firma, Folios y EnvioDTE
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
    $track_id = $EnvioDTE->enviar();
    // var_dump($track_id);

    // si hubo errores mostrar
    $errors = [];
    foreach (\sasco\LibreDTE\Log::readAll() as $error) {
      $errors[] = collect($error)
        ->only(['code', 'msg'])
        ->toArray();
    } */

    // dd($set_pruebas);
    return response()->json(
      [
        'set_pruebas' => $this->encodeObjectToUTF8($set_pruebas),
      ],
      200,
    );
  }
}
