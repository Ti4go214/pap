<?php
/**
 * @file conta.php
 * @brief Perfil do Cliente e Histórico de Encomendas (Loja Externa)
 * @author Antigravity
 * @date 2026-05-17
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../includes/db_connect.php';

// Verificar se está logado
if (!isset($_SESSION['cliente_logado'])) {
    header("Location: login.php");
    exit();
}

$id_cliente = (int)$_SESSION['cliente_logado'];

// Lidar com Logout
if (isset($_GET['logout'])) {
    unset($_SESSION['cliente_logado']);
    unset($_SESSION['cliente_nome']);
    unset($_SESSION['cliente_email']);
    header("Location: index.php");
    exit();
}

// Obter dados do cliente
$stmt = $conn->prepare("SELECT * FROM clientes WHERE id_cliente = ?");
$stmt->bind_param("i", $id_cliente);
$stmt->execute();
$cliente = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Obter encomendas do cliente
$stmt_enc = $conn->prepare("SELECT * FROM encomendas WHERE id_cliente = ? ORDER BY data_encomenda DESC, id_encomenda DESC");
$stmt_enc->bind_param("i", $id_cliente);
$stmt_enc->execute();
$encomendas = $stmt_enc->get_result();
$stmt_enc->close();

function getEstadoBadge($estado) {
    switch (strtolower($estado)) {
        case 'pendente': return '<span class="estado-badge estado-pendente"><i class="fas fa-clock"></i> Pendente</span>';
        case 'em_processamento': return '<span class="estado-badge estado-processamento"><i class="fas fa-box"></i> Em Processamento</span>';
        case 'enviado': return '<span class="estado-badge estado-enviado"><i class="fas fa-truck"></i> Enviado</span>';
        case 'entregue': return '<span class="estado-badge estado-entregue"><i class="fas fa-check-double"></i> Entregue</span>';
        case 'cancelado': return '<span class="estado-badge estado-cancelado"><i class="fas fa-times-circle"></i> Cancelado</span>';
        default: return '<span class="estado-badge">'.$estado.'</span>';
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>A Minha Conta • TSTORE</title>
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
            --border-color: #e2e8f0;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.05);
            --radius-lg: 16px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }

        body { background-color: var(--bg-main); color: var(--text-main); }

        .store-header {
            background-color: var(--bg-card);
            border-bottom: 1px solid var(--border-color);
            position: sticky; top: 0; z-index: 100;
        }

        .header-container {
            max-width: 1280px; margin: 0 auto; padding: 15px 30px;
            display: flex; align-items: center; justify-content: space-between;
        }

        .store-logo {
            font-size: 1.5rem; font-weight: 800; color: var(--text-main);
            text-decoration: none; display: flex; align-items: center; gap: 10px;
        }

        .store-logo i { color: var(--primary); }

        .btn-logout {
            color: #ef4444; background: #fee2e2; padding: 10px 20px;
            border-radius: 30px; font-weight: 700; text-decoration: none;
            display: inline-flex; align-items: center; gap: 8px;
            transition: all 0.3s;
        }
        .btn-logout:hover { background: #ef4444; color: #fff; }

        .btn-back {
            color: var(--text-muted); background: transparent; padding: 10px 20px;
            border-radius: 30px; font-weight: 700; text-decoration: none;
            display: inline-flex; align-items: center; gap: 8px; border: 1px solid var(--border-color);
        }

        .main-container { max-width: 1280px; margin: 40px auto; padding: 0 30px; display: grid; grid-template-columns: 350px 1fr; gap: 40px; }

        .card {
            background: var(--bg-card); border: 1px solid var(--border-color);
            border-radius: var(--radius-lg); padding: 30px; box-shadow: var(--shadow-sm);
        }

        .profile-avatar {
            width: 80px; height: 80px; background: var(--primary-light);
            color: var(--primary); border-radius: 50%; display: flex;
            align-items: center; justify-content: center; font-size: 2.5rem;
            margin-bottom: 20px; border: 3px solid #ecfdf5; box-shadow: var(--shadow-sm);
        }

        .info-group { margin-bottom: 15px; }
        .info-group label { display: block; font-size: 0.85rem; color: var(--text-muted); font-weight: 600; margin-bottom: 4px; }
        .info-group p { font-size: 1rem; color: var(--text-main); font-weight: 500; }

        .section-title { font-size: 1.5rem; font-weight: 800; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; }
        .section-title i { color: var(--primary); }

        .encomenda-card {
            background: var(--bg-main); border: 1px solid var(--border-color);
            border-radius: 12px; padding: 20px; margin-bottom: 20px;
            display: flex; justify-content: space-between; align-items: center;
        }

        .enc-numero { font-size: 1.2rem; font-weight: 800; color: #6366f1; margin-bottom: 5px; }
        .enc-data { font-size: 0.9rem; color: var(--text-muted); }
        .enc-total { font-size: 1.3rem; font-weight: 800; color: var(--text-main); }
        
        .estado-badge { padding: 6px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; }
        .estado-pendente { background: #fef3c7; color: #d97706; }
        .estado-processamento { background: #dbeafe; color: #2563eb; }
        .estado-enviado { background: #dcfce7; color: #16a34a; }
        .estado-entregue { background: #10b981; color: #ffffff; }
        .estado-cancelado { background: #fee2e2; color: #ef4444; }

    </style>
</head>
<body>

    <header class="store-header">
        <div class="header-container">
            <a href="index.php" class="store-logo">
                <i class="fas fa-bag-shopping"></i> TSTORE
            </a>
            <div style="display: flex; gap: 15px;">
                <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Voltar à Loja</a>
                <a href="conta.php?logout=1" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Terminar Sessão</a>
            </div>
        </div>
    </header>

    <main class="main-container">
        <!-- Coluna Esquerda: Perfil -->
        <aside>
            <div class="card">
                <div class="profile-avatar"><i class="fas fa-user"></i></div>
                <h2 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 25px;"><?php echo htmlspecialchars($cliente['nome']); ?></h2>
                
                <div class="info-group">
                    <label>Email</label>
                    <p><?php echo htmlspecialchars($cliente['email']); ?></p>
                </div>
                <div class="info-group">
                    <label>Telefone</label>
                    <p><?php echo htmlspecialchars($cliente['telefone'] ?? 'Não definido'); ?></p>
                </div>
                <div class="info-group">
                    <label>Morada de Entrega</label>
                    <p><?php echo htmlspecialchars($cliente['morada'] ?? 'Não definida'); ?></p>
                    <p><?php echo htmlspecialchars(($cliente['cpostal'] ?? '') . ' ' . ($cliente['localidade'] ?? '')); ?></p>
                </div>

                <?php if (($cliente['desconto_pendente'] ?? 0) == 1): ?>
                    <div style="margin-top: 25px; padding: 15px; background: #fef3c7; border: 1px solid #fde68a; border-radius: 12px; color: #d97706; font-weight: 600; font-size: 0.9rem; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-gift" style="font-size: 1.5rem;"></i>
                        <div>Tem um desconto de 10% disponível para a próxima encomenda!</div>
                    </div>
                <?php endif; ?>
            </div>
        </aside>

        <!-- Coluna Direita: Encomendas -->
        <section>
            <div class="card">
                <h2 class="section-title"><i class="fas fa-box-open"></i> O Meu Histórico de Encomendas</h2>
                
                <?php if ($encomendas && $encomendas->num_rows > 0): ?>
                    <?php while ($enc = $encomendas->fetch_assoc()): ?>
                        <div class="encomenda-card">
                            <div>
                                <div class="enc-numero"><?php echo htmlspecialchars($enc['num_encomenda']); ?></div>
                                <div class="enc-data"><i class="far fa-calendar-alt"></i> Realizada a <?php echo date('d/m/Y', strtotime($enc['data_encomenda'])); ?></div>
                            </div>
                            <div style="text-align: right;">
                                <div class="enc-total"><?php echo number_format($enc['total_liquido'], 2, ',', '.'); ?> €</div>
                                <div style="margin-top: 8px;"><?php echo getEstadoBadge($enc['estado']); ?></div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 60px 20px;">
                        <i class="fas fa-receipt" style="font-size: 4rem; color: #cbd5e1; margin-bottom: 20px;"></i>
                        <h3 style="font-size: 1.3rem; color: var(--text-main); margin-bottom: 10px;">Ainda não tem encomendas</h3>
                        <p style="color: var(--text-muted); margin-bottom: 25px;">Quando fizer uma compra na nossa loja, ela aparecerá aqui.</p>
                        <a href="index.php" class="btn-logout" style="background: var(--primary); color: white; padding: 14px 28px;">Ir para a Loja</a>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
</body>
</html>
