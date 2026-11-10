<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\HttpException;
use App\Request;
use App\Response;
use PDO;

final class BoardController
{
    public static function index(array $params): void
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, title, created_at FROM boards WHERE user_id = ? ORDER BY created_at DESC'
        );
        $stmt->execute([Auth::userId()]);
        Response::json($stmt->fetchAll());
    }

    public static function create(array $params): void
    {
        $userId = Auth::userId();
        $title = trim((string) (Request::body()['title'] ?? ''));
        if ($title === '') {
            throw new HttpException(422, 'El título es obligatorio');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('INSERT INTO boards (user_id, title) VALUES (?, ?)')->execute([$userId, $title]);
            $boardId = (int) $pdo->lastInsertId();

            $insert = $pdo->prepare('INSERT INTO columns_board (board_id, title, position) VALUES (?, ?, ?)');
            foreach (['Por hacer', 'En progreso', 'Hecho'] as $i => $col) {
                $insert->execute([$boardId, $col, $i]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        Response::json(['id' => $boardId, 'title' => $title], 201);
    }

    public static function show(array $params): void
    {
        $board = self::ownedBoard((int) $params['id']);
        $pdo = Database::connection();

        $cols = $pdo->prepare('SELECT id, title, position FROM columns_board WHERE board_id = ? ORDER BY position');
        $cols->execute([$board['id']]);
        $columns = $cols->fetchAll();

        $tasksStmt = $pdo->prepare(
            'SELECT t.id, t.column_id, t.title, t.description, t.priority, t.due_date, t.position
             FROM tasks t JOIN columns_board c ON c.id = t.column_id
             WHERE c.board_id = ? ORDER BY t.position'
        );
        $tasksStmt->execute([$board['id']]);

        $byColumn = [];
        foreach ($tasksStmt->fetchAll() as $task) {
            $byColumn[$task['column_id']][] = $task;
        }
        foreach ($columns as &$column) {
            $column['tasks'] = $byColumn[$column['id']] ?? [];
        }

        $board['columns'] = $columns;
        Response::json($board);
    }

    public static function delete(array $params): void
    {
        $board = self::ownedBoard((int) $params['id']);
        Database::connection()->prepare('DELETE FROM boards WHERE id = ?')->execute([$board['id']]);
        Response::json(['deleted' => true]);
    }

    public static function addColumn(array $params): void
    {
        $board = self::ownedBoard((int) $params['id']);
        $title = trim((string) (Request::body()['title'] ?? ''));
        if ($title === '') {
            throw new HttpException(422, 'El título es obligatorio');
        }

        $pdo = Database::connection();
        $pos = $pdo->prepare('SELECT COALESCE(MAX(position) + 1, 0) FROM columns_board WHERE board_id = ?');
        $pos->execute([$board['id']]);
        $position = (int) $pos->fetchColumn();

        $pdo->prepare('INSERT INTO columns_board (board_id, title, position) VALUES (?, ?, ?)')
            ->execute([$board['id'], $title, $position]);

        Response::json(['id' => (int) $pdo->lastInsertId(), 'title' => $title, 'position' => $position], 201);
    }

    public static function updateColumn(array $params): void
    {
        $column = self::ownedColumn((int) $params['id']);
        $title = trim((string) (Request::body()['title'] ?? ''));
        if ($title === '') {
            throw new HttpException(422, 'El título es obligatorio');
        }
        Database::connection()->prepare('UPDATE columns_board SET title = ? WHERE id = ?')
            ->execute([$title, $column['id']]);
        Response::json(['id' => $column['id'], 'title' => $title]);
    }

    public static function deleteColumn(array $params): void
    {
        $column = self::ownedColumn((int) $params['id']);
        Database::connection()->prepare('DELETE FROM columns_board WHERE id = ?')->execute([$column['id']]);
        Response::json(['deleted' => true]);
    }

    /** Comprueba que el tablero existe y pertenece al usuario autenticado. */
    public static function ownedBoard(int $boardId): array
    {
        $stmt = Database::connection()->prepare('SELECT id, title, created_at FROM boards WHERE id = ? AND user_id = ?');
        $stmt->execute([$boardId, Auth::userId()]);
        $board = $stmt->fetch();
        if (!$board) {
            throw new HttpException(404, 'Tablero no encontrado');
        }
        return $board;
    }

    /** Comprueba que la columna existe y su tablero es del usuario autenticado. */
    public static function ownedColumn(int $columnId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT c.id, c.board_id, c.title FROM columns_board c
             JOIN boards b ON b.id = c.board_id
             WHERE c.id = ? AND b.user_id = ?'
        );
        $stmt->execute([$columnId, Auth::userId()]);
        $column = $stmt->fetch();
        if (!$column) {
            throw new HttpException(404, 'Columna no encontrada');
        }
        return $column;
    }
}
