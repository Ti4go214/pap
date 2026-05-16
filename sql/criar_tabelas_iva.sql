-- Tabelas para o sistema de gestão de IVA

-- Tabela de taxas de IVA
CREATE TABLE IF NOT EXISTS iva_taxas (
    id_taxa INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL,
    taxa DECIMAL(5,2) NOT NULL,
    descricao TEXT,
    padrao TINYINT(1) DEFAULT 0,
    data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Adicionar coluna de IVA à tabela de categorias (se ainda não existir)
ALTER TABLE categoria 
ADD COLUMN IF NOT EXISTS id_iva INT NULL AFTER id_categoria;

-- Criar chave estrangeira para IVA nas categorias
ALTER TABLE categoria 
ADD CONSTRAINT fk_categoria_iva 
FOREIGN KEY (id_iva) REFERENCES iva_taxas(id_taxa) ON DELETE SET NULL;

-- Adicionar colunas de IVA à tabela de produtos (se ainda não existirem)
ALTER TABLE produtos 
ADD COLUMN IF NOT EXISTS preco_com_iva DECIMAL(10,2) DEFAULT 0 AFTER preco_unit,
ADD COLUMN IF NOT EXISTS id_iva INT NULL AFTER id_categoria;

-- Criar chave estrangeira para IVA nos produtos
ALTER TABLE produtos 
ADD CONSTRAINT fk_produto_iva 
FOREIGN KEY (id_iva) REFERENCES iva_taxas(id_taxa) ON DELETE SET NULL;

-- Adicionar colunas de IVA às tabelas de movimentos (se ainda não existirem)
ALTER TABLE sai_linhas 
ADD COLUMN IF NOT EXISTS valor_iva DECIMAL(10,2) DEFAULT 0 AFTER preço,
ADD COLUMN IF NOT EXISTS valor_total_iva DECIMAL(10,2) DEFAULT 0 AFTER valor_iva;

ALTER TABLE ent_linhas 
ADD COLUMN IF NOT EXISTS valor_iva DECIMAL(10,2) DEFAULT 0 AFTER preço,
ADD COLUMN IF NOT EXISTS valor_total_iva DECIMAL(10,2) DEFAULT 0 AFTER valor_iva;

-- Adicionar colunas de IVA à tabela de encomendas (se ainda não existirem)
ALTER TABLE encomendas_linhas 
ADD COLUMN IF NOT EXISTS valor_iva DECIMAL(10,2) DEFAULT 0 AFTER preco_unitario,
ADD COLUMN IF NOT EXISTS valor_total_iva DECIMAL(10,2) DEFAULT 0 AFTER valor_iva;

-- Índices para melhor performance
CREATE INDEX IF NOT EXISTS idx_iva_taxas_padrao ON iva_taxas(padrao);
CREATE INDEX IF NOT EXISTS idx_categoria_iva ON categoria(id_iva);
CREATE INDEX IF NOT EXISTS idx_produto_iva ON produtos(id_iva);

-- Inserir taxas de IVA padrão (Portugal Continental)
INSERT IGNORE INTO iva_taxas (nome, taxa, descricao, padrao) VALUES
('Normal', 23.00, 'Taxa normal de IVA - Portugal Continental', 1),
('Intermédio', 13.00, 'Taxa intermédia de IVA', 0),
('Reduzido', 6.00, 'Taxa reduzida de IVA', 0),
('Isento', 0.00, 'Isento de IVA', 0);
