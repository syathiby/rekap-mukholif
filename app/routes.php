<?php
declare(strict_types=1);

/** @var \App\Core\Router $router */

// Auth routes
$router->get('/login', 'AuthController', 'login');
$router->post('/login', 'AuthController', 'loginProcess');
$router->get('/logout', 'AuthController', 'logout');

// Dashboard routes
$router->get('/', 'DashboardController', 'index');
$router->get('/dashboard', 'DashboardController', 'index');

// Santri routes
$router->get('/santri', 'SantriController', 'index');
$router->get('/santri/create', 'SantriController', 'create');
$router->post('/santri', 'SantriController', 'store');
$router->get('/santri/{id}/edit', 'SantriController', 'edit');
$router->post('/santri/{id}', 'SantriController', 'update');
$router->post('/santri/{id}/delete', 'SantriController', 'destroy');

// Santri Bulk Routes
$router->get('/santri/bulk-create', 'SantriController', 'bulkCreate');
$router->post('/santri/bulk-create', 'SantriController', 'bulkStore');
$router->get('/santri/bulk-edit', 'SantriController', 'bulkEdit');
$router->post('/santri/bulk-edit', 'SantriController', 'bulkUpdate');
$router->post('/santri/bulk-delete', 'SantriController', 'bulkDestroy');
