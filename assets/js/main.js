/**
 * Purple Helpdesk — global UI interactions
 */
document.addEventListener('DOMContentLoaded', function () {

    // ---- Sidebar toggle (mobile) ----
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function () {
            sidebar.classList.toggle('show');
        });
    }

    // ---- Profile dropdown ----
    const profileBtn = document.getElementById('profileBtn');
    const profileDropdown = document.getElementById('profileDropdown');
    if (profileBtn && profileDropdown) {
        profileBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            profileDropdown.classList.toggle('show');
            document.getElementById('notifDropdown')?.classList.remove('show');
        });
    }

    // ---- Notification dropdown ----
    const notifBtn = document.getElementById('notifBtn');
    const notifDropdown = document.getElementById('notifDropdown');
    const notifList = document.getElementById('notifList');

    function loadNotifications() {
        if (!notifList || typeof BASE_URL_JS === 'undefined') return;
        fetch(BASE_URL_JS + '/ajax/notifications_list.php')
            .then(r => r.json())
            .then(data => {
                if (!data.items || !data.items.length) {
                    notifList.innerHTML = '<div class="notif-empty">You have no notifications yet.</div>';
                    return;
                }
                notifList.innerHTML = data.items.map(n => `
                    <a href="${n.ticket_id ? BASE_URL_JS + '/view_ticket.php?id=' + n.ticket_id : '#'}" class="notif-row ${n.is_read ? '' : 'unread'}">
                        <div>
                            <strong>${escapeHtml(n.title)}</strong>
                            <div>${escapeHtml(n.message || '')}</div>
                            <span class="time">${escapeHtml(n.time)}</span>
                        </div>
                    </a>
                `).join('');
            })
            .catch(() => { notifList.innerHTML = '<div class="notif-empty">Could not load notifications.</div>'; });
    }

    if (notifBtn && notifDropdown) {
        notifBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            notifDropdown.classList.toggle('show');
            profileDropdown?.classList.remove('show');
            if (notifDropdown.classList.contains('show')) loadNotifications();
        });
    }

    document.addEventListener('click', function () {
        profileDropdown?.classList.remove('show');
        notifDropdown?.classList.remove('show');
    });

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }

    // Auto-dismiss flash messages after 6s
    document.querySelectorAll('.flash').forEach(el => {
        setTimeout(() => { el.style.transition = 'opacity .4s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 400); }, 6000);
    });
});
