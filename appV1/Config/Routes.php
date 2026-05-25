<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

$routes->group('api', ['namespace' => 'App\Controllers'], function ($routes) {
    $routes->post('login', 'AuthController::login');
    $routes->post('logout', 'AuthController::logout');
    $routes->post('test-user', 'AuthController::createTestUser'); // Temporal
    $routes->post('upload-stage', 'InvoiceController::stage');
    $routes->post('delete-staged', 'InvoiceController::deleteStaged'); // Ruta para borrar temp
    $routes->post('process-batch', 'InvoiceController::processBatch');
    $routes->post('analyze-invoice', 'InvoiceController::analyze');
    $routes->post('save-invoice', 'InvoiceController::save');

    // Admin endpoints
    $routes->post('users', 'AuthController::createUser');
    $routes->get('users', 'AuthController::getUsers');
    $routes->put('users/(:num)', 'AuthController::updateUser/$1');
    $routes->get('providers', 'AuthController::getProviders');
    $routes->post('providers', 'AuthController::createProvider');
    $routes->put('providers/(:num)', 'AuthController::updateProvider/$1');
    $routes->get('empresas', 'AuthController::getEmpresas');

    $routes->options('(:any)', static function () { });
});
