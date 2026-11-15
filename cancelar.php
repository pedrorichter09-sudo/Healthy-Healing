<?php
require __DIR__ . '/config.php';

if (!esta_logado() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.html');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
try {
    // O "AND usuario_id" garante que ninguém cancele agendamento de outra pessoa
    $q = conectar()->prepare('DELETE FROM agendamentos WHERE id = ? AND usuario_id = ?');
    $q->execute([$id, $_SESSION['usuario_id']]);
} catch (PDOException $e) {
}
header('Location: perfil.php');
