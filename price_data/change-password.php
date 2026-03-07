<?php

declare(strict_types=1);

require __DIR__ . '/lib/bootstrap.php';
pj_require_auth_page();

$currentUser = pj_current_user();
$isAdmin = pj_is_admin();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alterar senha | PriceJust</title>
    <link rel="icon" href="assets/logo-primary.png">
    <style>
        :root {
            --bg: #070707;
            --card: rgba(17, 17, 17, 0.92);
            --border: rgba(255, 209, 26, 0.12);
            --accent: #ffd11a;
            --accent-strong: #ffb800;
            --text: #f7f2df;
            --muted: #9f9784;
            --danger: #ff785a;
            --success: #9bff8a;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: "Segoe UI", Arial, sans-serif; }
        body {
            min-height: 100vh;
            display: grid;
            place-items: center;
            color: var(--text);
            background: radial-gradient(circle at top, rgba(255, 209, 26, 0.14), transparent 32%), linear-gradient(180deg, #0b0b0b 0%, #050505 100%);
            padding: 24px;
        }
        .panel {
            width: min(100%, 480px);
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 28px;
            padding: 28px;
            box-shadow: 0 26px 80px rgba(0, 0, 0, 0.45);
            display: grid;
            gap: 18px;
        }
        .topbar { display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; }
        .brand { display: flex; align-items: center; gap: 12px; }
        .brand img { width: 48px; height: 48px; object-fit: contain; }
        .brand h1 { font-size: 1.5rem; font-weight: 800; }
        .brand h1 span { color: var(--accent); }
        .brand p { color: var(--muted); font-size: 0.9rem; margin-top: 4px; }
        .links { display: flex; gap: 10px; flex-wrap: wrap; }
        .btn-link, .btn-primary, .btn-danger { border-radius: 14px; padding: 11px 14px; text-decoration: none; font-size: 0.9rem; font-weight: 700; cursor: pointer; }
        .btn-link { color: var(--text); background: #171717; border: 1px solid rgba(255, 255, 255, 0.08); }
        .btn-primary { color: #111; background: linear-gradient(90deg, var(--accent-strong), var(--accent)); border: none; }
        .btn-danger { color: #fff; background: rgba(255, 120, 90, 0.18); border: 1px solid rgba(255, 120, 90, 0.34); }
        form { display: grid; gap: 14px; }
        label { font-size: 0.9rem; color: var(--muted); }
        input { width: 100%; margin-top: 6px; border-radius: 16px; border: 1px solid rgba(255, 255, 255, 0.08); background: #171717; color: var(--text); padding: 14px 16px; outline: none; }
        input:focus { border-color: rgba(255, 209, 26, 0.5); }
        .status { min-height: 1.25rem; font-size: 0.9rem; }
        .status.error { color: var(--danger); }
        .status.success { color: var(--success); }
        .hint { color: var(--muted); line-height: 1.5; font-size: 0.92rem; }
    </style>
</head>
<body>
    <div class="panel">
        <div class="topbar">
            <div class="brand">
                <img src="assets/logo-primary.png" alt="PriceJust">
                <div>
                    <h1>Alterar <span>senha</span></h1>
                    <p>Usuario atual: <?= htmlspecialchars((string) ($currentUser['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>
            <div class="links">
                <a class="btn-link" href="index.php">Painel</a>
                <?php if ($isAdmin): ?>
                    <a class="btn-link" href="admin.php">Usuarios</a>
                <?php endif; ?>
                <button class="btn-danger" type="button" id="logoutBtn">Sair</button>
            </div>
        </div>
        <p class="hint">Use uma senha com pelo menos 8 caracteres. A atualizacao acontece imediatamente na conta logada.</p>
        <form id="passwordForm">
            <label for="currentPassword">Senha atual
                <input id="currentPassword" name="currentPassword" type="password" autocomplete="current-password" required>
            </label>
            <label for="newPassword">Nova senha
                <input id="newPassword" name="newPassword" type="password" autocomplete="new-password" required>
            </label>
            <label for="confirmPassword">Confirmar nova senha
                <input id="confirmPassword" name="confirmPassword" type="password" autocomplete="new-password" required>
            </label>
            <div id="formStatus" class="status" aria-live="polite"></div>
            <button class="btn-primary" type="submit">Salvar nova senha</button>
        </form>
    </div>
    <script>
        const form = document.getElementById('passwordForm');
        const statusBox = document.getElementById('formStatus');

        function setStatus(message, type = '') {
            statusBox.className = 'status' + (type ? ' ' + type : '');
            statusBox.textContent = message || '';
        }

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            setStatus('');

            const currentPassword = form.currentPassword.value;
            const newPassword = form.newPassword.value;
            const confirmPassword = form.confirmPassword.value;

            if (newPassword !== confirmPassword) {
                setStatus('A confirmacao da senha nao confere.', 'error');
                return;
            }

            try {
                const response = await fetch('api.php?action=change_password', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ current_password: currentPassword, new_password: newPassword }),
                });
                const json = await response.json().catch(() => ({}));
                if (!response.ok || json.ok !== true) {
                    setStatus(json.error || 'Nao foi possivel atualizar a senha.', 'error');
                    return;
                }
                form.reset();
                setStatus(json.message || 'Senha atualizada com sucesso.', 'success');
            } catch (error) {
                setStatus('Falha ao conectar ao backend local.', 'error');
            }
        });

        document.getElementById('logoutBtn').addEventListener('click', async () => {
            try {
                await fetch('api.php?action=logout', { method: 'POST' });
            } finally {
                window.location.href = 'login.php';
            }
        });
    </script>
</body>
</html>