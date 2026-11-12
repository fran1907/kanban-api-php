# Kanban API (PHP)

API REST para un gestor de tareas estilo Kanban, hecha con **PHP y MySQL** (PDO). Permite registrarse, iniciar sesión, y gestionar tableros, columnas y tareas. Cada usuario solo ve sus propios datos.

Es el backend de [kanban-web](https://github.com/fran1907/kanban-web), el frontend en React + TypeScript.

## Tecnologías

- PHP 8 (sin frameworks)
- MySQL con PDO y consultas preparadas
- Contraseñas cifradas con `password_hash`
- Login con token guardado en la base de datos

## Estructura

```
kanban-api-php/
├── database/schema.sql     # Tablas de la base de datos
├── public/index.php        # Entrada de la API: decide qué función atiende cada ruta
├── src/
│   ├── funciones.php       # Conexión a la BD, responder en JSON, comprobar el usuario
│   ├── auth.php            # Registro y login
│   ├── tableros.php        # Tableros y columnas
│   └── tareas.php          # Tareas
├── config.example.php      # Plantilla de configuración (copiar a config.php)
└── README.md
```

### Cómo funciona

```
Frontend (React)  ->  public/index.php  ->  función de src/  ->  MySQL
                                                |
                                      responde con JSON
```

1. Todas las peticiones llegan a `public/index.php`, que mira el método (GET, POST...) y la ruta.
2. Llama a la función correspondiente (por ejemplo `crearTablero()`).
3. Esa función consulta MySQL con PDO y responde en JSON.

### Base de datos

```
users ──< boards ──< board_columns ──< tasks
```

Un usuario tiene muchos tableros, un tablero muchas columnas y una columna muchas tareas. Al borrar un tablero se borran también sus columnas y tareas (`ON DELETE CASCADE`).

### Seguridad

- Las contraseñas se guardan cifradas, nunca en texto plano.
- Al iniciar sesión se genera un token aleatorio que se guarda en la tabla `users`. El frontend lo envía en cada petición (`Authorization: Bearer <token>`) y la API busca a qué usuario pertenece.
- Todas las consultas usan parámetros (`?`), lo que evita la inyección SQL.
- Antes de tocar un tablero, columna o tarea se comprueba que pertenezca al usuario.

## Puesta en marcha

1. Arranca MySQL (por ejemplo con XAMPP) e importa `database/schema.sql` (en phpMyAdmin: pestaña *Importar*).
2. Copia la configuración y ajusta los datos si hace falta:
   ```bash
   copy config.example.php config.php
   ```
3. Arranca la API:
   ```bash
   php -S localhost:8000 -t public
   ```
   Queda en `http://localhost:8000/api`.

## Cómo arrancarlo cada día

1. XAMPP: *Start* en MySQL.
2. En la carpeta del proyecto: `php -S localhost:8000 -t public` (deja la terminal abierta).
3. Arrancar el frontend ([kanban-web](https://github.com/fran1907/kanban-web)) con `npm run dev`.

## Endpoints

Todas las rutas, salvo registro y login, necesitan la cabecera `Authorization: Bearer <token>`.

| Método | Ruta | Descripción |
|--------|------|-------------|
| POST | `/api/auth/register` | Crear usuario (`name`, `email`, `password`) |
| POST | `/api/auth/login` | Iniciar sesión (devuelve el token) |
| GET | `/api/boards` | Listar mis tableros |
| POST | `/api/boards` | Crear tablero (con 3 columnas por defecto) |
| GET | `/api/boards/{id}` | Ver tablero con sus columnas y tareas |
| DELETE | `/api/boards/{id}` | Borrar tablero |
| POST | `/api/boards/{id}/columns` | Añadir columna |
| DELETE | `/api/columns/{id}` | Borrar columna |
| POST | `/api/columns/{id}/tasks` | Crear tarea |
| PUT | `/api/tasks/{id}` | Editar tarea |
| PATCH | `/api/tasks/{id}/move` | Mover tarea a otra columna (`column_id`) |
| DELETE | `/api/tasks/{id}` | Borrar tarea |

### Ejemplo (PowerShell)

```powershell
Invoke-RestMethod -Method Post -Uri http://localhost:8000/api/auth/register -ContentType "application/json" -Body '{"name":"Ana","email":"ana@example.com","password":"12345678"}'
```

## Próximos pasos

- [ ] Reordenar tareas dentro de una columna
- [ ] Renombrar columnas
- [ ] Caducidad del token
- [ ] Desplegar en un hosting con PHP

## Licencia

MIT
