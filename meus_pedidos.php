<?php
require_once 'config.php';

if (!isset($_SESSION['cliente_id'])) {
    header("Location: auth.php");
    exit;
}

$clienteId = $_SESSION['cliente_id'];
$msgSuporte = '';
$erroSuporte = '';

// Processar chamado de suporte ou pedido de reembolso (RF-011 e RN-004)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['abrir_suporte'])) {
    $idPedidoRef = !empty($_POST['idpedidos']) ? (int)$_POST['idpedidos'] : null;
    $tipoChamado = $_POST['tipo_chamado'] ?? 'Dúvida Geral';
    $mensagem    = trim($_POST['registro_sup'] ?? '');

    if (!empty($mensagem)) {
        // Valida se o pedido informado realmente pertence ao cliente logado
        if ($idPedidoRef !== null) {
            $stmtCheckPed = $pdo->prepare("SELECT idpedidos FROM pedidos WHERE idpedidos = ? AND idclientes = ?");
            $stmtCheckPed->execute([$idPedidoRef, $clienteId]);
            if (!$stmtCheckPed->fetch()) {
                $erroSuporte = "O número de pedido informado não foi encontrado na sua conta.";
            }
        }

        if (empty($erroSuporte)) {
            $stmtSup = $pdo->prepare("INSERT INTO suporte (idclientes, idpedidos, tipo_chamado, registro_sup) VALUES (?, ?, ?, ?)");
            $stmtSup->execute([$clienteId, $idPedidoRef, $tipoChamado, $mensagem]);
            $msgSuporte = "Solicitação registrada com sucesso! Nossa equipe analisará seu chamado.";
        }
    }
}

// Busca saldo atual de pontos do cliente
$stmtCli = $pdo->prepare("SELECT ponto_fide FROM clientes WHERE idclientes = ?");
$stmtCli->execute([$clienteId]);
$pontosAtuais = $stmtCli->fetchColumn();

// Busca pedidos e dados de delivery (RF-012)
$stmtPed = $pdo->prepare("SELECT p.*, d.nome_entregador, d.local_entregador, d.status_entrega 
                          FROM pedidos p 
                          LEFT JOIN delivery d ON p.idpedidos = d.idpedidos 
                          WHERE p.idclientes = ? 
                          ORDER BY p.datahora_pedido DESC");
$stmtPed->execute([$clienteId]);
$pedidos = $stmtPed->fetchAll();

require_once 'header.php';
?>

<h2>Minha Conta & Rastreamento de Pedidos</h2>

<?php if (isset($_GET['sucesso'])): ?>
    <p style="color: green;"><strong>Compra realizada com sucesso!</strong> Pedido #<?= (int)$_GET['sucesso'] ?> confirmado.</p>
<?php endif; ?>

<?php if ($msgSuporte): ?>
    <p style="color: blue;"><strong>Suporte:</strong> <?= htmlspecialchars($msgSuporte) ?></p>
<?php endif; ?>

<?php if ($erroSuporte): ?>
    <p style="color: red;"><strong>Erro no Suporte:</strong> <?= htmlspecialchars($erroSuporte) ?></p>
<?php endif; ?>

<p>Seu Saldo de Pontos Fidelidade (RF-013): <strong><?= (int)$pontosAtuais ?> pontos</strong> <em>(A cada 100 pontos você ganha 10% de desconto na próxima compra)</em>.</p>

<h3>Histórico e Monitoramento de Entrega (RF-012)</h3>
<?php if (empty($pedidos)): ?>
    <p>Você ainda não realizou nenhum pedido.</p>
<?php else: ?>
    <?php foreach ($pedidos as $ped): ?>
        <div style="border: 1px solid #ccc; padding: 15px; margin-bottom: 15px;">
            <strong>Pedido #<?= $ped['idpedidos'] ?></strong> — Data: <?= date('d/m/Y H:i', strtotime($ped['datahora_pedido'])) ?><br>
            Total Pago: <strong><?= formatarMoeda($ped['valor_total']) ?></strong> (<?= htmlspecialchars($ped['forma_pagamento']) ?>)<br>
            Modalidade: <?= htmlspecialchars($ped['forma_retirada']) ?>
            <?php if ($ped['ganhou_brinde']): ?> | <strong>Brinde Incluso (RN-005)</strong><?php endif; ?>

            <?php if ($ped['forma_retirada'] === 'Delivery'): ?>
                <div style="background: #f4f4f4; padding: 10px; margin-top: 10px;">
                    <strong>Rastreamento em Tempo Real (RF-012):</strong><br>
                    Entregador: <?= htmlspecialchars($ped['nome_entregador'] ?? 'Aguardando atribuição') ?><br>
                    Status: <?= htmlspecialchars($ped['status_entrega'] ?? 'Em processamento') ?><br>
                    Localização Atual: <em><?= htmlspecialchars($ped['local_entregador'] ?? 'Loja Central') ?></em>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<hr>
<h3>Central de Suporte e Reembolso (RF-011 & RN-004)</h3>
<p>Recebeu algum chá ou mate danificado ou vencido? Solicite o reembolso integral abaixo.</p>

<form method="POST" action="meus_pedidos.php">
    <input type="hidden" name="abrir_suporte" value="1">
    <label>Tipo de Atendimento:
        <select name="tipo_chamado">
            <option value="Reembolso (Produto Danificado/Vencido - RN-004)">Solicitar Reembolso (Produto Danificado/Vencido)</option>
            <option value="Dúvida sobre Entrega">Dúvida sobre Entrega</option>
            <option value="Outros">Outros Assuntos</option>
        </select>
    </label><br><br>

    <label>Número do Pedido (opcional):
        <select name="idpedidos">
            <option value="">-- Nenhum pedido específico --</option>
            <?php foreach ($pedidos as $ped): ?>
                <option value="<?= $ped['idpedidos'] ?>">Pedido #<?= $ped['idpedidos'] ?> (<?= date('d/m/Y', strtotime($ped['datahora_pedido'])) ?>)</option>
            <?php endforeach; ?>
        </select>
    </label><br><br>

    <label>Descreva o ocorrido:<br>
        <textarea name="registro_sup" rows="4" cols="50" required></textarea>
    </label><br><br>

    <button type="submit">Enviar Solicitação ao Suporte</button>
</form>

<?php require_once 'footer.php'; ?>