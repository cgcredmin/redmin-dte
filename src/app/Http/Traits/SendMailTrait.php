<?php

namespace App\Http\Traits;

use App\Models\Log;

trait SendMailTrait
{
    public function send_mj(string $content, string $email, array $attachments = [])
    {
        $apiKeyPublic = env('MAILJET_APIKEY');
        $apiKeyPrivate = env('MAILJET_APISECRET');
        $url = 'https://api.mailjet.com/v3.1/send';
        $template_id = (int)env('MAILJET_TEMPLATEID');
        $fallbackEmail = env('FALLBACK_EMAIL', '');
        $mail_from = env('MAILJET_FROM_EMAIL','no-reply@noreply.mail');
        $name_from = env('APP_NAME','Mailjet');

        // dd($apiKeyPublic, $apiKeyPrivate, $url, $template_id, $fallbackEmail, $email, $attachments, $content);

        $data_string = json_encode([
            "Messages" => [
                [
                    "From" => [
                        "Email" => $mail_from,
                        "Name" => $name_from
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
                    "TemplateID" => $template_id,
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
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $data_string,
            CURLOPT_USERPWD => $apiKeyPublic . ':' . $apiKeyPrivate,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode($apiKeyPublic . ':' . $apiKeyPrivate)
            ),
        ));

        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        // Check for errors and close cURL
        if ($response === false) {
            Log::create(['message' => 'Error al enviar correo: ' . curl_error($curl), 'type' => 'error', 'user_id' => 1]);
        }

        curl_close($curl);

        $encoded = ['data' => json_decode($response), 'code' => intval($http_code)];
        return $encoded;
    }
}
