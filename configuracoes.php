<?php
/**
 * @file configuracoes.php
 * @brief Gestão de Definições de Marca e Sistema - TSTORE.
 */

include 'includes/db_connect.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']) || ($_SESSION['role'] ?? 1) != 0) {
    header("Location: index.php");
    exit();
}

$mensagem = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['save_config'])) {
    foreach ($_POST['config'] as $key => $value) {
        $stmt = $conn->prepare("UPDATE config SET param_value = ? WHERE param_key = ?");
        $stmt->bind_param("ss", $value, $key);
        $stmt->execute();
    }
    $mensagem = "Configurações guardadas com sucesso!";
    // Recarregar configurações após update
    $res_config = $conn->query("SELECT param_key, param_value FROM config");
    while ($row = $res_config->fetch_assoc()) {
        $settings[$row['param_key']] = $row['param_value'];
    }
}

// Buscar configs detalhadas para o form
$res_full = $conn->query("SELECT * FROM config ORDER BY tab_group, label");
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Configurações - TSTORE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css?v=1.3">
    <style>
        .config-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .config-group {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(188, 111, 241, 0.1);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
        }
        .config-group h3 {
            color: #bc6ff1;
            margin-bottom: 20px;
            font-size: 1.1rem;
            border-bottom: 1px solid rgba(188, 111, 241, 0.2);
            padding-bottom: 10px;
        }
        .config-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            gap: 20px;
        }
        .config-item label {
            flex: 1;
            text-transform: none;
            font-size: 0.9rem;
            font-weight: 400;
            color: #ccc;
        }
        .config-item input {
            width: 300px;
        }
        .success-bar {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            border: 1px solid #10b981;
        }
    </style>
</head>
<body>
    <div class="background-overlay"></div>
    <div class="wrapper">
        <?php include 'includes/navbar.php'; ?>

        <main class="content-area">
            <header class="table-header">
                <div style="display: flex; align-items: center; gap: 20px;">
                    <button class="nav-btn" onclick="location.href='index.php'"><i class="fas fa-arrow-left" style="margin:0;"></i></button>
                    <div>
                        <h2>Configurações do Sistema</h2>
                        <p>Personalize a identidade e parâmetros globais</p>
                    </div>
                </div>
            </header>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px;">
                <div class="config-group" style="margin-bottom: 0;">
                    <h3><i class="fas fa-percentage"></i> Sistema de IVA</h3>
                    <p style="color: #ccc; margin-bottom: 20px; font-size: 0.9rem;">Configure as taxas de imposto aplicáveis e a respetiva associação às categorias de produtos do seu inventário.</p>
                    <button class="nav-btn" onclick="location.href='configuracoes_iva.php'" style="width: 100%;">
                        <i class="fas fa-external-link-alt"></i> Gerir Taxas de IVA
                    </button>
                </div>

                <div class="config-group" style="margin-bottom: 0;">
                    <h3><i class="fas fa-envelope"></i> Servidor SMTP e Alertas</h3>
                    <p style="color: #ccc; margin-bottom: 20px; font-size: 0.9rem;">Defina as credenciais de envio de email para notificações de stock crítico e alertas de entrega de encomendas a clientes.</p>
                    <button class="nav-btn" onclick="location.href='configuracoes_email.php'" style="width: 100%;">
                        <i class="fas fa-external-link-alt"></i> Configurar Servidor de Email
                    </button>
                </div>
            </div>

            <?php if ($mensagem): ?><div class="success-bar"><?php echo $mensagem; ?></div><?php endif; ?>

            <div class="config-group">
                <h3><i class="fas fa-palette"></i> Identidade & Marca</h3>
                <p style="color: #ccc; margin-bottom: 25px; font-size: 0.9rem;">Definições de aparência e informações globais da loja.</p>
                
                <form method="POST">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                        <?php 
                        $res_full->data_seek(0);
                        while($row = $res_full->fetch_assoc()): 
                            if (in_array($row['label'], ['Limite de Stock Baixo', 'Símbolo da Moeda', 'Texto do Rodapé'])):
                        ?>
                            <div>
                                <label style="display: block; margin-bottom: 8px; color: #aaa; font-size: 0.9rem;"><?php echo $row['label']; ?></label>
                                <input type="text" name="config[<?php echo $row['param_key']; ?>]" value="<?php echo htmlspecialchars($row['param_value']); ?>" style="width: 100%;">
                            </div>
                        <?php 
                            endif;
                        endwhile; 
                        ?>
                    </div>
                    
                    <div style="margin-top: 30px; border-top: 1px solid rgba(188, 111, 241, 0.2); padding-top: 20px; text-align: right;">
                        <button type="submit" name="save_config" class="nav-btn active">
                            <i class="fas fa-save"></i> Guardar Alterações
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>
<script>

</script>
</body>
</html>
