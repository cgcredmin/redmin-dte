<?php

namespace App\Http\Traits;

trait StrangeCharsTrait
{
  public function parse(string $text): string
  {
    // PaÒuelo AFECTO   -> Pañuelo AFECTO
    // CajÛn AFECTO	    -> Cajón AFECTO

    $strangeChars = [
      'Ò' => 'ñ',
      'Û' => 'ó',
    ];

    return str_replace(
      array_keys($strangeChars),
      array_values($strangeChars),
      $text
    );
  }
}
