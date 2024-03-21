<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Storage;
use App\Http\Traits\DteAuthTrait;

class FoliosController extends Controller
{
    use DteAuthTrait;

    public function getFolios(Request $request)
    {
        $rules = [
            'folioInicial' => 'required|numeric',
            'tipoDte' => 'required|numeric',
            'folioFinal' => 'required|numeric',
        ];

        $this->validate($request, $rules);

        $cookies = new CookieJar();

        $headers = [
            'content-type' => 'application/json',
            'Accept' => 'application/json, text/plain, */*',
            'Accept-Encoding' => 'gzip, deflate, br',
        ];

        $credentials = [$this->dteconfig['pem'], $this->dteconfig['pass']];

        $client = new Client();
        $response = $client->request(
            'GET',
            'https://herculesr.sii.cl/cgi_AUT2000/CAutInicio.cgi?http://www.sii.cl',
            [
                [0 => 'path/cert.pem', 1 => 'passwordcert'],
                'cert' => $credentials,
                'query' => [
                    'rutcntr' => $this->rutCert,
                    'rut' => explode('-', $this->rutCert)[0],
                    'referencia' => 'https://www.sii.cl',
                    'dv' => explode('-', $this->rutCert)[1],
                ],
                'cookies' => $cookies,
            ],
        );

        $parsed = $this->parseResponse(
            (string)$response->getBody(),
        );

        $response_code = $response->getStatusCode();
        if ($response_code != 200 || $parsed['success'] === false) {
            $this->logout($cookies, $headers);
            if ($parsed['message']) {
                return response()->json(['message' => $parsed['message'],'code'=>'login'], 400);
            }
            return response()->json(['message' => 'Error al intentar loguearse','code'=>'login'], 400);
        }



        [$rutEmpresa, $dvEmpresa] = explode(
            '-',
            str_replace('.', '', $this->rutEmpresa),
        );
        try {
            /**
             * se envia solicitud de folios
             */
            $url = '/cvc_cgi/dte/of_solicita_folios';

            $query = [
                'RUT_EMP' => $rutEmpresa,
                'DV_EMP' => $dvEmpresa,
                'ACEPTAR' => 'Continuar',
            ];

            $client = new Client([
                'headers' => $headers,
                'base_uri' => 'https://' . $this->servidor . '.sii.cl',
                'cookies' => $cookies,
                'defaults' => [
                    'exceptions' => false,
                    'allow_redirects' => false,
                ],
                'query' => $query,
            ]);

            $response = $client->request('POST', $url, [
                'form_params' => $query,
            ]);

            // realizar consulta curl
            $response_code = $response->getStatusCode();
            $parsed = $this->parseResponse(
                (string)$response->getBody(),
            );

            if ($response_code != 200 || $parsed['success'] === false) {
                $this->logout($cookies, $headers);
                if ($parsed['message']) {
                    return response()->json(['message' => $parsed['message'],'code'=>'of_solicita_folios'], 400);
                }
                return response()->json(['message' => 'Error al solicitar folios','code'=>'of_solicita_folios'], 400);
            }

            /**
             * Se envia confirmacion de solicitud de folios
             */
            $url = '/cvc_cgi/dte/of_confirma_folio';

            $query = [
                'RUT_EMP' => $rutEmpresa,
                'DV_EMP' => $dvEmpresa,
                'FOLIO_INICIAL' => $request->folioInicial,
                'COD_DOCTO' => $request->tipoDte,
                'AFECTO_IVA' => 'S',
                'CON_CREDITO' => '0',
                'CON_AJUSTE' => '0',
                'FACTOR' => null,
                'CANT_DOCTOS' => $request->folioFinal - $request->folioInicial + 1,
                'ACEPTAR' => '(unable to decode value)',
            ];

            $client = new Client([
                'headers' => $headers,
                'base_uri' => 'https://' . $this->servidor . '.sii.cl',
                'cookies' => $cookies,
                'defaults' => [
                    'exceptions' => false,
                    'allow_redirects' => false,
                ],
                'query' => $query,
            ]);

            $response = $client->request('POST', $url, [
                'form_params' => $query,
            ]);

            // realizar consulta curl
            $response_code = $response->getStatusCode();
            $parsed = $this->parseResponse(
                (string)$response->getBody(),
            );
            if ($response_code != 200 || $parsed['success'] === false) {
                $this->logout($cookies, $headers);
                if ($parsed['message']) {
                    return response()->json(['message' => $parsed['message'],'code'=>'of_confirma_folio'], 400);
                }
                return response()->json(['message' => 'Error al solicitar folios','code'=>'of_confirma_folio'], 400);
            }

            /**
             * Se genera archivo folios
             */
            $url = '/cvc_cgi/dte/of_genera_folio';

            $query = [
                'NOMUSU' => strtoupper($this->nombreCert),
                'CON_CREDITO' => 0,
                'CON_AJUSTE' => 0,
                'FOLIO_INI' => $request->folioInicial,
                'FOLIO_FIN' => $request->folioFinal,
                'DIA' => date('d'),
                'MES' => date('m'),
                'ANO' => date('Y'),
                'HORA' => date('H'),
                'MINUTO' => date('i'),
                'RUT_EMP' => $rutEmpresa,
                'DV_EMP' => $dvEmpresa,
                'COD_DOCTO' => $request->tipoDte,
                'CANT_DOCTOS' => $request->folioFinal - $request->folioInicial + 1,
                'ACEPTAR' => 'Obtener Folios',
            ];

            $client = new Client([
                'headers' => $headers,
                'base_uri' => 'https://' . $this->servidor . '.sii.cl',
                'cookies' => $cookies,
                'defaults' => [
                    'exceptions' => false,
                    'allow_redirects' => false,
                ],
                'query' => $query,
            ]);

            $response = $client->request('POST', $url, [
                'form_params' => $query,
            ]);

            // realizar consulta curl
            $response_code = $response->getStatusCode();
            $parsed = $this->parseResponse(
                (string)$response->getBody(),
            );
            if ($response_code != 200 || $parsed['success'] === false) {
                $this->logout($cookies, $headers);
                if ($parsed['message']) {
                    return response()->json(['message' => $parsed['message'],'code'=>'of_genera_folio'], 400);
                }
                return response()->json(['message' => 'Error al solicitar folios','code'=>'of_genera_folio'], 400);
            }

            /**
             * Se genera el archivo de folios solicitado
             */
            $url = '/cvc_cgi/dte/of_genera_archivo';

            $query = [
                'RUT_EMP' => $rutEmpresa,
                'DV_EMP' => $dvEmpresa,
                'COD_DOCTO' => $request->tipoDte,
                'FOLIO_INI' => $request->folioInicial,
                'FOLIO_FIN' => $request->folioFinal,
                'FECHA' => date('Y-m-d'),
                'ACEPTAR' => 'AQUI',
            ];

            $client = new Client([
                'headers' => [
                    'content-type' => 'application/x-www-form-urlencoded',
                    'Accept' =>
                    'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
                    'Accept-Encoding' => 'gzip, deflate, br',
                ],
                'base_uri' => 'https://' . $this->servidor . '.sii.cl',
                'cookies' => $cookies,
                'defaults' => [
                    'exceptions' => false,
                    'allow_redirects' => false,
                ],
                'query' => $query,
            ]);

            $response = $client->request('POST', $url, [
                'form_params' => $query,
            ]);

            // realizar consulta curl
            $response_code = $response->getStatusCode();
            $parsed = $this->parseResponse(
                (string)$response->getBody(),
            );
            if ($response_code != 200 || $parsed['success'] === false) {
                $this->logout($cookies, $headers);
                if ($parsed['message']) {
                    return response()->json(['message' => $parsed['message'],'code'=>'of_genera_archivo'], 400);
                }
                return response()->json(['message' => 'Error al solicitar folios','code'=>'of_genera_archivo'], 400);
            }

            // store the file in xml/dte/folios
            $content = $response->getBody()->getContents();
            $filename = $this->rutas->folios . $request->tipoDte . '.xml';
            Storage::disk('local')->put($filename, $content);

            return $content;
        } catch (\Throwable $th) {
            return response()->json(
                [
                    'message' => 'Error al solicitar folios',
                    'error' => $th->getMessage(),
                ],
                400,
            );
        }
    }

    private function logout($cookies, $headers){
        // Close the session
        //https://zeusr.sii.cl/cgi_AUT2000/autTermino.cgi?http://www.sii.cl
        $client = new Client([
            'headers' => $headers,
            'base_uri' => 'https://' . $this->servidor . '.sii.cl',
            'cookies' => $cookies,
            'defaults' => [
                'exceptions' => false,
                'allow_redirects' => false,
            ],
        ]);

        $client->getAsync('https://zeusr.sii.cl/cgi_AUT2000/autTermino.cgi');
        // dd($response->getBody()->getContents());
    }

    private function parseResponse(string $content)
    {
        $err_texts = [
            'No ha sido posible completar su solicitud.',
        ];


        foreach ($err_texts as $err_text) {
            if (stripos($content, $err_text) !== false) {
                $message = $err_text;
                try {
                    $content = substr($content, strpos($content, '</head>'));
                    $content = str_replace('</html>','',$content);
                    $content = str_replace('</head>','',$content);
                    $content = str_replace('cute ','cute; ',$content);
                    // dd($content);
                    $dom = new \DOMDocument();
                    $dom->loadHTML($content);
                    $xpath = new \DOMXPath($dom);
                    $text = $xpath->query('//font[@class="texto"]');
                    // $text = $xpath->query('font.texto');
                    // get the last item
                    $message = trim($text[$text->length - 1]->textContent);
                    // dd($content, $message);
                } catch (\Throwable $th) {
                    $message = $err_text;
                }
                return ['success' => false, 'message' => $message];
            }
        }

        return ['success' => true, 'message' => 'OK'];
    }
}
