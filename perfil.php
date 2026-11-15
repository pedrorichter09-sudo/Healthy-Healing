<?php
require __DIR__ . '/config.php';

if (!esta_logado()) {
    header('Location: login.html');
    exit;
}

try {
    $pdo = conectar();

    $q = $pdo->prepare('SELECT * FROM usuarios WHERE id = ?');
    $q->execute([$_SESSION['usuario_id']]);
    $usuario = $q->fetch();

    if (!$usuario) { // conta apagada: encerra a sessão
        header('Location: logout.php');
        exit;
    }

    $q = $pdo->prepare('SELECT * FROM agendamentos WHERE usuario_id = ? ORDER BY data, hora');
    $q->execute([$usuario['id']]);
    $agendamentos = $q->fetchAll();
} catch (PDOException $e) {
    die('Erro ao conectar ao banco de dados. Verifique se o MySQL do XAMPP está ligado.');
}

// Nomes bonitos das especialidades (ajuste as chaves conforme o seu select.html)
$nomes = [
    'fisioterapia'  => 'Fisioterapia',
    'psicoterapia'  => 'Psicoterapia',
    'psicologia'    => 'Psicologia',
    'fonoaudiologia'=> 'Fonoaudiologia',
    'nutricao'      => 'Nutrição',
];

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$inicial     = mb_strtoupper(mb_substr($usuario['nome'], 0, 1));
$clienteDesde = (new DateTime($usuario['criado_em']))->format('d/m/Y');
$cpf = $usuario['cpf'];
$cpfMascarado = '***.***.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Meu perfil | Healthy Healing</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root { --verde-escuro: #146c43; --verde: #308568; --verde-claro: #bdf3e2; }
    body {
      min-height: 100vh;
      background: linear-gradient(135deg, #308568, #bdf3e2) fixed;
      color: #1f2d27;
      padding: 40px 0;
    }
    .painel {
      background: #fff; border-radius: 1.5rem; padding: 2rem;
      box-shadow: 0 10px 28px rgba(20, 108, 67, .22);
    }
    .avatar {
      width: 84px; height: 84px; border-radius: 50%;
      background: var(--verde-escuro); color: #fff;
      display: flex; align-items: center; justify-content: center;
      font-size: 2.2rem; font-weight: 800;
    }
    h2 { color: var(--verde-escuro); font-weight: 800; font-size: 1.3rem; }
    .btn-verde {
      background: var(--verde-escuro); color: #fff; font-weight: 700;
      border: 0; border-radius: 999px; padding: .6rem 1.5rem;
    }
    .btn-verde:hover { background: var(--verde); color: #fff; }
    dt { color: #5b6b64; font-weight: 600; }
  </style>
</head>
<body>
<div class="container" style="max-width: 800px;">

  <div class="d-flex justify-content-between mb-3">
    <a href="index.html" class="text-white fw-bold text-decoration-none">&larr; Início</a>
    <a href="logout.php" class="text-white fw-bold text-decoration-none">Sair</a>
  </div>

  <div class="painel mb-4">
    <div class="d-flex align-items-center gap-3 mb-4">
      <div class="avatar"><?= h($inicial) ?></div>
      <div>
        <h1 class="h3 fw-bold mb-0"><?= h($usuario['nome']) ?> <?= h($usuario['sobrenome']) ?></h1>
        <small class="text-muted">Cliente desde <?= h($clienteDesde) ?></small>
      </div>
    </div>
    <dl class="row mb-0">
      <dt class="col-sm-3">E-mail</dt><dd class="col-sm-9"><?= h($usuario['email']) ?></dd>
      <dt class="col-sm-3">Telefone</dt><dd class="col-sm-9"><?= h($usuario['telefone']) ?></dd>
      <dt class="col-sm-3">CPF</dt><dd class="col-sm-9 mb-0"><?= h($cpfMascarado) ?></dd>
    </dl>
  </div>

  <div class="painel">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="mb-0">Meus agendamentos</h2>
      <a href="select.html" class="btn btn-verde">Novo agendamento</a>
    </div>

    <?php if ($agendamentos): ?>
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead><tr><th>Atendimento</th><th>Dia</th><th>Horário</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($agendamentos as $a): ?>
          <tr>
            <td><?= h($nomes[$a['especialidade']] ?? ucfirst($a['especialidade'])) ?></td>
            <td><?= h((new DateTime($a['data']))->format('d/m/Y')) ?></td>
            <td><?= h(substr($a['hora'], 0, 5)) ?></td>
            <td class="text-end">
              <form action="cancelar.php" method="POST"
                    onsubmit="return confirm('Cancelar este agendamento?')">
                <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                <button class="btn btn-outline-danger btn-sm rounded-pill">Cancelar</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <p class="text-muted mb-0">Você ainda não tem agendamentos. Clique em "Novo agendamento" para marcar o seu.</p>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
