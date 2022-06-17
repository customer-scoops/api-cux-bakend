<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/
//RUTAS DE API SUITE
$router->group(['prefix' => 'api', 'middleware' => ['auth','throttle:10,1']], function () use ($router) {
    /*$router->get('/test', function () use ($router) {
        echo 'pase el token';
    });
    */
    $router->get('/suite/tickets', ['middleware' => ['accessSuite','logUsers:suite'],  
                  'uses' => 'SuiteController@getAll']);
    $router->get('/surveys', 'SuiteController@getSurvey');
    $router->get('/suite/indicators', 'SuiteController@getDataCards');
    $router->put('/suite', 'SuiteController@saveRegister');
    $router->put('/suite/banmedica', 'SuiteController@updateRegisterBanmedica');
  });
// RUTAS API DASHBOARD
$router->group(['prefix' => 'dashboard', 'middleware'=> ['auth','throttle:10,1','accessDashboard']], function () use ($router) {
    $router->get('/general-resumen', ['middleware' => 'logUsers:dashboard',
                  'uses' => 'DashboardController@index']);
    $router->get('/general-resumen-back-cards', 'DashboardController@indexBackCards');
    $router->get('/details-dashboard', 'DashboardController@detailsDash');
    $router->get('/text-mining', 'DashboardController@textMining');
    $router->get('/matriz', 'DashboardController@matriz');
    $router->get('/period-compare', 'PeriodController@getPeriod');
    $router->get('/cx-word', 'DashboardController@detailsDashCxWord');
    $router->get('/data-filters', 'DashboardController@filters');
    $router->get('/download-excel',['middleware' => 'download',
    'uses' => 'DashboardController@downloadExcel'] );
    $router->get('/download-excel-login','DashboardController@downloadExcelLogin' );
  });
  
