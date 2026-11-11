DROP DATABASE IF EXISTS mattedb;
CREATE DATABASE mattedb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mattedb;

-- 1. Clientes (RNF-001 senha criptografada, RNF-003 email único, RNF-004 NOT NULL, RNF-005 data_cadastro)
CREATE TABLE clientes (
    idclientes INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    telefone VARCHAR(20) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    ponto_fide INT DEFAULT 0,
    primeiro_pedido TINYINT(1) DEFAULT 1, -- Auxilia na RN-001 (Desconto 1º cadastro)
    endereco VARCHAR(255) NOT NULL,
    data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 2. Produtos (Catálogo de Chás e Mattes)
CREATE TABLE produtos (
    idprodutos INT PRIMARY KEY AUTO_INCREMENT,
    nome_produto VARCHAR(100) NOT NULL,
    descricao VARCHAR(255),
    custo_produto DECIMAL(10, 2) NOT NULL
);

-- 3. Estoque e Lotes (RF-003, RF-005, RF-006)
CREATE TABLE estoque (
    idestoque INT PRIMARY KEY AUTO_INCREMENT,
    idprodutos INT NOT NULL,
    codigo_lote VARCHAR(50) NOT NULL,
    quantidade_inicial INT NOT NULL,
    quantidade_atual INT NOT NULL,
    quantidade_minima INT NOT NULL, -- Calculado como 10% do lote inicial (RF-005)
    validade_lote DATE NOT NULL,
    verificacao_sanitaria TINYINT(1) DEFAULT 1, -- RF-006 Segurança de Alimentos
    registro_alimentos TEXT,
    FOREIGN KEY (idprodutos) REFERENCES produtos(idprodutos)
);

-- 4. Pedidos (RF-002, RF-004, RNF-005, RNF-006)
CREATE TABLE pedidos (
    idpedidos INT PRIMARY KEY AUTO_INCREMENT,
    idclientes INT NOT NULL,
    datahora_pedido DATETIME DEFAULT CURRENT_TIMESTAMP,
    quantidade_pedido INT NOT NULL,
    valor_subtotal DECIMAL(10, 2) NOT NULL,
    valor_frete DECIMAL(10, 2) NOT NULL,
    descontos_aplicados DECIMAL(10, 2) DEFAULT 0.00,
    taxa_cartao DECIMAL(10, 2) DEFAULT 0.00,
    valor_total DECIMAL(10, 2) NOT NULL,
    forma_pagamento VARCHAR(50) NOT NULL, -- Cartão, Dinheiro, Pix
    forma_retirada VARCHAR(50) NOT NULL,  -- Delivery ou Retirada
    ganhou_brinde TINYINT(1) DEFAULT 0,   -- RN-005 (>= R$ 500)
    status_pedido VARCHAR(50) DEFAULT 'Confirmado',
    registro_pedido TEXT,
    FOREIGN KEY (idclientes) REFERENCES clientes(idclientes)
);

-- 5. Itens do Pedido (Relacionamento N:N entre Pedidos e Produtos)
CREATE TABLE pedido_itens (
    iditem INT PRIMARY KEY AUTO_INCREMENT,
    idpedidos INT NOT NULL,
    idprodutos INT NOT NULL,
    quantidade INT NOT NULL,
    preco_unitario DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (idpedidos) REFERENCES pedidos(idpedidos),
    FOREIGN KEY (idprodutos) REFERENCES produtos(idprodutos)
);

-- 6. Delivery e Monitoramento (RF-012)
CREATE TABLE delivery (
    iddelivery INT PRIMARY KEY AUTO_INCREMENT,
    idpedidos INT NOT NULL,
    nome_entregador VARCHAR(100) DEFAULT 'Carlos Silva (Entregador Parceiro)',
    datahora_entrega DATETIME NULL,
    endereco_pedido VARCHAR(255) NOT NULL,
    local_entregador VARCHAR(100) DEFAULT 'Preparando rota na loja',
    status_entrega VARCHAR(50) DEFAULT 'Em separação',
    FOREIGN KEY (idpedidos) REFERENCES pedidos(idpedidos)
);

-- 7. Suporte e Reembolsos (RF-011, RN-004)
CREATE TABLE suporte (
    idsuporte INT PRIMARY KEY AUTO_INCREMENT,
    idclientes INT NULL,
    idpedidos INT NULL,
    tipo_chamado VARCHAR(50) NOT NULL, -- Dúvida ou Reembolso (RN-004)
    registro_sup TEXT NOT NULL,
    datahora_sup DATETIME DEFAULT CURRENT_TIMESTAMP,
    status_sup VARCHAR(50) DEFAULT 'Aberto',
    FOREIGN KEY (idclientes) REFERENCES clientes(idclientes),
    FOREIGN KEY (idpedidos) REFERENCES pedidos(idpedidos)
);

-- 8. Recursos Humanos e Folha de Pagamento (RF-007, RF-008, RF-009)
CREATE TABLE funcionarios (
    idfuncionario INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    cargo VARCHAR(60) NOT NULL,
    salario_hora DECIMAL(10, 2) NOT NULL
);

CREATE TABLE registro_ponto (
    idponto INT PRIMARY KEY AUTO_INCREMENT,
    idfuncionario INT NOT NULL,
    entrada DATETIME NOT NULL,
    saida DATETIME NULL,
    ocorrencias TEXT, -- RF-008 (Atrasos, metas atingidas, quebra de protocolo)
    FOREIGN KEY (idfuncionario) REFERENCES funcionarios(idfuncionario)
);

CREATE TABLE folha_pagamento (
    idfolha INT PRIMARY KEY AUTO_INCREMENT,
    idfuncionario INT NOT NULL,
    mes_referencia VARCHAR(7) NOT NULL,
    horas_trabalhadas DECIMAL(6, 2) NOT NULL,
    bonus_descontos DECIMAL(10, 2) DEFAULT 0.00,
    salario_final DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (idfuncionario) REFERENCES funcionarios(idfuncionario)
);

-- Dados Iniciais para Teste
INSERT INTO produtos (nome_produto, descricao, custo_produto) VALUES
('Erva-Mate Tradicional Moída Grossa 1kg', 'Folhas selecionadas com secagem lenta para chimarrão autêntico.', 24.90),
('Chá Matte Tostado com Limão 250g', 'Ideal para preparar gelado. Sabor cítrico e refrescante.', 15.50),
('Blend Chá Verde com Hortelã e Mate 100g', 'Ervas orgânicas certificadas para infusão quente ou fria.', 19.90),
('Kit Tereré Premium (Erva Menta + Cuia + Bomba)', 'Kit completo para consumo de mate gelado.', 89.90),
('Caixa Degustação Mestre Chazeiro (10 Sabores)', 'Seleção especial de chás e mattes artesanais.', 145.00);

INSERT INTO estoque (idprodutos, codigo_lote, quantidade_inicial, quantidade_atual, quantidade_minima, validade_lote, verificacao_sanitaria, registro_alimentos) VALUES
(1, 'LOTE-MT01', 100, 85, 10, '2027-06-15', 1, 'Umidade 4.2% - Aprovado ANVISA'),
(2, 'LOTE-LM04', 100, 8,  10, '2027-03-20', 1, 'Torra média controlada - Lote abaixo de 10% (RF-005)'),
(3, 'LOTE-CV09', 50,  40, 5,  '2027-08-10', 1, 'Orgânico certificado - Sem resíduos'),
(4, 'LOTE-KT02', 30,  22, 3,  '2028-01-01', 1, 'Erva lacrada a vácuo + inox 304'),
(5, 'LOTE-DG05', 40,  35, 4,  '2027-05-30', 1, 'Inspeção sanitária OK');
