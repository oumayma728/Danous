const API = '../../backend/api';
let currentAdmin = null;
let adminData = null;
let adminCategoryChart = null;
let adminExpensesChart = null;
let visibleAdminTransactions = [];

document.addEventListener('DOMContentLoaded', async () => {
    currentAdmin = await requireAdmin();
    if (!currentAdmin) return;
    setActiveAdminLink();
    await loadAdminData();
});

async function requireAdmin() {
    try {
        const res = await fetch(`${API}/auth/me.php`, { credentials: 'include' });
        const data = await res.json();
        if (!data.success) {
            window.location.href = 'login.html';
            return null;
        }
        if (data.user.role !== 'admin') {
            window.location.href = 'dashboard.html';
            return null;
        }
        const name = data.user.name || 'Admin';
        document.querySelectorAll('#adminName').forEach(el => { el.textContent = name; });
        sessionStorage.setItem('user_id', data.user.id || '');
        sessionStorage.setItem('user_name', name);
        sessionStorage.setItem('user_role', 'admin');
        return data.user;
    } catch {
        window.location.href = 'login.html';
        return null;
    }
}

function setActiveAdminLink() {
    const page = document.body.dataset.adminPage || 'users';
    document.querySelectorAll('[data-admin-link]').forEach(link => {
        link.classList.toggle('active', link.dataset.adminLink === page);
    });
}

async function loadAdminData() {
    const content = document.getElementById('adminContent');
    content.className = 'loader';
    content.textContent = "Chargement...";
    try {
        const res = await fetch(`${API}/get_admin_data.php`, { credentials: 'include' });
        const data = await res.json();
        if (!data.success) {
            if (res.status === 401) window.location.href = 'login.html';
            if (res.status === 403) window.location.href = 'dashboard.html';
            content.className = '';
            content.innerHTML = `<div class="empty">${esc(data.message || 'Acces refuse')}</div>`;
            return;
        }
        adminData = data;
        renderCurrentPage(data);
    } catch (error) {
        content.className = '';
        content.innerHTML = `<div class="empty">Erreur : ${esc(error.message)}</div>`;
    }
}

function renderCurrentPage(data) {
    const updated = document.getElementById('lastUpdated');
    if (updated) updated.textContent = `Derniere mise a jour : ${formatDateTime(data.last_updated)}`;
    if (data.admin_profile?.name) {
        document.querySelectorAll('#adminName').forEach(el => { el.textContent = data.admin_profile.name; });
    }

    const page = document.body.dataset.adminPage || 'users';
    const renderers = {
        users: renderUsersPage,
        roles: renderRolesPage,
        budgets: renderSharedBudgetsPage,
        categories: renderGlobalCategoriesPage,
        transactions: renderTransactionsPage,
        stats: renderStatsPage,
        profile: renderProfilePage
    };
    (renderers[page] || renderUsersPage)(data);
}

function renderUsersPage(data) {
    const pending = data.pending_users || [];
    const deletionRequests = data.deletion_requests || [];
    document.getElementById('adminContent').className = '';
    document.getElementById('adminContent').innerHTML = `
        <div class="stat-grid">
            ${statCard('Comptes', data.total_users, 'Tous les comptes')}
            ${statCard('En attente', pending.length, 'Nouveaux comptes a valider')}
            ${statCard('Suppressions', deletionRequests.length, 'Demandes de suppression')}
            ${statCard('Actifs', data.active_users || 0, 'Actifs sur 30 jours')}
        </div>
        <section class="section-card">
            <div class="section-head">
                <div>
                    <div class="section-title">Tous les comptes</div>
                    <div class="section-note">Consultation des comptes et validation des inscriptions</div>
                </div>
            </div>
            <div class="table-wrap">${renderAccountsTable(data.users || [])}</div>
        </section>
        <section class="section-card">
            <div class="section-head">
                <div>
                    <div class="section-title">Demandes de suppression</div>
                    <div class="section-note">Approbation ou rejet des demandes utilisateurs</div>
                </div>
            </div>
            <div class="table-wrap">${renderDeletionRequests(deletionRequests)}</div>
        </section>`;
}

function renderRolesPage(data) {
    document.getElementById('adminContent').className = '';
    document.getElementById('adminContent').innerHTML = `
        <section class="section-card">
            <div class="section-head">
                <div>
                    <div class="section-title">Gestion des roles</div>
                    <div class="section-note">Changer un utilisateur en administrateur, ou l'inverse</div>
                </div>
            </div>
            <div class="table-wrap">${renderRolesTable(data.users || [])}</div>
        </section>`;
}

function renderSharedBudgetsPage(data) {
    const budgets = data.shared_budgets || [];
    document.getElementById('adminContent').className = '';
    document.getElementById('adminContent').innerHTML = `
        <section class="section-card">
            <div class="section-head">
                <div>
                    <div class="section-title">Consultation des budgets partages</div>
                    <div class="section-note">Vue en lecture seule de tous les budgets partages</div>
                </div>
            </div>
            <div class="table-wrap">${renderSharedBudgets(budgets)}</div>
        </section>`;
}

function renderGlobalCategoriesPage(data) {
    document.getElementById('adminContent').className = '';
    document.getElementById('adminContent').innerHTML = `
        <form class="form-panel" onsubmit="submitGlobalCategory(event)">
            <div class="section-title" style="margin-bottom:12px">Ajouter une categorie globale</div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Nom de la categorie</label>
                    <input class="form-input" name="name" required placeholder="Ex. Transport">
                </div>
                <div class="form-group" style="align-self:end">
                    <button class="btn-primary" type="submit">Ajouter</button>
                </div>
            </div>
        </form>
        <section class="section-card">
            <div class="section-head">
                <div>
                    <div class="section-title">Categories globales</div>
                    <div class="section-note">Categories par defaut visibles par tous les utilisateurs</div>
                </div>
            </div>
            <div class="table-wrap">${renderGlobalCategories(data.default_categories || [])}</div>
        </section>`;
}

function renderTransactionsPage(data) {
    document.getElementById('adminContent').className = '';
    document.getElementById('adminContent').innerHTML = `
        <div class="filter-row">
            <input class="form-input" id="txSearch" placeholder="Rechercher utilisateur, categorie, budget..." oninput="filterAdminTransactions()">
            <select class="form-select" id="txTypeFilter" onchange="filterAdminTransactions()">
                <option value="">Tous les types</option>
                <option value="income">Revenus</option>
                <option value="expense">Depenses</option>
            </select>
            <button class="btn-secondary" type="button" onclick="exportAdminTransactionsCsv()">Exporter CSV</button>
        </div>
        <section class="section-card">
            <div class="section-head">
                <div>
                    <div class="section-title">Supervision des transactions</div>
                    <div class="section-note">Lecture seule des transactions du systeme</div>
                </div>
            </div>
            <div class="table-wrap" id="transactionsWrap">${renderTransactionsTable(data.all_transactions || [])}</div>
        </section>`;
}

function renderStatsPage(data) {
    document.getElementById('adminContent').className = '';
    document.getElementById('adminContent').innerHTML = `
        <div class="stat-grid">
            ${statCard('Utilisateurs', data.total_users, 'Nombre total d utilisateurs')}
            ${statCard('Transactions', data.total_transactions, 'Nombre total de transactions')}
            ${statCard('Volume revenus', `${formatMoney(data.total_volume)} TND`, 'Somme des revenus')}
            ${statCard('Actifs', data.active_users || 0, 'Connexions sur 30 jours')}
        </div>

        <div class="admin-chart-grid">
            <section class="section-card admin-chart-card">
                <div class="section-head">
                    <div>
                        <div class="section-title">Depenses globales par categorie</div>
                        <div class="section-note">Repartition des depenses du systeme</div>
                    </div>
                </div>
                <div class="section-body">
                    <div class="admin-chart-box">
                        <canvas id="adminCategoryChart"></canvas>
                        <div class="empty" id="adminCategoryEmpty">Aucune depense par categorie.</div>
                    </div>
                </div>
            </section>

            <section class="section-card admin-chart-card">
                <div class="section-head">
                    <div>
                        <div class="section-title">Evolution globale des depenses</div>
                        <div class="section-note">Depenses mensuelles sur les 6 derniers mois</div>
                    </div>
                </div>
                <div class="section-body">
                    <div class="admin-chart-box">
                        <canvas id="adminExpensesChart"></canvas>
                        <div class="empty" id="adminExpensesEmpty">Aucune depense mensuelle.</div>
                    </div>
                </div>
            </section>
        </div>

        <section class="section-card">
            <div class="section-head">
                <div>
                    <div class="section-title">Dernieres activites</div>
                    <div class="section-note">Activites recentes de tous les utilisateurs</div>
                </div>
            </div>
            <div class="section-body">${renderRecentActivities(data.recent_activities || [])}</div>
        </section>`;

    renderAdminCharts(data);
}

function renderProfilePage(data) {
    const profile = data.admin_profile || currentAdmin || {};
    document.getElementById('adminContent').className = '';
    document.getElementById('adminContent').innerHTML = `
        <form class="form-panel" onsubmit="submitAdminProfile(event)">
            <div class="section-title" style="margin-bottom:12px">Profil administrateur</div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Nom</label>
                    <input class="form-input" name="name" value="${escAttr(profile.name || '')}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">E-mail</label>
                    <input class="form-input" type="email" name="email" value="${escAttr(profile.email || '')}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Mot de passe actuel</label>
                    <input class="form-input" type="password" name="current_password" autocomplete="current-password">
                </div>
                <div class="form-group">
                    <label class="form-label">Nouveau mot de passe</label>
                    <input class="form-input" type="password" name="new_password" autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label class="form-label">Confirmer le mot de passe</label>
                    <input class="form-input" type="password" name="confirm_password" autocomplete="new-password">
                </div>
                <div class="form-group" style="align-self:end">
                    <button class="btn-primary" type="submit">Enregistrer</button>
                </div>
            </div>
        </form>`;
}

function statCard(label, value, sub) {
    return `<div class="stat-card"><div class="stat-label">${label}</div><div class="stat-value">${value ?? 0}</div><div class="stat-sub">${sub}</div></div>`;
}

function renderAccountsTable(users) {
    if (!users.length) return '<div class="empty">Aucun compte.</div>';
    return `
        <table>
            <thead><tr><th>Nom</th><th>E-mail</th><th>Role</th><th>Statut</th><th>Cree le</th><th>Validation</th></tr></thead>
            <tbody>${users.map(user => `
                <tr>
                    <td>${esc(user.name)}</td>
                    <td>${esc(user.email)}</td>
                    <td>${roleBadge(user.role)}</td>
                    <td>${statusBadge(user.status)}</td>
                    <td>${esc(user.created_at || '-')}</td>
                    <td><div class="row-actions">
                        ${user.status === 'pending' ? `<button class="btn-primary" onclick="validateUser(${Number(user.id)}, 'validate')">Valider</button><button class="btn-danger" onclick="validateUser(${Number(user.id)}, 'reject')">Rejeter</button>` : '<span class="section-note">-</span>'}
                    </div></td>
                </tr>`).join('')}</tbody>
        </table>`;
}

function renderDeletionRequests(requests) {
    if (!requests.length) return '<div class="empty">Aucune demande de suppression.</div>';
    return `
        <table>
            <thead><tr><th>Utilisateur</th><th>E-mail</th><th>Demande</th><th>Raison</th><th>Actions</th></tr></thead>
            <tbody>${requests.map(request => `
                <tr>
                    <td>${esc(request.name)}</td>
                    <td>${esc(request.email)}</td>
                    <td>${esc(request.requested_at || '-')}</td>
                    <td>${esc(request.reason || '-')}</td>
                    <td><div class="row-actions">
                        <button class="btn-danger" onclick="handleDeletionRequest(${Number(request.id)}, 'approve')">Approuver</button>
                        <button class="btn-secondary" onclick="handleDeletionRequest(${Number(request.id)}, 'reject')">Rejeter</button>
                    </div></td>
                </tr>`).join('')}</tbody>
        </table>`;
}

function renderRolesTable(users) {
    if (!users.length) return '<div class="empty">Aucun utilisateur.</div>';
    return `
        <table>
            <thead><tr><th>Nom</th><th>E-mail</th><th>Role actuel</th><th>Nouveau role</th></tr></thead>
            <tbody>${users.map(user => {
                const isSelf = String(user.id) === String(currentAdmin?.id);
                return `
                    <tr>
                        <td>${esc(user.name)}${isSelf ? ' <span class="badge badge-admin">Vous</span>' : ''}</td>
                        <td>${esc(user.email)}</td>
                        <td>${roleBadge(user.role)}</td>
                        <td>
                            <select class="role-select" onchange="changeRole(${Number(user.id)}, this.value)" ${isSelf ? 'disabled' : ''}>
                                <option value="user" ${user.role === 'user' ? 'selected' : ''}>Utilisateur</option>
                                <option value="admin" ${user.role === 'admin' ? 'selected' : ''}>Administrateur</option>
                            </select>
                        </td>
                    </tr>`;
            }).join('')}</tbody>
        </table>`;
}

function renderSharedBudgets(budgets) {
    if (!budgets.length) return '<div class="empty">Aucun budget partage.</div>';
    return `
        <table>
            <thead><tr><th>Budget</th><th>Proprietaire</th><th>Periode</th><th>Dates</th><th>Membres</th><th>Plafond</th><th>Depenses</th></tr></thead>
            <tbody>${budgets.map(b => `
                <tr>
                    <td>${esc(b.name)}</td>
                    <td>${esc(b.created_by_name || '-')}</td>
                    <td>${periodLabel(b.period)}</td>
                    <td>${esc(b.start_date || '-')} - ${esc(b.end_date || '-')}</td>
                    <td>${Number(b.member_count || 0)}</td>
                    <td>${formatMoney(b.cap_amount)} TND</td>
                    <td>${formatMoney(b.total_expenses)} TND</td>
                </tr>`).join('')}</tbody>
        </table>`;
}

function renderGlobalCategories(categories) {
    if (!categories.length) return '<div class="empty">Aucune categorie globale.</div>';
    return `
        <table>
            <thead><tr><th>Nom</th><th>Actions</th></tr></thead>
            <tbody>${categories.map(category => `
                <tr>
                    <td>${esc(category.name)}</td>
                    <td><div class="row-actions">
                        <button class="btn-secondary" onclick="editGlobalCategory(${Number(category.id)}, ${jsArg(category.name)})">Modifier</button>
                        <button class="btn-danger" onclick="deleteGlobalCategory(${Number(category.id)}, ${jsArg(category.name)})">Supprimer</button>
                    </div></td>
                </tr>`).join('')}</tbody>
        </table>`;
}

function renderTransactionsTable(transactions) {
    visibleAdminTransactions = transactions || [];
    if (!visibleAdminTransactions.length) return '<div class="empty">Aucune transaction.</div>';
    return `
        <table>
            <thead><tr><th>Date</th><th>Utilisateur</th><th>Type</th><th>Categorie</th><th>Budget</th><th>Description</th><th>Montant</th></tr></thead>
            <tbody>${visibleAdminTransactions.map(t => `
                <tr>
                    <td>${esc(t.date || '-')}</td>
                    <td>${esc(t.user_name || '-')}<br><span class="section-note">${esc(t.user_email || '')}</span></td>
                    <td>${typeBadge(t.type)}</td>
                    <td>${esc(t.category_name || '-')}</td>
                    <td>${esc(t.budget_name || '-')}</td>
                    <td>${esc(t.description || '-')}</td>
                    <td>${formatMoney(t.amount)} TND</td>
                </tr>`).join('')}</tbody>
        </table>`;
}

function renderRecentActivities(activities) {
    if (!activities.length) return '<div class="empty">Aucune activite recente.</div>';
    return `
        <div class="activity-list">${activities.map(activity => `
            <div class="activity-item">
                <div class="activity-main">
                    <div class="activity-user">${esc(activity.user_name || '-')}</div>
                    <div class="activity-action">${esc(activity.action || '-')}</div>
                </div>
                <div class="activity-date">${esc(activity.date || '-')}</div>
            </div>`).join('')}</div>`;
}

function renderAdminCharts(data) {
    if (adminCategoryChart) {
        adminCategoryChart.destroy();
        adminCategoryChart = null;
    }
    if (adminExpensesChart) {
        adminExpensesChart.destroy();
        adminExpensesChart = null;
    }

    if (typeof Chart === 'undefined') {
        document.getElementById('adminCategoryEmpty').textContent = 'Chart.js non charge.';
        document.getElementById('adminExpensesEmpty').textContent = 'Chart.js non charge.';
        return;
    }

    const categories = Array.isArray(data.global_categories) ? data.global_categories : [];
    const categoryCanvas = document.getElementById('adminCategoryChart');
    const categoryEmpty = document.getElementById('adminCategoryEmpty');
    if (categories.length) {
        categoryCanvas.style.display = 'block';
        categoryEmpty.style.display = 'none';
        adminCategoryChart = new Chart(categoryCanvas, {
            type: 'doughnut',
            data: {
                labels: categories.map(row => row.name || 'Sans categorie'),
                datasets: [{
                    data: categories.map(row => parseFloat(row.total || 0)),
                    backgroundColor: ['#1db87a', '#0a4a3a', '#f59e0b', '#e53935', '#1565c0', '#6a1b9a', '#e65100', '#2e7d32', '#00838f', '#ad1457'],
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: adminChartOptions(false)
        });
    } else {
        categoryCanvas.style.display = 'none';
        categoryEmpty.style.display = 'block';
    }

    const months = Array.isArray(data.global_months) ? data.global_months : [];
    const expenses = Array.isArray(data.global_monthly_expenses) ? data.global_monthly_expenses : [];
    const expensesCanvas = document.getElementById('adminExpensesChart');
    const expensesEmpty = document.getElementById('adminExpensesEmpty');
    if (months.length && expenses.length) {
        expensesCanvas.style.display = 'block';
        expensesEmpty.style.display = 'none';
        adminExpensesChart = new Chart(expensesCanvas, {
            type: 'line',
            data: {
                labels: months,
                datasets: [{
                    label: 'Depenses',
                    data: expenses.map(value => parseFloat(value || 0)),
                    borderColor: '#e53935',
                    backgroundColor: 'rgba(229,57,53,.09)',
                    tension: .35,
                    fill: true
                }]
            },
            options: adminChartOptions(true)
        });
    } else {
        expensesCanvas.style.display = 'none';
        expensesEmpty.style.display = 'block';
    }
}

function adminChartOptions(useScales) {
    const options = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
            tooltip: {
                callbacks: {
                    label: context => `${context.dataset.label || context.label}: ${formatMoney(context.raw)} TND`
                }
            }
        }
    };
    if (useScales) {
        options.scales = {
            y: {
                beginAtZero: true,
                ticks: { callback: value => formatMoney(value) }
            }
        };
    }
    return options;
}

function filterAdminTransactions() {
    const search = document.getElementById('txSearch')?.value.toLowerCase() || '';
    const type = document.getElementById('txTypeFilter')?.value || '';
    const transactions = (adminData?.all_transactions || []).filter(t => {
        const haystack = `${t.user_name || ''} ${t.user_email || ''} ${t.category_name || ''} ${t.budget_name || ''} ${t.description || ''}`.toLowerCase();
        return (!type || t.type === type) && (!search || haystack.includes(search));
    });
    document.getElementById('transactionsWrap').innerHTML = renderTransactionsTable(transactions);
}

function exportAdminTransactionsCsv() {
    if (!visibleAdminTransactions.length) {
        showMessage('Aucune transaction a exporter', 'error');
        return;
    }

    const headers = ['ID', 'Date', 'Utilisateur', 'E-mail', 'Type', 'Categorie', 'Budget', 'Description', 'Montant (TND)'];
    const rows = visibleAdminTransactions.map(t => {
        const signedAmount = t.type === 'income'
            ? parseFloat(t.amount || 0)
            : -parseFloat(t.amount || 0);
        return [
            t.id,
            t.date || '',
            t.user_name || '',
            t.user_email || '',
            t.type === 'income' ? 'Revenu' : 'Depense',
            t.category_name || '',
            t.budget_name || '',
            t.description || '',
            signedAmount.toFixed(2)
        ];
    });

    downloadCsv(`transactions_admin_${new Date().toISOString().slice(0, 10)}.csv`, headers, rows);
}

function downloadCsv(filename, headers, rows) {
    const csv = '\uFEFF' + [headers, ...rows]
        .map(row => row.map(csvCell).join(','))
        .join('\r\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
}

function csvCell(value) {
    const normalized = value === null || value === undefined
        ? ''
        : String(value).replace(/\r?\n|\r/g, ' ');
    return `"${normalized.replace(/"/g, '""')}"`;
}

async function validateUser(userId, action) {
    const verb = action === 'validate' ? 'valider' : 'rejeter';
    if (!confirm(`Confirmer : ${verb} cet utilisateur ?`)) return;
    await postAction(`${API}/validate_user.php`, { user_id: userId, action });
}

async function handleDeletionRequest(requestId, action) {
    const text = action === 'approve'
        ? 'Approuver cette demande et supprimer le compte ?'
        : 'Rejeter cette demande de suppression ?';
    if (!confirm(text)) return;
    await postAction(`${API}/admin_deletion_requests.php`, { request_id: requestId, action });
}

async function changeRole(userId, role) {
    if (!confirm('Modifier le role de cet utilisateur ?')) {
        renderCurrentPage(adminData);
        return;
    }
    await postAction(`${API}/change_role.php`, { user_id: userId, role });
}

async function submitGlobalCategory(event) {
    event.preventDefault();
    const name = event.target.elements.name.value.trim();
    if (!name) return;
    await postAction(`${API}/admin_categories.php`, { action: 'add', name });
    event.target.reset();
}

async function editGlobalCategory(categoryId, currentName) {
    const name = prompt('Nouveau nom de categorie', currentName);
    if (!name || !name.trim()) return;
    await postAction(`${API}/admin_categories.php`, { action: 'update', category_id: categoryId, name: name.trim() });
}

async function deleteGlobalCategory(categoryId, name) {
    if (!confirm(`Supprimer la categorie globale "${name}" ?`)) return;
    await postAction(`${API}/admin_categories.php`, { action: 'delete', category_id: categoryId });
}

async function submitAdminProfile(event) {
    event.preventDefault();
    const form = event.target;
    const payload = {
        name: form.elements.name.value.trim(),
        email: form.elements.email.value.trim(),
        current_password: form.elements.current_password.value,
        new_password: form.elements.new_password.value,
        confirm_password: form.elements.confirm_password.value
    };
    await postAction(`${API}/admin_profile.php`, payload);
    form.elements.current_password.value = '';
    form.elements.new_password.value = '';
    form.elements.confirm_password.value = '';
}

async function postAction(url, payload) {
    try {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            showMessage(data.message || 'Action effectuee', 'success');
            await loadAdminData();
        } else {
            showMessage(data.message || 'Action impossible', 'error');
            if (adminData) renderCurrentPage(adminData);
        }
    } catch (error) {
        showMessage('Erreur reseau : ' + error.message, 'error');
        if (adminData) renderCurrentPage(adminData);
    }
}

async function logout() {
    await fetch(`${API}/auth/logout.php`, { credentials: 'include' });
    sessionStorage.clear();
    localStorage.clear();
    window.location.href = 'login.html';
}

function roleBadge(role) {
    return `<span class="badge ${role === 'admin' ? 'badge-admin' : 'badge-user'}">${role === 'admin' ? 'Administrateur' : 'Utilisateur'}</span>`;
}

function statusBadge(status) {
    const map = {
        active: ['Actif', 'badge-active'],
        pending: ['En attente', 'badge-pending'],
        disabled: ['Desactive', 'badge-disabled']
    };
    const [label, cls] = map[status] || [status || '-', ''];
    return `<span class="badge ${cls}">${label}</span>`;
}

function typeBadge(type) {
    const label = type === 'income' ? 'Revenu' : type === 'expense' ? 'Depense' : type || '-';
    return `<span class="badge ${type === 'income' ? 'badge-active' : 'badge-disabled'}">${label}</span>`;
}

function periodLabel(period) {
    return { weekly: 'Hebdomadaire', monthly: 'Mensuel', custom: 'Personnalise' }[period] || period || '-';
}

function showMessage(text, type) {
    const msg = document.getElementById('msg');
    if (!msg) return;
    msg.textContent = text;
    msg.className = `msg show ${type}`;
}

function formatMoney(value) {
    return parseFloat(value || 0).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDateTime(value) {
    if (!value) return '-';
    return value.replace(' ', ' a ');
}

function esc(value) {
    return String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

function escAttr(value) {
    return esc(value).replace(/`/g, '&#96;');
}

function jsArg(value) {
    return escAttr(JSON.stringify(String(value ?? '')));
}
