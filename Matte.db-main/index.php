<?php
require_once 'config.php';

// Adicionar produto ao carrinho
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adicionar_id'])) {
    $idProd = (int)$_POST['adicionar_id'];
    $qtd = max(1, (int)$_POST['quantidade']);

    if (isset($_SESSION['carrinho'][$idProd])) {
        $_SESSION['carrinho'][$idProd] += $qtd;
    } else {
        $_SESSION['carrinho'][$idProd] = $qtd;
    }
    header("Location: index.php?msg=adicionado");
    exit;
}

// Busca produtos com dados de lote e estoque (RF-003, RF-005, RF-006)
$sql = "SELECT p.*, e.codigo_lote, e.quantidade_atual, e.quantidade_minima, e.validade_lote, e.verificacao_sanitaria 
        FROM produtos p 
        INNER JOIN estoque e ON p.idprodutos = e.idprodutos
        WHERE e.verificacao_sanitaria = 1";
$produtos = $pdo->query($sql)->fetchAll();

require_once 'header.php';
?>

<h2>Nossos Chás e Mattes Artesanais</h2>

<section class="linha-xicaras-sabores">
    <div class="xicaras-lista">
        <div class="xicara-item xicara-mate-verde">
            <div class="xicara-topo"></div>
            <span class="xicara-nome-sabor">Mate Verde</span>
        </div>
        <div class="xicara-item xicara-limao-mel">
            <div class="xicara-topo"></div>
            <span class="xicara-nome-sabor">Limão & Mel</span>
        </div>
        <div class="xicara-item xicara-hortela">
            <div class="xicara-topo"></div>
            <span class="xicara-nome-sabor">Chá Hortelã</span>
        </div>
        <div class="xicara-item xicara-camomila">
            <div class="xicara-topo"></div>
            <span class="xicara-nome-sabor">Camomila</span>
        </div>
        <div class="xicara-item xicara-mate-tostado">
            <div class="xicara-topo"></div>
            <span class="xicara-nome-sabor">Mate Tostado</span>
        </div>
    </div>
</section>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'adicionado'): ?>
    <p><strong>Produto adicionado ao carrinho!</strong> <a href="carrinho.php">Ir para o carrinho</a></p>
<?php endif; ?>

<section class="catalogo-produtos">
    <?php foreach ($produtos as $p): ?>
        <article class="card-produto" style="border: 1px solid #ccc; padding: 15px; margin-bottom: 15px;">
            <h3><?= htmlspecialchars($p['nome_produto']) ?></h3>
            <p><?= htmlspecialchars($p['descricao']) ?></p>
            <p><strong>Preço:</strong> <?= formatarMoeda($p['custo_produto']) ?></p>

            <!-- Dados de Rastreabilidade Sanitária e Lote (RF-003 e RF-006) -->
            <small>
                Lote: <?= htmlspecialchars($p['codigo_lote']) ?> |
                Validade: <?= date('d/m/Y', strtotime($p['validade_lote'])) ?> |
                Selo Sanitário: Aprovado
            </small><br>

            <!-- Alerta de Estoque Baixo < 10% (RF-005) -->
            <?php if ($p['quantidade_atual'] <= $p['quantidade_minima']): ?>
                <p style="color: red;"><strong>Aviso de Estoque Baixo:</strong> Restam apenas <?= $p['quantidade_atual'] ?> unidades neste lote!</p>
            <?php else: ?>
                <p>Disponível em estoque: <?= $p['quantidade_atual'] ?> unidades</p>
            <?php endif; ?>

            <?php if ($p['quantidade_atual'] > 0): ?>
                <form method="POST" action="index.php">
                    <input type="hidden" name="adicionar_id" value="<?= $p['idprodutos'] ?>">
                    <label>Qtd: <input type="number" name="quantidade" value="1" min="1" max="<?= $p['quantidade_atual'] ?>"></label>
                    <button type="submit">Adicionar ao Carrinho</button>
                </form>
            <?php else: ?>
                <button disabled>Produto Esgotado</button>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</section>

<?php require_once 'footer.php'; ?>