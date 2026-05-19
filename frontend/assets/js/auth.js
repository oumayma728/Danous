console.log('auth.js loaded successfully');

document.addEventListener('DOMContentLoaded', () => {
    console.log('DOM Content Loaded');
    
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    
    console.log('loginForm found:', loginForm);
    console.log('registerForm found:', registerForm);

    function showMsg(text, type) {
        const msg = document.getElementById('msg');
        if (!msg) {
            console.warn('msg element not found');
            return;
        }
        msg.classList.remove('hidden');
        msg.textContent = text;
        msg.className = 'msg ' + type;
        console.log('Message shown:', text, type);
    }

    if (loginForm) {
        console.log('Adding login event listener');
        loginForm.addEventListener('submit', async (e) => {
            console.log('Login form submitted - event triggered!');
            e.preventDefault();
            
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.textContent = 'Signing in...';

            const formData = new FormData(loginForm);
            console.log('Form data being sent:', Array.from(formData.entries()));
            
            try {
                console.log('Sending fetch request...');
                const res = await fetch('http://localhost/Danous/backend/auth/login.php', {
                    method: 'POST',
                    body: formData
                });
                
                console.log('Response status:', res.status);
                const data = await res.json();
                console.log('Response data:', data);

                if (data.success) {
                    console.log('Login successful!');
                    showMsg('Login successful! Redirecting...', 'success');
                    setTimeout(() => window.location.href = data.redirect, 800);
                } else {
                    console.log('Login failed:', data.message);
                    showMsg(data.message, 'error');
                    btn.disabled = false;
                    btn.textContent = 'Sign in';
                }
            } catch (error) {
                console.error('Fetch error:', error);
                showMsg('Network error: ' + error.message, 'error');
                btn.disabled = false;
                btn.textContent = 'Sign in';
            }
        });
    }

    if (registerForm) {
        console.log('Adding register event listener');
        registerForm.addEventListener('submit', async (e) => {
            console.log('Register form submitted');
            e.preventDefault();
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.textContent = 'Creating account...';

            const formData = new FormData(registerForm);
            const res = await fetch('http://localhost/Danous/backend/auth/register.php', { 
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
            btn.disabled = false;
            btn.textContent = 'Create account';
        });
    }
});