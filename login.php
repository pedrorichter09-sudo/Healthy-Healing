<?php
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.html');
    exit;
}

$email = strtolower(trim($_POST['email'] ?? ''));
$senha = $_POST['senha'] ?? '';

try {
    $q = conectar()->prepare('SELECT id, senha_hash FROM usuarios WHERE email = ?');
    $q->execute([$email]);
    $u = $q->fetch();

    if ($u && password_verify($senha, $u['senha_hash'])) {
        entrar((int)$u['id']);
        header('Location: index.html');
        exit;
    }
    header('Location: login.html?erro=credenciais');
} catch (PDOException $e) {
    header('Location: login.html?erro=banco');
}
