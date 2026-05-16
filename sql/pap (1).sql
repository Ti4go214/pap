-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Mar 26, 2026 at 10:39 AM
-- Server version: 9.1.0
-- PHP Version: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `pap`
--

-- --------------------------------------------------------

--
-- Table structure for table `categoria`
--

DROP TABLE IF EXISTS `categoria`;
CREATE TABLE IF NOT EXISTS `categoria` (
  `id_categoria` varchar(20) NOT NULL,
  `descricao` varchar(40) NOT NULL,
  PRIMARY KEY (`id_categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `categoria`
--

INSERT INTO `categoria` (`id_categoria`, `descricao`) VALUES
('1', 'ratos'),
('cat_1', 'Computadores'),
('cat_2', 'Periféricos'),
('cat_3', 'Componentes PC'),
('cat_4', 'Redes'),
('cat_5', 'Software'),
('cat_6', 'Armazenamento'),
('cat_7', 'Acessórios'),
('cat_8', 'Áudio'),
('cat_9', 'Monitores');

-- --------------------------------------------------------

--
-- Table structure for table `clientes`
--

DROP TABLE IF EXISTS `clientes`;
CREATE TABLE IF NOT EXISTS `clientes` (
  `id_cliente` int NOT NULL,
  `nome` mediumint NOT NULL,
  `cpostal` varchar(10) NOT NULL,
  `morada` varchar(50) NOT NULL,
  PRIMARY KEY (`id_cliente`),
  KEY `cpostal` (`cpostal`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `config`
--

DROP TABLE IF EXISTS `config`;
CREATE TABLE IF NOT EXISTS `config` (
  `id_config` int NOT NULL AUTO_INCREMENT,
  `param_key` varchar(50) NOT NULL,
  `param_value` text,
  `label` varchar(100) DEFAULT NULL,
  `tab_group` varchar(30) DEFAULT 'GERAL',
  PRIMARY KEY (`id_config`),
  UNIQUE KEY `param_key` (`param_key`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=latin1;

--
-- Dumping data for table `config`
--

INSERT INTO `config` (`id_config`, `param_key`, `param_value`, `label`, `tab_group`) VALUES
(1, 'app_name', 'STORE', 'Nome da Aplicação', 'GERAL'),
(2, 'currency', '€', 'Símbolo da Moeda', 'GERAL'),
(3, 'stock_low_limit', '5', 'Limite de Stock Baixo', 'GERAL'),
(4, 'footer_text', '© 2026 TSTORE • Gestão Profissional', 'Texto do Rodapé', 'GERAL');

-- --------------------------------------------------------

--
-- Table structure for table `ent_cab`
--

DROP TABLE IF EXISTS `ent_cab`;
CREATE TABLE IF NOT EXISTS `ent_cab` (
  `n_cab` int NOT NULL,
  `cliente` varchar(255) NOT NULL,
  `data` datetime NOT NULL,
  PRIMARY KEY (`n_cab`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `ent_cab`
--

INSERT INTO `ent_cab` (`n_cab`, `cliente`, `data`) VALUES
(1, '0', '2026-02-06 11:51:00'),
(2, '0', '2026-02-06 11:51:00'),
(3, '0', '2026-02-24 12:15:00'),
(4, 'worten', '2026-03-06 08:23:00'),
(5, 'worten', '2026-03-06 10:22:00'),
(6, 'hp', '2026-03-08 15:31:00'),
(9, 'worten', '2026-03-08 15:32:00'),
(12, 'amazon', '2026-03-08 15:46:00'),
(13, 'amazon', '2026-03-16 17:44:00'),
(14, 'jorge', '2026-03-19 15:44:00');

-- --------------------------------------------------------

--
-- Table structure for table `fornecedores`
--

DROP TABLE IF EXISTS `fornecedores`;
CREATE TABLE IF NOT EXISTS `fornecedores` (
  `id_fornecedor` int NOT NULL,
  `nome` int NOT NULL,
  `cpostal` int NOT NULL,
  `morada` int NOT NULL,
  PRIMARY KEY (`id_fornecedor`),
  KEY `cpostal` (`cpostal`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `linhas`
--

DROP TABLE IF EXISTS `linhas`;
CREATE TABLE IF NOT EXISTS `linhas` (
  `id` int NOT NULL,
  `n_linha` int NOT NULL,
  `id_produto` int NOT NULL,
  `id_categoria` int NOT NULL,
  `descricao` int NOT NULL,
  `quantidade` int NOT NULL,
  `preço` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`,`n_linha`),
  KEY `id_produto` (`id_produto`),
  KEY `id_categoria` (`id_categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `linhas`
--

INSERT INTO `linhas` (`id`, `n_linha`, `id_produto`, `id_categoria`, `descricao`, `quantidade`, `preço`) VALUES
(1, 1, 1, 1, 1, 1, 50.00),
(2, 1, 1, 1, 1, 1, 50.00),
(3, 1, 1, 1, 1, 1, 50.00),
(4, 1, 1, 1, 1, 1, 50.00),
(5, 1, 1, 1, 1, 1, 50.00),
(6, 1, 1, 1, 1, 1, 50.00),
(7, 1, 1, 1, 1, 1, 47.50),
(8, 1, 1, 1, 1, 1, 50.00),
(9, 1, 1, 1, 1, 1, 50.00),
(10, 1, 1, 1, 1, 1, 50.00),
(11, 1, 1, 1, 1, 1, 50.00),
(12, 1, 1, 1, 1, 10, 50.00),
(13, 1, 1, 1, 1, 1, 50.00),
(14, 1, 12, 0, 12, 1, 85.50);

-- --------------------------------------------------------

--
-- Table structure for table `logs`
--

DROP TABLE IF EXISTS `logs`;
CREATE TABLE IF NOT EXISTS `logs` (
  `id_log` int NOT NULL AUTO_INCREMENT,
  `id_user` varchar(30) NOT NULL,
  `acao` varchar(50) NOT NULL,
  `detalhes` text,
  `data_hora` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_log`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1;

--
-- Dumping data for table `logs`
--

INSERT INTO `logs` (`id_log`, `id_user`, `acao`, `detalhes`, `data_hora`) VALUES
(1, 'tguerra', 'REGISTO_MOVIMENTO', 'Tipo: ENTRADA, Entidade: amazon, Doc: 13', '2026-03-16 17:44:48'),
(2, 'tguerra', 'REGISTO_MOVIMENTO', 'Tipo: ENTRADA, Entidade: jorge, Doc: 14', '2026-03-19 15:44:11');

-- --------------------------------------------------------

--
-- Table structure for table `notificacoes`
--

DROP TABLE IF EXISTS `notificacoes`;
CREATE TABLE IF NOT EXISTS `notificacoes` (
  `id_notificacao` int NOT NULL AUTO_INCREMENT,
  `id_user` varchar(30) DEFAULT NULL,
  `tipo` varchar(30) NOT NULL,
  `mensagem` text NOT NULL,
  `link` varchar(100) DEFAULT NULL,
  `lida` tinyint(1) DEFAULT '0',
  `data_criacao` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_notificacao`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=latin1;

--
-- Dumping data for table `notificacoes`
--

INSERT INTO `notificacoes` (`id_notificacao`, `id_user`, `tipo`, `mensagem`, `link`, `lida`, `data_criacao`) VALUES
(1, NULL, 'STOCK_BAIXO', 'Aviso: O produto \'Desktop Office Pro\' atingiu o stock crítico (5).', 'stock.php', 1, '2026-03-17 12:41:22'),
(2, NULL, 'STOCK_BAIXO', 'Aviso: O produto \'Switch TP-Link 24p Gigabit\' atingiu o stock crítico (4).', 'stock.php', 1, '2026-03-17 12:41:22'),
(3, NULL, 'STOCK_BAIXO', 'Aviso: O produto \'Desktop Office Pro\' atingiu o stock crítico (5).', 'stock.php', 1, '2026-03-17 12:41:32'),
(4, NULL, 'STOCK_BAIXO', 'Aviso: O produto \'Desktop Office Pro\' atingiu o stock crítico (5).', 'stock.php', 1, '2026-03-17 12:41:37'),
(5, NULL, 'STOCK_BAIXO', 'Aviso: O produto \'Switch TP-Link 24p Gigabit\' atingiu o stock crítico (4).', 'stock.php', 1, '2026-03-17 12:41:41'),
(6, NULL, 'STOCK_BAIXO', 'Aviso: O produto \'Switch TP-Link 24p Gigabit\' atingiu o stock crítico (4).', 'stock.php', 1, '2026-03-18 17:43:09'),
(7, NULL, 'STOCK_BAIXO', 'Aviso: O produto \'Switch TP-Link 24p Gigabit\' atingiu o stock crítico (4).', 'stock.php', 1, '2026-03-19 10:19:00'),
(8, NULL, 'STOCK_BAIXO', 'Aviso: O produto \'Desktop Office Pro\' atingiu o stock crítico (5).', 'stock.php', 1, '2026-03-19 10:19:12'),
(9, NULL, 'STOCK_BAIXO', 'Aviso: O produto \'Switch TP-Link 24p Gigabit\' atingiu o stock crítico (4).', 'stock.php', 1, '2026-03-19 10:19:12'),
(10, NULL, 'STOCK_BAIXO', 'Aviso: O produto \'Desktop Office Pro\' atingiu o stock crítico (5).', 'stock.php', 1, '2026-03-19 10:19:35'),
(11, '1', 'STOCK_BAIXO', 'Aviso: O produto \'Desktop Office Pro\' atingiu o stock crítico (5).', 'stock.php', 1, '2026-03-19 10:36:01'),
(12, '1', 'STOCK_BAIXO', 'Aviso: O produto \'Switch TP-Link 24p Gigabit\' atingiu o stock crítico (4).', 'stock.php', 1, '2026-03-19 10:36:01'),
(13, '6985cfd2529c4', 'STOCK_BAIXO', 'Aviso: O produto \'Desktop Office Pro\' atingiu o stock crítico (5).', 'stock.php', 1, '2026-03-19 10:39:51'),
(14, '6985cfd2529c4', 'STOCK_BAIXO', 'Aviso: O produto \'Switch TP-Link 24p Gigabit\' atingiu o stock crítico (4).', 'stock.php', 0, '2026-03-19 10:39:51'),
(15, '6985cfd2529c4', 'STOCK_BAIXO', 'Aviso: O produto \'Desktop Office Pro\' atingiu o stock crítico (5).', 'stock.php', 0, '2026-03-24 11:29:26'),
(16, '1', 'STOCK_BAIXO', 'Aviso: O produto \'Desktop Office Pro\' atingiu o stock crítico (5).', 'stock.php', 1, '2026-03-24 11:29:33'),
(17, '1', 'STOCK_BAIXO', 'Aviso: O produto \'Switch TP-Link 24p Gigabit\' atingiu o stock crítico (4).', 'stock.php', 0, '2026-03-24 11:29:33');

-- --------------------------------------------------------

--
-- Table structure for table `produtos`
--

DROP TABLE IF EXISTS `produtos`;
CREATE TABLE IF NOT EXISTS `produtos` (
  `id_produto` int NOT NULL AUTO_INCREMENT,
  `id_categoria` varchar(20) NOT NULL,
  `quantidade` int NOT NULL,
  `preco_unit` decimal(12,2) NOT NULL,
  `descricao` varchar(50) NOT NULL,
  PRIMARY KEY (`id_produto`,`id_categoria`),
  KEY `id_categoria` (`id_categoria`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=latin1;

--
-- Dumping data for table `produtos`
--

INSERT INTO `produtos` (`id_produto`, `id_categoria`, `quantidade`, `preco_unit`, `descricao`) VALUES
(1, '1', 16, 50.00, 'rato hyperx'),
(3, 'cat_1', 10, 1200.00, 'Portátil Gaming Nitro 5'),
(4, 'cat_1', 5, 599.00, 'Desktop Office Pro'),
(5, 'cat_2', 20, 45.90, 'Rato Logitech G502'),
(6, 'cat_2', 15, 89.00, 'Teclado Mecânico Razer BlackWidow'),
(7, 'cat_3', 8, 649.00, 'Placa Gráfica RTX 4070'),
(8, 'cat_3', 12, 399.00, 'Processador Ryzen 7 7800X3D'),
(9, 'cat_4', 15, 119.90, 'Router ASUS Wi-Fi 6'),
(10, 'cat_4', 4, 210.00, 'Switch TP-Link 24p Gigabit'),
(11, 'cat_5', 50, 139.00, 'Windows 11 Home Retail'),
(12, 'cat_6', 31, 85.50, 'SSD Samsung 980 Pro 1TB'),
(13, 'cat_9', 10, 249.00, 'Monitor LG UltraGear 27\"'),
(14, 'cat_8', 12, 129.00, 'Auscultadores HyperX Cloud II');

-- --------------------------------------------------------

--
-- Table structure for table `sai_cab`
--

DROP TABLE IF EXISTS `sai_cab`;
CREATE TABLE IF NOT EXISTS `sai_cab` (
  `n_cab` int NOT NULL,
  `cliente` varchar(255) NOT NULL,
  `data` datetime NOT NULL,
  PRIMARY KEY (`n_cab`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `sai_cab`
--

INSERT INTO `sai_cab` (`n_cab`, `cliente`, `data`) VALUES
(1, 'worten', '2026-03-06 10:22:00'),
(2, 'hp', '2026-03-08 15:28:00'),
(7, 'worten', '2026-03-08 15:31:00'),
(8, 'worten', '2026-03-08 15:31:00'),
(10, 'a', '2026-03-08 15:32:00'),
(11, 'a', '2026-03-08 15:46:00');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id_user` varchar(30) NOT NULL,
  `username` varchar(30) NOT NULL,
  `pwd` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `role` varchar(15) NOT NULL,
  PRIMARY KEY (`id_user`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id_user`, `username`, `pwd`, `role`) VALUES
('1', 'tguerra', '$2y$10$ymzt1eqnRhxcWcxMoRsfdOgyQQ.hxyex/vugvtz73v9gFYe7pOxYi', '0'),
('6985cfd2529c4', 'reigota', '$2y$10$GK12d1wvwF6lYYuua96lMO2prAb6nMjMVRJglcy19NgXEri7a2FHK', '1');

--
-- Constraints for dumped tables
--

--
-- Constraints for table `produtos`
--
ALTER TABLE `produtos`
  ADD CONSTRAINT `produtos_ibfk_1` FOREIGN KEY (`id_categoria`) REFERENCES `categoria` (`id_categoria`) ON DELETE RESTRICT ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
