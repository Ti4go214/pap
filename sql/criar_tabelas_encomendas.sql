-- Tabelas para o sistema de encomendas/vendas

-- Tabela principal de encomendas
CREATE TABLE IF NOT EXISTS encomendas (
    id_encomenda INT AUTO_INCREMENT PRIMARY KEY,
    num_encomenda VARCHAR(20) NOT NULL UNIQUE,
    id_cliente INT NOT NULL,
    data_encomenda DATE NOT NULL,
    estado ENUM('pendente', 'processamento', 'enviado', 'entregue', 'cancelado') DEFAULT 'pendente',
    observacoes TEXT,
    data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_cliente) REFERENCES clientes(id_cliente) ON DELETE RESTRICT
);

-- Tabela das linhas das encomendas
CREATE TABLE IF NOT EXISTS encomendas_linhas (
    id_linha INT AUTO_INCREMENT PRIMARY KEY,
    id_encomenda INT NOT NULL,
    id_produto INT NOT NULL,
    quantidade INT NOT NULL,
    preco_unitario DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (id_encomenda) REFERENCES encomendas(id_encomenda) ON DELETE CASCADE,
    FOREIGN KEY (id_produto) REFERENCES produtos(id_produto) ON DELETE RESTRICT
);

-- Índices para melhor performance
CREATE INDEX IF NOT EXISTS idx_encomendas_cliente ON encomendas(id_cliente);
CREATE INDEX IF NOT EXISTS idx_encomendas_estado ON encomendas(estado);
CREATE INDEX IF NOT EXISTS idx_encomendas_data ON encomendas(data_encomenda);
CREATE INDEX IF NOT EXISTS idx_encomendas_numero ON encomendas(num_encomenda);
CREATE INDEX IF NOT EXISTS idx_encomendas_linhas_encomenda ON encomendas_linhas(id_encomenda);
CREATE INDEX IF NOT EXISTS idx_encomendas_linhas_produto ON encomendas_linhas(id_produto);
