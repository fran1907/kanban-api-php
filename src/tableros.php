<?php
// Tableros y columnas.

// Busca un tablero y comprueba que sea del usuario. Si no, responde 404.
function buscarTablero($usuario, $tableroId) {
    $stmt = conectar()->prepare('SELECT id, title FROM boards WHERE id = ? AND user_id = ?');
    $stmt->execute([$tableroId, $usuario['id']]);
    $tablero = $stmt->fetch();

    if (!$tablero) {
        responderError('Tablero no encontrado', 404);
    }
    return $tablero;
}

// Busca una columna y comprueba que su tablero sea del usuario.
function buscarColumna($usuario, $columnaId) {
    $sql = 'SELECT c.id, c.board_id, c.title
            FROM board_columns c
            JOIN boards b ON b.id = c.board_id
            WHERE c.id = ? AND b.user_id = ?';
    $stmt = conectar()->prepare($sql);
    $stmt->execute([$columnaId, $usuario['id']]);
    $columna = $stmt->fetch();

    if (!$columna) {
        responderError('Columna no encontrada', 404);
    }
    return $columna;
}

// GET /api/boards  -> lista de tableros del usuario
function listarTableros($usuario) {
    $stmt = conectar()->prepare('SELECT id, title FROM boards WHERE user_id = ? ORDER BY id DESC');
    $stmt->execute([$usuario['id']]);
    responder($stmt->fetchAll());
}

// POST /api/boards  -> crea un tablero con 3 columnas por defecto
function crearTablero($usuario) {
    $datos = leerJson();
    $titulo = trim($datos['title'] ?? '');

    if ($titulo == '') {
        responderError('El título es obligatorio', 422);
    }

    $pdo = conectar();
    $stmt = $pdo->prepare('INSERT INTO boards (user_id, title) VALUES (?, ?)');
    $stmt->execute([$usuario['id'], $titulo]);
    $tableroId = (int) $pdo->lastInsertId();

    $columnasPorDefecto = ['Por hacer', 'En progreso', 'Hecho'];
    foreach ($columnasPorDefecto as $nombre) {
        $stmt = $pdo->prepare('INSERT INTO board_columns (board_id, title) VALUES (?, ?)');
        $stmt->execute([$tableroId, $nombre]);
    }

    responder(['id' => $tableroId, 'title' => $titulo], 201);
}

// GET /api/boards/{id}  -> el tablero con sus columnas y las tareas de cada columna
function verTablero($usuario, $tableroId) {
    $tablero = buscarTablero($usuario, $tableroId);
    $pdo = conectar();

    $stmt = $pdo->prepare('SELECT id, title FROM board_columns WHERE board_id = ? ORDER BY id');
    $stmt->execute([$tablero['id']]);
    $columnas = $stmt->fetchAll();

    // Para cada columna cargamos sus tareas
    foreach ($columnas as $i => $columna) {
        $stmt = $pdo->prepare('SELECT id, column_id, title, description, priority FROM tasks WHERE column_id = ? ORDER BY id');
        $stmt->execute([$columna['id']]);
        $columnas[$i]['tasks'] = $stmt->fetchAll();
    }

    $tablero['columns'] = $columnas;
    responder($tablero);
}

// DELETE /api/boards/{id}  (se borran también sus columnas y tareas por el ON DELETE CASCADE)
function borrarTablero($usuario, $tableroId) {
    $tablero = buscarTablero($usuario, $tableroId);

    $stmt = conectar()->prepare('DELETE FROM boards WHERE id = ?');
    $stmt->execute([$tablero['id']]);
    responder(['deleted' => true]);
}

// POST /api/boards/{id}/columns  -> añade una columna
function crearColumna($usuario, $tableroId) {
    $tablero = buscarTablero($usuario, $tableroId);
    $datos = leerJson();
    $titulo = trim($datos['title'] ?? '');

    if ($titulo == '') {
        responderError('El título es obligatorio', 422);
    }

    $pdo = conectar();
    $stmt = $pdo->prepare('INSERT INTO board_columns (board_id, title) VALUES (?, ?)');
    $stmt->execute([$tablero['id'], $titulo]);

    responder(['id' => (int) $pdo->lastInsertId(), 'title' => $titulo], 201);
}

// DELETE /api/columns/{id}
function borrarColumna($usuario, $columnaId) {
    $columna = buscarColumna($usuario, $columnaId);

    $stmt = conectar()->prepare('DELETE FROM board_columns WHERE id = ?');
    $stmt->execute([$columna['id']]);
    responder(['deleted' => true]);
}
