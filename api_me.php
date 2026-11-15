<?php
// Usado pelo index.html para saber se há alguém logado.
require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$resposta = ['logado' => false, 'nome' => ''];

if (esta_logado()) {
    try {
        $q = conectar()->prepare('SELECT nome FROM usuarios WHERE id = ?');
        $q->execute([$_SESSION['usuario_id']]);
        if ($u = $q->fetch()) {
            $resposta = ['logado' => true, 'nome' => $u['nome']];
        } else {
            $_SESSION = []; // usuário não existe mais
        }
    } catch (PDOException $e) {
        // mantém como visitante
    }
}
echo json_encode($resposta, JSON_UNESCAPED_UNICODE);
