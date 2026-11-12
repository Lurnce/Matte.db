<?php
require_once 'config.php';

if (empty($_SESSION['carrinho'])) {
    header("Location: index.php");
    exit;
}

// Salva os parâmetros do carrinho antes de exigir o login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['checkout_pendente'] = [
        'forma_retirada'  => $_POST['forma_retirada'] ?? 'Delivery',
        'distancia_km'    => max(1, (float)($_POST['distancia_km'] ?? 5.0)),
        'forma_pagamento' => $_POST['forma_pagamento'] ?? 'Pix',
        'usar_pontos'     => ($_POST['usar_pontos'] ?? '0') === '1'
    ];
}

// EXIGÊNCIA DO USUÁRIO: Só pede login na hora de pagar
if (!isset($_SESSION['cliente_id'])) {
    header("Location: auth.php");
    exit;
}

$dadosCheckout = $_SESSION['checkout_pendente'] ?? [
    'forma_retirada'  => 'Delivery',
    'distancia_km'    => 5.0,
    'forma_pagamento' => 'Pix',
    'usar_pontos'     => false
];

$clienteId = $_SESSION['cliente_id'];

try {
    $pdo->beginTransaction();

    // 1. Busca dados do cliente com bloqueio de linha
    $stmtCli = $pdo->prepare("SELECT * FROM clientes WHERE idclientes = ? FOR UPDATE");
    $stmtCli->execute([$clienteId]);
    $cliente = $stmtCli->fetch();

    if (!$cliente) {
        throw new Exception("Cliente não encontrado.");
    }

    // 2. Recalcula itens e valida estoque
    $ids = implode(',', array_map('intval', array_keys($_SESSION['carrinho'])));
    $produtosDb = $pdo->query("SELECT p.*, e.quantidade_atual FROM produtos p INNER JOIN estoque e ON p.idprodutos = e.idprodutos WHERE p.idprodutos IN ($ids) FOR UPDATE")->fetchAll();

    $subtotal = 0.00;
    $qtdTotal = 0;

    foreach ($produtosDb as $prod) {
        $qtd = $_SESSION['carrinho'][$prod['idprodutos']];
        if ($prod['quantidade_atual'] < $qtd) {
            throw new Exception("Estoque insuficiente para o produto: " . $prod['nome_produto']);
        }
        $subtotal = round($subtotal + ($prod['custo_produto'] * $qtd), 2);
        $qtdTotal += $qtd;
    }

    // 3. Regras de Negócio Finais
    $valorFrete = ($dadosCheckout['forma_retirada'] === 'Retirada') ? 0.00 : round(($qtdTotal * 1.50) + ($dadosCheckout['distancia_km'] * 0.80), 2);

    $descontoPrimeiro = ($cliente['primeiro_pedido'] == 1) ? round($subtotal * 0.10, 2) : 0.00;
    $descontoPontos = ($dadosCheckout['usar_pontos'] && $cliente['ponto_fide'] >= 100) ? round($subtotal * 0.10, 2) : 0.00;
    $descontosTotais = round($descontoPrimeiro + $descontoPontos, 2);

    $base = max(0, round($subtotal + $valorFrete - $descontosTotais, 2));
    $taxaCartao = ($dadosCheckout['forma_pagamento'] === 'Cartão') ? round($base * 0.012, 2) : 0.00;
    $valorFinal = round($base + $taxaCartao, 2);
    $ganhouBrinde = ($subtotal >= 500.00) ? 1 : 0;

    // 4. Registra o Pedido (RF-004, RNF-005)
    $stmtPed = $pdo->prepare("INSERT INTO pedidos (idclientes, quantidade_pedido, valor_subtotal, valor_frete, descontos_aplicados, taxa_cartao, valor_total, forma_pagamento, forma_retirada, ganhou_brinde, registro_pedido) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $resumoTexto = "Pedido via web. Brinde: " . ($ganhouBrinde ? 'Sim' : 'Não');
    $stmtPed->execute([$clienteId, $qtdTotal, $subtotal, $valorFrete, $descontosTotais, $taxaCartao, $valorFinal, $dadosCheckout['forma_pagamento'], $dadosCheckout['forma_retirada'], $ganhouBrinde, $resumoTexto]);
    $idPedido = $pdo->lastInsertId();

    // 5. Registra Itens e Baixa no Estoque
    $stmtItem = $pdo->prepare("INSERT INTO pedido_itens (idpedidos, idprodutos, quantidade, preco_unitario) VALUES (?, ?, ?, ?)");
    $stmtBaixa = $pdo->prepare("UPDATE estoque SET quantidade_atual = quantidade_atual - ? WHERE idprodutos = ?");

    foreach ($produtosDb as $prod) {
        $qtd = $_SESSION['carrinho'][$prod['idprodutos']];
        $stmtItem->execute([$idPedido, $prod['idprodutos'], $qtd, $prod['custo_produto']]);
        $stmtBaixa->execute([$qtd, $prod['idprodutos']]);
    }

    // 6. Atualiza Pontos Fidelidade (RF-013: 1 ponto a cada R$ 1,00 gasto) e status de 1º pedido
    $pontosGanhos = (int)floor($valorFinal);
    $novosPontos = $cliente['ponto_fide'] - ($descontoPontos > 0 ? 100 : 0) + $pontosGanhos;
    $stmtUpdCli = $pdo->prepare("UPDATE clientes SET ponto_fide = ?, primeiro_pedido = 0 WHERE idclientes = ?");
    $stmtUpdCli->execute([$novosPontos, $clienteId]);

    // 7. Gera registro de Delivery em tempo real (RF-012)
    if ($dadosCheckout['forma_retirada'] === 'Delivery') {
        $stmtDel = $pdo->prepare("INSERT INTO delivery (idpedidos, nome_entregador, endereco_pedido, local_entregador, status_entrega) VALUES (?, ?, ?, ?, ?)");
        $stmtDel->execute([$idPedido, 'Aguardando atribuição', $cliente['endereco'], 'Loja Central - Preparando despacho', 'Em preparação']);
    }

    $pdo->commit();

    // Limpa carrinho e sessões temporárias
    unset($_SESSION['carrinho'], $_SESSION['checkout_pendente'], $_SESSION['opcoes_carrinho']);

    header("Location: meus_pedidos.php?sucesso=$idPedido");
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Erro ao processar pagamento: " . htmlspecialchars($e->getMessage()));
}
