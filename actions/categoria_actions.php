<?php
/**
 * @file  categoria_actions.php
 * @brief Backend handler para operações CRUD nas Categorias.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include __DIR__ . '/../includes/db_connect.php';

// Apenas admins podem realizar estas ações
if (!isset($_SESSION['user']) || ($_SESSION['role'] ?? 1) != 0) {
    header("Location: ../login.php");
    exit();
}

$referer = $_SERVER['HTTP_REFERER'] ?? 'categorias.php';

// =============== APAGAR CATEGORIA ===============
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    
    // Uma Foreign Key na Base de Dados (ON DELETE RESTRICT) impedirá a eliminação 
    // se existirem produtos com esta categoria. Podemos capturar o erro para dar 
    // um feedback mais amigável.
    try {
        $stmt = $conn->prepare("DELETE FROM categoria WHERE id_categoria = ?");
        $stmt->bind_param("s", $id);
        
        if ($stmt->execute()) {
            $_SESSION['msg'] = "Categoria apagada com sucesso.";
            $_SESSION['msg_type'] = "success";
        } else {
            $_SESSION['msg'] = "Erro ao apagar categoria.";
            $_SESSION['msg_type'] = "error";
        }
    } catch (mysqli_sql_exception $e) {
        // Erro 1451: Cannot delete or update a parent row: a foreign key constraint fails
        if ($e->getCode() == 1451) {
            $_SESSION['msg'] = "Não é possível apagar a categoria porque existem produtos associados a ela.";
        } else {
            $_SESSION['msg'] = "Erro de base de dados: " . $e->getMessage();
        }
        $_SESSION['msg_type'] = "error";
    }
    header("Location: " . $referer);
    exit();
}

// =============== GUARDAR (CRIAR / EDITAR) ===============
if (isset($_POST['btn_save_categoria'])) {
    
    $old_id = $_POST['old_id_categoria'] ?? '';
    $id = trim($_POST['id_categoria'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    
    // Validações básicas
    if (empty($id) || empty($descricao)) {
        header("Location: ../categorias.php?erro=Campos_obrigatórios_em_falta");
        exit();
    }
    
    if (empty($old_id)) {
        // É UMA NOVA CATEGORIA
        // 1. Verificar se id já existe
        $check = $conn->prepare("SELECT id_categoria FROM categoria WHERE id_categoria = ?");
        $check->bind_param("s", $id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            header("Location: ../categorias.php?erro=ID_já_existe");
            exit();
        }
        
        $ins = $conn->prepare("INSERT INTO categoria (id_categoria, descricao) VALUES (?, ?)");
        $ins->bind_param("ss", $id, $descricao);
        $ins->execute();
    } else {
        // É UMA EDIÇÃO (apenas descrição, o ID não pode mudar devido a restrições relacionais)
        $upd = $conn->prepare("UPDATE categoria SET descricao = ? WHERE id_categoria = ?");
        $upd->bind_param("ss", $descricao, $old_id);
        $upd->execute();
    }

    header("Location: ../categorias.php");
    exit();
}

// Se chegar aqui sem post/get, volta.
header("Location: ../categorias.php");
exit();
