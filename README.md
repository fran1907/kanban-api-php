# Kanban API (PHP)

API REST para un gestor de tareas estilo Kanban, escrita en **PHP 8 puro con PDO** y **MySQL**. Incluye registro y login con **JWT**, tableros con columnas y tareas, y movimiento de tareas entre columnas.

Es el backend de un proyecto full-stack; el front (React + TypeScript) irá en un repositorio aparte.

## Tecnologías

- PHP 8.1+ (sin frameworks, router propio)
- MySQL / MariaDB con PDO y consultas preparadas
- Autenticación JWT (HS256) y contraseñas con `password_hash`
- CORS configurable

## Estructura

```
kanban-api-php/
├── database/schema.sql        # Estructura de la base de datos
├── public/index.php           # Punto de entrada y definición de rutas
├── src/
│   ├── Core.php               # Env y conexión PDO
│   ├── Http.php               # Router, Request, Response, errores HTTP
│   ├── Auth.php               # JWT y autenticación Bearer
│   └── Controllers/           # Auth, Board (tableros/columnas), Task
├── .env.example
└── README.md
```

## Puesta en marcha

1. Crea la base de datos:
   ```bash
   mysql -u root -p < database/schema.sql
   ```
2. Copia la configuración y edítala con tus datos:
   ```bash
   cp .env.example .env
   ```
   Cambia `JWT_SECRET` por una clave larga y aleatoria.
3. Arranca el servidor de desarrollo de PHP:
   ```bash
   php -S localhost:8000 -t public
   ```
   La API queda en `http://localhost:8000/api`.

## Endpoints

Todas las rutas, salvo registro y login, requieren la cabecera `Authorization: Bearer <token>`.

| Método | Ruta | Descripción |
|--------|------|-------------|
| POST | `/api/auth/register` | Crear usuario (`name`, `email`, `password`) |
| POST | `/api/auth/login` | Iniciar sesión, devuelve el token |
| GET | `/api/me` | Datos del usuario autenticado |
| GET | `/api/boards` | Listar mis tableros |
| POST | `/api/boards` | Crear tablero (con 3 columnas por defecto) |
| GET | `/api/boards/{id}` | Tablero con sus columnas y tareas |
| DELETE | `/api/boards/{id}` | Borrar tablero |
| POST | `/api/boards/{id}/columns` | Añadir columna |
| PUT | `/api/columns/{id}` | Renombrar columna |
| DELETE | `/api/columns/{id}` | Borrar columna |
| POST | `/api/columns/{id}/tasks` | Crear tarea |
| PUT | `/api/tasks/{id}` | Editar tarea |
| PATCH | `/api/tasks/{id}/move` | Mover tarea (`column_id`, `position`) |
| DELETE | `/api/tasks/{id}` | Borrar tarea |

### Ejemplo rápido

```bash
# Registro
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Ana","email":"ana@example.com","password":"12345678"}'

# Crear un tablero (sustituye TOKEN)
curl -X POST http://localhost:8000/api/boards \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"title":"Mi primer tablero"}'
```

## Decisiones de diseño

- **Seguridad:** consultas preparadas contra inyección SQL, contraseñas con hash y comprobación de propiedad en cada recurso (un usuario solo ve y modifica sus tableros).
- **Posiciones:** las tareas y columnas guardan un campo `position`; al mover una tarea se reordenan las demás dentro de una transacción.
- **Sin dependencias:** el JWT y el router están escritos a mano para entender cómo funcionan. En un proyecto real se podría usar `firebase/php-jwt` y un microframework como Slim.

## Próximos pasos

- [ ] Front en React + TypeScript
- [ ] Reordenar columnas
- [ ] Tests con PHPUnit
- [ ] Docker Compose (PHP + MySQL)

## Licencia

MIT
