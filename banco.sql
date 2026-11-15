-- Rode este arquivo no phpMyAdmin (aba "SQL") ou importe-o.
CREATE DATABASE IF NOT EXISTS healthy_healing
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE healthy_healing;

CREATE TABLE IF NOT EXISTS usuarios (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome       VARCHAR(80)  NOT NULL,
  sobrenome  VARCHAR(80)  NOT NULL,
  email      VARCHAR(150) NOT NULL UNIQUE,
  cpf        CHAR(11)     NOT NULL UNIQUE,
  telefone   VARCHAR(20)  NOT NULL,
  senha_hash VARCHAR(255) NOT NULL,
  criado_em  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS agendamentos (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id    INT UNSIGNED NOT NULL,
  especialidade VARCHAR(50)  NOT NULL,
  data          DATE         NOT NULL,
  hora          TIME         NOT NULL,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;
