<?php

declare(strict_types=1);

require __DIR__ . '/lib/bootstrap.php';

if (pj_is_authenticated()) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PriceJust Login</title>
    <link rel="icon" href="assets/logo-primary.png">
    <style>
        :root {
            --bg: #090909;
            --card: rgba(16, 16, 16, 0.9);
            --card-border: rgba(255, 209, 26, 0.12);
            --accent: #ffd11a;
            --accent-strong: #ffb800;
            --text: #f7f2df;
            --muted: #9f9784;
            --field: #171717;
            --field-border: rgba(255, 255, 255, 0.08);
            --danger: #ff7b5d;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: "Segoe UI", Arial, sans-serif;
        }

        body {
            min-height: 100vh;
            display: grid;
            place-items: center;
            color: var(--text);
            background:
                radial-gradient(circle at top, rgba(255, 209, 26, 0.15), transparent 32%),
                radial-gradient(circle at bottom, rgba(255, 184, 0, 0.08), transparent 28%),
                linear-gradient(180deg, #0b0b0b 0%, #050505 100%);
            padding: 24px;
        }

        .panel {
            width: min(100%, 440px);
            background: var(--card);
            border: 1px solid var(--card-border);
            border-radius: 28px;
            padding: 28px;
            box-shadow: 0 26px 80px rgba(0, 0, 0, 0.45);
            position: relative;
            overflow: hidden;
        }

        .panel::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255, 209, 26, 0.08), transparent 38%);
            pointer-events: none;
        }

        .brand {
            position: relative;
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 24px;
        }

        .brand img {
            width: 52px;
            height: 52px;
            object-fit: contain;
            filter: drop-shadow(0 10px 22px rgba(255, 209, 26, 0.24));
        }

        .brand h1 {
            font-size: 2rem;
            letter-spacing: -0.04em;
            font-weight: 800;
        }

        .brand h1 span {
            color: var(--accent);
        }

        .subtitle {
            position: relative;
            color: var(--muted);
            margin-bottom: 24px;
            line-height: 1.5;
        }

        form {
            position: relative;
            display: grid;
            gap: 14px;
        }

        label {
            font-size: 0.92rem;
            color: var(--muted);
        }

        input {
            width: 100%;
            border-radius: 16px;
            border: 1px solid var(--field-border);
            background: var(--field);
            color: var(--text);
            padding: 14px 16px;
            font-size: 1rem;
            outline: none;
            transition: border-color 0.2s ease, transform 0.2s ease;
        }

        input:focus {
            border-color: rgba(255, 209, 26, 0.5);
            transform: translateY(-1px);
        }

        button {
            margin-top: 6px;
            border: none;
            border-radius: 16px;
            padding: 14px 18px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            color: #111;
            background: linear-gradient(90deg, var(--accent-strong), var(--accent));
            box-shadow: 0 18px 30px rgba(255, 184, 0, 0.22);
        }

        .hint {
            margin-top: 16px;
            color: var(--muted);
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .error {
            min-height: 1.25rem;
            color: var(--danger);
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="panel">
        <div class="brand">
            <img src="assets/logo-primary.png" alt="PriceJust">
            <h1>Price<span>Just</span></h1>
        </div>
        <p class="subtitle">Painel local protegido para monitoramento, leitura de odds e operacao manual. O primeiro admin geral e migrado automaticamente do arquivo local de configuracao.</p>
        <form id="loginForm">
            <div>
                <label for="username">Usuario</label>
                <input id="username" name="username" type="text" placeholder="admin" autocomplete="username" required>
            </div>
            <div>
                <label for="password">Senha</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>
            </div>
            <div id="errorBox" class="error"></div>
            <button type="submit">Entrar</button>
        </form>
    </div>
    <script>
        const form = document.getElementById('loginForm');
        const errorBox = document.getElementById('errorBox');

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            errorBox.textContent = '';

            const payload = {
                username: form.username.value.trim(),
                password: form.password.value,
            };

            try {
                const response = await fetch('api.php?action=login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                const json = await response.json().catch(() => ({}));
                if (!response.ok || json.ok !== true) {
                    errorBox.textContent = json.error || 'Falha ao autenticar.';
                    return;
                }
                window.location.href = 'index.php';
            } catch (error) {
                errorBox.textContent = 'Nao foi possivel conectar ao backend local.';
            }
        });
    </script>
</body>
</html>
