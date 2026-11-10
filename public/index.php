<?php
declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\BoardController;
use App\Controllers\TaskController;
use App\Env;
use App\HttpException;
use App\Response;
use App\Router;

$root = dirname(__DIR__);
require $root . '/src/Core.php';
require $root . '/src/Http.php';
require $root . '/src/Auth.php';
require $root . '/src/Controllers/AuthController.php';
require $root . '/src/Controllers/BoardController.php';
require $root . '/src/Controllers/TaskController.php';

Env::load($root . '/.env');

// CORS para que el front en React (otro puerto) pueda llamar a la API
header('Access-Control-Allow-Origin: ' . Env::get('ALLOWED_ORIGIN', '*'));
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$router = new Router();

// Auth
$router->add('POST', '/api/auth/register', [AuthController::class, 'register']);
$router->add('POST', '/api/auth/login', [AuthController::class, 'login']);
$router->add('GET', '/api/me', [AuthController::class, 'me']);

// Tableros y columnas
$router->add('GET', '/api/boards', [BoardController::class, 'index']);
$router->add('POST', '/api/boards', [BoardController::class, 'create']);
$router->add('GET', '/api/boards/{id}', [BoardController::class, 'show']);
$router->add('DELETE', '/api/boards/{id}', [BoardController::class, 'delete']);
$router->add('POST', '/api/boards/{id}/columns', [BoardController::class, 'addColumn']);
$router->add('PUT', '/api/columns/{id}', [BoardController::class, 'updateColumn']);
$router->add('DELETE', '/api/columns/{id}', [BoardController::class, 'deleteColumn']);

// Tareas
$router->add('POST', '/api/columns/{id}/tasks', [TaskController::class, 'create']);
$router->add('PUT', '/api/tasks/{id}', [TaskController::class, 'update']);
$router->add('PATCH', '/api/tasks/{id}/move', [TaskController::class, 'move']);
$router->add('DELETE', '/api/tasks/{id}', [TaskController::class, 'delete']);

try {
    $router->dispatch($method, $_SERVER['REQUEST_URI']);
} catch (HttpException $e) {
    Response::json(['error' => $e->getMessage()], $e->status);
} catch (PDOException $e) {
    error_log($e->getMessage());
    Response::json(['error' => 'Error de base de datos'], 500);
} catch (Throwable $e) {
    error_log($e->getMessage());
    Response::json(['error' => 'Error interno del servidor'], 500);
}
