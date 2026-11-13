<?php
require_once __DIR__ . '/config/config.php';

if (isset($_GET['remover'])) {
    unset($_SESSION['carrinho'][(int)$_GET['remover']]);
    redirect(BASE_URL . 'carrinho.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validarCsrf($_POST['csrf'] ?? null)) {
    $_SESSION['opcoes_carrinho'] = [
        'distancia_km'    => max(1, (float)($_POST['distancia_km'] ?? 5)),
        'forma_retirada'  => $_POST['forma_retirada'] ?? 'Delivery',
        'forma_pagamento' => $_POST['forma_pagamento'] ?? 'Pix',
        'usar_pontos'     => isset($_POST['usar_pontos']),
    ];
}

$opcoes = $_SESSION['opcoes_carrinho'] ?? [
    'distancia_km' => 5.0,
    'forma_retirada' => 'Delivery',
    'forma_pagamento' => 'Pix',
    'usar_pontos' => false,
];

$itens = [];
$subtotal = 0.0;
$qtdTotal = 0;

if (!empty($_SESSION['carrinho'])) {
    $ids = implode(',', array_map('intval', array_keys($_SESSION['carrinho'])));
    $rows = $pdo->query("SELECT * FROM produtos WHERE idprodutos IN ($ids)")->fetchAll();
    foreach ($rows as $p) {
        $q = $_SESSION['carrinho'][$p['idprodutos']];
        $tot = round($p['custo_produto'] * $q, 2);
        $subtotal += $tot;
        $qtdTotal += $q;
        $itens[] = ['produto' => $p, 'qtd' => $q, 'total' => $tot];
    }
    $subtotal = round($subtotal, 2);
}

$frete = $opcoes['forma_retirada'] === 'Retirada'
    ? 0.0
    : round(($qtdTotal * 1.5) + ($opcoes['distancia_km'] * 0.8), 2);

$descPrimeiro = 0.0;
$descFidelidade = 0.0;
$pontos = 0;
if (!empty($_SESSION['cliente_id'])) {
    $s = $pdo->prepare("SELECT ponto_fide, primeiro_pedido FROM clientes WHERE idclientes = ?");
    $s->execute([$_SESSION['cliente_id']]);
    $d = $s->fetch();
    if ($d) {
        $pontos = (int)$d['ponto_fide'];
        if ($d['primeiro_pedido'] == 1) $descPrimeiro = round($subtotal * 0.10, 2);
        if ($opcoes['usar_pontos'] && $pontos >= 100) $descFidelidade = round($subtotal * 0.10, 2);
    }
}
$descTotal = round($descPrimeiro + $descFidelidade, 2);
$base      = max(0, round($subtotal + $frete - $descTotal, 2));
$taxaCart  = $opcoes['forma_pagamento'] === 'Cartão' ? round($base * 0.012, 2) : 0.0;
$total     = round($base + $taxaCart, 2);
$brinde    = $subtotal >= 500.0;

require_once __DIR__ . '/includes/header.php';
?>
<h2>Seu Carrinho</h2>
<?php if (!$itens): ?>
    <p>Seu carrinho está vazio. <a href="<?= BASE_URL ?>loja/index.php">Voltar ao catálogo</a>.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Produto</th>
                <th>Preço</th>
                <th>Qtd</th>
                <th>Subtotal</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($itens as $i): ?>
                <tr>
                    <td><?= e($i['produto']['nome_produto']) ?></td>
                    <td><?= formatarMoeda($i['produto']['custo_produto']) ?></td>
                    <td><?= $i['qtd'] ?></td>
                    <td><?= formatarMoeda($i['total']) ?></td>
                    <td class="acoes-tabela"><a href="?remover=<?= (int)$i['produto']['idprodutos'] ?>">Remover</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <form method="POST" class="resumo-box">
        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
        <h3>Opções de entrega e pagamento</h3>
        <label>Modo de recebimento
            <select name="forma_retirada" onchange="this.form.submit()">
                <option value="Delivery" <?= $opcoes['forma_retirada'] === 'Delivery' ? 'selected' : '' ?>>Delivery</option>
                <option value="Retirada" <?= $opcoes['forma_retirada'] === 'Retirada' ? 'selected' : '' ?>>Retirada na loja</option>
            </select>
        </label>
        <?php if ($opcoes['forma_retirada'] === 'Delivery'): ?>
            <label>Distância (km)
                <input type="number" step="0.5" min="1" name="distancia_km" value="<?= e($opcoes['distancia_km']) ?>">
            </label>
        <?php else: ?>
            <input type="hidden" name="distancia_km" value="<?= e($opcoes['distancia_km']) ?>">
        <?php endif; ?>
        <label>Forma de pagamento
            <select name="forma_pagamento" onchange="this.form.submit()">
                <option value="Pix" <?= $opcoes['forma_pagamento'] === 'Pix' ? 'selected' : '' ?>>Pix (sem taxa)</option>
                <option value="Dinheiro" <?= $opcoes['forma_pagamento'] === 'Dinheiro' ? 'selected' : '' ?>>Dinheiro</option>
                <option value="Cartão" <?= $opcoes['forma_pagamento'] === 'Cartão' ? 'selected' : '' ?>>Cartão (+1,2%)</option>
            </select>
        </label>
        <?php if ($pontos >= 100): ?>
            <label><input type="checkbox" name="usar_pontos" <?= $opcoes['usar_pontos'] ? 'checked' : '' ?>> Usar 100 pontos (10% desc.) — você tem <?= $pontos ?> pts</label>
        <?php endif; ?>
        <button type="submit">Recalcular</button>
    </form>

    <div class="resumo-box">
        <p>Subtotal: <strong><?= formatarMoeda($subtotal) ?></strong></p>
        <p>Frete: <strong><?= formatarMoeda($frete) ?></strong></p>
        <?php if ($descPrimeiro): ?><p>Desconto 1ª compra: <strong>- <?= formatarMoeda($descPrimeiro) ?></strong></p><?php endif; ?>
        <?php if ($descFidelidade): ?><p>Desconto fidelidade: <strong>- <?= formatarMoeda($descFidelidade) ?></strong></p><?php endif; ?>
        <?php if ($taxaCart): ?><p>Taxa cartão: <strong>+ <?= formatarMoeda($taxaCart) ?></strong></p><?php endif; ?>
        <?php if ($brinde): ?><p><strong>Brinde:</strong> Kit Infusor exclusivo</p><?php endif; ?>
        <h2>Total: <?= formatarMoeda($total) ?></h2>
        <form method="POST" action="<?= BASE_URL ?>checkout.php">
            <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
            <input type="hidden" name="forma_retirada" value="<?= e($opcoes['forma_retirada']) ?>">
            <input type="hidden" name="distancia_km" value="<?= e($opcoes['distancia_km']) ?>">
            <input type="hidden" name="forma_pagamento" value="<?= e($opcoes['forma_pagamento']) ?>">
            <input type="hidden" name="usar_pontos" value="<?= $opcoes['usar_pontos'] ? '1' : '0' ?>">
            <button type="submit" class="btn-primario">Finalizar compra</button>
        </form>
    </div>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>