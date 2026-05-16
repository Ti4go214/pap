<?php
/**
 * @file adicionar_registos_exemplo.php
 * @brief Adicionar registos imaginários de clientes e fornecedores
 * @author Antigravity
 * @date 2026-04-28
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'includes/db_connect.php';

// Proteção - apenas admin
if (!isset($_SESSION['user']) || ($_SESSION['role'] ?? 1) != 0) {
    die("Acesso negado. Apenas administradores podem executar esta ação.");
}

echo "<!DOCTYPE html>
<html lang='pt'>
<head>
    <meta charset='UTF-8'>
    <title>Adicionar Registos de Exemplo</title>
    <link rel='stylesheet' href='assets/css/styles.css'>
</head>
<body>
    <div class='background-overlay'></div>
    <div class='wrapper'>
        <main class='content-area'>
            <h2>Adicionar Registos de Exemplo</h2>";

// Clientes imaginários
$clientes_exemplo = [
    ['nome' => 'Worten', 'nif' => '500000000', 'email' => 'compras@worten.pt', 'telefone' => '707500000', 'morada' => 'Avenida da Liberdade, 100', 'cpostal' => '1250-146', 'cidade' => 'Lisboa'],
    ['nome' => 'Fnac', 'nif' => '500000001', 'email' => 'parcerias@fnac.pt', 'telefone' => '707200000', 'morada' => 'Rua do Carmo, 50', 'cpostal' => '1200-092', 'cidade' => 'Lisboa'],
    ['nome' => 'MediaMarkt', 'nif' => '500000002', 'email' => 'procurement@mediamarkt.pt', 'telefone' => '707300000', 'morada' => 'Avenida da República, 200', 'cpostal' => '1050-055', 'cidade' => 'Lisboa'],
    ['nome' => 'PcComponentes', 'nif' => '500000003', 'email' => 'compras@pccomponentes.pt', 'telefone' => '210000000', 'morada' => 'Rua da Indústria, 300', 'cpostal' => '2635-051', 'cidade' => 'Rio de Mouro'],
    ['nome' => 'GlobalData', 'nif' => '500000004', 'email' => 'it@globaldata.pt', 'telefone' => '220000000', 'morada' => 'Rua do Comércio, 75', 'cpostal' => '4000-099', 'cidade' => 'Porto'],
    ['nome' => 'TechSolutions', 'nif' => '500000005', 'email' => 'admin@techsolutions.pt', 'telefone' => '230000000', 'morada' => 'Avenida Central, 150', 'cpostal' => '3000-001', 'cidade' => 'Coimbra'],
    ['nome' => 'Innovation Corp', 'nif' => '500000006', 'email' => 'procurement@innovation.pt', 'telefone' => '240000000', 'morada' => 'Rua da Tecnologia, 50', 'cpostal' => '4470-057', 'cidade' => 'Maia'],
    ['nome' => 'Digital Systems', 'nif' => '500000007', 'email' => 'orders@digitalsystems.pt', 'telefone' => '250000000', 'morada' => 'Avenida do Futuro, 200', 'cpostal' => '2795-011', 'cidade' => 'Linda-a-Velha'],
    ['nome' => 'Cloud Computing Ltd', 'nif' => '500000008', 'email' => 'sales@cloudcomputing.pt', 'telefone' => '260000000', 'morada' => 'Rua das Nuvens, 100', 'cpostal' => '2855-123', 'cidade' => 'Corroios'],
    ['nome' => 'Smart Tech', 'nif' => '500000009', 'email' => 'info@smarttech.pt', 'telefone' => '270000000', 'morada' => 'Avenida da Inovação, 75', 'cpostal' => '1990-099', 'cidade' => 'Lisboa'],
];

// Fornecedores imaginários
$fornecedores_exemplo = [
    ['nome' => 'Dell Portugal', 'nif' => '510000000', 'email' => 'partners@dell.com', 'telefone' => '800100000', 'morada' => 'Parque das Nações, Edifício 1', 'cpostal' => '1990-099', 'cidade' => 'Lisboa', 'contacto_principal' => 'João Silva', 'email_contacto' => 'joao.silva@dell.com'],
    ['nome' => 'HP Portugal', 'nif' => '510000001', 'email' => 'channel@hp.com', 'telefone' => '800200000', 'morada' => 'Avenida da Boavista, 1500', 'cpostal' => '4050-114', 'cidade' => 'Porto', 'contacto_principal' => 'Maria Santos', 'email_contacto' => 'maria.santos@hp.com'],
    ['nome' => 'Lenovo Portugal', 'nif' => '510000002', 'email' => 'business@lenovo.com', 'telefone' => '800300000', 'morada' => 'Rua Augusta, 50', 'cpostal' => '1100-051', 'cidade' => 'Lisboa', 'contacto_principal' => 'Pedro Costa', 'email_contacto' => 'pedro.costa@lenovo.com'],
    ['nome' => 'Asus Portugal', 'nif' => '510000003', 'email' => 'resellers@asus.com', 'telefone' => '800400000', 'morada' => 'Avenida dos Aliados, 200', 'cpostal' => '4000-065', 'cidade' => 'Porto', 'contacto_principal' => 'Ana Rodrigues', 'email_contacto' => 'ana.rodrigues@asus.com'],
    ['nome' => 'Microsoft Portugal', 'nif' => '510000004', 'email' => 'partners@microsoft.com', 'telefone' => '800500000', 'morada' => 'Avenida da Liberdade, 180', 'cpostal' => '1250-146', 'cidade' => 'Lisboa', 'contacto_principal' => 'Carlos Ferreira', 'email_contacto' => 'carlos.ferreira@microsoft.com'],
    ['nome' => 'Intel Portugal', 'nif' => '510000005', 'email' => 'distributors@intel.com', 'telefone' => '800600000', 'morada' => 'Rua do Comércio, 120', 'cpostal' => '4000-099', 'cidade' => 'Porto', 'contacto_principal' => 'Luís Martins', 'email_contacto' => 'luis.martins@intel.com'],
    ['nome' => 'Samsung Portugal', 'nif' => '510000006', 'email' => 'b2b@samsung.com', 'telefone' => '800700000', 'morada' => 'Avenida da República, 300', 'cpostal' => '1050-055', 'cidade' => 'Lisboa', 'contacto_principal' => 'Sofia Almeida', 'email_contacto' => 'sofia.almeida@samsung.com'],
    ['nome' => 'LG Portugal', 'nif' => '510000007', 'email' => 'partners@lg.com', 'telefone' => '800800000', 'morada' => 'Rua da Indústria, 400', 'cpostal' => '2635-051', 'cidade' => 'Rio de Mouro', 'contacto_principal' => 'Ricardo Pereira', 'email_contacto' => 'ricardo.pereira@lg.com'],
    ['nome' => 'Western Digital', 'nif' => '510000008', 'email' => 'resellers@wd.com', 'telefone' => '800900000', 'morada' => 'Avenida Central, 250', 'cpostal' => '3000-001', 'cidade' => 'Coimbra', 'contacto_principal' => 'Beatriz Carvalho', 'email_contacto' => 'beatriz.carvalho@wd.com'],
    ['nome' => 'Seagate Portugal', 'nif' => '510000009', 'email' => 'distribution@seagate.com', 'telefone' => '801000000', 'morada' => 'Rua da Tecnologia, 100', 'cpostal' => '4470-057', 'cidade' => 'Maia', 'contacto_principal' => 'Tiago Gomes', 'email_contacto' => 'tiago.gomes@seagate.com'],
];

// Inserir clientes
echo "<h3>1. Inserindo clientes de exemplo...</h3>";
$clientes_inseridos = 0;

foreach ($clientes_exemplo as $cliente) {
    // Verificar se já existe
    $check = $conn->prepare("SELECT id_cliente FROM clientes WHERE nome = ?");
    $check->bind_param("s", $cliente['nome']);
    $check->execute();
    $check_result = $check->get_result();
    
    if ($check_result->num_rows == 0) {
        // Guardar valores em variáveis para bind_param
        $nome = $cliente['nome'];
        $nif = $cliente['nif'];
        $email = $cliente['email'];
        $telefone = $cliente['telefone'];
        $morada = $cliente['morada'];
        $cpostal = $cliente['cpostal'];
        $cidade = $cliente['cidade'];
        $pais = $cliente['pais'] ?? 'Portugal';
        
        $insert = $conn->prepare("INSERT INTO clientes (nome, nif, email, telefone, morada, cpostal, cidade, pais) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $insert->bind_param("ssssssss", $nome, $nif, $email, $telefone, $morada, $cpostal, $cidade, $pais);
        if ($insert->execute()) {
            $clientes_inseridos++;
            echo "<p style='color: green;'>✓ Cliente inserido: " . htmlspecialchars($cliente['nome']) . "</p>";
        }
    } else {
        echo "<p style='color: #aaa;'>- Cliente já existe: " . htmlspecialchars($cliente['nome']) . "</p>";
    }
}

echo "<p><strong>Total de clientes inseridos: $clientes_inseridos</strong></p>";

// Inserir fornecedores
echo "<h3>2. Inserindo fornecedores de exemplo...</h3>";
$fornecedores_inseridos = 0;

foreach ($fornecedores_exemplo as $fornecedor) {
    // Verificar se já existe
    $check = $conn->prepare("SELECT id_fornecedor FROM fornecedores WHERE nome = ?");
    $check->bind_param("s", $fornecedor['nome']);
    $check->execute();
    $check_result = $check->get_result();
    
    if ($check_result->num_rows == 0) {
        // Guardar valores em variáveis para bind_param
        $nome = $fornecedor['nome'];
        $nif = $fornecedor['nif'];
        $email = $fornecedor['email'];
        $telefone = $fornecedor['telefone'];
        $morada = $fornecedor['morada'];
        $cpostal = $fornecedor['cpostal'];
        $cidade = $fornecedor['cidade'];
        $pais = $fornecedor['pais'] ?? 'Portugal';
        $contacto_principal = $fornecedor['contacto_principal'];
        $email_contacto = $fornecedor['email_contacto'];
        
        $insert = $conn->prepare("INSERT INTO fornecedores (nome, nif, email, telefone, morada, cpostal, cidade, pais, contacto_principal, email_contacto) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $insert->bind_param("ssssssssss", $nome, $nif, $email, $telefone, $morada, $cpostal, $cidade, $pais, $contacto_principal, $email_contacto);
        if ($insert->execute()) {
            $fornecedores_inseridos++;
            echo "<p style='color: green;'>✓ Fornecedor inserido: " . htmlspecialchars($fornecedor['nome']) . "</p>";
        }
    } else {
        echo "<p style='color: #aaa;'>- Fornecedor já existe: " . htmlspecialchars($fornecedor['nome']) . "</p>";
    }
}

echo "<p><strong>Total de fornecedores inseridos: $fornecedores_inseridos</strong></p>";

echo "<h3 style='color: green;'>✓ Registos de exemplo adicionados com sucesso!</h3>";
echo "<p><a href='gestao.php' class='nav-btn active' style='display: inline-block; margin-top: 20px;'>Voltar à Gestão</a></p>";
echo "</main></div></body></html>";
?>
