-- Tabelas para o sistema de promoções e descontos

-- Tabela principal de promoções
CREATE TABLE IF NOT EXISTS promocoes (
    id_promocao INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    tipo ENUM('percentagem', 'fixo') NOT NULL,
    valor_desconto DECIMAL(10,2) NOT NULL,
    data_inicio DATE NOT NULL,
    data_fim DATE NOT NULL,
    codigo_promocional VARCHAR(20) NULL,
    minimo_compra DECIMAL(10,2) DEFAULT 0,
    utilizacoes_maximas INT DEFAULT 0,
    ativo TINYINT(1) DEFAULT 1,
    data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de associação entre promoções e categorias
CREATE TABLE IF NOT EXISTS promocoes_categorias (
    id_promocao INT NOT NULL,
    id_categoria INT NOT NULL,
    PRIMARY KEY (id_promocao, id_categoria),
    FOREIGN KEY (id_promocao) REFERENCES promocoes(id_promocao) ON DELETE CASCADE,
    FOREIGN KEY (id_categoria) REFERENCES categoria(id_categoria) ON DELETE CASCADE
);

-- Tabela de associação entre promoções e produtos
CREATE TABLE IF NOT EXISTS promocoes_produtos (
    id_promocao INT NOT NULL,
    id_produto INT NOT NULL,
    PRIMARY KEY (id_promocao, id_produto),
    FOREIGN KEY (id_promocao) REFERENCES promocoes(id_promocao) ON DELETE CASCADE,
    FOREIGN KEY (id_produto) REFERENCES produtos(id_produto) ON DELETE CASCADE
);

-- Tabela para registar utilização de códigos promocionais
CREATE TABLE IF NOT EXISTS promocoes_utilizacoes (
    id_utilizacao INT AUTO_INCREMENT PRIMARY KEY,
    id_promocao INT NOT NULL,
    id_cliente INT NULL,
    id_encomenda INT NULL,
    data_utilizacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    valor_desconto_aplicado DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (id_promocao) REFERENCES promocoes(id_promocao) ON DELETE CASCADE,
    FOREIGN KEY (id_cliente) REFERENCES clientes(id_cliente) ON DELETE SET NULL,
    FOREIGN KEY (id_encomenda) REFERENCES encomendas(id_encomenda) ON DELETE SET NULL
);

-- Índices para melhor performance
CREATE INDEX IF NOT EXISTS idx_promocoes_ativo ON promocoes(ativo);
CREATE INDEX IF NOT EXISTS idx_promocoes_datas ON promocoes(data_inicio, data_fim);
CREATE INDEX IF NOT EXISTS idx_promocoes_codigo ON promocoes(codigo_promocional);
CREATE INDEX IF NOT EXISTS idx_promocoes_utilizacoes_data ON promocoes_utilizacoes(data_utilizacao);
CREATE INDEX IF NOT EXISTS idx_promocoes_utilizacoes_cliente ON promocoes_utilizacoes(id_cliente);
