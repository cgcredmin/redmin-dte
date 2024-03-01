<?php namespace App\Http\Traits;

use App\Models\Log;

trait SendMailTrait{
    protected $apiKeyPublic = env('MAILJET_APIKEY');
    protected $apiKeyPrivate = env('MAILJET_APISECRET');
    protected $url = 'https://api.mailjet.com/v3.1/send';
    protected $template_id = env('MAILJET_TEMPLATEID');

    public function send_mj(string $content, string $email, array $attachments=[])
    {
        $fallbackEmail = env('FALLBACK_EMAIL','');

        $data_string = json_encode([
            "Messages" => [
                [
                    "From" => [
                        "Email" => "no-reply@iegsw.cl",
                        "Name" => "IEGSW"
                    ],
                    "To" => [
                        [
                            "Email" => $email,
                            "Name" => $email
                        ]
                    ],
                    "Bcc" => [
                        [
                            "Email" => $fallbackEmail,
                            "Name" => $fallbackEmail
                        ],
                    ],
                    "TemplateID" => $this->template_id,
                    "TemplateLanguage" => true,
                    "Subject" => "Envío de Documento Trinutario Electrónico",
                    "Variables" => [
                        "title" => "Envío de Documento Trinutario Electrónico",
                        "content" => $content
                    ],
                    "Attachments" => $attachments
                ]
            ]
        ]);
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $data_string,
            CURLOPT_USERPWD => $this->apiKeyPublic . ':' . $this->apiKeyPrivate,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode($this->apiKeyPublic . ':' . $this->apiKeyPrivate)
            ),
        ));

        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        // Check for errors and close cURL
        if ($response === false) {
            Log::create(['message' => 'Error al enviar correo: ' . curl_error($curl), 'type' => 'error', 'user_id' => 1]);
        }

        curl_close($curl);

        $encoded =['data' => json_decode($response), 'code' => intval($http_code)];
        return $encoded;
    }

}
