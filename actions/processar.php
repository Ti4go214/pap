<?php
include __DIR__ . '/../includes/db_connect.php';

// ADICIONAR / EDITAR
if (isset($_POST['btn_save'])) {
    $user = $_POST['user'];
    $pwd = $_POST['pwd'];
    $nome = $_POST['nome'] ?? '';
    $morada = $_POST['morada'] ?? '';
    $postal = $_POST['postal'] ?? '';
    $nascimento = $_POST['nascimento'] ?? '';
    $nif = $_POST['nif'] ?? '';
    $contacto = $_POST['contacto'] ?? '';
    $pais = $_POST['pais'] ?? 'Portugal';
    $localidade = $_POST['localidade'] ?? '';
    $role = $_POST['role'] ?? '1'; // Usar o role enviado, default para 1 (utilizador)

    // Verificar se o utilizador existe
    $check = $conn->query("SELECT * FROM users WHERE username = '$user'");
    if ($check->num_rows > 0) {
        // ATUALIZAR
        $sql = "UPDATE users SET 
                    role='$role',
                    nome='$nome',
                    morada='$morada',
                    postal='$postal',
                    nascimento='$nascimento',
                    nif='$nif',
                    contacto='$contacto',
                    pais='$pais',
                    localidade='$localidade'";
        
        if (!empty($pwd)) {
            $hash = password_hash($pwd, PASSWORD_DEFAULT);
            $sql .= ", pwd='$hash'";
        }
        
        $sql .= " WHERE username='$user'";
        $conn->query($sql);

    } else {
        // INSERIR
        $hash = password_hash($pwd, PASSWORD_DEFAULT);
        $id = uniqid();

        $sql = "INSERT INTO users (id_user, username, pwd, role, nome, morada, postal, nascimento, nif, contacto, pais, localidade) 
                VALUES ('$id', '$user', '$hash', '$role', '$nome', '$morada', '$postal', '$nascimento', '$nif', '$contacto', '$pais', '$localidade')";
        $conn->query($sql);
    }

    header("Location: ../utilizadores.php");
    exit();
}

// ELIMINAR
if (isset($_GET['delete'])) {
    $user = $_GET['delete'];
    // Impedir de eliminar a si mesmo? (Segurança opcional)
    $conn->query("DELETE FROM users WHERE username = '$user'");
    header("Location: ../utilizadores.php");
    exit();
}
?>