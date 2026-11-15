const express = require("express");
const Database = require("better-sqlite3");
const crypto = require("crypto");
const path = require("path");

const PORT = process.env.PORT || 3000;
const ADMIN_TOKEN = process.env.ADMIN_TOKEN || ""; // defina para liberar a área da clínica

const PLANOS = {
  plus: "Healthy Healing Plus"
};

/* ---------- Banco de dados ---------- */
const db = new Database(path.join(__dirname, "cadastros.db"));
db.pragma("journal_mode = WAL");
db.exec(`
  CREATE TABLE IF NOT EXISTS cadastros (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    numero TEXT NOT NULL UNIQUE,
    titular TEXT NOT NULL,
    cpf_titular TEXT NOT NULL UNIQUE,
    dependente TEXT,
    cpf_dependente TEXT,
    plano TEXT NOT NULL,
    criado_em TEXT NOT NULL DEFAULT (datetime('now'))
  )
`);

const stmt = {
  porCpf: db.prepare("SELECT * FROM cadastros WHERE cpf_titular = ?"),
  porNumero: db.prepare("SELECT * FROM cadastros WHERE numero = ?"),
  listar: db.prepare("SELECT * FROM cadastros ORDER BY id DESC"),
  inserir: db.prepare(`
    INSERT INTO cadastros (numero, titular, cpf_titular, dependente, cpf_dependente, plano)
    VALUES (@numero, @titular, @cpf_titular, @dependente, @cpf_dependente, @plano)
  `),
  excluir: db.prepare("DELETE FROM cadastros WHERE numero = ?")
};

/* ---------- Utilidades ---------- */
function soDigitos(v) {
  return String(v || "").replace(/\D/g, "");
}

function cpfValido(cpf) {
  cpf = soDigitos(cpf);
  if (cpf.length !== 11 || /^(\d)\1+$/.test(cpf)) return false;
  for (let t = 9; t < 11; t++) {
    let soma = 0;
    for (let i = 0; i < t; i++) soma += Number(cpf[i]) * (t + 1 - i);
    const dig = ((soma * 10) % 11) % 10;
    if (dig !== Number(cpf[t])) return false;
  }
  return true;
}

function gerarNumero() {
  for (let i = 0; i < 10; i++) {
    const n = String(crypto.randomInt(100000000000, 1000000000000));
    if (!stmt.porNumero.get(n)) return n;
  }
  throw new Error("Não foi possível gerar o número da carteirinha.");
}

function paraResposta(c) {
  return {
    numero: c.numero,
    titular: c.titular,
    cpfTitular: c.cpf_titular,
    dependente: c.dependente || "",
    cpfDependente: c.cpf_dependente || "",
    plano: c.plano,
    criadoEm: c.criado_em
  };
}

function somenteAdmin(req, res, next) {
  if (!ADMIN_TOKEN) {
    return res.status(503).json({ erro: "Área da clínica desativada (ADMIN_TOKEN não definido)." });
  }
  const enviado = Buffer.from(String(req.get("x-admin-token") || ""));
  const esperado = Buffer.from(ADMIN_TOKEN);
  const ok = enviado.length === esperado.length && crypto.timingSafeEqual(enviado, esperado);
  if (!ok) return res.status(401).json({ erro: "Token inválido." });
  next();
}

/* ---------- App ---------- */
const app = express();
app.use(express.json({ limit: "10kb" }));
app.use(express.static(path.join(__dirname, "public")));

// Criar cadastro (público)
app.post("/api/cadastros", (req, res) => {
  const titular = String(req.body.titular || "").trim().slice(0, 150);
  const cpfTitular = soDigitos(req.body.cpfTitular);
  const dependente = String(req.body.dependente || "").trim().slice(0, 150);
  const cpfDependente = soDigitos(req.body.cpfDependente);
  const plano = PLANOS[req.body.plano];

  if (!titular) return res.status(400).json({ erro: "Informe o nome completo do titular." });
  if (!cpfValido(cpfTitular)) return res.status(400).json({ erro: "CPF do titular inválido." });
  if (dependente && !cpfValido(cpfDependente)) return res.status(400).json({ erro: "CPF do dependente inválido." });
  if (!plano) return res.status(400).json({ erro: "Plano inválido." });

  // CPF já cadastrado: devolve a carteirinha existente sem sobrescrever
  const existente = stmt.porCpf.get(cpfTitular);
  if (existente) {
    return res.json({ ...paraResposta(existente), jaExistia: true });
  }

  stmt.inserir.run({
    numero: gerarNumero(),
    titular,
    cpf_titular: cpfTitular,
    dependente: dependente || null,
    cpf_dependente: dependente ? cpfDependente : null,
    plano
  });

  const novo = stmt.porCpf.get(cpfTitular);
  res.status(201).json({ ...paraResposta(novo), jaExistia: false });
});

// Consultar carteirinha pelo CPF do titular (público)
app.get("/api/carteirinha/:cpf", (req, res) => {
  const c = stmt.porCpf.get(soDigitos(req.params.cpf));
  if (!c) return res.status(404).json({ erro: "Nenhum cadastro encontrado para este CPF." });
  res.json(paraResposta(c));
});

// Listar todos (clínica)
app.get("/api/cadastros", somenteAdmin, (req, res) => {
  res.json(stmt.listar.all().map(paraResposta));
});

// Excluir (clínica)
app.delete("/api/cadastros/:numero", somenteAdmin, (req, res) => {
  const r = stmt.excluir.run(req.params.numero);
  if (r.changes === 0) return res.status(404).json({ erro: "Cadastro não encontrado." });
  res.json({ ok: true });
});

// Erro genérico
app.use((err, req, res, next) => {
  console.error(err);
  res.status(500).json({ erro: "Erro interno no servidor." });
});

app.listen(PORT, () => {
  console.log(`Servidor rodando em http://localhost:${PORT}`);
  if (!ADMIN_TOKEN) console.log("Aviso: ADMIN_TOKEN não definido; a área da clínica está desativada.");
});