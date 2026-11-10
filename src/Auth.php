<?php
declare(strict_types=1);

namespace App;

/**
 * JWT HS256 mínimo, escrito a mano para entender cómo funciona.
 * En producción puedes sustituirlo por firebase/php-jwt.
 */
final class Jwt
{
    private static function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function unb64(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/')) ?: '';
    }

    public static function encode(array $payload): string
    {
        $header = self::b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $body = self::b64(json_encode($payload));
        $sig = self::b64(hash_hmac('sha256', "$header.$body", self::secret(), true));
        return "$header.$body.$sig";
    }

    public static function decode(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new HttpException(401, 'Token inválido');
        }
        [$header, $body, $sig] = $parts;
        $expected = self::b64(hash_hmac('sha256', "$header.$body", self::secret(), true));
        if (!hash_equals($expected, $sig)) {
            throw new HttpException(401, 'Firma de token inválida');
        }
        $payload = json_decode(self::unb64($body), true);
        if (!is_array($payload) || ($payload['exp'] ?? 0) < time()) {
            throw new HttpException(401, 'Token caducado');
        }
        return $payload;
    }

    private static function secret(): string
    {
        $secret = Env::get('JWT_SECRET');
        if (!$secret) {
            throw new HttpException(500, 'JWT_SECRET no configurado');
        }
        return $secret;
    }
}

final class Auth
{
    /** Devuelve el id del usuario autenticado o lanza 401. */
    public static function userId(): int
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            throw new HttpException(401, 'Falta el token Bearer');
        }
        $payload = Jwt::decode($m[1]);
        return (int) $payload['sub'];
    }
}
