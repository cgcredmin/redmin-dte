<?php

$router->group(['prefix' => 'api'], function () use ($router) {
    $router->post('login', 'AuthController@login');
    $router->post('logout', 'AuthController@logout');
    $router->post('refresh', 'AuthController@refresh');
    $router->post('user-profile', 'AuthController@me');

    $router->group(['middleware' => 'auth'], function () use ($router) {
        $router->group(['prefix' => 'dte'], function () use ($router) {
            $router->post('checkDocumento', 'DteController@comprobarDocumento');
            $router->post('checkDocumentoAv', 'DteController@comprobarDocumentoAv');
            $router->post('sendDocumento', 'DteController@enviaDocumento');
            $router->post('sendBoleta', 'BoletaController@enviaDocumento');
            $router->get('getTempLink', 'DteController@getTempLink');

            $router->post('requestFolios', 'FoliosController@getFolios');
            $router->post('renewToken', 'Controller@renewToken');
        });

        $router->group(['prefix' => 'contribuyentes'], function () use ($router) {
            $router->post('getContribuyentes', 'ContribuyentesController@index');
            $router->post('uploadContribuyentes', 'ContribuyentesController@upload');
        });

        $router->group(['prefix' => 'compraventa'], function () use ($router) {
            $router->post('getCompras', 'LibroCompraVentaController@getCompras');
            $router->post('getVentas', 'LibroCompraVentaController@getVentas');
        });

        $router->group(['prefix' => 'compras'], function () use ($router) {
            $router->post('/', 'ComprasController@getCompras');
            $router->get('/pdf/{hash}', 'ComprasController@getPdf');
        });

        $router->group(['prefix' => 'config'], function () use ($router) {
            $router->post('uploadCertOrFolio', 'ConfigController@upload');
            $router->post('setConfig', 'ConfigController@setConfig');
            $router->post('getConfig', 'ConfigController@getConfig');
            $router->post('gmail', 'ConfigController@gmail');
            $router->post('getLogs', 'ConfigController@getLogs');
            $router->post('uploadDataBase', 'ConfigController@uploadDataBase');
            $router->post('downloadDataBase', 'ConfigController@downloadDataBase');
        });

        $router->group(['prefix' => 'certificacion'], function () use ($router) {
            $router->post('generatePDF', 'CertificacionController@generatePDF');
            $router->post('sendSetBasico', 'CertificacionController@sendSetBasico');
            $router->post('sendSetGuias', 'CertificacionController@sendSetGuias');
            $router->post('sendSetCompras', 'CertificacionController@sendSetCompras');
            $router->post('sendSimulacion', 'CertificacionController@sendSimulacion');
            $router->post(
                'sendLibroVentas',
                'CertificacionController@sendLibroVentas',
            );
            $router->post(
                'sendLibroCompras',
                'CertificacionController@sendLibroCompras',
            );
            $router->post(
                'sendLibroGuias',
                'CertificacionController@sendLibroGuias',
            );
        });

        $router->post('send-document', 'SendDocumentController@send');

        $router->post('test', 'TestController@test');

        $router->post('get-crucial-data', 'InitController@getCrucialData');
    });

    $router->get('file/{hash}', 'TemporalController@getFile');

    $router->post('upload/rcv', 'RcvController@upload');

    $router->post('get-pdf', 'PdfController@getPdf');
});
