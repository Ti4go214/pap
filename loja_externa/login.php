<?php
/**
 * @file login.php
 * @brief Login e Registo de Clientes da Loja Externa
 * @author Antigravity
 * @date 2026-05-17
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../includes/db_connect.php';

// Redireciona se já estiver logado
if (isset($_SESSION['cliente_logado'])) {
    header("Location: index.php");
    exit();
}

$erro = "";
$sucesso = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        $email = trim($_POST['email'] ?? '');
        $pwd = $_POST['pwd'] ?? '';

        if (!empty($email) && !empty($pwd)) {
            $stmt = $conn->prepare("SELECT id_cliente, nome, password_hash FROM clientes WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($cli = $res->fetch_assoc()) {
                if (empty($cli['password_hash'])) {
                    $erro = "Esta conta não tem uma palavra-passe definida. Utilize a recuperação de conta (Em breve).";
                } elseif (password_verify($pwd, $cli['password_hash'])) {
                    $_SESSION['cliente_logado'] = $cli['id_cliente'];
                    $_SESSION['cliente_nome'] = $cli['nome'];
                    $_SESSION['cliente_email'] = $email;
                    header("Location: index.php");
                    exit();
                } else {
                    $erro = "Palavra-passe incorreta.";
                }
            } else {
                $erro = "Não existe nenhuma conta associada a este email.";
            }
            $stmt->close();
        } else {
            $erro = "Por favor preencha todos os campos.";
        }
    } elseif ($action === 'registar') {
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $pwd = $_POST['pwd'] ?? '';
        $pwd_confirm = $_POST['pwd_confirm'] ?? '';

        if (!empty($nome) && !empty($email) && !empty($pwd) && !empty($pwd_confirm)) {
            if ($pwd !== $pwd_confirm) {
                $erro = "As palavras-passe não coincidem.";
            } else {
                // Verificar se o email já existe
                $stmt_check = $conn->prepare("SELECT id_cliente, password_hash FROM clientes WHERE email = ? LIMIT 1");
                $stmt_check->bind_param("s", $email);
                $stmt_check->execute();
                $res_check = $stmt_check->get_result();
                
                $hash = password_hash($pwd, PASSWORD_DEFAULT);

                if ($cli_existente = $res_check->fetch_assoc()) {
                    if (empty($cli_existente['password_hash'])) {
                        // Cliente existe (criado no backend ou checkout antigo) mas não tem password. Reclamar conta!
                        $stmt_upd = $conn->prepare("UPDATE clientes SET password_hash = ? WHERE id_cliente = ?");
                        $stmt_upd->bind_param("si", $hash, $cli_existente['id_cliente']);
                        if ($stmt_upd->execute()) {
                            $sucesso = "A sua conta antiga foi ativada com sucesso! Pode agora iniciar sessão.";
                        } else {
                            $erro = "Erro ao ativar conta existente.";
                        }
                        $stmt_upd->close();
                    } else {
                        $erro = "Este email já está registado e possui uma palavra-passe. Por favor inicie sessão.";
                    }
                } else {
                    // Criar cliente totalmente novo
                    $stmt_ins = $conn->prepare("INSERT INTO clientes (nome, email, password_hash, pais) VALUES (?, ?, ?, 'Portugal')");
                    $stmt_ins->bind_param("sss", $nome, $email, $hash);
                    
                    if ($stmt_ins->execute()) {
                        $sucesso = "Conta criada com sucesso! Pode agora iniciar sessão.";
                    } else {
                        $erro = "Erro ao criar conta: " . $conn->error;
                    }
                    $stmt_ins->close();
                }
                $stmt_check->close();
            }
        } else {
            $erro = "Por favor preencha todos os campos.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso Cliente • TSTORE</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --bg-main: #f8fafc;
            --bg-card: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --primary: #10b981;
            --primary-hover: #059669;
            --primary-light: #ecfdf5;
            --border-color: #e2e8f0;
            --shadow-lg: 0 10px 25px -5px rgba(0,0,0,0.05), 0 8px 10px -6px rgba(0,0,0,0.02);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }

        body {
            background-color: var(--bg-main);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .auth-container {
            width: 100%;
            max-width: 450px;
            background: var(--bg-card);
            border-radius: 24px;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
            overflow: hidden;
            position: relative;
        }

        .auth-header {
            text-align: center;
            padding: 40px 30px 20px 30px;
        }

        .store-logo {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--text-main);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .store-logo i { color: var(--primary); }

        .auth-subtitle {
            color: var(--text-muted);
            font-size: 1rem;
        }

        .auth-tabs {
            display: flex;
            border-bottom: 1px solid var(--border-color);
        }

        .auth-tab {
            flex: 1;
            text-align: center;
            padding: 15px;
            font-weight: 700;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.3s;
            border-bottom: 3px solid transparent;
        }

        .auth-tab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        .auth-body {
            padding: 30px;
        }

        .auth-form { display: none; }
        .auth-form.active { display: block; animation: fadeIn 0.4s ease; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .input-group {
            margin-bottom: 20px;
        }

        .input-group label {
            display: block;
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 8px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }

        .input-wrapper input {
            width: 100%;
            padding: 14px 20px 14px 45px;
            background-color: var(--bg-main);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            font-size: 1rem;
            color: var(--text-main);
            outline: none;
            transition: all 0.3s;
        }

        .input-wrapper input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px var(--primary-light);
            background-color: var(--bg-card);
        }

        .btn-submit {
            width: 100%;
            padding: 16px;
            background-color: var(--primary);
            border: none;
            border-radius: 12px;
            color: #ffffff;
            font-weight: 800;
            font-size: 1.05rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.25);
            margin-top: 10px;
        }

        .btn-submit:hover {
            background-color: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
        }

        .alert {
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
        }

        .alert-error { background: #fee2e2; color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }
        .alert-success { background: var(--primary-light); color: var(--primary); border: 1px solid rgba(16, 185, 129, 0.2); }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 25px;
            color: var(--text-muted);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            transition: color 0.3s;
        }

        .back-link:hover { color: var(--primary); }
    </style>
</head>
<body>

    <div class="auth-container">
        <div class="auth-header">
            <a href="index.php" class="store-logo">
                <i class="fas fa-bag-shopping"></i> TSTORE
            </a>
            <p class="auth-subtitle">Aceda à sua área de cliente</p>
        </div>

        <div class="auth-tabs">
            <div class="auth-tab active" onclick="switchTab('login')">Iniciar Sessão</div>
            <div class="auth-tab" onclick="switchTab('registar')">Criar Conta</div>
        </div>

        <div class="auth-body">
            <?php if (!empty($erro)): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $erro; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($sucesso)): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $sucesso; ?></div>
            <?php endif; ?>

            <!-- Formulário de Login -->
            <form method="POST" class="auth-form active" id="form-login">
                <input type="hidden" name="action" value="login">
                
                <div class="input-group">
                    <label>Email</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" required placeholder="O seu email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                </div>

                <div class="input-group">
                    <label>Palavra-passe</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="pwd" required placeholder="A sua palavra-passe">
                    </div>
                </div>

                <button type="submit" class="btn-submit">Entrar <i class="fas fa-arrow-right"></i></button>
            </form>

            <!-- Formulário de Registo -->
            <form method="POST" class="auth-form" id="form-registar">
                <input type="hidden" name="action" value="registar">
                
                <div class="input-group">
                    <label>Nome Completo</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user"></i>
                        <input type="text" name="nome" required placeholder="Ex: João Silva" value="<?php echo htmlspecialchars($_POST['nome'] ?? ''); ?>">
                    </div>
                </div>

                <div class="input-group">
                    <label>Email</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" required placeholder="O seu email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                </div>

                <div class="input-group">
                    <label>Palavra-passe</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="pwd" required placeholder="Crie uma palavra-passe" minlength="6">
                    </div>
                </div>

                <div class="input-group">
                    <label>Confirmar Palavra-passe</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="pwd_confirm" required placeholder="Repita a palavra-passe" minlength="6">
                    </div>
                </div>

                <button type="submit" class="btn-submit">Criar Conta</button>
            </form>
            
            <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Voltar à Loja</a>
        </div>
    </div>

    <script>
        function switchTab(tabId) {
            document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.auth-form').forEach(f => f.classList.remove('active'));
            
            if (tabId === 'login') {
                document.querySelectorAll('.auth-tab')[0].classList.add('active');
                document.getElementById('form-login').classList.add('active');
            } else {
                document.querySelectorAll('.auth-tab')[1].classList.add('active');
                document.getElementById('form-registar').classList.add('active');
            }
        }
        
        <?php if (isset($_POST['action']) && $_POST['action'] === 'registar' && empty($sucesso)): ?>
            switchTab('registar');
        <?php endif; ?>
    </script>
</body>
</html>
