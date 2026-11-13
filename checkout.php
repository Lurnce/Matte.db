<?php
require_once __DIR__ . '/config/config.php';

if (empty($_SESSION['carrinho'])) redirect(BASE_URL . 'loja/index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validarCsrf($_POST['csrf'] ?? null)) {
    $_SESSION['checkout_pendente'] = [
        'forma_retirada'  => $_POST['forma_retirada'] ?? 'Delivery',
        'distancia_km'    => max(1, (float)($_POST['distancia_km'] ?? 5)),
        'forma_pagamento' => $_POST['forma_pagamento'] ?? 'Pix',
        'usar_pontos'     => ($_POST['usar_pontos'] ?? '0') === '1',
    ];
}

if (empty($_SESSION['cliente_id'])) redirect(BASE_URL . 'auth/login.php');

$op = $_SESSION['checkout_pendente'] ?? [
    'forma_retirada' => 'Delivery',
    'distancia_km' => 5.0,
    'forma_pagamento' => 'Pix',
    'usar_pontos' => false,
];
$cid = (int)$_SESSION['cliente_id'];

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE idclientes = ? FOR UPDATE");
    $stmt->execute([$cid]);
    $cli = $stmt->fetch();
    if (!$cli) throw new Exception('Cliente não encontrado.');

    $ids = implode(',', array_map('intval', array_keys($_SESSION['carrinho'])));
    $prods = $pdo->query("SELECT p.*, e.quantidade_atual FROM produtos p
                          INNER JOIN estoque e ON p.idprodutos = e.idprodutos
                          WHERE p.idprodutos IN ($ids) FOR UPDATE")->fetchAll();

    $sub = 0.0;
    $qt = 0;
    foreach ($prods as $p) {
        $q = $_SESSION['carrinho'][$p['idprodutos']];
        if ($p['quantidade_atual'] < $q) throw new Exception("Estoque insuficiente: " . $p['nome_produto']);
        $sub += $p['custo_produto'] * $q;
        $qt  += $q;
    }
    $sub = round($sub, 2);

    $frete = $op['forma_retirada'] === 'Retirada' ? 0.0 : round(($qt * 1.5) + ($op['distancia_km'] * 0.8), 2);
    $d1 = $cli['primeiro_pedido'] == 1 ? round($sub * 0.10, 2) : 0.0;
    $d2 = ($op['usar_pontos'] && $cli['ponto_fide'] >= 100) ? round($sub * 0.10, 2) : 0.0;
    $dt = round($d1 + $d2, 2);
    $base = max(0, round($sub + $frete - $dt, 2));
    $tx = $op['forma_pagamento'] === 'Cartão' ? round($base * 0.012, 2) : 0.0;
    $total = round($base + $tx, 2);
    $brinde = $sub >= 500.0 ? 1 : 0;

    $ins = $pdo->prepare("INSERT INTO pedidos (idclientes,quantidade_pedido,valor_subtotal,valor_frete,
        descontos_aplicados,taxa_cartao,valor_total,forma_pagamento,forma_retirada,ganhou_brinde,registro_pedido)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)");
    $ins->execute([
        $cid,
        $qt,
        $sub,
        $frete,
        $dt,
        $tx,
        $total,
        $op['forma_pagamento'],
        $op['forma_retirada'],
        $brinde,
        'Pedido via web. Brinde: ' . ($brinde ? 'Sim' : 'Não')
    ]);
    $idPed = (int)$pdo->lastInsertId();

    $insItem  = $pdo->prepare("INSERT INTO pedido_itens (idpedidos,idprodutos,quantidade,preco_unitario) VALUES (?,?,?,?)");
    $baixaEst = $pdo->prepare("UPDATE estoque SET quantidade_atual = quantidade_atual - ? WHERE idprodutos = ?");
    foreach ($prods as $p) {
        $q = $_SESSION['carrinho'][$p['idprodutos']];
        $insItem->execute([$idPed, $p['idprodutos'], $q, $p['custo_produto']]);
        $baixaEst->execute([$q, $p['idprodutos']]);
    }

    $ptsGanhos = (int)floor($total);
    $novos = $cli['ponto_fide'] - ($d2 > 0 ? 100 : 0) + $ptsGanhos;
    $upd = $pdo->prepare("UPDATE clientes SET ponto_fide = ?, primeiro_pedido = 0 WHERE idclientes = ?");
    $upd->execute([$novos, $cid]);

    if ($op['forma_retirada'] === 'Delivery') {
        $d = $pdo->prepare("INSERT INTO delivery (idpedidos,nome_entregador,endereco_pedido,local_entregador,status_entrega) VALUES (?,?,?,?,?)");
        $d->execute([$idPed, 'Aguardando atribuição', $cli['endereco'], 'Loja Central - Preparando despacho', 'Em preparação']);
    }

    $pdo->commit();
    unset($_SESSION['carrinho'], $_SESSION['checkout_pendente'], $_SESSION['opcoes_carrinho']);
    redirect(BASE_URL . 'perfil/pedidos.php?sucesso=' . $idPed);
} catch (Exception $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash('erro', 'Erro ao processar: ' . $ex->getMessage());
    redirect(BASE_URL . 'carrinho.php');
}
