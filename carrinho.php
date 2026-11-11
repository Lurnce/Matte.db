<?php
require_once 'config.php';

// Remover item ou limpar carrinho
if (isset($_GET['remover'])) {
    $idRem = (int)$_GET['remover'];
    unset($_SESSION['carrinho'][$idRem]);
    header("Location: carrinho.php");
    exit;
}

$itensCarrinho = [];
$subtotal = 0.00;
$qtdTotalProdutos = 0;

if (!empty($_SESSION['carrinho'])) {
    $ids = implode(',', array_map('intval', array_keys($_SESSION['carrinho'])));
    $stmt = $pdo->query("SELECT * FROM produtos WHERE idprodutos IN ($ids)");
    $produtosDb = $stmt->fetchAll();

    foreach ($produtosDb as $prod) {
        $qtd = $_SESSION['carrinho'][$prod['idprodutos']];
        $totalItem = round($prod['custo_produto'] * $qtd, 2);
        $subtotal = round($subtotal + $totalItem, 2);
        $qtdTotalProdutos += $qtd;

        $itensCarrinho[] = [
            'produto' => $prod,
            'qtd' => $qtd,
            'total_item' => $totalItem
        ];
    }
}

// Simulação de cálculo de frete (RF-010: Quantidade + Distância)
$distanciaKm = isset($_POST['distancia_km']) ? max(1, (float)$_POST['distancia_km']) : 5.0;
$formaRetirada = $_POST['forma_retirada'] ?? 'Delivery';
$formaPagamento = $_POST['forma_pagamento'] ?? 'Pix';
$usarPontos = isset($_POST['usar_pontos']);

$valorFrete = ($formaRetirada === 'Retirada') ? 0.00 : round(($qtdTotalProdutos * 1.50) + ($distanciaKm * 0.80), 2);

// Verifica regras de negócio caso o cliente já esteja logado
$descontoPrimeiroCadastro = 0.00;
$descontoFidelidade = 0.00;
$pontosCliente = 0;

if (isset($_SESSION['cliente_id'])) {
    $stmtCli = $pdo->prepare("SELECT ponto_fide, primeiro_pedido FROM clientes WHERE idclientes = ?");
    $stmtCli->execute([$_SESSION['cliente_id']]);
    $dadosCli = $stmtCli->fetch();

    if ($dadosCli) {
        $pontosCliente = (int)$dadosCli['ponto_fide'];
        // RN-001: 10% de desconto na primeira compra
        if ($dadosCli['primeiro_pedido'] == 1) {
            $descontoPrimeiroCadastro = round($subtotal * 0.10, 2);
        }
        // RN-003: A cada 100 pontos, ganha 10% de desconto
        if ($usarPontos && $pontosCliente >= 100) {
            $descontoFidelidade = round($subtotal * 0.10, 2);
        }
    }
}

$totalDescontos = round($descontoPrimeiroCadastro + $descontoFidelidade, 2);
$baseCalculo = max(0, round($subtotal + $valorFrete - $totalDescontos, 2));

// RN-002: Acréscimo de 1,2% se pagamento for no Cartão
$taxaCartao = ($formaPagamento === 'Cartão') ? round($baseCalculo * 0.012, 2) : 0.00;

// Valor Final obrigatório em 2 casas decimais (RNF-006)
$valorFinal = round($baseCalculo + $taxaCartao, 2);

// RN-005: Brinde em compras >= R$ 500,00
$ganhaBrinde = ($subtotal >= 500.00);

require_once 'header.php';
?>

<h2>Seu Carrinho de Compras</h2>

<?php if (empty($itensCarrinho)): ?>
    <p>Seu carrinho está vazio. <a href="index.php">Voltar ao catálogo de chás</a>.</p>
<?php else: ?>
    <table border="1" cellpadding="10" cellspacing="0" width="100%">
        <thead>
            <tr>
                <th>Produto</th>
                <th>Preço Unitário</th>
                <th>Quantidade</th>
                <th>Subtotal</th>
                <th>Ação</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($itensCarrinho as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['produto']['nome_produto']) ?></td>
                    <td><?= formatarMoeda($item['produto']['custo_produto']) ?></td>
                    <td><?= $item['qtd'] ?></td>
                    <td><?= formatarMoeda($item['total_item']) ?></td>
                    <td><a href="carrinho.php?remover=<?= $item['produto']['idprodutos'] ?>">Remover</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <br>
    <!-- Formulário de Simulação de Frete, Pagamento e Regras de Negócio -->
    <form method="POST" action="carrinho.php">
        <h3>Opções de Entrega e Pagamento</h3>

        <label>Modo de Recebimento:
            <select name="forma_retirada" onchange="this.form.submit()">
                <option value="Delivery" <?= $formaRetirada === 'Delivery' ? 'selected' : '' ?>>Delivery (Entrega em casa)</option>
                <option value="Retirada" <?= $formaRetirada === 'Retirada' ? 'selected' : '' ?>>Retirar na Loja (Frete Grátis)</option>
            </select>
        </label><br><br>

        <?php if ($formaRetirada === 'Delivery'): ?>
            <label>Distância estimada até seu endereço (km - RF-010):
                <input type="number" step="0.5" min="1" name="distancia_km" value="<?= $distanciaKm ?>">
            </label>
            <button type="submit">Recalcular Frete</button><br><br>
        <?php endif; ?>

        <label>Forma de Pagamento (RF-002):
            <select name="forma_pagamento" onchange="this.form.submit()">
                <option value="Pix" <?= $formaPagamento === 'Pix' ? 'selected' : '' ?>>Pix (Sem taxa)</option>
                <option value="Dinheiro" <?= $formaPagamento === 'Dinheiro' ? 'selected' : '' ?>>Dinheiro na entrega (Sem taxa)</option>
                <option value="Cartão" <?= $formaPagamento === 'Cartão' ? 'selected' : '' ?>>Cartão de Crédito/Débito (+1,2% RN-002)</option>
            </select>
        </label><br><br>

        <?php if (isset($_SESSION['cliente_id']) && $pontosCliente >= 100): ?>
            <label>
                <input type="checkbox" name="usar_pontos" <?= $usarPontos ? 'checked' : '' ?> onchange="this.form.submit()">
                Usar 100 Pontos Fidelidade para 10% de desconto (Você tem <?= $pontosCliente ?> pts - RN-003)
            </label><br><br>
        <?php endif; ?>
    </form>

    <div style="border: 1px solid #000; padding: 15px;">
        <h3>Resumo Financeiro</h3>
        <p>Subtotal dos Produtos: <strong><?= formatarMoeda($subtotal) ?></strong></p>
        <p>Frete Calculado (<?= $qtdTotalProdutos ?> itens / <?= $distanciaKm ?> km): <strong><?= formatarMoeda($valorFrete) ?></strong></p>

        <?php if ($descontoPrimeiroCadastro > 0): ?>
            <p style="color: green;">Desconto 1º Cadastro (10% - RN-001): <strong>- <?= formatarMoeda($descontoPrimeiroCadastro) ?></strong></p>
        <?php elseif (!isset($_SESSION['cliente_id'])): ?>
            <p><small><em>Cadastre-se no próximo passo para ganhar 10% de desconto na 1ª compra (RN-001)!</em></small></p>
        <?php endif; ?>

        <?php if ($descontoFidelidade > 0): ?>
            <p style="color: green;">Desconto Fidelidade (100 pts - RN-003): <strong>- <?= formatarMoeda($descontoFidelidade) ?></strong></p>
        <?php endif; ?>

        <?php if ($taxaCartao > 0): ?>
            <p style="color: darkorange;">Taxa Operadora Cartão (1,2% - RN-002): <strong>+ <?= formatarMoeda($taxaCartao) ?></strong></p>
        <?php endif; ?>

        <?php if ($ganhaBrinde): ?>
            <p style="color: blue;"><strong>Parabéns (RN-005):</strong> Sua compra ultrapassou R$ 500,00 e você ganhou um <strong>Kit Infusor Exclusivo</strong> de brinde!</p>
        <?php endif; ?>

        <h2>Total a Pagar: <?= formatarMoeda($valorFinal) ?></h2>

        <!-- Botão de Pagamento: Exige Login apenas neste momento -->
        <form method="POST" action="checkout.php">
            <input type="hidden" name="forma_retirada" value="<?= htmlspecialchars($formaRetirada) ?>">
            <input type="hidden" name="distancia_km" value="<?= $distanciaKm ?>">
            <input type="hidden" name="forma_pagamento" value="<?= htmlspecialchars($formaPagamento) ?>">
            <input type="hidden" name="usar_pontos" value="<?= $usarPontos ? '1' : '0' ?>">

            <?php if (!isset($_SESSION['cliente_id'])): ?>
                <button type="submit">Ir para Pagamento (Entrar ou Cadastrar)</button>
            <?php else: ?>
                <button type="submit">Confirmar e Pagar Pedido</button>
            <?php endif; ?>
        </form>
    </div>
<?php endif; ?>

<?php require_once 'footer.php'; ?>