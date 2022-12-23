<?php
namespace App\Http\Traits;

trait DteDatosTrait
{
  protected $dte_estados = [
    'DOK' =>
      'Documento Recibido por el SII. Datos Coinciden con los Registrados.',
    'DNK' =>
      'Documento Recibido por el SII pero Datos NO Coinciden con los registrados.',
    'FAU' => 'Documento No Recibido por el SII.',
    'FNA' => 'Documento No Autorizado.',
    'FAN' => 'Documento Anulado.',
    'EMP' =>
      'Empresa no autorizada a Emitir Documentos Tributarios Electrónicos',
    'TMD' => 'Existe Nota de Debito que Modifica Texto Documento.',
    'TMC' => 'Existe Nota de Crédito que Modifica Textos Documento.',
    'MMD' => 'Existe Nota de Debito que Modifica Montos Documento.',
    'MMC' => 'Existe Nota de Crédito que Modifica Montos Documento.',
    'AND' => 'Existe Nota de Debito que Anula Documento.',
    'ANC' => 'Existe Nota de Crédito que Anula Documento.',
  ];

  protected $dte_srv_codes = [
    0 => 'Todo OK',
    1 => 'Error en Entrada',
    2 => 'Error SQL',
  ];

  protected $dte_sql_codes = [
    0 => 'Documento Recibido por el SII. Datos Coinciden con los Registrados.',
    1 => 'Documento Recibido por el SII pero Datos NO Coinciden con los registrados.',
    2 => 'NE',
    3 => 'Documento No Recibido por el SII.',
    4 => 'Documento No Autorizado.',
    5 => 'Documento Anulado.',
    6 => 'Empresa no autorizada a Emitir Documentos Tributarios Electrónicos.',
    7 => 'NE',
    8 => 'NE',
    9 => 'NE',
    10 => 'Existe Nota de Debito que Modifica Texto Documento.',
    11 => 'Existe Nota de Crédito que Modifica Textos Documento.',
    12 => 'Existe Nota de Debito que Modifica Montos Documento.',
    13 => 'Existe Nota de Crédito que Modifica Montos Documento.',
    14 => 'Existe Nota de Debito que Anula Documento.',
    15 => 'Existe Nota de Crédito que Anula Documento.',
    'Otro' => 'Error Interno.',
  ];

  public function getKeysDteEstados()
  {
    return array_keys($this->dte_estados);
  }
}
