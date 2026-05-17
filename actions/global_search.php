<?php
header('Content-Type: application/json');
include __DIR__ . '/../includes/db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$query = $_GET['q'] ?? '';

if (strlen($query) < 2) {
    echo json_encode(['success' => true, 'results' => []]);
    exit();
}

$results = [];
$searchTerm = "%$query%";

// 1. Pesquisar em Produtos
try {
    $stmt = $conn->prepare("SELECT id_produto as id, 'Produto' as type, descricao as title, preco_unit as subtitle, 'stock.php' as link FROM produtos WHERE descricao LIKE ? LIMIT 5");
    $stmt->bind_param("s", $searchTerm);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $row['subtitle'] = number_format($row['subtitle'], 2) . ' €';
        $row['link'] = "stock.php?id=" . $row['id'];
        $results[] = $row;
    }
} catch (Exception $e) {
    // Log error or ignore
}

// 2. Pesquisar em Clientes
try {
    $stmt = $conn->prepare("SELECT id_cliente as id, 'Cliente' as type, nome as title, email as subtitle, 'clientes.php' as link FROM clientes WHERE nome LIKE ? OR email LIKE ? LIMIT 5");
    $stmt->bind_param("ss", $searchTerm, $searchTerm);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $row['link'] = "clientes.php?id=" . $row['id'];
        $results[] = $row;
    }
} catch (Exception $e) {
}

// 3. Pesquisar em Fornecedores
try {
    $stmt = $conn->prepare("SELECT id_fornecedor as id, 'Fornecedor' as type, nome as title, email as subtitle, 'fornecedores.php' as link FROM fornecedores WHERE nome LIKE ? OR email LIKE ? LIMIT 5");
    $stmt->bind_param("ss", $searchTerm, $searchTerm);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $row['link'] = "fornecedores.php?id=" . $row['id'];
        $results[] = $row;
    }
} catch (Exception $e) {
}

// 4. Pesquisar em Utilizadores (Apenas para Admin)
if (isset($_SESSION['role']) && $_SESSION['role'] == 0) {
    try {
        $stmt = $conn->prepare("SELECT id_user as id, 'Utilizador' as type, username as title, nome as subtitle, 'utilizadores.php' as link FROM users WHERE username LIKE ? OR nome LIKE ? LIMIT 5");
        $stmt->bind_param("ss", $searchTerm, $searchTerm);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $row['link'] = "utilizadores.php?id=" . $row['id'];
            $results[] = $row;
        }
    } catch (Exception $e) {
    }
}

// 5. Pesquisar em Categorias (Apenas para Admin)
if (isset($_SESSION['role']) && $_SESSION['role'] == 0) {
    try {
        $stmt = $conn->prepare("SELECT id_categoria as id, 'Categoria' as type, descricao as title, '' as subtitle, 'categorias.php' as link FROM categoria WHERE descricao LIKE ? LIMIT 5");
        $stmt->bind_param("s", $searchTerm);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $row['link'] = "categorias.php?id=" . $row['id'];
            $results[] = $row;
        }
    } catch (Exception $e) {
    }
}

echo json_encode(['success' => true, 'results' => $results]);
?>
