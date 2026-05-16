<?php
/**
 * @file configuracoes_email.php
 * @brief Página de configuração do sistema de notificações por email
 * @author Antigravity
 * @date 2026-05-12
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'includes/db_connect.php';

if (!isset($_SESSION['user']) || ($_SESSION['role'] ?? 1) != 0) {
    header("Location: index.php");
    exit();
}

// Processar formulário
$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'atualizar_config') {
        $smtp_host = $_POST['smtp_host'] ?? '';
        $smtp_port = $_POST['smtp_port'] ?? 587;
        $smtp_username = $_POST['smtp_username'] ?? '';
        $smtp_password = $_POST['smtp_password'] ?? '';
        $smtp_from = $_POST['smtp_from'] ?? '';
        $smtp_from_name = $_POST['smtp_from_name'] ?? '';
        
        // Atualizar configurações na tabela config
        $configs = [
            'smtp_host' => $smtp_host,
            'smtp_port' => $smtp_port,
            'smtp_username' => $smtp_username,
            'smtp_password' => $smtp_password,
            'smtp_from' => $smtp_from,
            'smtp_from_name' => $smtp_from_name
        ];
        
        foreach ($configs as $key => $value) {
            $stmt = $conn->prepare("INSERT INTO config (param_key, param_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE param_value = VALUES(param_value)");
            $stmt->bind_param("ss", $key, $value);
            $stmt->execute();
        }
        
        registarLog($_SESSION['id_user'], 'CONFIG_EMAIL_ATUALIZADA', "Configurações de email atualizadas");
        $success = "Configurações de email atualizadas com sucesso!";
    } elseif ($action === 'testar_email') {
        $email_teste = $_POST['email_teste'] ?? '';
        
        if (!empty($email_teste)) {
            include __DIR__ . '/email_notifications.php';
            
            $mensagem = '<html><body>';
            $mensagem .= '<h2 style="color: #10b981;">✅ Teste de Configuração de Email</h2>';
            $mensagem .= '<p>Este é um email de teste para verificar se as configurações de SMTP estão corretas.</p>';
            $mensagem .= '<p><strong>Data do teste:</strong> ' . date('d/m/Y H:i:s') . '</p>';
            $mensagem .= '<p>Se recebeu este email, as configurações estão a funcionar corretamente!</p>';
            $mensagem .= '</body></html>';
            
            if (enviarEmail($email_teste, '✅ Teste de Email - TSTORE', $mensagem, true)) {
                $success = "Email de teste enviado com sucesso para $email_teste!";
            } else {
                $error = "Falha ao enviar email. Verifique as configurações de SMTP.";
            }
        } else {
            $error = "Digite um email válido para teste.";
        }
    } elseif ($action === 'atualizar_notificacoes') {
        $alertas_stock = isset($_POST['alertas_stock']) ? 1 : 0;
        $resumo_diario = isset($_POST['resumo_diario']) ? 1 : 0;
        $novas_encomendas = isset($_POST['novas_encomendas']) ? 1 : 0;
        $emails_admin = $_POST['emails_admin'] ?? '';
        
        $configs = [
            'email_alertas_stock' => $alertas_stock,
            'email_resumo_diario' => $resumo_diario,
            'email_novas_encomendas' => $novas_encomendas,
            'email_admins' => $emails_admin
        ];
        
        foreach ($configs as $key => $value) {
            $stmt = $conn->prepare("INSERT INTO config (param_key, param_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE param_value = VALUES(param_value)");
            $stmt->bind_param("ss", $key, $value);
            $stmt->execute();
        }
        
        registarLog($_SESSION['id_user'], 'CONFIG_NOTIFICACOES_ATUALIZADAS', "Configurações de notificações atualizadas");
        $success = "Configurações de notificações atualizadas com sucesso!";
    }
}

// Buscar configurações atuais
$config_atual = [
    'smtp_host' => $settings['smtp_host'] ?? '',
    'smtp_port' => $settings['smtp_port'] ?? 587,
    'smtp_username' => $settings['smtp_username'] ?? '',
    'smtp_password' => $settings['smtp_password'] ?? '',
    'smtp_from' => $settings['smtp_from'] ?? '',
    'smtp_from_name' => $settings['smtp_from_name'] ?? 'TSTORE Sistema',
    'alertas_stock' => $settings['email_alertas_stock'] ?? 0,
    'resumo_diario' => $settings['email_resumo_diario'] ?? 0,
    'novas_encomendas' => $settings['email_novas_encomendas'] ?? 0,
    'emails_admin' => $settings['email_admins'] ?? ''
];
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Configurações de Email - TSTORE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .config-section {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(188, 111, 241, 0.1);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .section-title {
            color: #bc6ff1;
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .config-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .input-group {
            margin-bottom: 15px;
        }
        
        .input-group label {
            display: block;
            margin-bottom: 5px;
            color: #ccc;
            font-size: 0.9rem;
        }
        
        .input-group input, .input-group textarea, .input-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid rgba(188, 111, 241, 0.3);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
            font-size: 0.9rem;
        }
        
        .input-group input:focus, .input-group textarea:focus, .input-group select:focus {
            outline: none;
            border-color: #bc6ff1;
            box-shadow: 0 0 0 3px rgba(188, 111, 241, 0.2);
        }
        
        .checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .checkbox-item input {
            width: 20px;
            height: 20px;
        }
        
        .checkbox-item label {
            color: #ccc;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .test-section {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .btn-testar {
            background: #10b981;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            transition: background 0.3s;
        }
        
        .btn-testar:hover {
            background: #059669;
        }
        
        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-left: 10px;
        }
        
        .status-ok { background: #10b981; }
        .status-error { background: #ef4444; }
    </style>
</head>
<body>
    <div class="background-overlay"></div>
    <div class="wrapper">
        <?php include 'includes/navbar.php'; ?>
        
        <main class="content-area">
            <header class="table-header">
                <div style="display: flex; align-items: center; gap: 20px;">
                    <button class="nav-btn" onclick="location.href='configuracoes.php'" title="Voltar">
                        <i class="fas fa-arrow-left" style="margin:0;"></i>
                    </button>
                    <div>
                        <h2>Configurações de Email</h2>
                        <p>Configurar notificações automáticas por email</p>
                    </div>
                </div>
            </header>

            <?php if ($success): ?>
                <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); color: #10b981; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div style="background: rgba(255, 75, 75, 0.1); border: 1px solid rgba(255, 75, 75, 0.3); color: #ff4b2b; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Configurações SMTP -->
            <div class="config-section">
                <h3 class="section-title">
                    <i class="fas fa-envelope"></i>
                    Configurações SMTP
                </h3>
                
                <form method="POST">
                    <input type="hidden" name="action" value="atualizar_config">
                    
                    <div class="config-grid">
                        <div class="input-group">
                            <label>Servidor SMTP *</label>
                            <input type="text" name="smtp_host" value="<?php echo htmlspecialchars($config_atual['smtp_host']); ?>" required>
                        </div>
                        
                        <div class="input-group">
                            <label>Porta SMTP *</label>
                            <input type="number" name="smtp_port" value="<?php echo $config_atual['smtp_port']; ?>" required>
                        </div>
                        
                        <div class="input-group">
                            <label>Utilizador *</label>
                            <input type="text" name="smtp_username" value="<?php echo htmlspecialchars($config_atual['smtp_username']); ?>" required>
                        </div>
                        
                        <div class="input-group">
                            <label>Password *</label>
                            <input type="password" name="smtp_password" value="<?php echo htmlspecialchars($config_atual['smtp_password']); ?>" required>
                        </div>
                        
                        <div class="input-group">
                            <label>Email Remetente *</label>
                            <input type="email" name="smtp_from" value="<?php echo htmlspecialchars($config_atual['smtp_from']); ?>" required>
                        </div>
                        
                        <div class="input-group">
                            <label>Nome Remetente</label>
                            <input type="text" name="smtp_from_name" value="<?php echo htmlspecialchars($config_atual['smtp_from_name']); ?>">
                        </div>
                    </div>
                    
                    <div style="text-align: right; margin-top: 20px;">
                        <button type="submit" class="nav-btn active">
                            <i class="fas fa-save"></i> Guardar Configurações
                        </button>
                    </div>
                </form>
            </div>

            <!-- Configurações de Notificações -->
            <div class="config-section">
                <h3 class="section-title">
                    <i class="fas fa-bell"></i>
                    Notificações Automáticas
                </h3>
                
                <form method="POST">
                    <input type="hidden" name="action" value="atualizar_notificacoes">
                    
                    <div class="checkbox-group">
                        <div class="checkbox-item">
                            <input type="checkbox" name="alertas_stock" id="alertas_stock" <?php echo $config_atual['alertas_stock'] ? 'checked' : ''; ?>>
                            <label for="alertas_stock">Alertas de Stock Baixo</label>
                            <span class="status-indicator <?php echo !empty($config_atual['smtp_host']) ? 'status-ok' : 'status-error'; ?>"></span>
                        </div>
                        
                        <div class="checkbox-item">
                            <input type="checkbox" name="resumo_diario" id="resumo_diario" <?php echo $config_atual['resumo_diario'] ? 'checked' : ''; ?>>
                            <label for="resumo_diario">Resumo Diário de Vendas (enviado às 08:00)</label>
                        </div>
                        
                        <div class="checkbox-item">
                            <input type="checkbox" name="novas_encomendas" id="novas_encomendas" <?php echo $config_atual['novas_encomendas'] ? 'checked' : ''; ?>>
                            <label for="novas_encomendas">Novas Encomendas Recebidas</label>
                        </div>
                    </div>
                    
                    <div class="input-group">
                        <label>Emails dos Administradores (separados por vírgula)</label>
                        <textarea name="emails_admin" rows="3" placeholder="admin@exemplo.com, gestor@exemplo.com"><?php echo htmlspecialchars($config_atual['emails_admin']); ?></textarea>
                    </div>
                    
                    <div style="text-align: right; margin-top: 20px;">
                        <button type="submit" class="nav-btn active">
                            <i class="fas fa-save"></i> Guardar Notificações
                        </button>
                    </div>
                </form>
            </div>

            <!-- Teste de Email -->
            <div class="test-section">
                <h3 style="color: #10b981; margin-bottom: 15px;">
                    <i class="fas fa-paper-plane"></i>
                    Testar Configurações
                </h3>
                
                <form method="POST" style="display: flex; gap: 10px; align-items: end;">
                    <input type="hidden" name="action" value="testar_email">
                    
                    <div class="input-group" style="margin: 0; flex: 1;">
                        <label>Email para Teste *</label>
                        <input type="email" name="email_teste" placeholder="Digite seu email para teste" required>
                    </div>
                    
                    <button type="submit" class="btn-testar">
                        <i class="fas fa-paper-plane"></i> Enviar Teste
                    </button>
                </form>
                
                <p style="margin-top: 15px; font-size: 0.9rem; color: #888;">
                    <i class="fas fa-info-circle"></i>
                    Após configurar o SMTP, envie um email de teste para verificar se tudo está funcionando corretamente.
                </p>
            </div>
        </main>
    </div>
</body>
</html>
