<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

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
      $router->post('getPdf/{hash}', 'DteController@obtenerPdf');

      $router->post('requestFolios', 'FoliosController@getFolios');
      $router->post('renewToken', 'Controller@renewToken');
    });

    $router->group(['prefix' => 'contribuyentes'], function () use ($router) {
      $router->post('getContribuyentes', 'ContribuyentesController@index');
    });

    $router->group(['prefix' => 'compraventa'], function () use ($router) {
      $router->post('getCompras', 'LibroCompraVentaController@getCompras');
      $router->post('getVentas', 'LibroCompraVentaController@getVentas');
    });

    $router->group(['prefix' => 'config'], function () use ($router) {
      $router->post('uploadCertOrFolio', 'ConfigController@upload');
      $router->post('setConfig', 'ConfigController@setConfig');
      $router->post('getConfig', 'ConfigController@getConfig');
    });

    $router->post('test', 'TestController@test');
  });

  $router->get('file/{hash}', 'TemporalController@getFile');
});
