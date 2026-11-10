<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\HttpException;
use App\Request;
use App\Response;

final class TaskController
{
    private const PRIORITIES = ['baja', 'media', 'alta'];

    public static function create(array $params): void
    {
        $column = BoardController::ownedColumn((int) $params['id']);
        $data = Request::body();
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new HttpException(422, 'El título es obligatorio');
        }
        $priority = self::priority($data['priority'] ?? 'media');
        $due = self::date($data['due_date'] ?? null);

        $pdo = Database::connection();
        $pos = $pdo->prepare('SELECT COALESCE(MAX(position) + 1, 0) FROM tasks WHERE column_id = ?');
        $pos->execute([$column['id']]);
        $position = (int) $pos->fetchColumn();

        $pdo->prepare(
            'INSERT INTO tasks (column_id, title, description, priority, due_date, position) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$column['id'], $title, $data['description'] ?? null, $priority, $due, $position]);

        Response::json(['id' => (int) $pdo->lastInsertId(), 'title' => $title, 'position' => $position], 201);
    }

    public static function update(array $params): void
    {
        $task = self::ownedTask((int) $params['id']);
        $data = Request::body();

        $title = array_key_exists('title', $data) ? trim((string) $data['title']) : $task['title'];
        if ($title === '') {
            throw new HttpException(422, 'El título no puede estar vacío');
        }
        $description = array_key_exists('description', $data) ? $data['description'] : $task['description'];
        $priority = array_key_exists('priority', $data) ? self::priority($data['priority']) : $task['priority'];
        $due = array_key_exists('due_date', $data) ? self::date($data['due_date']) : $task['due_date'];

        Database::connection()->prepare(
            'UPDATE tasks SET title = ?, description = ?, priority = ?, due_date = ? WHERE id = ?'
        )->execute([$title, $description, $priority, $due, $task['id']]);

        Response::json(['id' => $task['id'], 'title' => $title, 'description' => $description, 'priority' => $priority, 'due_date' => $due]);
    }

    /** Mueve una tarea a otra columna (del mismo tablero) y/o a otra posición. */
    public static function move(array $params): void
    {
        $task = self::ownedTask((int) $params['id']);
        $data = Request::body();

        $targetColumnId = (int) ($data['column_id'] ?? $task['column_id']);
        $position = max(0, (int) ($data['position'] ?? 0));

        $target = BoardController::ownedColumn($targetColumnId);
        if ((int) $target['board_id'] !== (int) $task['board_id']) {
            throw new HttpException(422, 'No puedes mover tareas a otro tablero');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            // Quita la tarea de su columna actual y cierra el hueco
            $pdo->prepare('UPDATE tasks SET position = position - 1 WHERE column_id = ? AND position > ?')
                ->execute([$task['column_id'], $task['position']]);

            // Abre hueco en la columna destino
            $pdo->prepare('UPDATE tasks SET position = position + 1 WHERE column_id = ? AND position >= ? AND id <> ?')
                ->execute([$targetColumnId, $position, $task['id']]);

            $pdo->prepare('UPDATE tasks SET column_id = ?, position = ? WHERE id = ?')
                ->execute([$targetColumnId, $position, $task['id']]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        Response::json(['id' => $task['id'], 'column_id' => $targetColumnId, 'position' => $position]);
    }

    public static function delete(array $params): void
    {
        $task = self::ownedTask((int) $params['id']);
        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM tasks WHERE id = ?')->execute([$task['id']]);
        $pdo->prepare('UPDATE tasks SET position = position - 1 WHERE column_id = ? AND position > ?')
            ->execute([$task['column_id'], $task['position']]);
        Response::json(['deleted' => true]);
    }

    private static function ownedTask(int $taskId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT t.id, t.column_id, t.title, t.description, t.priority, t.due_date, t.position, c.board_id
             FROM tasks t
             JOIN columns_board c ON c.id = t.column_id
             JOIN boards b ON b.id = c.board_id
             WHERE t.id = ? AND b.user_id = ?'
        );
        $stmt->execute([$taskId, Auth::userId()]);
        $task = $stmt->fetch();
        if (!$task) {
            throw new HttpException(404, 'Tarea no encontrada');
        }
        $task['id'] = (int) $task['id'];
        return $task;
    }

    private static function priority(mixed $value): string
    {
        if (!in_array($value, self::PRIORITIES, true)) {
            throw new HttpException(422, 'La prioridad debe ser: baja, media o alta');
        }
        return $value;
    }

    private static function date(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $d = \DateTime::createFromFormat('Y-m-d', (string) $value);
        if (!$d || $d->format('Y-m-d') !== $value) {
            throw new HttpException(422, 'La fecha debe tener formato YYYY-MM-DD');
        }
        return $value;
    }
}
