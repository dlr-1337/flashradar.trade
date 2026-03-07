<?php

declare(strict_types=1);

require __DIR__ . '/lib/bootstrap.php';
pj_require_admin_page();

$currentUser = pj_current_user();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios | PriceJust</title>
    <link rel="icon" href="assets/logo-primary.png">
    <style>
        :root {
            --bg: #060606;
            --card: #111111;
            --card-2: #171717;
            --border: rgba(255, 209, 26, 0.12);
            --accent: #ffd11a;
            --accent-strong: #ffb800;
            --text: #f7f1dc;
            --muted: #9a9178;
            --danger: #ff785a;
            --success: #9bff8a;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: "Segoe UI", Arial, sans-serif; }
        body {
            min-height: 100vh;
            background: radial-gradient(circle at top, rgba(255, 209, 26, 0.12), transparent 30%), linear-gradient(180deg, #0a0a0a 0%, #050505 100%);
            color: var(--text);
            padding: 24px;
        }
        .shell { max-width: 1120px; margin: 0 auto; display: grid; gap: 18px; }
        .topbar, .panel { background: rgba(17, 17, 17, 0.92); border: 1px solid var(--border); border-radius: 22px; box-shadow: 0 24px 60px rgba(0, 0, 0, 0.35); }
        .topbar { display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 18px 22px; flex-wrap: wrap; }
        .brand { display: flex; align-items: center; gap: 14px; }
        .brand img { width: 50px; height: 50px; object-fit: contain; }
        .brand h1 { font-size: 1.4rem; font-weight: 800; }
        .brand h1 span { color: var(--accent); }
        .brand p { color: var(--muted); font-size: 0.9rem; margin-top: 4px; }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .btn, .btn-danger, .btn-link { border: none; border-radius: 14px; padding: 12px 16px; font-size: 0.92rem; font-weight: 700; text-decoration: none; cursor: pointer; transition: transform 0.18s ease, opacity 0.18s ease, background 0.18s ease; }
        .btn:hover, .btn-danger:hover, .btn-link:hover { transform: translateY(-1px); }
        .btn { color: #111; background: linear-gradient(90deg, var(--accent-strong), var(--accent)); }
        .btn-link { color: var(--text); background: var(--card-2); border: 1px solid rgba(255, 255, 255, 0.08); }
        .btn-danger { color: #fff; background: rgba(255, 120, 90, 0.18); border: 1px solid rgba(255, 120, 90, 0.34); }
        .grid { display: grid; grid-template-columns: minmax(280px, 360px) 1fr; gap: 18px; }
        .panel { padding: 20px; }
        .panel h2 { font-size: 1.05rem; margin-bottom: 10px; }
        .panel p { color: var(--muted); line-height: 1.5; margin-bottom: 18px; }
        form { display: grid; gap: 12px; }
        label { font-size: 0.84rem; color: var(--muted); }
        input { width: 100%; margin-top: 6px; border-radius: 14px; border: 1px solid rgba(255, 255, 255, 0.08); background: #1a1a1a; color: var(--text); padding: 12px 14px; outline: none; }
        input:focus { border-color: rgba(255, 209, 26, 0.5); }
        .status { min-height: 1.25rem; font-size: 0.9rem; }
        .status.error { color: var(--danger); }
        .status.success { color: var(--success); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 10px; border-bottom: 1px solid rgba(255, 255, 255, 0.06); text-align: left; vertical-align: middle; }
        th { color: var(--muted); font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.04em; }
        td strong { display: block; margin-bottom: 4px; }
        .badge { display: inline-flex; align-items: center; justify-content: center; border-radius: 999px; padding: 6px 10px; font-size: 0.75rem; font-weight: 700; }
        .badge.admin { color: #111; background: linear-gradient(90deg, var(--accent-strong), var(--accent)); }
        .badge.user { color: var(--text); background: rgba(255, 255, 255, 0.08); }
        .badge.active { color: #0d2811; background: rgba(155, 255, 138, 0.85); }
        .badge.inactive { color: #fff; background: rgba(255, 120, 90, 0.28); }
        .row-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .row-actions button { border: 1px solid rgba(255, 255, 255, 0.08); background: #1b1b1b; color: var(--text); border-radius: 12px; padding: 8px 12px; cursor: pointer; }
        .empty { padding: 24px 0; color: var(--muted); text-align: center; }
        @media (max-width: 920px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="shell">
        <div class="topbar">
            <div class="brand">
                <img src="assets/logo-primary.png" alt="PriceJust">
                <div>
                    <h1>Price<span>Just</span> Usuarios</h1>
                    <p>Admin geral: <?= htmlspecialchars((string) ($currentUser['username'] ?? 'admin'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>
            <div class="actions">
                <a class="btn-link" href="index.php">Voltar ao painel</a>
                <a class="btn-link" href="change-password.php">Alterar senha</a>
                <button class="btn-danger" type="button" id="logoutBtn">Sair</button>
            </div>
        </div>
        <div class="grid">
            <section class="panel">
                <h2>Novo usuario</h2>
                <p>As contas criadas aqui entram no painel normalmente, mas nao administram outros logins.</p>
                <form id="createUserForm">
                    <label for="username">Usuario
                        <input id="username" name="username" type="text" autocomplete="off" required>
                    </label>
                    <label for="password">Senha inicial
                        <input id="password" name="password" type="password" autocomplete="new-password" required>
                    </label>
                    <div id="formStatus" class="status" aria-live="polite"></div>
                    <button class="btn" type="submit">Criar usuario</button>
                </form>
            </section>
            <section class="panel">
                <h2>Usuarios cadastrados</h2>
                <p>O admin geral permanece unico nesta versao. Para contas comuns, voce pode editar o login, resetar a senha e ativar ou desativar acesso.</p>
                <div id="tableStatus" class="status" aria-live="polite"></div>
                <table>
                    <thead>
                        <tr>
                            <th>Login</th>
                            <th>Papel</th>
                            <th>Status</th>
                            <th>Atualizado</th>
                            <th>Acoes</th>
                        </tr>
                    </thead>
                    <tbody id="usersTableBody">
                        <tr><td colspan="5" class="empty">Carregando usuarios...</td></tr>
                    </tbody>
                </table>
            </section>
        </div>
    </div>
    <script>
        const form = document.getElementById('createUserForm');
        const formStatus = document.getElementById('formStatus');
        const tableStatus = document.getElementById('tableStatus');
        const usersTableBody = document.getElementById('usersTableBody');

        function setStatus(element, message, type = '') {
            element.className = 'status' + (type ? ' ' + type : '');
            element.textContent = message || '';
        }

        function escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function formatDate(value) {
            if (!value) return '-';
            const parsed = new Date(value);
            if (Number.isNaN(parsed.getTime())) return value;
            return parsed.toLocaleString('pt-BR');
        }

        async function apiRequest(action, payload, method = 'POST') {
            const response = await fetch(`api.php?action=${encodeURIComponent(action)}`, {
                method,
                headers: payload ? { 'Content-Type': 'application/json' } : undefined,
                body: payload ? JSON.stringify(payload) : undefined,
            });
            const json = await response.json().catch(() => ({}));
            if (!response.ok || json.ok !== true) {
                throw new Error(json.error || 'Falha na requisicao.');
            }
            return json;
        }

        function renderUsers(users) {
            if (!Array.isArray(users) || users.length === 0) {
                usersTableBody.innerHTML = '<tr><td colspan="5" class="empty">Nenhum usuario cadastrado.</td></tr>';
                return;
            }

            usersTableBody.innerHTML = users.map((user) => {
                const isAdmin = user.role === 'admin';
                const toggleLabel = user.active ? 'Desativar' : 'Ativar';
                const actions = isAdmin
                    ? '<span class="badge admin">Admin geral</span>'
                    : `
                        <div class="row-actions">
                            <button type="button" onclick="editUser('${escapeHtml(user.id)}', '${escapeHtml(user.username)}')">Editar login</button>
                            <button type="button" onclick="resetPassword('${escapeHtml(user.id)}', '${escapeHtml(user.username)}')">Resetar senha</button>
                            <button type="button" onclick="toggleUser('${escapeHtml(user.id)}', ${user.active ? 'false' : 'true'}, '${escapeHtml(user.username)}')">${toggleLabel}</button>
                        </div>
                    `;

                return `
                    <tr>
                        <td><strong>${escapeHtml(user.username)}</strong><span>${escapeHtml(user.id)}</span></td>
                        <td><span class="badge ${isAdmin ? 'admin' : 'user'}">${isAdmin ? 'Admin' : 'Usuario'}</span></td>
                        <td><span class="badge ${user.active ? 'active' : 'inactive'}">${user.active ? 'Ativo' : 'Inativo'}</span></td>
                        <td>${escapeHtml(formatDate(user.updated_at))}</td>
                        <td>${actions}</td>
                    </tr>
                `;
            }).join('');
        }

        async function loadUsers() {
            setStatus(tableStatus, '');
            try {
                const response = await apiRequest('users', null, 'GET');
                renderUsers(response.users || []);
            } catch (error) {
                usersTableBody.innerHTML = '<tr><td colspan="5" class="empty">Nao foi possivel carregar os usuarios.</td></tr>';
                setStatus(tableStatus, error.message, 'error');
            }
        }

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            setStatus(formStatus, '');
            try {
                await apiRequest('create_user', {
                    username: form.username.value.trim(),
                    password: form.password.value,
                });
                form.reset();
                setStatus(formStatus, 'Usuario criado com sucesso.', 'success');
                await loadUsers();
            } catch (error) {
                setStatus(formStatus, error.message, 'error');
            }
        });

        async function editUser(id, currentUsername) {
            const nextUsername = window.prompt('Novo login para ' + currentUsername + ':', currentUsername);
            if (!nextUsername || nextUsername.trim() === '') return;
            setStatus(tableStatus, '');
            try {
                await apiRequest('update_user', { id, username: nextUsername.trim() });
                setStatus(tableStatus, 'Login atualizado com sucesso.', 'success');
                await loadUsers();
            } catch (error) {
                setStatus(tableStatus, error.message, 'error');
            }
        }

        async function resetPassword(id, username) {
            const password = window.prompt('Nova senha para ' + username + ':');
            if (!password) return;
            setStatus(tableStatus, '');
            try {
                await apiRequest('reset_user_password', { id, password });
                setStatus(tableStatus, 'Senha resetada com sucesso.', 'success');
                await loadUsers();
            } catch (error) {
                setStatus(tableStatus, error.message, 'error');
            }
        }

        async function toggleUser(id, active, username) {
            const confirmed = window.confirm((active ? 'Ativar' : 'Desativar') + ' o acesso de ' + username + '?');
            if (!confirmed) return;
            setStatus(tableStatus, '');
            try {
                await apiRequest('toggle_user_active', { id, active });
                setStatus(tableStatus, 'Status atualizado com sucesso.', 'success');
                await loadUsers();
            } catch (error) {
                setStatus(tableStatus, error.message, 'error');
            }
        }

        document.getElementById('logoutBtn').addEventListener('click', async () => {
            try {
                await fetch('api.php?action=logout', { method: 'POST' });
            } finally {
                window.location.href = 'login.php';
            }
        });

        loadUsers();
        window.editUser = editUser;
        window.resetPassword = resetPassword;
        window.toggleUser = toggleUser;
    </script>
</body>
</html>