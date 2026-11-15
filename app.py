from datetime import date, datetime

import mysql.connector
from flask import Flask, abort, jsonify, redirect, render_template, request, session, url_for
from jinja2 import TemplateNotFound
from werkzeug.security import check_password_hash, generate_password_hash

# static_url_path="" deixa os arquivos da pasta static/ na raiz do site
# (ex.: static/cards/foto.png -> /cards/foto.png), então seus caminhos não mudam.
app = Flask(__name__, static_url_path="")
app.secret_key = "troque-esta-chave-por-algo-secreto"

ESPECIALIDADES = ["psicoterapia", "fisioterapia", "fonoaudiologia", "nutricao"]
HORARIOS = [f"{h:02d}:00" for h in range(8, 21)]  # 08:00 às 20:00


def conectar_banco():
    return mysql.connector.connect(
        host="localhost", user="root", password="", database="healthy_healing"
    )


# ---------- PÁGINAS ----------
@app.route("/")
def inicio():
    return render_template("index.html")


@app.route("/cadastro")
def cadastro():
    return render_template("cadastro.html")


@app.route("/agendar.html")
def agendar():
    if "usuario_id" not in session:
        return redirect(url_for("login"))
    return render_template("agendar.html")


# Qualquer outra página (index.html, select.html, plano-saude.html...) da pasta templates/
@app.route("/<pagina>.html")
def pagina(pagina):
    try:
        return render_template(f"{pagina}.html")
    except TemplateNotFound:
        abort(404)


# ---------- CADASTRO ----------
@app.route("/cadastrar", methods=["POST"])
def cadastrar():
    f = request.form
    nome = f.get("nome", "").strip()
    sobrenome = f.get("sobrenome", "").strip()
    email = f.get("email", "").strip().lower()
    cpf = "".join(c for c in f.get("cpf", "") if c.isdigit())
    telefone = f.get("telefone", "").strip()
    senha = f.get("senha", "")

    def erro(codigo):
        return redirect(url_for("cadastro", erro=codigo))

    if senha != f.get("confirmarSenha", ""):
        return erro("senhas")
    if len(senha) < 6:
        return erro("senha_curta")
    if len(cpf) != 11:
        return erro("cpf")

    try:
        conexao = conectar_banco()
        cursor = conexao.cursor()

        cursor.execute("SELECT email FROM usuarios WHERE email = %s OR cpf = %s", (email, cpf))
        existente = cursor.fetchone()
        if existente:
            cursor.close()
            conexao.close()
            return erro("email_existe" if existente[0] == email else "cpf_existe")

        cursor.execute(
            """INSERT INTO usuarios (nome, sobrenome, email, cpf, telefone, senha)
               VALUES (%s, %s, %s, %s, %s, %s)""",
            (nome, sobrenome, email, cpf, telefone, generate_password_hash(senha)),
        )
        conexao.commit()
        novo_id = cursor.lastrowid
        cursor.close()
        conexao.close()
        session["usuario_id"] = novo_id
        session["nome"] = nome
        return redirect(url_for("inicio"))  # após o cadastro, vai para o index

    except mysql.connector.Error as e:
        print("ERRO NO MYSQL:", e)
        return erro("banco")


# ---------- LOGIN ----------
@app.route("/login", methods=["GET", "POST"])
def login():
    if request.method == "GET":
        return render_template("login.html")

    email = request.form.get("email", "").strip().lower()
    senha = request.form.get("senha", "")
    try:
        conexao = conectar_banco()
        cursor = conexao.cursor(dictionary=True)
        cursor.execute("SELECT id, nome, senha FROM usuarios WHERE email = %s", (email,))
        usuario = cursor.fetchone()
        cursor.close()
        conexao.close()
    except mysql.connector.Error as e:
        print("ERRO NO MYSQL:", e)
        return redirect(url_for("login", erro="banco"))

    if usuario and check_password_hash(usuario["senha"], senha):
        session["usuario_id"] = usuario["id"]
        session["nome"] = usuario["nome"]
        return redirect(url_for("perfil"))
    return redirect(url_for("login", erro="credenciais"))


@app.route("/logout")
def logout():
    session.clear()
    return redirect("/")


@app.route("/api/me")
def api_me():
    return jsonify(logado="usuario_id" in session, nome=session.get("nome", ""))


# ---------- PERFIL ----------
NOMES_ESP = {
    "psicoterapia": "Psicoterapia", "fisioterapia": "Fisioterapia",
    "fonoaudiologia": "Fonoaudiologia", "nutricao": "Nutrição",
}


@app.route("/perfil")
def perfil():
    if "usuario_id" not in session:
        return redirect(url_for("login"))
    try:
        conexao = conectar_banco()
        cursor = conexao.cursor(dictionary=True)
        cursor.execute(
            "SELECT nome, sobrenome, email, cpf, telefone, criado_em FROM usuarios WHERE id = %s",
            (session["usuario_id"],),
        )
        usuario = cursor.fetchone()
        cursor.execute(
            """SELECT id, especialidade, data, hora FROM agendamentos
               WHERE usuario_id = %s AND data >= CURDATE() ORDER BY data, hora""",
            (session["usuario_id"],),
        )
        agendamentos = cursor.fetchall()
        cursor.close()
        conexao.close()
    except mysql.connector.Error as e:
        print("ERRO NO MYSQL:", e)
        return redirect(url_for("login", erro="banco"))

    if not usuario:
        session.clear()
        return redirect(url_for("login"))
    usuario["cpf_mascarado"] = "***.***.***-" + usuario["cpf"][-2:]
    return render_template("perfil.html", usuario=usuario, agendamentos=agendamentos, nomes=NOMES_ESP)


@app.route("/cancelar/<int:agendamento_id>", methods=["POST"])
def cancelar(agendamento_id):
    if "usuario_id" not in session:
        return redirect(url_for("login"))
    try:
        conexao = conectar_banco()
        cursor = conexao.cursor()
        # O AND usuario_id garante que o cliente só cancela os próprios agendamentos
        cursor.execute(
            "DELETE FROM agendamentos WHERE id = %s AND usuario_id = %s",
            (agendamento_id, session["usuario_id"]),
        )
        conexao.commit()
        cursor.close()
        conexao.close()
    except mysql.connector.Error as e:
        print("ERRO NO MYSQL:", e)
    return redirect(url_for("perfil"))


# ---------- AGENDAMENTO ----------
def validar_dia(texto):
    try:
        dia = datetime.strptime(texto or "", "%Y-%m-%d").date()
    except ValueError:
        return "Data inválida."
    if dia < date.today():
        return "Não é possível agendar em datas passadas."
    if dia.weekday() == 0:  # segunda-feira
        return "Não atendemos às segundas-feiras."
    return None


@app.route("/api/horarios")
def api_horarios():
    esp = request.args.get("esp")
    data = request.args.get("data")
    if esp not in ESPECIALIDADES:
        return jsonify(erro="Especialidade inválida."), 400
    erro = validar_dia(data)
    if erro:
        return jsonify(erro=erro), 400

    try:
        conexao = conectar_banco()
        cursor = conexao.cursor()
        cursor.execute("SELECT hora FROM agendamentos WHERE especialidade = %s AND data = %s", (esp, data))
        ocupados = [linha[0] for linha in cursor.fetchall()]
        cursor.close()
        conexao.close()
    except mysql.connector.Error as e:
        print("ERRO NO MYSQL:", e)
        return jsonify(erro="Erro ao consultar o banco de dados."), 500
    return jsonify(horarios=HORARIOS, ocupados=ocupados)


@app.route("/api/agendamentos", methods=["POST"])
def api_agendar():
    if "usuario_id" not in session:
        return jsonify(erro="Faça login para agendar."), 401

    d = request.get_json(silent=True) or {}
    esp, data, hora = d.get("especialidade"), d.get("data"), d.get("hora")
    if esp not in ESPECIALIDADES:
        return jsonify(erro="Especialidade inválida."), 400
    erro = validar_dia(data)
    if erro:
        return jsonify(erro=erro), 400
    if hora not in HORARIOS:
        return jsonify(erro="Horário inválido."), 400

    try:
        conexao = conectar_banco()
        cursor = conexao.cursor()
        cursor.execute(
            "INSERT INTO agendamentos (usuario_id, especialidade, data, hora) VALUES (%s, %s, %s, %s)",
            (session["usuario_id"], esp, data, hora),
        )
        conexao.commit()
        cursor.close()
        conexao.close()
        return jsonify(ok=True), 201
    except mysql.connector.IntegrityError as e:
        if e.errno == 1062:  # horário já reservado por outro cliente
            return jsonify(erro="Este horário acabou de ser reservado. Escolha outro."), 409
        print("ERRO NO MYSQL:", e)
        return jsonify(erro="Erro ao salvar."), 500
    except mysql.connector.Error as e:
        print("ERRO NO MYSQL:", e)
        return jsonify(erro="Erro ao salvar. Tente novamente."), 500


if __name__ == "__main__":
    app.run(debug=True)
