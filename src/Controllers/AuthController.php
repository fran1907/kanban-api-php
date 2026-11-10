<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\HttpException;
use App\Jwt;
use App\Request;
use App\Response;

final class AuthController
{
    public static function register(array $params): void
    {
        $data = Request::body();
        $name = trim((string) ($data['name'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            throw new HttpException(422, 'Nombre, email válido y contraseña de al menos 8 caracteres son obligatorios');
        }

        $pdo = Database::connection();
        $exists = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $exists->execute([$email]);
        if ($exists->fetch()) {
            throw new HttpException(409, 'Ya existe un usuario con ese email');
        }

        $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
        $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
        $id = (int) $pdo->lastInsertId();

        Response::json([
            'token' => self::token($id),
            'user' => ['id' => $id, 'name' => $name, 'email' => $email],
        ], 201);
    }

    public static function login(array $params): void
    {
        $data = Request::body();
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');

        $stmt = Database::connection()->prepare('SELECT id, name, email, password_hash FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            throw new HttpException(401, 'Credenciales incorrectas');
        }

        Response::json([
            'token' => self::token((int) $user['id']),
            'user' => ['id' => (int) $user['id'], 'name' => $user['name'], 'email' => $user['email']],
        ]);
    }

    public static function me(array $params): void
    {
        $stmt = Database::connection()->prepare('SELECT id, name, email, created_at FROM users WHERE id = ?');
        $stmt->execute([Auth::userId()]);
        $user = $stmt->fetch();
        if (!$user) {
            throw new HttpException(404, 'Usuario no encontrado');
        }
        Response::json($user);
    }

    private static function token(int $userId): string
    {
        $ttl = (int) (\App\Env::get('JWT_TTL', '86400'));
        return Jwt::encode(['sub' => $userId, 'iat' => time(), 'exp' => time() + $ttl]);
    }
}
