<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Traits\MakePDFTrait;
use sasco\LibreDTE\Sii\Dte\Base\DteImpreso;
use App\Http\Traits\SendMailTrait;

class SendDocumentController extends Controller
{
    use MakePDFTrait;
    use DteImpreso;
    use SendMailTrait;

    public function send(Request $request)
    {
        $rules = [
            "email" => "required|string",
            "xml" => "required|string"
        ];

        $this->validate($request, $rules);

        // generate pdf from xml
        $pdf = $this->make_pdf($request->xml);
        $parts = explode('/', $pdf);
        $file_name = str_replace('.pdf', '', $parts[count($parts) - 1]);
        // store xml into a file
        $xml = $this->rutas->tmp . $file_name . '.xml';
        file_put_contents($xml, $request->xml);
        $xml_string = file_get_contents($xml);

        // read xml and get tipo_dte and folio
        $xml_content = simplexml_load_string($xml_string);

        $folio = (string) $xml_content->SetDTE->DTE->Documento->Encabezado->IdDoc->Folio;
        $tipo_dte = (string) $xml_content->SetDTE->DTE->Documento->Encabezado->IdDoc->TipoDTE;
        $tipo_doc = $this->getTipo($tipo_dte);
        $attachments = [];
        if ($pdf) {
            $fcontent = file_get_contents($pdf);
            $attachments[] = [
                "ContentType" => "application/pdf",
                "Filename" => "DTE_{$tipo_doc}_{$folio}",
                "Base64Content" => base64_encode($fcontent)
            ];
        }
        if ($xml) {
            $fcontent = file_get_contents($xml);
            $attachments[] = [
                "ContentType" => "text/xml",
                "Filename" => "DTE_{$tipo_doc}_{$folio}",
                "Base64Content" => base64_encode($fcontent)
            ];
        }

        $content = view('emails.dte')
                ->with([
                    'folio' => $folio,
                    'tipo_doc_nombre' => $tipo_doc,
                ])
                ->render();


        $result = $this->send_mj($content, $request->email, $attachments);

        return response()->json($result);
    }
}
