<?php namespace App\Http\Traits;

trait MakePDFTrait {

    /**
     * Generate a PDF from a XML
     *
     * @param string $xml XML string
    * @return string returns the full path of the generated PDF
     */
    private function make_pdf(string $xml): string
    {
        $EnvioDte = new \sasco\LibreDTE\Sii\EnvioDte();
        $EnvioDte->loadXML($xml);
        $Caratula = $EnvioDte->getCaratula();
        $dte = $EnvioDte->getDocumentos()[0];

        // =false hoja carta, =true papel contínuo (false por defecto si no se pasa)
        $pdf = new \sasco\LibreDTE\Sii\Dte\PDF\Dte(false);
        $pdf->setFooterText('redminDTE');
        $pdf->setLogo('/var/www/html/public/dist/images/logo-sin-fondo.png'); // debe ser PNG!
        $pdf->setResolucion(['FchResol' => $Caratula['FchResol'], 'NroResol' => $Caratula['NroResol']]);
        $pdf->setCedible(false);
        $pdf->agregar($dte->getDatos(), $dte->getTED());

        $full_path = $this->rutas->tmp . 'dte_' . $Caratula['RutEmisor'] . '_' . $dte->getID() . '.pdf';
        $pdf->Output($full_path, 'F');

        return $full_path;
    }

}
