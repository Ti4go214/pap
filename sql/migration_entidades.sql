-- Migração de Estrutura de Entidades (Clientes e Fornecedores)
-- Execute este ficheiro via phpMyAdmin ou importação SQL

-- 1. Remover tabelas existentes (se existirem)
DROP TABLE IF EXISTS clientes;
DROP TABLE IF EXISTS fornecedores;

-- 2. Criar tabela clientes com estrutura correta
CREATE TABLE IF NOT EXISTS clientes (
  id_cliente INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(100) NOT NULL,
  nif VARCHAR(20),
  email VARCHAR(100),
  telefone VARCHAR(20),
  morada VARCHAR(200),
  cpostal VARCHAR(10),
  cidade VARCHAR(50),
  pais VARCHAR(50) DEFAULT 'Portugal',
  data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
  data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Criar tabela fornecedores com estrutura correta
CREATE TABLE IF NOT EXISTS fornecedores (
  id_fornecedor INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(100) NOT NULL,
  nif VARCHAR(20),
  email VARCHAR(100),
  telefone VARCHAR(20),
  morada VARCHAR(200),
  cpostal VARCHAR(10),
  cidade VARCHAR(50),
  pais VARCHAR(50) DEFAULT 'Portugal',
  contacto_principal VARCHAR(100),
  email_contacto VARCHAR(100),
  data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
  data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Inserir dados de exemplo
INSERT INTO clientes (nome, nif, email, telefone, morada, cpostal, cidade) 
VALUES ('João Silva', '123456789', 'joao.silva@email.com', '912345678', 'Rua das Flores, 123', '1000-001', 'Lisboa');

INSERT INTO fornecedores (nome, nif, email, telefone, morada, cpostal, cidade, contacto_principal) 
VALUES ('Tech Distribuidora', '987654321', 'contacto@techdist.pt', '213456789', 'Avenida da Indústria, 456', '2000-000', 'Porto', 'João Costa');
