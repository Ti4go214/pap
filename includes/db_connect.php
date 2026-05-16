<?php 

// Configurar timezone para Portugal (inclui horário de verão)
date_default_timezone_set('Europe/Lisbon');

$host = "127.0.0.1";
$user= "root";
$password= "123";
$bd = "pap";
$port = "3306";

$conn = new mysqli($host, $user, $password, $bd, $port);

if($conn->connect_error)
{
die("Conexão Falhou: " . $conn->connect_error);
}



/**
 * Regista uma ação no sistema de auditoria
 */
function registarLog($id_user, $acao, $detalhes = "") {
    global $conn;
    // Se não for passado um ID, tenta ir buscar à sessão
    if (empty($id_user) && isset($_SESSION['id_user'])) {
        $id_user = $_SESSION['id_user'];
    }
    
    $stmt = $conn->prepare("INSERT INTO logs (id_user, acao, detalhes) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $id_user, $acao, $detalhes);
    $stmt->execute();
}

// Carregar Configurações Globais (Abordagem robusta para configuração inicial)
$settings = [];
try {
    $res_config = $conn->query("SELECT param_key, param_value FROM config");
    if ($res_config) {
        while ($row = $res_config->fetch_assoc()) {
            $settings[$row['param_key']] = $row['param_value'];
        }
    }
} catch (mysqli_sql_exception $e) {
    // Tabela ainda não existe, usar fallbacks (normal durante a configuração)
}

// Valores de fallback
$currency = $settings['currency'] ?? '€';
$stock_limit = $settings['stock_low_limit'] ?? 5;

// Incluir notificações globalmente
include_once __DIR__ . '/../actions/notifications_actions.php';
?>
