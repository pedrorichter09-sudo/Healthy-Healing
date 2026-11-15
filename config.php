<?php
// ===== Conexão com o banco (XAMPP padrão) =====
const DB_HOST = 'localhost';
const DB_NOME = 'healthy_healing';
const DB_USER = 'root';
const DB_PASS = '';

function conectar(): PDO {
    return new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NOME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
}

// ===== Sessão que dura 30 dias: o usuário fica "salvo" no navegador =====
$dias = 30 * 24 * 60 * 60;
ini_set('session.gc_maxlifetime', (string)$dias);
session_set_cookie_params([
    'lifetime' => $dias,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

function esta_logado(): bool {
    return !empty($_SESSION['usuario_id']);
}

function entrar(int $id): void {
    session_regenerate_id(true);
    $_SESSION['usuario_id'] = $id;
}
