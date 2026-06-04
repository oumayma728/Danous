document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');

    function showMsg(text, type) {
        const msg = document.getElementById('msg');
        if (!msg) {
            console.warn('msg element not found');
            return;
        }
        msg.classList.remove('hidden');
        msg.textContent = text || 'Une erreur est survenue';
        msg.className = 'msg ' + type;
        msg.classList.add('show');
    }

    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.textContent = 'Connexion...';

            try {
                const formData = new FormData(loginForm);
                const res = await fetch('../../backend/api/auth/login.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });
                
                const data = await res.json();

                if (data.success) {
                    if (data.user) {
                        sessionStorage.setItem('user_name', data.user.name || '');
                        sessionStorage.setItem('user_role', data.user.role || '');
                        sessionStorage.setItem('user_id', data.user.id || '');
                    }
                    showMsg('Connexion réussie ! Redirection...', 'success');
                    setTimeout(() => window.location.href = data.redirect, 800);
                } else {
                    showMsg(data.message, 'error');
                    btn.disabled = false;
                    btn.textContent = 'Se connecter';
                }
            } catch (error) {
                showMsg('Erreur réseau : ' + error.message, 'error');
                btn.disabled = false;
                btn.textContent = 'Se connecter';
            }
        });
    }

    if (registerForm) {
        registerForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.textContent = 'Création du compte...';

            try {
                const formData = new FormData(registerForm);
                const res = await fetch('../../backend/api/auth/register.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    showMsg(data.message, 'success');
                    registerForm.reset();
                } else {
                    showMsg(data.message, 'error');
                }
            } catch (error) {
                showMsg('Erreur réseau : ' + error.message, 'error');
            }

            btn.disabled = false;
            btn.textContent = 'Créer un compte';
        });
    }
});
