<?php
// Inicia a sessão para ter acesso às variáveis atuais
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Limpa todas as variáveis de sessão
$_SESSION = array();

// Se desejar destruir completamente a sessão, apague também o cookie da sessão.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destrói a sessão no servidor
session_destroy();

// Redireciona para a página de login (ajusta o caminho se necessário)
header("Location: login.php");
exit;
?>