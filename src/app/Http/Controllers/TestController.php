<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Milon\Barcode\DNS2D;

class TestController extends Controller
{
  public function test()
  {
    $ted =
      '<TED version="1.0"><DD><RE>76192083-9</RE><TD>33</TD><F>46710</F><FE>2022-12-21</FE><RR>60803000-K</RR><RSR>Servicio de Impuestos Internos</RSR><MNT>2380</MNT><IT1>item 1 afecto</IT1><CAF version="1.0"><DA><RE>76248151-0</RE><RS>SOCIEDAD COMERCIALIZADORA IEG LIMITADA</RS><TD>33</TD><RNG><D>46709</D><H>47208</H></RNG><FA>2022-11-29</FA><RSAPK><M>06ZpNfdTrAD89OLXP/NPaE9iDQ3ozELU9+5VPMs2fQxlHXMts0sfjGVYU32e68IGudolfiqACLNJnI9oJHkpoQ==</M><E>Aw==</E></RSAPK><IDK>300</IDK></DA><FRMA algoritmo="SHA1withRSA"> C0IiBORUgBZHu4rU4/MKAY0gbcxbB/+2JlHF2QY98Fzi7P4LeYSSpPaWHduV+8BBe/G75QrvDe0poLeqpw8tOg==</FRMA></CAF><TSTED>2022-12-21T15:51:13</TSTED></DD><FRMT algoritmo="SHA1withRSA">RA0EBa4HvO87DOuUF232cpe96HScYaEUTHK0mSCu6D+zdV7u9rz47LpNPsIoCJ1A71AmUyfCGPmraGqFFvfajA==</FRMT></TED>';

    // $ted = 'hola mundo';
    $code = new DNS2D();
    $result = $code->getBarcodePNG($ted, 'PDF417');
    // $result = $code->getBarcodeSVG($ted, 'PDF417');

    return $result;
  }
}
