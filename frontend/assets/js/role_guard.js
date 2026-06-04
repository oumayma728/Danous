async function requireRole(allowedRoles) {
    try {
        const res = await fetch('../../backend/api/auth/me.php', {
            credentials: 'include'
        });
        const data = await res.json();

        if (!data.success) {
            window.location.href = 'login.html';
            return null;
        }

        const role = data.user.role || '';

        if (!allowedRoles.includes(role)) {
            window.location.href = role === 'admin'
                ? 'admin.html'
                : 'dashboard.html';
            return null;
        }

        document.querySelectorAll('#username, #navUserName, #adminName').forEach(el => {
            el.textContent = data.user.name || '';
        });
        sessionStorage.setItem('user_id', data.user.id || '');
        sessionStorage.setItem('user_name', data.user.name || '');
        sessionStorage.setItem('user_role', role);

        return data.user;
    } catch {
        window.location.href = 'login.html';
        return null;
    }
}

function logout() {
    window.location.href = '../../backend/api/auth/logout.php';
}
