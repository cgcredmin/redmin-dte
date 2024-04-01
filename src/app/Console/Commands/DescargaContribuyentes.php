<?php

namespace App\Console\Commands;

ini_set('memory_limit', '1024M');

use App\Console\Commands\ComandoBase;
use Illuminate\Support\Facades\Storage;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\DB;

use App\Jobs\ProcessBulkCopy;

class DescargaContribuyentes extends ComandoBase
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redmin:contribuyentes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Descarga el listado de contribuyentes de la página del SII.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('Downloader...');
        // delete files
        Storage::disk('local')->delete('contribuyentes.csv');
        Storage::disk('local')->delete('contribuyentes.html');

        // this request requires a certificate included in the request
        [$rut, $dv] = explode('-', $this->rutCert);
        $credentials = [$this->dteconfig['pem'], $this->dteconfig['pass']];

        $cookies = new CookieJar();
        $client = new Client();

        try {
            $response = $client->request(
                'GET',
                'https://herculesr.sii.cl/cgi_AUT2000/CAutInicio.cgi?http://www.sii.cl',
                [
                    [0 => 'path/cert.pem', 1 => 'passwordcert'],
                    'cert' => $credentials,
                    'query' => [
                        'rutcntr' => $this->rutCert,
                        'rut' => $rut,
                        'referencia' => 'https://www.sii.cl',
                        'dv' => $dv,
                    ],
                    'cookies' => $cookies,
                ],
            );

            if ($response->getStatusCode() != 200) {
                $this->error($response->getReasonPhrase());
                $this->sendToCommandRetry('redmin:contribuyentes', [], 3);
                return false;
            }
        } catch (\Exception $e) {
            $this->error('Error al descargar el listado de contribuyentes');
            $this->sendToCommandRetry('redmin:contribuyentes', [], 3);
            return false;
        }

        $output = '';

        try {
            // $url = "https://palena.sii.cl/cvc_cgi/dte/ce_empresas_dwnld";
            $url = "https://{$this->servidor}.sii.cl/cvc_cgi/dte/ce_empresas_dwnld";
            $client = new Client([
                'headers' => [
                    'content-type' => 'application/json',
                    'Accept' => 'application/json, text/plain, */*',
                    'Accept-Encoding' => 'gzip, deflate, br',
                ],
                'base_uri' => 'https://' . $this->servidor . '.sii.cl',
                'cookies' => $cookies,
                'defaults' => [
                    'exceptions' => false,
                    'allow_redirects' => false,
                ],
            ]);

            $response = $client->request('GET', $url);

            // realizar consulta curl
            $response_code = $response->getStatusCode();

            if ($response_code != 200) {
                $this->error($response->getReasonPhrase());
                return false;
            }

            $output = $response->getBody()->getContents();

            // if $output starts by '<!DOCTYPE html PUBLIC ' then the request failed
            if (strpos($output, '<!DOCTYPE html PUBLIC ') === 0) {
                $this->error('Error al descargar el listado de contribuyentes');
                Storage::disk('local')->put('contribuyentes.html', $output);
                exit(1);
            }
            Storage::disk('local')->put('contribuyentes.csv', $output);
        } catch (\Exception $e) {
            $this->error('Error al descargar el listado de contribuyentes');
            exit(1);
        }

        // check file size
        $size = Storage::disk('local')->size('contribuyentes.csv');
        // if the file is bigger than 20Mb, then should be OK
        if ($size > 20000000) {

            $csvFile = storage_path('app/contribuyentes.csv');
            if (!file_exists($csvFile)) {
                $this->error('Error al descargar el listado de contribuyentes');
                exit(1);
            }

            try {
                $sql = "LOAD DATA LOCAL INFILE '$csvFile'
        INTO TABLE bulkCopyTable
        FIELDS TERMINATED BY ';'
        LINES TERMINATED BY '\n'
        IGNORE 1 LINES";

                DB::connection('mysql')->getpdo()->exec($sql);

                // launch job ProcessBulkCopy to process the data, queue it for later (5min)
                dispatch(new ProcessBulkCopy())->delay(now()->addMinutes(5));

                $this->info('OK');
            } catch (\Exception $e) {
                // dd($e->getMessage());
                $this->error('Error al insertar los datos en la base de datos');
                $this->error($e->getMessage());
                exit(1);
            }
        } else {
            $this->error('Error al descargar el listado de contribuyentes');
            exit(1);
        }
    }

    private function sendToCommandRetry($command, $arguments, $attempts = 3)
    {
        $attempts--;
        try {
            $this->info("Reintentando comando {$command}...");
            // add a delay of 5 seconds
            sleep(5);
            $this->call($command, $arguments);
        } catch (\Exception $e) {
            if ($attempts > 0) {
                $this->sendToCommandRetry($command, $arguments, $attempts);
            } else {
                throw $e;
            }
        }
    }

    /*
  https://zeusr.sii.cl/AUT2000/InicioAutenticacion/IngresoCertificado.html?https://misiir.sii.cl/cgi_misii/siihome.cgi
  https://herculesr.sii.cl/cgi_AUT2000/CAutInicio.cgi?https://misiir.sii.cl/cgi_misii/siihome.cgi
  */
}
