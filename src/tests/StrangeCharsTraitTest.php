<?php

namespace Tests;

use App\Http\Traits\StrangeCharsTrait;
use PHPUnit\Framework\TestCase;

class StrangeCharsTest extends TestCase
{
  use StrangeCharsTrait;

  public function test_parse_replaces_strange_chars_with_correct_chars()
  {
    $text = 'PaÒuelo AFECTO';
    $expected = 'Pañuelo AFECTO';

    $result = $this->parse($text);

    $this->assertEquals($expected, $result);
  }

  public function test_parse_does_not_replace_chars_if_no_strange_chars_present()
  {
    $text = 'Cajón AFECTO';
    $expected = 'Cajón AFECTO';

    $result = $this->parse($text);

    $this->assertEquals($expected, $result);
  }
}
