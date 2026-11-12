<?php
// Funciones auxiliares que usan todos los demás archivos.

// Conecta con MySQL usando PDO. La conexión se crea una sola vez.
function conectar() {
    static $pdo = null;

    if ($pdo === null) {
        $config = require __DIR__ . '/../config.php';
        $dsn = 'mysql:host=' . $config['host'] . ';dbname=' . $config['base_datos'] . ';charset=utf8mb4';
        $pdo = new PDO($dsn, $config['usuario'], $config['clave']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);       // lanzar excepciones si falla una consulta
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);  // filas como arrays asociativos
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);               // devuelve los números como números
    }

    return $pdo;
}

// Envía una respuesta en formato JSON y termina el script.
function responder($datos, $codigo = 200) {
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

// Envía un error en JSON, por ejemplo: {"error": "Tablero no encontrado"}
function responderError($mensaje, $codigo) {
    responder(['error' => $mensaje], $codigo);
}

// Lee el JSON que envía el frontend en el cuerpo de la petición.
function leerJson() {
    $texto = file_get_contents('php://input');
    $datos = json_decode($texto, true);

    if (!is_array($datos)) {
        return [];
    }
    return $datos;
}

// Devuelve el usuario que ha iniciado sesión, mirando el token de la cabecera.
// El frontend envía:  Authorization: Bearer <token>
function usuarioActual() {
    $cabecera = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = trim(str_replace('Bearer', '', $cabecera));

    if ($token == '') {
        responderError('Falta el token, inicia sesión', 401);
    }

    $stmt = conectar()->prepare('SELECT id, name, email FROM users WHERE token = ?');
    $stmt->execute([$token]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        responderError('Sesión no válida, inicia sesión otra vez', 401);
    }
    return $usuario;
}
