<?php
// Garantir que as notificações estão carregadas
include_once __DIR__ . '/../actions/notifications_actions.php';
checkStockAlerts();
$notif_count = getUnreadCount();
$role = $_SESSION["role"] ?? 1;
$is_admin = ($role == 0);

// Detectar se estamos numa subpasta (como /actions/ ou /includes/) para ajustar os links
$current_path = $_SERVER['PHP_SELF'];
$path_prefix = (strpos($current_path, '/actions/') !== false || strpos($current_path, '/includes/') !== false) ? '../' : '';
?>
<nav class="navbar">
    <div class="nav-logo" onclick="location.href='<?php echo $path_prefix; ?>index.php'" style="cursor:pointer">
        <i class="fas fa-ghost"></i><span class="t-letter">T</span><span class="store-text">STORE</span>
    </div>

    <button class="hamburger" onclick="toggleMobileMenu()">
        <i class="fas fa-bars"></i>
    </button>

    <div class="nav-links" id="navLinks">
        <button class="nav-btn <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>" onclick="location.href='<?php echo $path_prefix; ?>index.php'">
            <i class="fas fa-chart-line"></i> Dashboard
        </button>
        <button class="nav-btn <?php echo (basename($_SERVER['PHP_SELF']) == 'stock.php') ? 'active' : ''; ?>" onclick="location.href='<?php echo $path_prefix; ?>stock.php'">
            <i class="fas fa-boxes-stacked"></i> Stock
        </button>
        <button class="nav-btn <?php echo (basename($_SERVER['PHP_SELF']) == 'movimentos.php') ? 'active' : ''; ?>" onclick="location.href='<?php echo $path_prefix; ?>movimentos.php'">
            <i class="fas fa-exchange-alt"></i> Movimentos
        </button>
        
        <div class="nav-dropdown <?php echo in_array(basename($_SERVER['PHP_SELF']), ['encomendas.php','encomendas_fornecedores.php']) ? 'active' : ''; ?>">
            <button class="nav-btn nav-dropdown-toggle <?php echo in_array(basename($_SERVER['PHP_SELF']), ['encomendas.php','encomendas_fornecedores.php']) ? 'active' : ''; ?>" onclick="toggleNavDropdown(event)">
                <i class="fas fa-shopping-cart"></i> Encomendas <i class="fas fa-chevron-down nav-dropdown-arrow"></i>
            </button>
            <div class="nav-dropdown-menu">
                <a href="<?php echo $path_prefix; ?>encomendas.php" class="nav-dropdown-item <?php echo basename($_SERVER['PHP_SELF']) == 'encomendas.php' ? 'active' : ''; ?>">
                    <i class="fas fa-receipt"></i> Vendas (Clientes)
                </a>
                <a href="<?php echo $path_prefix; ?>encomendas_fornecedores.php" class="nav-dropdown-item <?php echo basename($_SERVER['PHP_SELF']) == 'encomendas_fornecedores.php' ? 'active' : ''; ?>">
                    <i class="fas fa-truck"></i> Encomendas a Fornecedores
                </a>
            </div>
        </div>

        <?php if ($is_admin): ?>
            <button class="nav-btn <?php echo (basename($_SERVER['PHP_SELF']) == 'gestao.php') ? 'active' : ''; ?>" onclick="location.href='<?php echo $path_prefix; ?>gestao.php'">
                <i class="fas fa-sliders"></i> Gestão
            </button>
        <?php endif; ?>
    </div>

    <div class="nav-right">
        <div class="notification-bell-container">
            <div class="notification-bell" onclick="openSearch()" title="Pesquisa Global (Ctrl+K)" style="margin-right: 15px; background: rgba(188, 111, 241, 0.1);">
                <i class="fas fa-search"></i>
            </div>
            <div class="notification-bell" onclick="toggleNotifications()" title="Notificações">
                <i class="fas fa-bell"></i>
                <?php if ($notif_count > 0): ?><span class="bell-badge"><?php echo $notif_count; ?></span><?php endif; ?>
            </div>

            <div class="notif-dropdown" id="notifDropdown">
                <div class="notif-header">
                    <span><i class="fas fa-bell"></i> Alertas</span>
                </div>
                <div class="notif-body">
                    <?php
                    $notifs = getUnreadNotifications();
                    if ($notifs && $notifs->num_rows > 0) {
                        while ($n = $notifs->fetch_assoc()) {
                            ?>
                            <div class="notif-item">
                                <div class="notif-content">
                                    <div class="notif-msg"><?php echo htmlspecialchars($n['mensagem']); ?></div>
                                    <div class="notif-date">
                                        <?php echo date('d/m H:i', strtotime($n['data_criacao'])); ?></div>
                                </div>
                                <div class="notif-actions">
                                    <?php if (!empty($n['link'])): ?>
                                        <a href="<?php echo htmlspecialchars($n['link']); ?>" class="notif-action view" title="Ver Detalhes"><i class="fas fa-external-link-alt"></i></a>
                                    <?php endif; ?>
                                    <a href="javascript:void(0)"
                                        onclick="dismissNotification(<?php echo $n['id_notificacao']; ?>, this)"
                                        class="notif-action" title="Marcar como lida"><i class="fas fa-check"></i></a>
                                </div>
                            </div>
                            <?php
                        }
                    } else {
                        echo "<div class='no-notifs'>Sem alertas pendentes.</div>";
                    }
                    ?>
                </div>
            </div>
        </div>
        <span class="user-name clickable" onclick="location.href='<?php echo $path_prefix; ?>perfil.php'" title="Ver Perfil">
            <i class="fas fa-user-circle"></i>
            <?php echo htmlspecialchars($_SESSION["user"]); ?>
            <span class="role-badge" style="background: <?php 
                $role = $_SESSION['role'] ?? 1;
                if ($role == 0) echo '#bc6ff1';
                elseif ($role == 2) echo '#10b981';
                else echo '#4b5563';
            ?>;">
                <?php 
                $role = $_SESSION['role'] ?? 1;
                if ($role == 0) echo 'ADMIN';
                elseif ($role == 2) echo 'VENDEDOR';
                else echo 'USER';
                ?>
            </span>
        </span>
        <!-- Botão de tema removido (Modo Escuro Fixo) -->

        <button class="nav-btn logout-btn" onclick="showConfirm('Deseja realmente sair do sistema?', () => location.href='<?php echo $path_prefix; ?>logout.php', 'Sair', 'fa-power-off')">
            <i class="fas fa-power-off"></i> Sair
        </button>
    </div>
</nav>

<!-- Floating Action Button (Quick Actions) -->
<div class="fab-container">
    <div class="fab-options">
        <div class="fab-option" onclick="location.href='<?php echo $path_prefix; ?>registar_movimento.php?tipo=SAIDA'">
            <i class="fas fa-cart-plus"></i>
            <span class="fab-label">Novo Movimento</span>
        </div>
        <div class="fab-option" onclick="location.href='<?php echo $path_prefix; ?>stock.php?action=new'">
            <i class="fas fa-box-open"></i>
            <span class="fab-label">Novo Produto</span>
        </div>
        <div class="fab-option" onclick="location.href='<?php echo $path_prefix; ?>clientes.php?action=new'">
            <i class="fas fa-user-plus"></i>
            <span class="fab-label">Novo Cliente</span>
        </div>
    </div>
    <div class="fab-main">
        <i class="fas fa-plus"></i>
    </div>
</div>

<!-- Modal de Pesquisa Global -->
<div id="globalSearchModal" class="search-modal">
    <div class="search-container">
        <div class="search-input-wrapper">
            <i class="fas fa-search"></i>
            <input type="text" id="globalSearchInput" placeholder="O que procura? (Produtos, Clientes, Fornecedores...)" autocomplete="off">
        </div>
        <div id="globalSearchResults" class="search-results">
            <!-- Resultados aparecem aqui -->
            <div style="padding: 40px; text-align: center; opacity: 0.5;">
                <i class="fas fa-keyboard" style="font-size: 2rem; margin-bottom: 10px;"></i>
                <p>Comece a escrever para pesquisar...</p>
            </div>
        </div>
        <div class="search-hint">
            <span><span>ESC</span> para fechar</span>
            <span><span>&crarr;</span> para selecionar</span>
        </div>
    </div>
</div>

<!-- Modal de Confirmação Premium (Substitui o confirm nativo) -->
<div id="customConfirmModal" class="confirm-modal">
    <div class="confirm-content">
        <div class="confirm-icon" id="confirmIcon">
            <i class="fas fa-question-circle"></i>
        </div>
        <h3 id="confirmTitle">Tem a certeza?</h3>
        <p id="confirmMessage">Esta ação não pode ser desfeita.</p>
        <div class="confirm-btns">
            <button class="confirm-btn cancel" id="confirmCancelBtn">Cancelar</button>
            <button class="confirm-btn ok" id="confirmOkBtn">Confirmar</button>
        </div>
    </div>
</div>

<!-- Container para Notificações Toast -->
<div id="toastContainer" class="toast-container"></div>

<script>
// Sistema Global de Toasts
function showToast(message, type = 'success', duration = 3000) {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        info: 'fa-info-circle'
    };

    toast.innerHTML = `
        <i class="fas ${icons[type]}"></i>
        <div class="toast-msg">${message}</div>
        <div class="toast-progress"></div>
    `;

    container.appendChild(toast);
    setTimeout(() => toast.classList.add('show'), 10);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 500);
    }, duration);
}

// Lógica de Temas (Fixa em Modo Escuro)
function toggleTheme() {
    document.body.setAttribute('data-theme', 'dark');
    localStorage.setItem('theme', 'dark');
}

function updateThemeIcons(theme) {}

// Pesquisa Global
let searchModal, searchInput, searchResults;

// Sistema Global de Confirmação Premium
let confirmCallback = null;

function showConfirm(message, onConfirm, title = "Confirmação", icon = "fa-question-circle") {
    const modal = document.getElementById('customConfirmModal');
    const msgEl = document.getElementById('confirmMessage');
    const titleEl = document.getElementById('confirmTitle');
    const iconEl = document.getElementById('confirmIcon');
    
    msgEl.innerText = message;
    titleEl.innerText = title;
    iconEl.innerHTML = `<i class="fas ${icon}"></i>`;
    
    modal.classList.add('show');
    confirmCallback = onConfirm;
}

document.getElementById('confirmCancelBtn').addEventListener('click', () => {
    document.getElementById('customConfirmModal').classList.remove('show');
    confirmCallback = null;
});

document.getElementById('confirmOkBtn').addEventListener('click', () => {
    document.getElementById('customConfirmModal').classList.remove('show');
    if (typeof confirmCallback === 'function') {
        confirmCallback();
    }
    confirmCallback = null;
});

document.addEventListener('DOMContentLoaded', () => {
    searchModal = document.getElementById('globalSearchModal');
    searchInput = document.getElementById('globalSearchInput');
    searchResults = document.getElementById('globalSearchResults');
    
    // Abrir com Ctrl+K
    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            openSearch();
        }
        if (e.key === 'Escape') closeSearch();
    });

    if (searchModal) {
        searchModal.addEventListener('click', (e) => {
            if (e.target === searchModal) closeSearch();
        });
    }

    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            const query = e.target.value.trim();
            if (query.length < 2) {
                searchResults.innerHTML = '<div style="padding: 40px; text-align: center; opacity: 0.5;"><i class="fas fa-keyboard" style="font-size: 2rem; margin-bottom: 10px;"></i><p>Comece a escrever para pesquisar...</p></div>';
                return;
            }
            
            searchTimeout = setTimeout(() => {
                const searchUrl = window.location.pathname.includes('/actions/') ? 'global_search.php' : 'actions/global_search.php';
                fetch(searchUrl + '?q=' + encodeURIComponent(query))
                    .then(res => res.json())
                    .then(data => renderSearchResults(data.results))
                    .catch(err => {
                        console.error('Erro na pesquisa:', err);
                        searchResults.innerHTML = '<div style="padding: 40px; text-align: center; color: #ff4d4d;"><i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 10px;"></i><p>Erro ao realizar pesquisa.</p></div>';
                    });
            }, 300);
        });
    }

    // Aplicar tema escuro fixo
    document.body.setAttribute('data-theme', 'dark');
    localStorage.setItem('theme', 'dark');
});

function openSearch() {
    if (!searchModal) {
        searchModal = document.getElementById('globalSearchModal');
        searchInput = document.getElementById('globalSearchInput');
        searchResults = document.getElementById('globalSearchResults');
    }
    if (searchModal) {
        searchModal.style.display = 'flex';
        setTimeout(() => searchInput.focus(), 100);
    }
}

function closeSearch() {
    if (searchModal) {
        searchModal.style.display = 'none';
        searchInput.value = '';
        searchResults.innerHTML = '<div style="padding: 40px; text-align: center; opacity: 0.5;"><i class="fas fa-keyboard" style="font-size: 2rem; margin-bottom: 10px;"></i><p>Comece a escrever para pesquisar...</p></div>';
    }
}

function renderSearchResults(results) {
    if (!searchResults) return;
    if (results.length === 0) {
        searchResults.innerHTML = '<div style="padding: 40px; text-align: center; opacity: 0.5;"><p>Nenhum resultado encontrado.</p></div>';
        return;
    }
    searchResults.innerHTML = results.map(item => `
        <a href="${item.link}${item.link.includes('?') ? '&' : '?'}edit_id=${item.id}" class="search-item">
            <div class="item-icon">
                <i class="fas ${item.type === 'Produto' ? 'fa-box' : (item.type === 'Cliente' ? 'fa-user' : 'fa-truck')}"></i>
            </div>
            <div class="item-info">
                <div class="item-title">${item.title}</div>
                <div class="item-subtitle">${item.subtitle}</div>
            </div>
            <div class="item-type">${item.type}</div>
        </a>
    `).join('');
}

function dismissNotification(id, element) {
    const searchUrl = window.location.pathname.includes('/actions/') ? '../actions/notifications_actions.php' : 'actions/notifications_actions.php';
    
    fetch(searchUrl + '?mark_read=' + id + '&ajax=1')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Alerta arquivado com sucesso', 'success');
                const item = element.closest('.notif-item');
                if (item) {
                    item.style.opacity = '0';
                    item.style.transform = 'translateX(20px)';
                    item.style.transition = 'all 0.4s ease';
                    setTimeout(() => {
                        item.remove();
                        const badge = document.querySelector('.bell-badge');
                        const body = document.querySelector('.notif-body');
                        if (badge) {
                            let count = parseInt(badge.innerText) - 1;
                            if (count <= 0) badge.remove();
                            else badge.innerText = count;
                        }
                        if (body && body.querySelectorAll('.notif-item').length === 0) {
                            body.innerHTML = '<div class="no-notifs">Sem alertas pendentes.</div>';
                        }
                    }, 400);
                }
            } else {
                showToast('Erro ao arquivar alerta', 'error');
            }
        })
        .catch(err => {
            console.error('Erro:', err);
            showToast('Erro de ligação ao servidor', 'error');
        });
}

function toggleNotifications() {
    const dropdown = document.getElementById('notifDropdown');
    const bell = document.querySelector('.notification-bell');
    if (dropdown) dropdown.classList.toggle('show');
    if (bell) bell.classList.toggle('active');
}

function toggleMobileMenu() {
    const navLinks = document.getElementById('navLinks');
    if (navLinks) navLinks.classList.toggle('active');
}

function toggleNavDropdown(e) {
    e.preventDefault();
    e.stopPropagation();
    const dropdown = e.currentTarget.closest('.nav-dropdown');
    if (dropdown) dropdown.classList.toggle('open');
}

// Fechar menus ao clicar fora
window.addEventListener('click', function(e) {
    if (!e.target.closest('.notification-bell-container')) {
        const dropdown = document.getElementById('notifDropdown');
        const bell = document.querySelector('.notification-bell');
        if (dropdown && dropdown.classList.contains('show')) {
            dropdown.classList.remove('show');
            if (bell) bell.classList.remove('active');
        }
    }
    const navLinks = document.getElementById('navLinks');
    const hamburger = document.querySelector('.hamburger');
    if (navLinks && hamburger) {
        if (!navLinks.contains(e.target) && !hamburger.contains(e.target)) {
            navLinks.classList.remove('active');
        }
    }
    if (!e.target.closest('.nav-dropdown')) {
        document.querySelectorAll('.nav-dropdown.open').forEach(d => d.classList.remove('open'));
    }
});
</script>
