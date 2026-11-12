<?php
// Punto de entrada de la API: todas las peticiones llegan aquí.
// Aquí se decide qué función atiende cada ruta.

require __DIR__ . '/../src/funciones.php';
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/tableros.php';
require __DIR__ . '/../src/tareas.php';

// CORS: permite que el frontend (puerto 5173) llame a esta API (puerto 8000).
// Si algún día cambias la dirección del frontend, cámbiala aquí.
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');

$metodo = $_SERVER['REQUEST_METHOD'];

// El navegador envía antes una petición OPTIONS para preguntar si puede llamar; respondemos que sí.
if ($metodo == 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Dividimos la ruta. Ejemplo: /api/boards/3/columns  ->  ['api', 'boards', '3', 'columns']
$ruta = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$partes = explode('/', $ruta);

$recurso = $partes[1] ?? '';   // auth, boards, columns o tasks
$id = $partes[2] ?? '';        // el id (o "register"/"login" en auth)
$accion = $partes[3] ?? '';    // columns, tasks o move

try {
    if ($partes[0] != 'api') {
        responderError('Ruta no encontrada', 404);
    }

    // ---- Registro y login (no necesitan estar identificado) ----
    if ($recurso == 'auth' && $id == 'register' && $metodo == 'POST') {
        registrar();
    } elseif ($recurso == 'auth' && $id == 'login' && $metodo == 'POST') {
        login();
    }

    // ---- Tableros ----
    elseif ($recurso == 'boards') {
        $usuario = usuarioActual();

        if ($id == '' && $metodo == 'GET') {
            listarTableros($usuario);
        } elseif ($id == '' && $metodo == 'POST') {
            crearTablero($usuario);
        } elseif ($accion == '' && $metodo == 'GET') {
            verTablero($usuario, (int) $id);
        } elseif ($accion == '' && $metodo == 'DELETE') {
            borrarTablero($usuario, (int) $id);
        } elseif ($accion == 'columns' && $metodo == 'POST') {
            crearColumna($usuario, (int) $id);
        }
    }

    // ---- Columnas ----
    elseif ($recurso == 'columns') {
        $usuario = usuarioActual();

        if ($accion == '' && $metodo == 'DELETE') {
            borrarColumna($usuario, (int) $id);
        } elseif ($accion == 'tasks' && $metodo == 'POST') {
            crearTarea($usuario, (int) $id);
        }
    }

    // ---- Tareas ----
    elseif ($recurso == 'tasks') {
        $usuario = usuarioActual();

        if ($accion == '' && $metodo == 'PUT') {
            editarTarea($usuario, (int) $id);
        } elseif ($accion == 'move' && $metodo == 'PATCH') {
            moverTarea($usuario, (int) $id);
        } elseif ($accion == '' && $metodo == 'DELETE') {
            borrarTarea($usuario, (int) $id);
        }
    }

    // Si ninguna ruta ha respondido antes, es que no existe
    responderError('Ruta no encontrada', 404);

} catch (PDOException $e) {
    error_log($e->getMessage());
    responderError('Error de base de datos', 500);
}
