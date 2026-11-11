<?php
$totalItensCarrinho = array_sum($_SESSION['carrinho']);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Matte & Chá | Loja Oficial</title>
</head>

<body>
    <header>
        <nav>
            <h1><a href="index.php">Matte & Chá Store</a></h1>
            <ul>
                <li><a href="index.php">Catálogo de Chás e Mattes</a></li>
                <li><a href="carrinho.php">Carrinho (<?= $totalItensCarrinho ?>)</a></li>
                <?php if (isset($_SESSION['cliente_id'])): ?>
                    <li><a href="meus_pedidos.php">Meus Pedidos & Rastreio</a></li>
                    <li>Olá, <strong><?= htmlspecialchars($_SESSION['cliente_nome']) ?></strong> | <a href="auth.php?acao=sair">Sair</a></li>
                <?php else: ?>
                    <li><em>Navegando como visitante (Login solicitado apenas no pagamento)</em></li>
                <?php endif; ?>
            </ul>
        </nav>
        <hr>
    </header>
    <main>