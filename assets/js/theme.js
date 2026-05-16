document.addEventListener('DOMContentLoaded', () => {
    const themeToggle = document.getElementById('themeToggle');
    const htmlElement = document.documentElement;
    const bodyElement = document.body;
    
    // Carregar tema guardado
    const savedTheme = localStorage.getItem('theme') || 'dark';
    htmlElement.setAttribute('data-theme', savedTheme);
    updateToggleIcon(savedTheme);

    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            const currentTheme = htmlElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            htmlElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateToggleIcon(newTheme);
            
            // Log de depuração (opcional)
            console.log(`Tema alterado para: ${newTheme}`);
        });
    }

    function updateToggleIcon(theme) {
        if (!themeToggle) return;
        const icon = themeToggle.querySelector('i');
        if (theme === 'light') {
            icon.classList.remove('fa-sun');
            icon.classList.add('fa-moon');
        } else {
            icon.classList.remove('fa-moon');
            icon.classList.add('fa-sun');
        }
    }
});

// FUNÇÕES DE NOTIFICAÇÕES GLOBAIS
function toggleNotifications() {
    const dropdown = document.getElementById('notifDropdown');
    const bell = document.querySelector('.notification-bell');
    if (dropdown) dropdown.classList.toggle('show');
    if (bell) bell.classList.toggle('active');
}

function dismissNotification(id, element) {
    // Removido o confirm para ser mais fluido, conforme pedido do utilizador
    fetch('actions/notifications_actions.php?mark_read=' + id + '&ajax=1')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const item = element.closest('.notif-item');
                const body = item.parentElement;
                
                // Fade out effect
                item.style.opacity = '0';
                item.style.transform = 'translateX(20px)';
                item.style.transition = 'all 0.3s ease';
                
                setTimeout(() => {
                    item.remove();
                    
                    // Atualizar badge
                    const badge = document.querySelector('.bell-badge');
                    if (badge) {
                        let count = parseInt(badge.innerText) - 1;
                        if (count <= 0) {
                            badge.remove();
                        } else {
                            badge.innerText = count;
                        }
                    }
                    
                    // Se não houver mais notificações, mostrar mensagem
                    if (body.querySelectorAll('.notif-item').length === 0) {
                        body.innerHTML = '<div class="no-notifs">Sem alertas pendentes.</div>';
                    }
                }, 300);
            }
        })
        .catch(err => console.error('Erro ao marcar notificação:', err));
}

// Fechar dropdown ao clicar fora
window.addEventListener('click', function(e) {
    if (!e.target.closest('.notification-bell-container')) {
        const dropdown = document.getElementById('notifDropdown');
        const bell = document.querySelector('.notification-bell');
        if (dropdown && dropdown.classList.contains('show')) {
            dropdown.classList.remove('show');
            if (bell) bell.classList.remove('active');
        }
    }
});
