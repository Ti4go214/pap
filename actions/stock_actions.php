<?php
include __DIR__ . '/../includes/db_connect.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']) || (($_SESSION['role'] ?? 1) != 0 && ($_SESSION['role'] ?? 1) != 2)) {
    header("Location: ../index.php");
    exit();
}

if (isset($_POST['btn_save_stock'])) {
    $id = $_POST['id_produto']; // Vazio se novo, tem valor se a editar
    $descricao = $_POST['descricao'];
    $categoria = $_POST['categoria'];
    $quantidade = $_POST['quantidade'];
    $preco = $_POST['preco'];

    // Tratamento de Upload de Imagem
    $imagem_final = "";
    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] == 0) {
        $uploadDir = __DIR__ . '/../assets/img/products/';
        $ext = pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION);
        $fileName = time() . '_' . uniqid() . '.' . $ext;
        $targetPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['imagem']['tmp_name'], $targetPath)) {
            $imagem_final = $fileName;
        }
    }

    if (!empty($id)) {
        // ATUALIZAR
        if (!empty($imagem_final)) {
            $stmt = $conn->prepare("UPDATE produtos SET descricao=?, id_categoria=?, quantidade=?, preco_unit=?, imagem=? WHERE id_produto=?");
            $stmt->bind_param("ssidsi", $descricao, $categoria, $quantidade, $preco, $imagem_final, $id);
        } else {
            $stmt = $conn->prepare("UPDATE produtos SET descricao=?, id_categoria=?, quantidade=?, preco_unit=? WHERE id_produto=?");
            $stmt->bind_param("ssidi", $descricao, $categoria, $quantidade, $preco, $id);
        }

        if ($stmt->execute()) {
            registarLog($_SESSION['id_user'], "ATUALIZACAO_PRODUTO", "Editou o produto ID: $id ($descricao)");
        }
        $stmt->close();
    } else {
        // INSERIR
        if (empty($imagem_final)) $imagem_final = "default_product.png";
        
        $stmt = $conn->prepare("INSERT INTO produtos (descricao, id_categoria, quantidade, preco_unit, imagem) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssids", $descricao, $categoria, $quantidade, $preco, $imagem_final);

        if ($stmt->execute()) {
             registarLog($_SESSION['id_user'], "CRIACAO_PRODUTO", "Criou o produto: $descricao");
        }
        $stmt->close();
    }

    header("Location: ../stock.php");
    exit();
}

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];

    $stmt = $conn->prepare("DELETE FROM produtos WHERE id_produto = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        registarLog($_SESSION['id_user'], "ELIMINACAO_PRODUTO", "Eliminou o produto ID: $id");
    }
    $stmt->close();

    header("Location: ../stock.php");
    exit();
}
?>