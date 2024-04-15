<?php

namespace App\Http\Traits;


use GuzzleHttp\Client;

trait SiiScrapperTrait
{


    private function logout($cookies, $headers)
    {
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
                    $content = str_replace('</html>', '', $content);
                    $content = str_replace('</head>', '', $content);
                    $content = str_replace('cute ', 'cute; ', $content);
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
                    $message = $err_text . ': ' . $th->getMessage();
                }
                return ['success' => false, 'message' => $message];
            }
        }

        return ['success' => true, 'message' => 'OK'];
    }
}
