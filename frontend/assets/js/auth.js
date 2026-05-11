// Works for both login and register pages
document.addEventListener('DOMContentLoaded', () => {
    const loginForm    = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const msg          = document.getElementById('msg');

    function showMsg(text, type) {
        msg.textContent = text;
        msg.className   = 'msg ' + type;
    }

    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.textContent = 'Signing in...';

            const formData = new FormData(loginForm);
            const res  = await fetch('../../../backend/auth/login.php', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.success) {
                showMsg('Login successful! Redirecting...', 'success');
                setTimeout(() => window.location.href = data.redirect, 800);
            } else {
                showMsg(data.message, 'error');
                btn.disabled = false;
                btn.textContent = 'Sign in';
            }
        });
    }

    if (registerForm) {
        registerForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.textContent = 'Creating account...';

            const formData = new FormData(registerForm);
            const res  = await fetch('../../../backend/auth/register.php', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.success) {
                showMsg(data.message, 'success');
                registerForm.reset();
            } else {
                showMsg(data.message, 'error');
            }
            btn.disabled = false;
            btn.textContent = 'Create account';
        });
    }
});