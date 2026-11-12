<?php
require_once 'config.php';

// Logout seguro
if (isset($_GET['acao']) && $_GET['acao'] === 'sair') {
    $_SESSION = [];
    session_destroy();
    header("Location: index.php");
    exit;
}

$erro = '';

// Processar Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'login') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if (empty($email) || empty($senha)) {
        $erro = "Preencha e-mail e senha para entrar.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM clientes WHERE email = ?");
        $stmt->execute([$email]);
        $cliente = $stmt->fetch();

        // RNF-001: Verificação de senha criptografada
        if ($cliente && password_verify($senha, $cliente['senha'])) {
            session_regenerate_id(true); // Previne Session Fixation
            $_SESSION['cliente_id'] = $cliente['idclientes'];
            $_SESSION['cliente_nome'] = $cliente['nome'];

            $destino = isset($_SESSION['checkout_pendente']) ? 'checkout.php' : 'carrinho.php';
            header("Location: $destino");
            exit;
        } else {
            $erro = "E-mail ou senha inválidos.";
        }
    }
}

// Processar Novo Cadastro (RF-001, RNF-001, RNF-003, RNF-004, RN-001)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'cadastrar') {
    $nome     = trim($_POST['nome'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');
    $senha    = $_POST['senha'] ?? '';

    // RNF-004: Não permitir valores nulos/vazios e validar e-mail
    if (empty($nome) || empty($telefone) || empty($email) || empty($endereco) || empty($senha)) {
        $erro = "Todos os campos (Nome, Telefone, E-mail, Endereço e Senha) são obrigatórios.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = "Informe um endereço de e-mail válido.";
    } else {
        // RNF-003: Verificar cadastro repetido
        $stmtCheck = $pdo->prepare("SELECT idclientes FROM clientes WHERE email = ?");
        $stmtCheck->execute([$email]);

        if ($stmtCheck->fetch()) {
            $erro = "Este e-mail já está cadastrado no sistema.";
        } else {
            // RNF-001: Criptografar informações pessoais (senha)
            $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

            $stmtInsert = $pdo->prepare("INSERT INTO clientes (nome, telefone, email, senha, endereco, ponto_fide, primeiro_pedido) VALUES (?, ?, ?, ?, ?, 0, 1)");
            $stmtInsert->execute([$nome, $telefone, $email, $senhaHash, $endereco]);

            session_regenerate_id(true);
            $_SESSION['cliente_id'] = $pdo->lastInsertId();
            $_SESSION['cliente_nome'] = $nome;

            $destino = isset($_SESSION['checkout_pendente']) ? 'checkout.php' : 'carrinho.php';
            header("Location: $destino");
            exit;
        }
    }
}

require_once 'header.php';
?>

<h2>Identificação Necessária para Finalizar o Pagamento</h2>
<p>Para concluir sua compra de chás e mattes, faça login ou crie sua conta rapidamente abaixo.</p>

<?php if ($erro): ?>
    <p style="color: red;"><strong>Erro:</strong> <?= htmlspecialchars($erro) ?></p>
<?php endif; ?>

<div style="display: flex; gap: 40px;">
    <!-- Formulário de Login -->
    <section style="border: 1px solid #ccc; padding: 20px; width: 45%;">
        <h3>Já tenho cadastro</h3>
        <form method="POST" action="auth.php">
            <input type="hidden" name="acao" value="login">
            <label>E-mail:<br><input type="email" name="email" required></label><br><br>
            <label>Senha:<br><input type="password" name="senha" required></label><br><br>
            <button type="submit">Entrar e Continuar Pagamento</button>
        </form>
    </section>

    <!-- Formulário de Cadastro -->
    <section style="border: 1px solid #ccc; padding: 20px; width: 45%;">
        <h3>Primeiro Cadastro (Ganhe Desconto - RN-001)</h3>
        <form method="POST" action="auth.php">
            <input type="hidden" name="acao" value="cadastrar">
            <label>Nome Completo:<br><input type="text" name="nome" required></label><br><br>
            <label>Telefone / WhatsApp:<br><input type="text" name="telefone" required></label><br><br>
            <label>E-mail:<br><input type="email" name="email" required></label><br><br>
            <label>Endereço Completo de Entrega:<br><input type="text" name="endereco" required></label><br><br>
            <label>Crie uma Senha:<br><input type="password" name="senha" required></label><br><br>
            <button type="submit">Cadastrar e Continuar Pagamento</button>
        </form>
    </section>
</div>

<?php require_once 'footer.php'; ?>