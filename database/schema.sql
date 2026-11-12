-- Base de datos del gestor Kanban
CREATE DATABASE IF NOT EXISTS kanban CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE kanban;

-- Un usuario tiene muchos tableros
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  token VARCHAR(64) NULL            -- se genera al iniciar sesión
);

-- Un tablero tiene muchas columnas
CREATE TABLE IF NOT EXISTS boards (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(150) NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Una columna tiene muchas tareas
CREATE TABLE IF NOT EXISTS board_columns (
  id INT AUTO_INCREMENT PRIMARY KEY,
  board_id INT NOT NULL,
  title VARCHAR(100) NOT NULL,
  FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tasks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  column_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  description TEXT NULL,
  priority VARCHAR(10) NOT NULL DEFAULT 'media',   -- baja, media o alta
  FOREIGN KEY (column_id) REFERENCES board_columns(id) ON DELETE CASCADE
);
