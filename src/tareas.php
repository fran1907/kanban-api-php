<?php
// Tareas.
// Usa buscarColumna() de tableros.php, que ya comprueba que la columna sea del usuario.

$PRIORIDADES = ['baja', 'media', 'alta'];

// Busca una tarea y comprueba que sea del usuario (a través de su columna y su tablero).
function buscarTarea($usuario, $tareaId) {
    $sql = 'SELECT t.id, t.column_id, t.title, t.description, t.priority, c.board_id
            FROM tasks t
            JOIN board_columns c ON c.id = t.column_id
            JOIN boards b ON b.id = c.board_id
            WHERE t.id = ? AND b.user_id = ?';
    $stmt = conectar()->prepare($sql);
    $stmt->execute([$tareaId, $usuario['id']]);
    $tarea = $stmt->fetch();

    if (!$tarea) {
        responderError('Tarea no encontrada', 404);
    }
    return $tarea;
}

// POST /api/columns/{id}/tasks  -> crea una tarea en la columna
function crearTarea($usuario, $columnaId) {
    global $PRIORIDADES;

    $columna = buscarColumna($usuario, $columnaId);
    $datos = leerJson();
    $titulo = trim($datos['title'] ?? '');
    $descripcion = $datos['description'] ?? null;
    $prioridad = $datos['priority'] ?? 'media';

    if ($titulo == '') {
        responderError('El título es obligatorio', 422);
    }
    if (!in_array($prioridad, $PRIORIDADES)) {
        responderError('La prioridad debe ser baja, media o alta', 422);
    }

    $pdo = conectar();
    $stmt = $pdo->prepare('INSERT INTO tasks (column_id, title, description, priority) VALUES (?, ?, ?, ?)');
    $stmt->execute([$columna['id'], $titulo, $descripcion, $prioridad]);

    responder(['id' => (int) $pdo->lastInsertId(), 'title' => $titulo], 201);
}

// PUT /api/tasks/{id}  -> edita título, descripción o prioridad (solo lo que se envíe)
function editarTarea($usuario, $tareaId) {
    global $PRIORIDADES;

    $tarea = buscarTarea($usuario, $tareaId);
    $datos = leerJson();

    $titulo = trim($datos['title'] ?? $tarea['title']);
    $descripcion = $datos['description'] ?? $tarea['description'];
    $prioridad = $datos['priority'] ?? $tarea['priority'];

    if ($titulo == '') {
        responderError('El título no puede estar vacío', 422);
    }
    if (!in_array($prioridad, $PRIORIDADES)) {
        responderError('La prioridad debe ser baja, media o alta', 422);
    }

    $stmt = conectar()->prepare('UPDATE tasks SET title = ?, description = ?, priority = ? WHERE id = ?');
    $stmt->execute([$titulo, $descripcion, $prioridad, $tarea['id']]);

    responder(['id' => $tarea['id'], 'title' => $titulo, 'description' => $descripcion, 'priority' => $prioridad]);
}

// PATCH /api/tasks/{id}/move  -> mueve la tarea a otra columna del mismo tablero
function moverTarea($usuario, $tareaId) {
    $tarea = buscarTarea($usuario, $tareaId);
    $datos = leerJson();
    $columnaDestino = buscarColumna($usuario, (int) ($datos['column_id'] ?? 0));

    if ($columnaDestino['board_id'] != $tarea['board_id']) {
        responderError('No puedes mover una tarea a otro tablero', 422);
    }

    $stmt = conectar()->prepare('UPDATE tasks SET column_id = ? WHERE id = ?');
    $stmt->execute([$columnaDestino['id'], $tarea['id']]);

    responder(['id' => $tarea['id'], 'column_id' => $columnaDestino['id']]);
}

// DELETE /api/tasks/{id}
function borrarTarea($usuario, $tareaId) {
    $tarea = buscarTarea($usuario, $tareaId);

    $stmt = conectar()->prepare('DELETE FROM tasks WHERE id = ?');
    $stmt->execute([$tarea['id']]);
    responder(['deleted' => true]);
}
