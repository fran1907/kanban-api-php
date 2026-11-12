<?php
// Registro e inicio de sesión.

function registrar() {
    $datos = leerJson();
    $nombre = trim($datos['name'] ?? '');
    $email = strtolower(trim($datos['email'] ?? ''));
    $clave = $datos['password'] ?? '';

    // Validaciones
    if ($nombre == '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($clave) < 8) {
        responderError('Nombre, email válido y contraseña de al menos 8 caracteres son obligatorios', 422);
    }

    $pdo = conectar();

    // ¿Ya existe ese email?
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        responderError('Ya existe un usuario con ese email', 409);
    }

    // Guardamos la contraseña cifrada (nunca en texto plano) y un token de sesión
    $token = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, token) VALUES (?, ?, ?, ?)');
    $stmt->execute([$nombre, $email, password_hash($clave, PASSWORD_DEFAULT), $token]);

    responder([
        'token' => $token,
        'user' => ['id' => (int) $pdo->lastInsertId(), 'name' => $nombre, 'email' => $email],
    ], 201);
}

function login() {
    $datos = leerJson();
    $email = strtolower(trim($datos['email'] ?? ''));
    $clave = $datos['password'] ?? '';

    $pdo = conectar();
    $stmt = $pdo->prepare('SELECT id, name, email, password_hash FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();

    // password_verify compara la contraseña escrita con la cifrada de la base de datos
    if (!$usuario || !password_verify($clave, $usuario['password_hash'])) {
        responderError('Email o contraseña incorrectos', 401);
    }

    // Cada vez que se inicia sesión se genera un token nuevo
    $token = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare('UPDATE users SET token = ? WHERE id = ?');
    $stmt->execute([$token, $usuario['id']]);

    responder([
        'token' => $token,
        'user' => ['id' => $usuario['id'], 'name' => $usuario['name'], 'email' => $usuario['email']],
    ]);
}
