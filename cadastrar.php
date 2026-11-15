<?php
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: cadastro.html');
    exit;
}

function voltar(string $erro): never {
    header('Location: cadastro.html?erro=' . $erro);
    exit;
}

function cpf_valido(string $cpf): bool {
    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) return false;
    for ($t = 9; $t < 11; $t++) {
        $soma = 0;
        for ($i = 0; $i < $t; $i++) $soma += $cpf[$i] * (($t + 1) - $i);
        $dv = ((10 * $soma) % 11) % 10;
        if ((int)$cpf[$t] !== $dv) return false;
    }
    return true;
}

$nome      = trim($_POST['nome'] ?? '');
$sobrenome = trim($_POST['sobrenome'] ?? '');
$email     = strtolower(trim($_POST['email'] ?? ''));
$cpf       = preg_replace('/\D/', '', $_POST['cpf'] ?? '');
$telefone  = trim($_POST['telefone'] ?? '');
$senha     = $_POST['senha'] ?? '';
$confirmar = $_POST['confirmarSenha'] ?? '';

if ($nome === '' || $sobrenome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $telefone === '') {
    voltar('banco'); // campos básicos inválidos (o HTML já impede isso normalmente)
}
if ($senha !== $confirmar)   voltar('senhas');
if (strlen($senha) < 6)      voltar('senha_curta');
if (!cpf_valido($cpf))       voltar('cpf');

try {
    $pdo = conectar();

    $q = $pdo->prepare('SELECT 1 FROM usuarios WHERE email = ?');
    $q->execute([$email]);
    if ($q->fetch()) voltar('email_existe');

    $q = $pdo->prepare('SELECT 1 FROM usuarios WHERE cpf = ?');
    $q->execute([$cpf]);
    if ($q->fetch()) voltar('cpf_existe');

    $q = $pdo->prepare(
        'INSERT INTO usuarios (nome, sobrenome, email, cpf, telefone, senha_hash)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $q->execute([$nome, $sobrenome, $email, $cpf, $telefone, password_hash($senha, PASSWORD_DEFAULT)]);

    // Já deixa o usuário logado e volta para o index
    entrar((int)$pdo->lastInsertId());
    header('Location: index.html');
    exit;
} catch (PDOException $e) {
    voltar($e->getCode() === '23000' ? 'email_existe' : 'banco');
}
