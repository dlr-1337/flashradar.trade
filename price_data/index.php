<?php

declare(strict_types=1);

require __DIR__ . '/lib/bootstrap.php';
pj_require_auth_page();

$appConfig = [
    'username' => pj_auth_username(),
    'refreshSeconds' => (int) (pj_config()['dashboard']['refresh_seconds'] ?? 60),
    'timezone' => (string) (pj_config()['api']['timezone'] ?? 'America/Sao_Paulo'),
];
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>PriceJust Local Panel</title>
    <link rel="icon" href="assets/logo-primary.png" />

    <style>
        :root {
            --bg-body: #050505;
            --bg-card: #101010;
            --bg-hover: #181818;
            --accent: #ffd11a;
            --accent-hover: #ffbf00;
            --accent-dim: rgba(255, 209, 26, 0.14);
            --blue: #fff4bf;
            --blue-dim: rgba(255, 244, 191, 0.16);
            --text-main: #f7f1dc;
            --text-muted: #9a9178;
            --border: rgba(255, 209, 26, 0.12);
            --danger: #ff785a;
            --success: #a9ff8f;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Segoe UI", Arial, sans-serif;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        header {
            background: rgba(20, 20, 20, 0.8);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .logo-area {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-area img {
            width: 46px;
            height: 34px;
            object-fit: contain;
        }

        .logo-area h1 {
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .logo-area h1 span {
            color: var(--accent);
        }

        .hdr-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-chip {
            background: rgba(255, 255, 255, 0.03);
            color: var(--text-muted);
            border: 1px solid var(--border);
            border-radius: 999px;
            padding: 8px 12px;
            font-size: 0.82rem;
            white-space: nowrap;
        }

        .btn-add {
            background-color: var(--accent);
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }

        .btn-add:hover {
            background-color: var(--accent-hover);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: #101010;
            color: #ddd;
            border: 1px solid rgba(255, 255, 255, 0.12);
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        .btn-secondary:hover {
            background: #141414;
            transform: translateY(-1px);
            border-color: rgba(255, 255, 255, 0.18);
            color: #fff;
        }

        .top-banner {
            display: none;
            margin: 16px auto 0;
            max-width: 1200px;
            width: calc(100% - 60px);
            border-radius: 12px;
            padding: 12px 14px;
            border: 1px solid rgba(255, 209, 26, 0.18);
            background: rgba(255, 209, 26, 0.08);
            color: var(--text-main);
            font-size: 0.88rem;
            line-height: 1.4;
        }

        .top-banner.stale {
            display: block;
            border-color: rgba(255, 120, 90, 0.3);
            background: rgba(255, 120, 90, 0.1);
        }

        .filters-container {
            padding: 20px 30px;
            background: var(--bg-body);
            border-bottom: 1px solid var(--border);
        }

        .filters-bar {
            display: flex;
            gap: 12px;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-input,
        .filter-select {
            background: var(--bg-card);
            border: 1px solid var(--border);
            color: white;
            padding: 10px 14px;
            border-radius: 6px;
            font-size: 0.85rem;
            outline: none;
        }

        .filter-input {
            flex: 1.35;
            min-width: 170px;
        }

        .filter-select {
            flex: 1;
            min-width: 140px;
            cursor: pointer;
        }

        .filter-input:focus,
        .filter-select:focus {
            border-color: var(--accent);
        }

        .btn-clear {
            background: rgba(255, 60, 60, 0.1);
            color: #ff4d4d;
            border: 1px solid rgba(255, 60, 60, 0.25);
            padding: 9px 14px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
            outline: none;
        }

        .btn-clear:hover {
            background: rgba(255, 60, 60, 0.2);
            border-color: rgba(255, 60, 60, 0.4);
            color: #fff;
            transform: translateY(-1px);
        }

        .shown-count {
            margin-left: auto;
            font-size: 0.82rem;
            color: #a9a9a9;
            padding: 8px 10px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            white-space: nowrap;
        }

        .shown-count b {
            color: #fff;
        }

        .summary-wrap {
            max-width: 1200px;
            margin: 14px auto 0;
            width: 100%;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }

        .summary-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            transition: 0.2s;
        }

        .summary-card:hover {
            background: var(--bg-hover);
            border-color: #333;
            transform: translateY(-1px);
        }

        .summary-card.price-click {
            cursor: pointer;
        }

        .summary-left {
            display: flex;
            flex-direction: column;
            gap: 4px;
            min-width: 0;
        }

        .summary-title {
            font-size: 0.78rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.6px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .summary-sub {
            font-size: 0.75rem;
            color: #888;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .summary-value {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 12px;
            border-radius: 8px;
            font-weight: 800;
            font-family: 'Courier New', monospace;
            font-size: 1.05rem;
            min-width: 90px;
            background: #000;
            border: 1px solid rgba(255, 255, 255, 0.10);
        }

        .summary-value.ht {
            color: var(--accent);
            border-color: rgba(255, 102, 0, 0.45);
            box-shadow: 0 0 18px rgba(255, 102, 0, 0.10);
        }

        .summary-value.ft {
            color: var(--blue);
            border-color: rgba(120, 197, 255, 0.40);
            box-shadow: 0 0 18px rgba(120, 197, 255, 0.10);
        }

        #dashboard {
            padding: 30px;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
        }

        .empty-state {
            margin: 36px auto 0;
            max-width: 640px;
            padding: 24px 26px;
            border-radius: 14px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            background: linear-gradient(180deg, rgba(255, 209, 26, 0.08), rgba(255, 255, 255, 0.02));
            text-align: center;
        }

        .empty-state strong {
            display: block;
            font-size: 1rem;
            color: #fff;
            margin-bottom: 8px;
        }

        .empty-state p {
            color: var(--text-muted);
            line-height: 1.5;
        }

        .league-section {
            margin-bottom: 40px;
            animation: fadeIn 0.4s ease;
        }

        .league-title {
            font-size: 1rem;
            font-weight: 700;
            color: #fff;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 15px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 8px;
        }

        .category-group {
            margin-bottom: 25px;
            overflow-x: auto;
        }

        .category-name {
            font-size: 0.85rem;
            color: var(--accent);
            margin-bottom: 10px;
            font-weight: 600;
            display: inline-block;
        }

        .data-table {
            width: 100%;
            min-width: 980px;
            border-collapse: separate;
            border-spacing: 0 6px;
            table-layout: fixed;
        }

        .data-table th {
            color: var(--text-muted);
            font-size: 0.75rem;
            text-transform: uppercase;
            padding: 0 10px 5px;
            font-weight: 500;
        }

        .data-table td {
            background: var(--bg-card);
            padding: 14px 10px;
            font-size: 0.9rem;
            vertical-align: middle;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
        }

        .text-left {
            text-align: left;
        }

        .text-center {
            text-align: center;
        }

        .col-date {
            width: 12%;
        }

        .col-match {
            width: 30%;
        }

        .col-time {
            width: 10%;
        }

        .col-price {
            width: 10%;
        }

        .col-pay {
            width: 14%;
        }

        .col-actions {
            width: 14%;
        }

        .data-table td:first-child {
            border-left: 1px solid var(--border);
            border-radius: 8px 0 0 8px;
            padding-left: 20px;
        }

        .data-table td:last-child {
            border-right: 1px solid var(--border);
            border-radius: 0 8px 8px 0;
            padding-right: 20px;
        }

        .data-table tr:hover td {
            background: var(--bg-hover);
            border-color: #333;
        }

        .match-display {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            font-weight: 500;
            width: 100%;
        }

        .match-display>span:first-child,
        .match-display>span:last-child {
            flex: 1;
            width: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .match-display>span:first-child {
            text-align: right;
        }

        .match-display>span:last-child {
            text-align: left;
        }

        .team-active {
            color: white;
            font-weight: 700;
        }

        .team-opponent {
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        .score-box {
            background: #000;
            border: 1px solid #333;
            color: var(--accent);
            padding: 4px 10px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-weight: 700;
            min-width: 50px;
            text-align: center;
            text-transform: lowercase;
            flex: 0 0 auto;
        }

        .avg-row td {
            background: rgba(255, 255, 255, 0.03);
            border-color: #3a3a3a;
        }

        .avg-row:hover td {
            background: rgba(255, 255, 255, 0.05);
        }

        .score-box-avg {
            color: #ffffff;
            border-color: rgba(255, 255, 255, 0.25);
            background: #0d0d0d;
            text-transform: none;
        }

        .odd-badge-ht.odd-badge-avg {
            background: #000;
            border: 1px solid rgba(255, 102, 0, 0.60);
            box-shadow: 0 0 18px rgba(255, 102, 0, 0.12);
            width: 100%;
        }

        .odd-badge-ft.odd-badge-avg {
            background: #000;
            border: 1px solid rgba(120, 197, 255, 0.55);
            box-shadow: 0 0 18px rgba(120, 197, 255, 0.12);
            width: 100%;
        }

        .odd-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 700;
            font-family: 'Courier New', monospace;
            font-size: 0.95rem;
            min-width: 70px;
        }

        .odd-badge-ht {
            background: var(--accent-dim);
            color: var(--accent);
            border: 1px solid rgba(255, 102, 0, 0.3);
            box-shadow: 0 0 10px rgba(255, 102, 0, 0.05);
        }

        .odd-badge-ft {
            background: var(--blue-dim);
            color: var(--blue);
            border: 1px solid rgba(41, 121, 255, 0, 0.3);
            box-shadow: 0 0 10px rgba(41, 121, 255, 0.05);
        }

        .date-val {
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(4px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal {
            background: #181818;
            width: 100%;
            max-width: 400px;
            border-radius: 12px;
            border: 1px solid var(--border);
            padding: 25px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.6);
        }

        .modal h3 {
            margin-bottom: 20px;
            color: white;
            font-size: 1.1rem;
            text-align: center;
        }

        .form-compact {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .input-group label {
            display: block;
            font-size: 0.75rem;
            color: #888;
            margin-bottom: 4px;
        }

        .input-group input,
        .input-group select {
            width: 100%;
            background: #222;
            border: 1px solid #333;
            color: white;
            padding: 10px;
            border-radius: 6px;
            font-size: 0.9rem;
        }

        .input-group input:focus,
        .input-group select:focus {
            border-color: var(--accent);
            outline: none;
        }

        .row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .btn-submit {
            background: var(--accent);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
        }

        .btn-cancel {
            background: transparent;
            color: #666;
            border: none;
            padding: 10px;
            cursor: pointer;
            text-align: center;
            font-size: 0.85rem;
        }

        #newLeagueInput {
            display: none;
            margin-top: 8px;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media(max-width:900px) {
            .summary-grid {
                grid-template-columns: 1fr;
            }
        }

        @media(max-width:700px) {
            .filters-bar {
                flex-direction: column;
                align-items: stretch;
            }
            .shown-count {
                margin-left: 0;
                width: fit-content;
            }
            .data-table thead {
                display: none;
            }
            .category-group {
                overflow-x: visible;
            }
            .data-table,
            .data-table tbody,
            .data-table tr,
            .data-table td {
                display: block;
                width: 100%;
            }
            .data-table {
                min-width: 0;
            }
            .data-table tr {
                margin-bottom: 15px;
            }
            .data-table td {
                text-align: right;
                padding: 10px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                border-radius: 0 !important;
                border-left: none !important;
                border-right: none !important;
            }
            .data-table td::before {
                content: attr(data-label);
                color: var(--text-muted);
                font-size: 0.75rem;
                font-weight: 600;
                text-transform: uppercase;
            }
            .match-display {
                justify-content: flex-end;
                width: auto;
            }
            .match-display>span:first-child,
            .match-display>span:last-child {
                flex: initial;
                width: auto;
            }
            .row-actions {
                justify-content: flex-end;
            }
            .row-actions .row-btn {
                height: 30px;
            }
            .data-table td[data-label="Pagamento / Min"],
            .data-table td[data-label="Ações"] {
                flex-direction: column;
                align-items: stretch;
                gap: 8px;
            }
            .data-table td[data-label="Pagamento / Min"] .metric-stack {
                width: 100%;
                align-items: flex-end;
                text-align: right;
            }
            .data-table td[data-label="Ações"] .row-actions {
                width: 100%;
                flex-wrap: nowrap;
                gap: 4px;
                overflow: hidden;
            }
            .data-table td[data-label="Ações"] .row-actions .row-btn {
                flex: 1 1 0;
                min-width: 0;
                justify-content: center;
                padding: 0 6px;
                font-size: 0.65rem;
                letter-spacing: 0.2px;
            }
        }

        .price-click {
            cursor: pointer;
            user-select: none;
        }

        .price-click:hover {
            filter: brightness(1.08);
            transform: translateY(-1px);
        }

        .match-meta {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .source-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            padding: 4px 8px;
            border: 1px solid var(--border);
            background: rgba(255, 255, 255, 0.03);
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .source-pill.api {
            background: var(--accent);
            color: #111;
            border-color: transparent;
        }

        .time-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 98px;
            border-radius: 999px;
            padding: 7px 10px;
            border: 1px solid var(--border);
            background: rgba(255, 255, 255, 0.03);
            font-size: 0.8rem;
            font-weight: 700;
        }

        .metric-stack {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            line-height: 1.1;
        }

        .metric-main {
            font-weight: 700;
        }

        .metric-sub {
            font-size: 0.76rem;
            color: var(--text-muted);
        }

        .price-line-modal {
            --pl-cell-min-width: 14.25rem;
            --pl-minute-width: 2.15rem;
            --pl-uf-width: 3.95rem;
            --pl-odd-width: 2.5rem;
            --pl-tkm-width: 5.1rem;
            max-width: 920px;
            width: min(calc(100vw - 12px), 920px);
            padding: 16px 12px 14px;
        }

        .pl-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 8px;
        }

        .pl-head-left {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
            flex-wrap: wrap;
        }

        .pl-title {
            font-size: 1.02rem;
            font-weight: 900;
            letter-spacing: -0.2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .pl-close {
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: #bbb;
            border-radius: 10px;
            padding: 6px 10px;
            cursor: pointer;
            flex: 0 0 auto;
        }

        .pl-close:hover {
            background: rgba(255, 255, 255, 0.06);
            color: #fff;
        }

        .pl-live-btn {
            background: #0f0f0f;
            border: 1px solid rgba(255, 255, 255, 0.14);
            color: #e9e9e9;
            border-radius: 999px;
            padding: 6px 10px;
            font-weight: 900;
            font-size: 0.74rem;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.15s;
            flex: 0 0 auto;
            user-select: none;
            line-height: 1;
        }

        .pl-live-btn::before {
            content: "";
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #ff2a2a;
            box-shadow: 0 0 10px rgba(255, 42, 42, 0.35);
            display: inline-block;
        }

        .pl-live-btn:hover {
            background: rgba(255, 255, 255, 0.05);
            transform: translateY(-1px);
            border-color: rgba(255, 255, 255, 0.22);
            color: #fff;
        }

        .pl-live-btn.active {
            border-color: rgba(4, 255, 0, 0.40);
            box-shadow: 0 0 18px rgba(4, 255, 0, 0.10);
        }

        .pl-live-btn.active::before {
            background: #04ff00;
            box-shadow: 0 0 12px rgba(4, 255, 0, 0.25);
        }

        .pl-plus-btn {
            background: #0f0f0f;
            border: 1px solid rgba(255, 255, 255, 0.14);
            color: #e9e9e9;
            border-radius: 999px;
            padding: 6px 10px;
            font-weight: 900;
            font-size: 0.74rem;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.15s;
            flex: 0 0 auto;
            user-select: none;
            line-height: 1;
        }

        .pl-plus-btn:hover {
            background: rgba(255, 255, 255, 0.05);
            transform: translateY(-1px);
            border-color: rgba(255, 255, 255, 0.22);
            color: #fff;
        }

        .pl-plus-btn.active {
            border-color: rgba(255, 255, 255, 0.24);
            box-shadow: 0 0 18px rgba(255, 255, 255, 0.08);
        }

        .pl-plus-btn .tag {
            font-family: 'Courier New', monospace;
            font-weight: 900;
            padding: 2px 6px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.10);
        }

        .pl-live-inline {
            display: none;
            align-items: center;
            gap: 8px;
            flex: 0 0 auto;
        }

        .pl-live-inline input {
            width: 76px;
            background: #090909;
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 10px;
            padding: 8px 10px;
            font-weight: 900;
            outline: none;
            font-family: 'Courier New', monospace;
            letter-spacing: 0.3px;
            height: 32px;
        }

        .pl-live-inline input:focus {
            border-color: rgba(255, 255, 255, 0.22);
        }

        .pl-live-inline .hint {
            color: #ffffffd4;
            font-size: 0.76rem;
            user-select: none;
            white-space: nowrap;
            background: #19351299;
            padding: 5px 8px;
            border-radius: 8px;
            font-weight: 600;
            border-left: 2px solid #4dc731;
        }

        .pl-category-ms {
            flex: 0 0 auto;
            min-width: 220px;
            max-width: 240px;
            z-index: 30;
        }

        .pl-category-ms .ms-btn {
            background: #0f0f0f;
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 999px;
            padding: 7px 11px;
            gap: 8px;
            min-height: 34px;
        }

        .pl-category-ms .ms-btn:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.22);
            color: #fff;
        }

        .pl-category-ms .ms-btn .ms-label {
            font-size: 0.66rem;
        }

        .pl-category-ms .ms-btn .ms-summary {
            font-size: 0.78rem;
        }

        .pl-category-ms .ms-panel {
            left: auto;
            right: 0;
            width: 280px;
            top: calc(100% + 6px);
        }

        .pl-category-ms.disabled .ms-btn,
        .pl-category-ms .ms-btn:disabled {
            cursor: not-allowed;
            opacity: 0.45;
            transform: none !important;
            color: #b7b7b7;
        }

        .pl-category-ms.disabled .ms-btn:hover,
        .pl-category-ms .ms-btn:disabled:hover {
            background: #0f0f0f;
            border-color: rgba(255, 255, 255, 0.14);
            color: #b7b7b7;
        }

        .pl-sub {
            color: #8b8b8b;
            font-size: 0.82rem;
            margin-bottom: 10px;
        }

        .pl-switch {
            display: none;
            gap: 6px;
            margin: 0 0 10px;
            flex-wrap: wrap;
        }

        .pl-switch.grid {
            display: grid;
            grid-template-columns: 1.25fr repeat(4, 1fr);
            grid-template-rows: auto auto;
            gap: 6px;
        }

        .pl-pill {
            background: #0f0f0f;
            border: 1px solid rgba(255, 255, 255, 0.10);
            border-radius: 12px;
            padding: 9px 7px;
            cursor: pointer;
            transition: 0.15s;
            min-width: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .pl-pill:hover {
            background: rgba(255, 255, 255, 0.04);
            transform: translateY(-1px);
        }

        .pl-pill.disabled {
            opacity: 0.35;
            cursor: not-allowed;
            transform: none !important;
        }

        .pl-pill .lbl {
            font-size: 0.60rem;
            color: #9a9a9a;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .pl-pill .val {
            font-family: 'Courier New', monospace;
            font-weight: 900;
            font-size: 1.05rem;
            margin-top: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .pl-pill.active {
            border-color: rgba(255, 255, 255, 0.24);
            box-shadow: 0 0 18px rgba(255, 255, 255, 0.06);
        }

        .pl-pill.ht.active {
            border-color: rgba(255, 102, 0, 0.55);
            box-shadow: 0 0 18px rgba(255, 102, 0, 0.10);
        }

        .pl-pill.ft.active {
            border-color: rgba(120, 197, 255, 0.55);
            box-shadow: 0 0 18px rgba(120, 197, 255, 0.10);
        }

        .pl-pill.span-normal {
            grid-column: 1;
            grid-row: 1 / span 2;
            align-items: flex-start;
            padding: 10px 10px;
        }

        .pl-pill.span-normal .lbl {
            font-size: 0.62rem;
        }

        .pl-pill.span-normal .val {
            font-size: 1.15rem;
        }

        .pl-pill.row1 {
            grid-row: 1;
        }

        .pl-pill.row2 {
            grid-row: 2;
        }

        .pl-pill.c2 {
            grid-column: 2;
        }

        .pl-pill.c3 {
            grid-column: 3;
        }

        .pl-pill.c4 {
            grid-column: 4;
        }

        .pl-pill.c5 {
            grid-column: 5;
        }

        .pl-body {
            background: #111;
            border: 1px solid rgba(255, 255, 255, 0.10);
            border-radius: 12px;
            overflow-x: auto;
            overflow-y: auto;
            padding: 9px;
            scrollbar-gutter: stable;
            -webkit-overflow-scrolling: touch;
        }

        .pl-grid {
            display: grid;
            grid-template-columns: repeat(var(--pl-column-count, 3), minmax(var(--pl-cell-min-width), 1fr));
            grid-auto-columns: minmax(var(--pl-cell-min-width), 1fr);
            grid-template-rows: repeat(12, auto);
            grid-auto-flow: column;
            gap: 6px 8px;
            width: max-content;
            min-width: 100%;
        }

        .pl-cell {
            display: grid;
            grid-template-columns: minmax(var(--pl-minute-width), max-content) minmax(calc(var(--pl-uf-width) + var(--pl-odd-width) + var(--pl-tkm-width) + 1rem), 1fr);
            align-items: center;
            gap: 8px;
            padding: 7px 8px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.06);
            font-family: 'Courier New', monospace;
            line-height: 1;
            min-width: var(--pl-cell-min-width);
            font-variant-numeric: tabular-nums;
        }

        .pl-cell .m {
            color: #d7d7d7;
            font-size: 0.82rem;
            white-space: nowrap;
            min-width: var(--pl-minute-width);
        }

        .pl-cell .o {
            display: grid;
            grid-template-columns: minmax(var(--pl-uf-width), max-content) minmax(var(--pl-odd-width), max-content) minmax(var(--pl-tkm-width), max-content);
            align-items: center;
            justify-content: flex-end;
            gap: 6px;
            white-space: nowrap;
            flex: 1 1 auto;
            min-width: 0;
            justify-items: end;
        }

        .pl-cell .o .uf {
            display: grid;
            grid-auto-flow: column;
            align-items: center;
            gap: 5px;
            font-family: 'Courier New', monospace;
            font-weight: 900;
            zoom: 96%;
            min-width: var(--pl-uf-width);
            width: auto;
            text-align: left;
            justify-content: start;
        }

        .pl-cell .o .tkm {
            display: grid;
            grid-auto-flow: column;
            align-items: center;
            justify-content: flex-end;
            gap: 5px;
            font-family: 'Courier New', monospace;
            font-weight: 900;
            zoom: 96%;
            min-width: var(--pl-tkm-width);
            width: auto;
            text-align: right;
            white-space: nowrap;
        }

        .pl-cell .o .uf-tag {
            padding: 2px 6px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.10);
            font-size: 0.70rem;
            letter-spacing: 0.3px;
            color: #eaeaea;
        }

        .pl-cell .o .uf-val {
            font-size: 0.86rem;
            color: #ffffffc4;
            min-width: 2.2rem;
            text-align: right;
        }

        .pl-cell .o .tkm-tag {
            padding: 2px 6px;
            border-radius: 8px;
            background: rgba(255, 209, 26, 0.10);
            border: 1px solid rgba(255, 209, 26, 0.18);
            font-size: 0.68rem;
            letter-spacing: 0.3px;
            color: #f9e69d;
        }

        .pl-cell .o .tkm-val {
            font-size: 0.84rem;
            color: #fff6d0;
            min-width: 2.5rem;
            text-align: right;
        }

        .pl-cell .o .main {
            font-weight: 900;
            font-size: 0.90rem;
            white-space: nowrap;
            width: auto;
            min-width: var(--pl-odd-width);
            text-align: right;
        }

        .pl-plus-row {
            display: flex;
            min-width: var(--pl-cell-min-width);
        }

        .pl-plus-row .m {
            font-weight: 900;
            letter-spacing: 0.4px;
            color: #e6e6e6;
        }

        .pl-plus-row .o {
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 4px;
            justify-content: flex-end;
            grid-template-columns: none;
        }

        .pl-plus-row input {
            width: 38px;
            background: #090909;
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 10px;
            padding: 6px 8px;
            font-weight: 900;
            outline: none;
            font-family: 'Courier New', monospace;
            height: 28px;
            text-align: center;
        }

        .pl-plus-row input:focus {
            border-color: rgba(255, 255, 255, 0.24);
        }

        .pl-step-btn {
            width: 30px;
            height: 28px;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.14);
            background: #0f0f0f;
            color: #fff;
            font-weight: 900;
            font-size: 1.00rem;
            line-height: 1;
            cursor: pointer;
            user-select: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: 0.15s;
        }

        .pl-step-btn:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.22);
            transform: translateY(-1px);
        }

        .pl-step-btn:active {
            transform: translateY(0px);
            filter: brightness(1.05);
        }

        .pl-hit-2 {
            background: #0100ff42;
            border-color: rgba(255, 255, 255, 0.14);
            box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.06) inset;
            border-left: 3px solid rgba(255, 255, 255, 0.25);
        }

        .pl-hit-3 {
            background: #0100ff42;
            border-color: rgba(255, 255, 255, 0.18);
            border-left: 3px solid rgba(255, 255, 255, 0.45);
        }

        .pl-hit-80 {
            background: rgba(255, 102, 0, 0.12);
            border-color: rgba(255, 102, 0, 0.22);
            border-left: 3px solid rgba(255, 102, 0, 0.35);
        }

        .pl-live-now {
            background: rgb(4 255 0 / 24%) !important;
            border-color: rgb(4 255 0 / 56%) !important;
            box-shadow: 0 0 0 1px rgba(4, 255, 0, 0.10) inset, 0 0 18px rgba(4, 255, 0, 0.08);
        }

        .pl-live-now .m {
            color: #eaffea;
            font-weight: 900;
        }

        @media (max-width:560px) {
            .price-line-modal {
                width: calc(100vw - 8px);
                padding: 12px 10px;
            }
            .pl-body {
                padding: 8px;
            }
            .pl-switch.grid {
                grid-template-columns: 1fr 1fr;
                grid-template-rows: auto auto auto auto auto;
                gap: 6px;
            }
            .pl-pill.span-normal {
                grid-column: 1 / span 2;
                grid-row: 1;
            }
            .pl-pill.row1.c2 {
                grid-row: 2;
                grid-column: 1;
            }
            .pl-pill.row1.c3 {
                grid-row: 2;
                grid-column: 2;
            }
            .pl-pill.row1.c4 {
                grid-row: 3;
                grid-column: 1;
            }
            .pl-pill.row1.c5 {
                grid-row: 3;
                grid-column: 2;
            }
            .pl-pill.row2.c2 {
                grid-row: 4;
                grid-column: 1;
            }
            .pl-pill.row2.c3 {
                grid-row: 4;
                grid-column: 2;
            }
            .pl-pill.row2.c4 {
                grid-row: 5;
                grid-column: 1;
            }
            .pl-pill.row2.c5 {
                grid-row: 5;
                grid-column: 2;
            }
        }

        .pl-odd-ht {
            color: var(--accent);
        }

        .pl-odd-ft {
            color: var(--blue);
        }

        .pl-custom {
            display: none;
            gap: 10px;
            align-items: center;
            margin: 0 0 10px;
        }

        .pl-group-row {
            display: flex;
            gap: 10px;
            align-items: center;
            margin: 0 0 10px;
        }

        .pl-custom .pc,
        .pl-group-row .pc {
            flex: 1;
            background: #0f0f0f;
            border: 1px solid rgba(255, 255, 255, 0.10);
            border-radius: 12px;
            padding: 10px;
            min-width: 0;
        }

        .pl-group-row .pc {
            flex: 0 0 190px;
            max-width: 220px;
        }

        .pl-custom .pc label,
        .pl-group-row .pc label {
            display: block;
            font-size: 0.70rem;
            color: #9a9a9a;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-bottom: 6px;
        }

        .pl-custom .pc select,
        .pl-custom .pc input,
        .pl-group-row .pc select {
            width: 100%;
            background: #090909;
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.10);
            border-radius: 10px;
            padding: 10px 10px;
            font-weight: 800;
            outline: none;
        }

        .pl-custom .pc select:focus,
        .pl-custom .pc input:focus,
        .pl-group-row .pc select:focus {
            border-color: rgba(255, 255, 255, 0.20);
        }

        .ms {
            position: relative;
            flex: 1.25;
            min-width: 240px;
        }

        .ms.league {
            flex: 1.55;
            min-width: 290px;
        }

        .ms.cat {
            flex: 1.1;
            min-width: 205px;
        }

        .ms-btn {
            width: 100%;
            background: var(--bg-card);
            border: 1px solid var(--border);
            color: #fff;
            padding: 10px 14px;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            justify-content: space-between;
            outline: none;
            transition: 0.15s;
        }

        .ms-btn:focus {
            border-color: var(--accent);
        }

        .ms-btn .ms-label {
            color: #9a9a9a;
            font-weight: 700;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            white-space: nowrap;
        }

        .ms-btn .ms-summary {
            flex: 1;
            text-align: left;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-weight: 600;
            color: #fff;
            min-width: 0;
        }

        .ms-btn .ms-caret {
            color: #bbb;
            flex: 0 0 auto;
        }

        .ms-panel {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            width: 100%;
            background: #121212;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.55);
            z-index: 200;
            display: none;
            overflow: hidden;
        }

        .ms.open .ms-panel {
            display: block;
        }

        .ms-actions {
            display: flex;
            gap: 10px;
            padding: 10px 10px 8px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            background: rgba(255, 255, 255, 0.02);
        }

        .ms-mini-btn {
            background: #0f0f0f;
            border: 1px solid rgba(255, 255, 255, 0.10);
            color: #d0d0d0;
            padding: 7px 10px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 0.78rem;
            font-weight: 800;
        }

        .ms-mini-btn:hover {
            background: rgba(255, 255, 255, 0.04);
            color: #fff;
            border-color: rgba(255, 255, 255, 0.16);
        }

        .ms-search {
            padding: 10px 10px 8px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            background: rgba(255, 255, 255, 0.02);
        }

        .ms-search input {
            width: 100%;
            background: #0f0f0f;
            border: 1px solid rgba(255, 255, 255, 0.14);
            color: #fff;
            padding: 9px 10px;
            border-radius: 10px;
            font-size: 0.82rem;
            font-weight: 800;
            outline: none;
        }

        .ms-search input:focus {
            border-color: rgba(255, 102, 0, 0.55);
            box-shadow: 0 0 18px rgba(255, 102, 0, 0.10);
        }

        .ms-list {
            max-height: 320px;
            overflow: auto;
            padding: 8px 10px 10px;
        }

        .ms-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 8px;
            border-radius: 10px;
            cursor: pointer;
            user-select: none;
        }

        .ms-item:hover {
            background: rgba(255, 255, 255, 0.04);
        }

        .ms-item input {
            accent-color: var(--accent);
            cursor: pointer;
        }

        .ms-item span {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: block;
            width: 100%;
            font-size: 0.86rem;
            color: #e6e6e6;
        }

        .ms-sep {
            margin: 10px 0 8px;
            padding: 6px 8px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: #bdbdbd;
            font-weight: 900;
            letter-spacing: 0.7px;
            font-size: 0.70rem;
            text-transform: uppercase;
            user-select: none;
            cursor: default;
        }

        .ms-sep:first-child {
            margin-top: 0;
        }

        .row-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            width: 100%;
        }

        .row-btn {
            height: 32px;
            padding: 0 10px;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            background: #0f0f0f;
            color: #eaeaea;
            font-weight: 900;
            font-size: 0.74rem;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            cursor: pointer;
            transition: 0.15s;
            user-select: none;
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }

        .row-btn:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.22);
            transform: translateY(-1px);
            color: #fff;
        }

        .row-btn:disabled {
            cursor: default;
            opacity: 0.55;
            transform: none;
        }

        .row-btn.edit {
            border-color: rgba(255, 102, 0, 0.28);
            box-shadow: 0 0 14px rgba(255, 102, 0, 0.06);
        }

        .row-btn.del {
            border-color: rgba(255, 60, 60, 0.28);
            box-shadow: 0 0 14px rgba(255, 60, 60, 0.06);
        }
    </style>
</head>

<body>
    <header>
        <div class="logo-area">
            <img src="assets/logo-primary.png" alt="PriceJust" />
            <h1>Price<span>Just</span> Local</h1>
        </div>
        <div class="hdr-actions">
            <span class="user-chip">Sessão: <?= htmlspecialchars((string) ($appConfig['username'] ?? 'admin'), ENT_QUOTES, 'UTF-8') ?></span>
            <button class="btn-secondary" type="button" onclick="abrirCustomLP()">Custom LP</button>
            <button class="btn-add" type="button" onclick="abrirModal()">
        <span>+</span> Novo
      </button>
            <button class="btn-secondary" type="button" id="logoutBtn">Sair</button>
        </div>
    </header>

    <div class="top-banner" id="apiWarning"></div>

    <div class="filters-container">
        <div class="filters-bar">
            <input type="text" id="filterSearch" class="filter-input" placeholder="Pesquisar time..." />

            <div class="ms league" id="msLeague" data-ms-root="league">
                <button type="button" class="ms-btn" data-ms="league" aria-haspopup="listbox" aria-expanded="false">
          <span class="ms-label">Ligas</span>
          <span class="ms-summary" id="msLeagueSummary">Todas</span>
          <span class="ms-caret">▾</span>
        </button>
                <div class="ms-panel" role="listbox" aria-label="Selecionar ligas">
                    <div class="ms-actions">
                        <button type="button" class="ms-mini-btn" data-ms-action="all" data-ms="league">Marcar todas</button>
                        <button type="button" class="ms-mini-btn" data-ms-action="none" data-ms="league">Limpar</button>
                    </div>
                    <div class="ms-search">
                        <input id="msLeagueSearch" type="text" placeholder="Buscar liga..." autocomplete="off" />
                    </div>
                    <div class="ms-list" id="msLeagueList"></div>
                </div>
            </div>

            <div class="ms cat" id="msCategory" data-ms-root="category">
                <button type="button" class="ms-btn" data-ms="category" aria-haspopup="listbox" aria-expanded="false">
          <span class="ms-label">Categorias</span>
          <span class="ms-summary" id="msCategorySummary">Todas</span>
          <span class="ms-caret">▾</span>
        </button>
                <div class="ms-panel" role="listbox" aria-label="Selecionar categorias">
                    <div class="ms-actions">
                        <button type="button" class="ms-mini-btn" data-ms-action="all" data-ms="category">Marcar todas</button>
                        <button type="button" class="ms-mini-btn" data-ms-action="none" data-ms="category">Limpar</button>
                    </div>
                    <div class="ms-list" id="msCategoryList"></div>
                </div>
            </div>

            <select id="filterStatus" class="filter-select">
        <option value="">Todos os Resultados</option>
        <option value="vencendo">Vencendo</option>
        <option value="empatando">Empatando</option>
        <option value="perdendo">Perdendo</option>
      </select>

            <select id="filterVenue" class="filter-select">
        <option value="">Casa e Fora</option>
        <option value="Casa">Jogando em Casa</option>
        <option value="Fora">Jogando Fora</option>
      </select>

            <select id="paymentWindow" class="filter-select">
        <option value="5">Pagamento / 5 min</option>
        <option value="10">Pagamento / 10 min</option>
        <option value="15">Pagamento / 15 min</option>
      </select>

            <button type="button" class="btn-clear" onclick="limparFiltros()">Limpar</button>
            <div class="shown-count" id="shownCount"><b>0</b> jogos</div>
        </div>

        <div class="summary-wrap" id="globalSummary">
            <div class="summary-grid" style="margin-bottom:12px;">
                <div class="summary-card">
                    <div class="summary-left">
                        <div class="summary-title"><b style="font-weight:900;color:var(--accent)">HT • Jogo Under ▼</b></div>
                        <div class="summary-sub">abaixo da média</div>
                    </div>
                    <div class="summary-value ht" id="sumHTUnder">--</div>
                </div>
                <div class="summary-card">
                    <div class="summary-left">
                        <div class="summary-title"><b style="font-weight:900;color:var(--accent)">HT • Jogo Normal</b></div>
                        <div class="summary-sub">média global</div>
                    </div>
                    <div class="summary-value ht" id="sumHTTotal">--</div>
                </div>
                <div class="summary-card">
                    <div class="summary-left">
                        <div class="summary-title"><b style="font-weight:900;color:var(--accent)">HT • Jogo Over ▲</b></div>
                        <div class="summary-sub">acima da média</div>
                    </div>
                    <div class="summary-value ht" id="sumHTOver">--</div>
                </div>
            </div>

            <div class="summary-grid">
                <div class="summary-card">
                    <div class="summary-left">
                        <div class="summary-title"><b style="font-weight:900;color:var(--blue)">FT • Jogo Under ▼</b></div>
                        <div class="summary-sub">abaixo da média</div>
                    </div>
                    <div class="summary-value ft" id="sumFTUnder">--</div>
                </div>
                <div class="summary-card">
                    <div class="summary-left">
                        <div class="summary-title"><b style="font-weight:900;color:var(--blue)">FT • Jogo Normal</b></div>
                        <div class="summary-sub">média global</div>
                    </div>
                    <div class="summary-value ft" id="sumFTTotal">--</div>
                </div>
                <div class="summary-card">
                    <div class="summary-left">
                        <div class="summary-title"><b style="font-weight:900;color:var(--blue)">FT • Jogo Over ▲</b></div>
                        <div class="summary-sub">acima da média</div>
                    </div>
                    <div class="summary-value ft" id="sumFTOver">--</div>
                </div>
            </div>
        </div>
    </div>

    <div id="dashboard"></div>

    <div class="modal-overlay" id="modalOverlay">
        <div class="modal">
            <h3>Novo Registro</h3>
            <form id="formEstudo" class="form-compact">
                <div class="input-group">
                    <label>Campeonato</label>
                    <select id="leagueSelect" onchange="verificarNovaLiga(this)">
            <option value="" disabled selected>Selecione...</option>
            <option value="NEW_LEAGUE_OPTION" style="color:var(--accent); font-weight:bold;">+ Novo Campeonato</option>
          </select>
                    <input type="text" id="newLeagueInput" placeholder="Nome do Campeonato" />
                </div>

                <div class="row-2">
                    <div class="input-group">
                        <label>Categoria</label>
                        <select id="category" required>
              <option value="Jogo parelho">Jogo parelho</option>
              <option value="Jogo de favorito">Jogo de favorito</option>
              <option value="Jogo de super favorito">Jogo de super favorito</option>
            </select>
                    </div>
                    <div class="input-group">
                        <label>Data</label>
                        <input type="month" id="date" required />
                    </div>
                </div>

                <div class="input-group">
                    <label>Time</label>
                    <input type="text" id="team" placeholder="Nome do time" required />
                </div>

                <div class="row-2">
                    <div class="input-group">
                        <label>Mando</label>
                        <select id="venue" required>
              <option value="Casa">Casa</option>
              <option value="Fora">Fora</option>
            </select>
                    </div>
                    <div class="input-group">
                        <label>Placar Intervalo</label>
                        <input type="text" id="score_ht" placeholder="Ex: 1x0" required />
                    </div>
                </div>

                <div class="row-2">
                    <div class="input-group">
                        <label>Odd HT (Under Limite)</label>
                        <input type="number" step="0.01" id="odd_ht" placeholder="3.25" required />
                    </div>
                    <div class="input-group">
                        <label>Odd FT (Under Limite)</label>
                        <input type="number" step="0.01" id="odd_ft" placeholder="4.60" required />
                    </div>
                </div>

                <button type="submit" class="btn-submit">Salvar Dados</button>
                <button type="button" class="btn-cancel" onclick="fecharModal()">Cancelar</button>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="editOverlay" style="z-index:1050;">
        <div class="modal">
            <h3>Editar Registro</h3>
            <form id="formEdit" class="form-compact">
                <input type="hidden" id="editId" />
                <div class="input-group">
                    <label>Campeonato</label>
                    <input type="text" id="editLeague" required />
                </div>
                <div class="row-2">
                    <div class="input-group">
                        <label>Categoria</label>
                        <select id="editCategory" required>
              <option value="Jogo parelho">Jogo parelho</option>
              <option value="Jogo de favorito">Jogo de favorito</option>
              <option value="Jogo de super favorito">Jogo de super favorito</option>
            </select>
                    </div>
                    <div class="input-group">
                        <label>Data</label>
                        <input type="month" id="editDate" required />
                    </div>
                </div>
                <div class="input-group">
                    <label>Time</label>
                    <input type="text" id="editTeam" required />
                </div>
                <div class="row-2">
                    <div class="input-group">
                        <label>Mando</label>
                        <select id="editVenue" required>
              <option value="Casa">Casa</option>
              <option value="Fora">Fora</option>
            </select>
                    </div>
                    <div class="input-group">
                        <label>Placar Intervalo</label>
                        <input type="text" id="editScoreHT" required />
                    </div>
                </div>
                <div class="row-2">
                    <div class="input-group">
                        <label>Odd HT (Under Limite)</label>
                        <input type="number" step="0.01" id="editOddHT" required />
                    </div>
                    <div class="input-group">
                        <label>Odd FT (Under Limite)</label>
                        <input type="number" step="0.01" id="editOddFT" required />
                    </div>
                </div>
                <button type="submit" class="btn-submit">Salvar Alterações</button>
                <button type="button" class="btn-cancel" onclick="fecharEdit()">Cancelar</button>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="priceLineOverlay" style="z-index:1100;">
        <div class="modal price-line-modal">
            <div class="pl-head">
                <div class="pl-head-left">
                    <div class="pl-title" id="plTitle">Linha de preço</div>
                    <button class="pl-plus-btn" id="plPlusBtn" type="button" title="Mostrar tabela 35+/80+">
            <span id="plPlusTxt">80MIN+</span>
            <span class="tag">+</span>
          </button>
                    <button class="pl-live-btn" id="plLiveBtn" type="button" title="Marcar minuto ao vivo">AO VIVO</button>
                    <div class="ms cat pl-category-ms" id="plCategoryMs" data-ms-root="category">
                        <button type="button" class="ms-btn" data-ms="category" aria-haspopup="listbox" aria-expanded="false">
              <span class="ms-label">Categorias</span>
              <span class="ms-summary">Todas</span>
              <span class="ms-caret">â–¾</span>
            </button>
                        <div class="ms-panel" role="listbox" aria-label="Selecionar categorias da Linha de preço">
                            <div class="ms-actions">
                                <button type="button" class="ms-mini-btn" data-ms-action="all" data-ms="category">Marcar todas</button>
                                <button type="button" class="ms-mini-btn" data-ms-action="none" data-ms="category">Limpar</button>
                            </div>
                            <div class="ms-list"></div>
                        </div>
                    </div>
                    <div class="pl-live-inline" id="plLiveInline">
                        <input id="plLiveTime" type="text" inputmode="numeric" placeholder="77:12" maxlength="5" />
                        <div class="hint" id="plLiveHint">Tempo Atual: —</div>
                    </div>
                </div>
                <button class="pl-close" type="button" onclick="fecharPriceLine()">X</button>
            </div>

            <div class="pl-sub" id="plSub">—</div>

            <div class="pl-custom" id="plCustomWrap">
                <div class="pc">
                    <label>Tempo</label>
                    <select id="plCustomPhase">
            <option value="HT">HT</option>
            <option value="FT" selected>FT</option>
          </select>
                </div>
                <div class="pc">
                    <label>Linha de Preço</label>
                    <input id="plCustomOdd" type="number" step="0.10" inputmode="decimal" placeholder="4.60" />
                </div>
            </div>

            <div class="pl-group-row">
                <div class="pc">
                    <label>Agrupar tempos</label>
                    <select id="plGroupStep">
            <option value="1">1 em 1</option>
            <option value="5">5 em 5</option>
            <option value="10">10 em 10</option>
            <option value="15">15 em 15</option>
          </select>
                </div>
            </div>

            <div class="pl-switch" id="plSwitch"></div>

            <div class="pl-body">
                <div id="plGrid" class="pl-grid">
                    <div class="pl-cell pl-plus-row" id="plPlusInline" style="display:none;">
                        <div class="m">+ADD</div>
                        <div class="o">
                            <button type="button" id="plPlusMinus" class="pl-step-btn" aria-label="Diminuir acréscimos">−</button>
                            <input id="plPlusAdd" type="text" inputmode="numeric" maxlength="2" placeholder="0" />
                            <button type="button" id="plPlusPlus" class="pl-step-btn" aria-label="Aumentar acréscimos">+</button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        const APP_CONFIG = <?= json_encode($appConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

        let TODOS_DADOS = [];
        let PL_CUSTOM_MODE = false;
        let LAST_API_META = {
            stale: false,
            configured: true,
            from_cache: false,
            warning: null,
            fetched_at: null,
            refresh_seconds: APP_CONFIG.refreshSeconds || 60
        };
        let SELECTED_PAYMENT_WINDOW = 5;
        let PRICE_LINE_GROUP_STEP = 1;

        let RENDER_QUEUE = [];
        let CURRENT_RENDER_INDEX = 0;
        const RENDER_BATCH_SIZE = 100;
        let CURRENT_TBODY_REF = null;
        let IS_RENDERING = false;

        let LAST_SUMMARY = {
            HT: {
                vvunder: null,
                vunder: null,
                under: null,
                munder: null,
                total: null,
                mover: null,
                over: null,
                vover: null,
                vvover: null
            },
            FT: {
                vvunder: null,
                vunder: null,
                under: null,
                munder: null,
                total: null,
                mover: null,
                over: null,
                vover: null,
                vvover: null
            }
        };

        const ACTIVE_FILTERS = {
            league: new Set(),
            category: new Set()
        };
        const CATEGORY_OPTIONS = ["Jogo parelho", "Jogo de favorito", "Jogo de super favorito"];

        const LADDER_RANGES = [{
                upTo: 2.00,
                step: 0.01
            },
            {
                upTo: 3.00,
                step: 0.02
            },
            {
                upTo: 4.00,
                step: 0.05
            },
            {
                upTo: 6.00,
                step: 0.10
            },
            {
                upTo: 10.00,
                step: 0.20
            },
            {
                upTo: 20.00,
                step: 0.50
            },
            {
                upTo: 30.00,
                step: 1.00
            },
            {
                upTo: 50.00,
                step: 2.00
            },
            {
                upTo: 100.00,
                step: 5.00
            },
            {
                upTo: Infinity,
                step: 10.00
            }
        ];

        const CORRELATION_AFRENTE = [{
                l: 1.20,
                af: 1.01
            }, {
                l: 1.42,
                af: 1.05
            }, {
                l: 1.67,
                af: 1.10
            }, {
                l: 2.00,
                af: 1.18
            },
            {
                l: 2.50,
                af: 1.30
            }, {
                l: 3.00,
                af: 1.40
            }, {
                l: 3.50,
                af: 1.50
            }, {
                l: 3.90,
                af: 1.60
            },
            {
                l: 4.40,
                af: 1.70
            }, {
                l: 4.60,
                af: 1.80
            }, {
                l: 5.00,
                af: 1.90
            }, {
                l: 5.50,
                af: 2.00
            },
            {
                l: 6.20,
                af: 2.20
            }, {
                l: 7.20,
                af: 2.40
            }, {
                l: 8.00,
                af: 2.60
            }, {
                l: 9.00,
                af: 2.80
            },
            {
                l: 10.00,
                af: 3.00
            }
        ];

        let PL_LIVE_ENABLED = false;
        let PL_LIVE_TIME_SEC = null;
        let PL_LIVE_TIMER = null;
        let PL_LIVE_LAST_TS = null;
        let PL_LIVE_TIME_STR = "";
        let PL_LIVE_LAST_PLUSMODE = null;

        function escapeHtml(s) {
            return String(s ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", "&#039;");
        }

        function stripAccents(s) {
            return String(s || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }

        function normalizeSearch(s) {
            return stripAccents(String(s || '').trim().toLowerCase());
        }

        function isLogged() {
            return true;
        }

        function getRowId(j) {
            const direct = (j && (j.id ?? j._id ?? j.uid ?? j.key)) ?? null;
            if (direct != null && String(direct).trim() !== '') return String(direct);
            const parts = [j ?.league, j ?.category, j ?.team, j ?.venue, j ?.date, j ?.score_ht, j ?.odd_ht, j ?.odd_ft].map(v => String(v ?? '').trim()).join('|');
            let h = 2166136261;
            for (let i = 0; i < parts.length; i++) {
                h ^= parts.charCodeAt(i);
                h = Math.imul(h, 16777619);
            }
            return `row_${(h >>> 0).toString(16)}`;
        }

        function getLadderTick(val) {
            if (!Number.isFinite(val) || val < 1.01) return 1.01;
            for (let i = 0; i < LADDER_RANGES.length; i++) {
                const r = LADDER_RANGES[i];
                if (val <= r.upTo) {
                    const step = r.step;
                    const rounded = Math.round(val / step) * step;
                    return Math.max(1.01, Number(rounded.toFixed(2)));
                }
            }
            return Number(val.toFixed(2));
        }

        function getPriceLineTargetMinute(phase, plusMode, addMin) {
            const p = (phase === 'HT') ? 'HT' : 'FT';
            const baseEnd = (p === 'HT') ? 45 : 90;
            if (!plusMode) return baseEnd;
            const add = Math.max(0, Math.min(19, parseInt(addMin || 0, 10) || 0));
            return baseEnd + add;
        }

        function calcTickPerMinValue(odd, minute, targetMinute) {
            const currentOdd = Number(odd);
            const currentMinute = Number(minute);
            const endMinute = Number(targetMinute);
            if (!Number.isFinite(currentOdd) || !Number.isFinite(currentMinute) || !Number.isFinite(endMinute)) return null;
            const remainingMinutes = endMinute - currentMinute;
            if (remainingMinutes <= 0) return null;
            const oddDelta = currentOdd - 1;
            if (oddDelta < 0) return null;
            return oddDelta / remainingMinutes;
        }

        function hydrateTickPerMin(line, targetMinute) {
            if (!Array.isArray(line)) return [];
            for (let i = 0; i < line.length; i++) {
                const row = line[i] || {};
                line[i].tickPerMin = calcTickPerMinValue(row.odd, row.minute, targetMinute);
            }
            return line;
        }

        function sanitizePriceLineGroupStep(step) {
            const normalized = Number(step);
            return [1, 5, 10, 15].includes(normalized) ? normalized : 1;
        }

        function getPriceLineGroupStep() {
            return sanitizePriceLineGroupStep(PRICE_LINE_GROUP_STEP);
        }

        function syncPriceLineGroupUi() {
            const select = document.getElementById('plGroupStep');
            if (select) select.value = String(getPriceLineGroupStep());
        }

        function formatPriceLineRange(startMinute, endMinute) {
            return startMinute === endMinute ? `${startMinute}'` : `${startMinute}-${endMinute}'`;
        }

        function buildGroupedPriceLine(line, step) {
            if (!Array.isArray(line) || !line.length) return [];
            const groupStep = sanitizePriceLineGroupStep(step);
            const grouped = [];
            for (let i = 0; i < line.length; i += groupStep) {
                const bucket = line.slice(i, i + groupStep);
                const first = bucket[0];
                const last = bucket[bucket.length - 1];
                const tickValues = bucket
                    .map((row) => Number(row?.tickPerMin))
                    .filter((value) => Number.isFinite(value));
                const tickPerMin = tickValues.length
                    ? tickValues.reduce((total, value) => total + value, 0) / tickValues.length
                    : null;
                grouped.push({
                    ...first,
                    tickPerMin,
                    minuteStart: first.minute,
                    minuteEnd: last.minute,
                    rangeLabel: formatPriceLineRange(first.minute, last.minute)
                });
            }
            return grouped;
        }

        function rangeContainsMinute(row, minute) {
            if (!Number.isFinite(minute)) return false;
            const minuteStart = Number(row?.minuteStart ?? row?.minute);
            const minuteEnd = Number(row?.minuteEnd ?? row?.minute);
            return Number.isFinite(minuteStart) && Number.isFinite(minuteEnd) && minute >= minuteStart && minute <= minuteEnd;
        }

        function interpolateCorrelation(fairLimite) {
            if (!Number.isFinite(fairLimite)) return NaN;
            const table = CORRELATION_AFRENTE;
            const first = table[0];
            const last = table[table.length - 1];
            if (fairLimite <= first.l) return first.af;
            if (fairLimite >= last.l) return last.af;
            for (let i = 0; i < table.length - 1; i++) {
                const a = table[i];
                const b = table[i + 1];
                if (fairLimite >= a.l && fairLimite <= b.l) {
                    if (b.l - a.l === 0) return a.af;
                    const w = (fairLimite - a.l) / (b.l - a.l);
                    return a.af + (b.af - a.af) * w;
                }
            }
            return last.af;
        }

        function addTicksFrom(baseOddFloor, ticks) {
            const raw = baseOddFloor + (Number(ticks || 0) / 100);
            return getLadderTick(raw);
        }

        function buildPriceLine(phase, baseOdd) {
            const out = [];
            if (!Number.isFinite(baseOdd) || baseOdd <= 1.0001) return out;
            const targetMinute = getPriceLineTargetMinute(phase, false, 0);
            const halfStart = (phase === 'HT') ? 0 : 45;
            const limit = (phase === 'HT') ? 35 : 80;
            for (let m = halfStart; m <= limit; m++) {
                const t = m - halfStart;
                const raw = Math.pow(baseOdd, 1 - (t * 0.02));
                if (!(raw > 1.0001)) continue;
                const tick = getLadderTick(raw);
                out.push({
                    minute: m,
                    odd: tick,
                    tickPerMin: null
                });
            }
            return hydrateTickPerMin(out, targetMinute);
        }

        function buildPriceLinePlus(phase, baseOdd, addMin) {
            const out = [];
            if (!Number.isFinite(baseOdd) || baseOdd <= 1.00) return out;

            const p = (phase === 'HT') ? 'HT' : 'FT';
            const baseStart = (p === 'HT') ? 30 : 75;
            const baseEnd = (p === 'HT') ? 45 : 90;

            const add = Math.max(0, Math.min(19, parseInt(addMin || 0, 10) || 0));
            const end = getPriceLineTargetMinute(p, true, add);

            for (let m = baseStart; m <= end; m++) {
                const delta = end - m;
                const ticks = delta * baseOdd;
                const odd = addTicksFrom(1.00, ticks);
                out.push({
                    minute: m,
                    odd,
                    tickPerMin: null
                });
            }
            return hydrateTickPerMin(out, end);
        }

        function kindLabel(kind) {
            if (kind === 'vvunder') return 'Jogo Super Under';
            if (kind === 'vunder') return 'Jogo Muito Under';
            if (kind === 'under') return 'Jogo Under';
            if (kind === 'munder') return 'Jogo Meio Under';
            if (kind === 'total') return 'Jogo Normal';
            if (kind === 'mover') return 'Jogo Meio Over';
            if (kind === 'over') return 'Jogo Over';
            if (kind === 'vover') return 'Jogo Muito Over';
            if (kind === 'vvover') return 'Jogo Super Over';
            return '';
        }

        function fmt2(v) {
            return (v == null || !Number.isFinite(v)) ? '--' : Number(v).toFixed(2);
        }

        function formatTickPerMinDisplay(v) {
            return (v == null || !Number.isFinite(v)) ? '—' : Number(v).toFixed(3).replace('.', ',');
        }

        function fmtTickPerMin(v) {
            return (v == null || !Number.isFinite(v)) ? '—' : String(Math.max(1, Math.round(Number(v))));
        }

        function avg(arr) {
            if (!arr || !arr.length) return null;
            return arr.reduce((a, b) => a + b, 0) / arr.length;
        }

        function fmt(v) {
            return (v == null || !Number.isFinite(v)) ? '--' : v.toFixed(2);
        }

        function getPaymentWindow() {
            return Math.max(1, Number(SELECTED_PAYMENT_WINDOW || 5));
        }

        function fmtPayment(v) {
            return (v == null || !Number.isFinite(v)) ? '--' : Number(v).toFixed(2);
        }

        function calcPayment(v) {
            return Number.isFinite(v) ? v / getPaymentWindow() : null;
        }

        function formatKickoffLabel(isoString) {
            if (!isoString) return '--';
            try {
                const date = new Date(isoString);
                return new Intl.DateTimeFormat('pt-BR', {
                    timeZone: APP_CONFIG.timezone || 'America/Sao_Paulo',
                    day: '2-digit',
                    month: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit'
                }).format(date);
            } catch (err) {
                return '--';
            }
        }

        function formatCountdown(totalMinutes, elapsedMin) {
            if (!Number.isFinite(elapsedMin)) return '--';
            const remain = Math.max(0, (totalMinutes - elapsedMin) * 60);
            const mm = Math.floor(remain / 60);
            const ss = remain % 60;
            return `${String(mm).padStart(2, '0')}:${String(ss).padStart(2, '0')}`;
        }

        function getLiveElapsed(row) {
            const baseElapsed = Number(row?.elapsed_min);
            const status = String(row?.match_status || '').toUpperCase();
            if (!Number.isFinite(baseElapsed)) return NaN;
            if (!['1H', '2H'].includes(status)) return baseElapsed;
            const fetchedAt = Date.parse(String(LAST_API_META?.fetched_at || ''));
            if (!Number.isFinite(fetchedAt)) return baseElapsed;
            const deltaSeconds = Math.max(0, (Date.now() - fetchedAt) / 1000);
            return baseElapsed + (deltaSeconds / 60);
        }

        function getTimeLabel(row) {
            const status = String(row?.match_status || '').toUpperCase();
            const elapsed = getLiveElapsed(row);
            if (status === 'LIVE') return 'Ao vivo';
            if (status === 'HT') return 'Intervalo';
            if (status === 'FT') return 'Encerrado';
            if (status === 'AET' || status === 'ET') return 'Prorrogacao';
            if (status === 'PEN') return 'Penaltis';
            if (status === 'PST') return 'Adiado';
            if (status === 'NS' || status === 'TBD' || status === 'PST') return formatKickoffLabel(row?.kickoff_at);
            if (status === '1H') {
                if (elapsed >= 45) return '45+';
                return formatCountdown(45, elapsed);
            }
            if (status === '2H') {
                if (elapsed >= 90) return '90+';
                return formatCountdown(90, elapsed);
            }
            return row?.source === 'manual' ? 'Manual' : '--';
        }

        function updateRenderedTimers() {
            const rows = document.querySelectorAll('#dashboard tr[data-row-id]');
            rows.forEach((rowEl) => {
                const row = findRowById(rowEl.dataset.rowId);
                if (!row) return;
                const pill = rowEl.querySelector('[data-role="time-pill"]');
                if (!pill) return;
                pill.textContent = getTimeLabel(row);
            });
        }

        function renderApiWarning() {
            const banner = document.getElementById('apiWarning');
            if (!banner) return;
            const warning = String(LAST_API_META?.warning || '').trim();
            const configured = LAST_API_META?.configured !== false;
            if (!warning) {
                banner.classList.remove('stale');
                banner.style.display = 'none';
                banner.textContent = '';
                return;
            }
            const prefix = !configured
                ? 'Painel em modo manual.'
                : (LAST_API_META?.from_cache
                    ? 'Usando cache local.'
                    : (LAST_API_META?.stale ? 'Falha ao consultar a API.' : 'Aviso da API.'));
            banner.textContent = `${prefix} ${warning}`;
            banner.style.display = 'block';
            banner.classList.toggle('stale', configured && !!LAST_API_META?.stale);
        }

        function getEmptyStateHtml() {
            const totalRows = Array.isArray(TODOS_DADOS) ? TODOS_DADOS.length : 0;
            const warning = String(LAST_API_META?.warning || '').trim();
            const configured = LAST_API_META?.configured !== false;

            let title = 'Nenhum dado encontrado.';
            let description = 'Ajuste os filtros para exibir outros registros.';

            if (totalRows === 0) {
                if (warning) {
                    title = 'Falha ao carregar dados da API.';
                    description = 'Veja o aviso acima. O painel volta a exibir jogos quando a API responder novamente ou o cache local for atualizado.';
                } else if (!configured) {
                    title = 'Modo manual ativo.';
                    description = 'Cadastre registros manualmente ou configure api.key em config.local.php para carregar odds da API.';
                } else {
                    title = 'Nenhum jogo disponivel agora.';
                    description = 'A API nao retornou odds ao vivo nem partidas para hoje neste momento.';
                }
            }

            return `
                <div class="empty-state">
                    <strong>${escapeHtml(title)}</strong>
                    <p>${escapeHtml(description)}</p>
                </div>
            `;
        }

        function avgUnderOverByMean(values, mean) {
            if (!Number.isFinite(mean)) return {
                vvunder: null,
                vunder: null,
                under: null,
                over: null,
                vover: null,
                vvover: null
            };
            const underArr = values.filter(v => Number.isFinite(v) && v < mean);
            const overArr = values.filter(v => Number.isFinite(v) && v > mean);
            const under = avg(underArr);
            const over = avg(overArr);
            const vunderArr = Number.isFinite(under) ? underArr.filter(v => v < under) : [];
            const voverArr = Number.isFinite(over) ? overArr.filter(v => v > over) : [];
            const vunder = avg(vunderArr);
            const vover = avg(voverArr);
            const vvunderArr = Number.isFinite(vunder) ? vunderArr.filter(v => v < vunder) : [];
            const vvoverArr = Number.isFinite(vover) ? voverArr.filter(v => v > vover) : [];
            return {
                vvunder: avg(vvunderArr),
                vunder,
                under,
                over,
                vover,
                vvover: avg(vvoverArr)
            };
        }

        function customKindFactor(kind) {
            if (kind === 'vvunder') return 0.70;
            if (kind === 'vunder') return 0.80;
            if (kind === 'under') return 0.90;
            if (kind === 'total') return 1.00;
            if (kind === 'over') return 1.10;
            if (kind === 'vover') return 1.20;
            if (kind === 'vvover') return 1.30;
            return 1.00;
        }

        function setSummaryCard(id, phase, kind, val) {
            const el = document.getElementById(id);
            if (!el) return;
            const card = el.closest('.summary-card');
            el.textContent = fmt(val);
            if (card) {
                card.dataset.phase = phase;
                card.dataset.kind = kind;
                card.dataset.origin = 'card';
            }
            if (Number.isFinite(val)) {
                const oddStr = Number(val).toFixed(2);
                if (card) {
                    card.dataset.odd = oddStr;
                    card.classList.add('price-click');
                    card.style.opacity = '1';
                }
                el.dataset.odd = oddStr;
                el.dataset.phase = phase;
                el.dataset.kind = kind;
                el.dataset.origin = 'card';
                el.classList.add('price-click');
                el.style.opacity = '1';
            } else {
                if (card) {
                    card.dataset.odd = '';
                    card.classList.remove('price-click');
                    card.style.opacity = '0.45';
                }
                el.dataset.odd = '';
                el.classList.remove('price-click');
                el.style.opacity = '0.45';
            }
        }

        function atualizarResumoGlobal(dados) {
            const htVals = [];
            const ftVals = [];
            (dados || []).forEach(j => {
                const vHT = parseFloat(j.odd_ht);
                const vFT = parseFloat(j.odd_ft);
                if (Number.isFinite(vHT)) htVals.push(vHT);
                if (Number.isFinite(vFT)) ftVals.push(vFT);
            });
            const avgHT = avg(htVals);
            const avgFT = avg(ftVals);
            const ht = avgUnderOverByMean(htVals, avgHT);
            const ft = avgUnderOverByMean(ftVals, avgFT);

            const munderHT = (Number.isFinite(ht.under) && Number.isFinite(avgHT)) ? (ht.under + avgHT) / 2 : null;
            const moverHT = (Number.isFinite(ht.over) && Number.isFinite(avgHT)) ? (avgHT + ht.over) / 2 : null;
            const munderFT = (Number.isFinite(ft.under) && Number.isFinite(avgFT)) ? (ft.under + avgFT) / 2 : null;
            const moverFT = (Number.isFinite(ft.over) && Number.isFinite(avgFT)) ? (avgFT + ft.over) / 2 : null;

            LAST_SUMMARY = {
                HT: {
                    vvunder: ht.vvunder,
                    vunder: ht.vunder,
                    under: ht.under,
                    munder: munderHT,
                    total: avgHT,
                    mover: moverHT,
                    over: ht.over,
                    vover: ht.vover,
                    vvover: ht.vvover
                },
                FT: {
                    vvunder: ft.vvunder,
                    vunder: ft.vunder,
                    under: ft.under,
                    munder: munderFT,
                    total: avgFT,
                    mover: moverFT,
                    over: ft.over,
                    vover: ft.vover,
                    vvover: ft.vvover
                }
            };

            setSummaryCard('sumHTUnder', 'HT', 'under', ht.under);
            setSummaryCard('sumHTTotal', 'HT', 'total', avgHT);
            setSummaryCard('sumHTOver', 'HT', 'over', ht.over);

            setSummaryCard('sumFTUnder', 'FT', 'under', ft.under);
            setSummaryCard('sumFTTotal', 'FT', 'total', avgFT);
            setSummaryCard('sumFTOver', 'FT', 'over', ft.over);
        }

        function renderSwitchBar(phase, activeKind, showMids) {
            const wrap = document.getElementById('plSwitch');
            if (!wrap) return;

            const ov = getOverlay();
            const p = (phase === 'HT') ? 'HT' : 'FT';
            const colorClass = (p === 'HT') ? 'ht' : 'ft';
            const isCustom = !!ov && ov.dataset.customMode === '1';

            let items = [];
            if (isCustom) {
                const baseOdd = parseFloat(ov.dataset.baseOdd || ov.dataset.customBaseOdd || '');
                const base = Number.isFinite(baseOdd) ? baseOdd : parseFloat(ov.dataset.odd || '');
                const mk = (k) => {
                    const f = customKindFactor(k);
                    const v = Number.isFinite(base) ? getLadderTick(base * f) : null;
                    return {
                        kind: k,
                        val: v
                    };
                };
                items = [{
                        kind: 'vvunder',
                        val: mk('vvunder').val
                    },
                    {
                        kind: 'vunder',
                        val: mk('vunder').val
                    },
                    {
                        kind: 'under',
                        val: mk('under').val
                    },
                    {
                        kind: 'total',
                        val: mk('total').val
                    },
                    {
                        kind: 'over',
                        val: mk('over').val
                    },
                    {
                        kind: 'vover',
                        val: mk('vover').val
                    },
                    {
                        kind: 'vvover',
                        val: mk('vvover').val
                    }
                ];
            } else {
                items = [{
                        kind: 'vvunder',
                        val: LAST_SUMMARY[p].vvunder
                    },
                    {
                        kind: 'vunder',
                        val: LAST_SUMMARY[p].vunder
                    },
                    {
                        kind: 'under',
                        val: LAST_SUMMARY[p].under
                    },
                    {
                        kind: 'munder',
                        val: showMids ? LAST_SUMMARY[p].munder : null
                    },
                    {
                        kind: 'total',
                        val: LAST_SUMMARY[p].total
                    },
                    {
                        kind: 'mover',
                        val: showMids ? LAST_SUMMARY[p].mover : null
                    },
                    {
                        kind: 'over',
                        val: LAST_SUMMARY[p].over
                    },
                    {
                        kind: 'vover',
                        val: LAST_SUMMARY[p].vover
                    },
                    {
                        kind: 'vvover',
                        val: LAST_SUMMARY[p].vvover
                    }
                ].filter(it => it.kind === 'total' || Number.isFinite(it.val) || it.kind === 'munder' || it.kind === 'mover' || it.kind === 'vvunder' || it.kind === 'vunder' || it.kind === 'under' || it.kind === 'over' || it.kind === 'vover' || it.kind === 'vvover');
            }

            const underKinds = showMids && !isCustom ? ['vvunder', 'vunder', 'under', 'munder'] : ['vvunder', 'vunder', 'under', 'total'];
            const overKinds = showMids && !isCustom ? ['mover', 'over', 'vover', 'vvover'] : ['over', 'vover', 'vvover', 'total'];

            const getVal = (k) => {
                const it = items.find(x => x.kind === k);
                return it ? it.val : null;
            };

            const normalVal = isCustom ? getVal('total') : LAST_SUMMARY[p].total;
            const normalOk = Number.isFinite(normalVal);
            const normalActive = (activeKind ? activeKind === 'total' : true);

            wrap.classList.add('grid');

            const pillHtml = (k, posCls, lblTxt, v, rowCls, colCls) => {
                const ok = Number.isFinite(v);
                const isActive = (activeKind && k === activeKind) || (!activeKind && k === 'total');
                const disabled = ok ? '' : 'disabled';
                return `
      <div class="pl-pill ${colorClass} ${posCls} ${rowCls} ${colCls} ${isActive ? 'active' : ''} ${disabled}"
           data-phase="${p}" data-kind="${k}" data-odd="${ok ? Number(v).toFixed(2) : ''}">
        <div class="lbl">${lblTxt}</div>
        <div class="val ${p === 'HT' ? 'pl-odd-ht' : 'pl-odd-ft'}">${fmt2(v)}</div>
      </div>
    `;
            };

            let html = '';
            html += `
    <div class="pl-pill ${colorClass} span-normal ${normalActive ? 'active' : ''} ${normalOk ? '' : 'disabled'}"
         data-phase="${p}" data-kind="total" data-odd="${normalOk ? Number(normalVal).toFixed(2) : ''}">
      <div class="lbl">JOGO NORMAL</div>
      <div class="val ${p === 'HT' ? 'pl-odd-ht' : 'pl-odd-ft'}">${fmt2(normalVal)}</div>
    </div>
  `;

            const underSlots = showMids && !isCustom ?
                [{
                        k: 'vvunder',
                        lbl: 'SUPER UNDER'
                    },
                    {
                        k: 'vunder',
                        lbl: 'MUITO UNDER'
                    },
                    {
                        k: 'under',
                        lbl: 'UNDER'
                    },
                    {
                        k: 'munder',
                        lbl: 'MEIO UNDER'
                    }
                ] :
                [{
                        k: 'total',
                        lbl: 'NORMAL'
                    },
                    {
                        k: 'vvunder',
                        lbl: 'SUPER UNDER'
                    },
                    {
                        k: 'vunder',
                        lbl: 'MUITO UNDER'
                    },
                    {
                        k: 'under',
                        lbl: 'UNDER'
                    }
                ];

            const overSlots = showMids && !isCustom ?
                [{
                        k: 'mover',
                        lbl: 'MEIO OVER'
                    },
                    {
                        k: 'over',
                        lbl: 'OVER'
                    },
                    {
                        k: 'vover',
                        lbl: 'MUITO OVER'
                    },
                    {
                        k: 'vvover',
                        lbl: 'SUPER OVER'
                    }
                ] :
                [{
                        k: 'total',
                        lbl: 'NORMAL'
                    },
                    {
                        k: 'over',
                        lbl: 'OVER'
                    },
                    {
                        k: 'vover',
                        lbl: 'MUITO OVER'
                    },
                    {
                        k: 'vvover',
                        lbl: 'SUPER OVER'
                    }
                ];

            const colMap = ['c2', 'c3', 'c4', 'c5'];

            for (let i = 0; i < 4; i++) {
                const s = underSlots[i];
                const v = getVal(s.k);
                const vFixed = (isCustom && s.k === 'total') ? normalVal : v;
                html += pillHtml(s.k, '', s.lbl, vFixed, 'row1', colMap[i]);
            }
            for (let i = 0; i < 4; i++) {
                const s = overSlots[i];
                const v = getVal(s.k);
                const vFixed = (isCustom && s.k === 'total') ? normalVal : v;
                html += pillHtml(s.k, '', s.lbl, vFixed, 'row2', colMap[i]);
            }

            wrap.innerHTML = html;
        }

        function parseMmSs(str) {
            const s = String(str || '').trim();
            const m = s.match(/^(\d{1,3})(?::([0-5]\d))$/);
            if (!m) return null;
            const mm = parseInt(m[1], 10);
            const ss = parseInt(m[2], 10);
            if (!Number.isFinite(mm) || !Number.isFinite(ss)) return null;
            return mm * 60 + ss;
        }

        function formatMmSs(sec) {
            if (!Number.isFinite(sec) || sec < 0) return '—';
            const mm = Math.floor(sec / 60);
            const ss = Math.floor(sec % 60);
            return `${mm}:${String(ss).padStart(2,'0')}`;
        }

        function getOverlay() {
            return document.getElementById('priceLineOverlay');
        }

        function getPlusAdd() {
            const inp = document.getElementById('plPlusAdd');
            const v = parseInt(String(inp ?.value ?? '').replace(/[^\d]/g, ''), 10);
            return Number.isFinite(v) ? Math.max(0, Math.min(19, v)) : 0;
        }

        function isPlusMode() {
            const ov = getOverlay();
            return !!ov && ov.dataset.plusMode === '1';
        }

        function plusWindowForPhase(phase, add) {
            const p = (phase === 'HT') ? 'HT' : 'FT';
            const start = (p === 'HT') ? 30 : 75;
            const baseEnd = (p === 'HT') ? 45 : 90;
            const end = baseEnd + Math.max(0, Math.min(19, parseInt(add || 0, 10) || 0));
            return {
                start,
                baseEnd,
                end
            };
        }

        function liveMinuteForPhase(phase, sec) {
            if (!Number.isFinite(sec) || sec < 0) return null;
            const mm = Math.floor(sec / 60);
            const ov = getOverlay();
            const p = (phase === 'HT') ? 'HT' : 'FT';
            const plus = (!!ov && ov.dataset.plusMode === '1');
            if (!plus) {
                if (p === 'HT') {
                    if (mm < 0 || mm > 35) return null;
                    return mm;
                }
                if (p === 'FT') {
                    if (mm < 45 || mm > 80) return null;
                    return mm;
                }
                return null;
            }
            const add = getPlusAdd();
            const w = plusWindowForPhase(p, add);
            if (mm < w.start || mm > w.end) return null;
            return mm;
        }

        function applyLiveMarkerToGrid(minute) {
            const grid = document.getElementById('plGrid');
            if (!grid) return;
            const cells = Array.from(grid.querySelectorAll('.pl-cell'));
            for (const c of cells) {
                c.classList.remove('pl-live-now');
                const mEl = c.querySelector('.m');
                if (!mEl) continue;
                const rangeLabel = String(c.dataset.rangeLabel || '').trim();
                mEl.textContent = rangeLabel || mEl.textContent.replace(/^🟢\s*/, '');
            }
            if (minute == null) return;
            for (const c of cells) {
                const minuteStart = Number(c.dataset.minuteStart);
                const minuteEnd = Number(c.dataset.minuteEnd);
                if (!Number.isFinite(minuteStart) || !Number.isFinite(minuteEnd)) continue;
                const mEl = c.querySelector('.m');
                if (!mEl) continue;
                if (minute >= minuteStart && minute <= minuteEnd) {
                    const rangeLabel = String(c.dataset.rangeLabel || formatPriceLineRange(minuteStart, minuteEnd));
                    mEl.textContent = `🟢 ${rangeLabel}`;
                    c.classList.add('pl-live-now');
                    break;
                }
            }
        }

        function updatePlusUi() {
            const ov = getOverlay();
            if (!ov) return;
            const p = (ov.dataset.phase || '').toUpperCase() === 'HT' ? 'HT' : 'FT';
            const btn = document.getElementById('plPlusBtn');
            const txt = document.getElementById('plPlusTxt');
            const inline = document.getElementById('plPlusInline');
            if (btn && txt) txt.textContent = (p === 'HT') ? '35MIN+' : '80MIN+';
            if (btn) btn.style.display = 'inline-flex';
            const plus = (ov.dataset.plusMode === '1');
            if (btn) btn.classList.toggle('active', plus);
            if (inline) inline.style.display = plus ? 'flex' : 'none';
        }

        function rerenderCurrentPriceLine() {
            const ov = getOverlay();
            if (!ov) return;
            const phase = (ov.dataset.phase || 'FT').toUpperCase();
            const odd = parseFloat(ov.dataset.odd || '');
            const kind = (ov.dataset.kind || '').toLowerCase();
            const showSwitch = ov.dataset.showSwitch === '1';
            const custom = ov.dataset.customMode === '1';
            if ((phase !== 'HT' && phase !== 'FT') || !Number.isFinite(odd)) return;
            abrirPriceLine(phase, odd, kind, showSwitch, custom);
        }

        function maybeAutoSwitchPlus() {
            if (!PL_LIVE_ENABLED) return;
            if (!Number.isFinite(PL_LIVE_TIME_SEC)) return;
            const ov = getOverlay();
            if (!ov) return;
            if (ov.dataset.plusAuto === '0') return;
            const phase = (ov.dataset.phase || '').toUpperCase();
            if (phase !== 'HT' && phase !== 'FT') return;
            if (PL_CUSTOM_MODE) return;
            if (ov.dataset.plusMode === '1') return;
            const sec = PL_LIVE_TIME_SEC;
            if (phase === 'FT') {
                if (sec >= 81 * 60) {
                    ov.dataset.plusMode = '1';
                    updatePlusUi();
                    rerenderCurrentPriceLine();
                }
            } else {
                if (sec >= 36 * 60) {
                    ov.dataset.plusMode = '1';
                    updatePlusUi();
                    rerenderCurrentPriceLine();
                }
            }
        }

        function updateLiveMarker() {
            if (!PL_LIVE_ENABLED) return;
            const hint = document.getElementById('plLiveHint');
            const phase = (getOverlay() ?.dataset ?.phase || '').toUpperCase();
            if (!phase || !Number.isFinite(PL_LIVE_TIME_SEC)) {
                if (hint) hint.textContent = 'Tempo Atual: —';
                applyLiveMarkerToGrid(null);
                return;
            }
            maybeAutoSwitchPlus();
            const liveMin = liveMinuteForPhase(phase, PL_LIVE_TIME_SEC);
            if (liveMin == null) {
                if (hint) hint.textContent = 'Tempo Atual: —';
                applyLiveMarkerToGrid(null);
                return;
            }
            if (hint) hint.textContent = `Tempo Atual: ${formatMmSs(PL_LIVE_TIME_SEC)}`;
            applyLiveMarkerToGrid(liveMin);
        }

        function startLiveTicker() {
            if (PL_LIVE_TIMER) clearInterval(PL_LIVE_TIMER);
            PL_LIVE_LAST_TS = Date.now();
            PL_LIVE_TIMER = setInterval(() => {
                if (!PL_LIVE_ENABLED) return;
                if (!Number.isFinite(PL_LIVE_TIME_SEC)) return;
                const now = Date.now();
                const dt = Math.max(0, now - (PL_LIVE_LAST_TS || now));
                PL_LIVE_LAST_TS = now;
                PL_LIVE_TIME_SEC += dt / 1000;
                updateLiveMarker();
            }, 250);
        }

        function updateLiveFromInput() {
            if (!PL_LIVE_ENABLED) return;
            const inp = document.getElementById('plLiveTime');
            const raw = inp ? String(inp.value || '').trim() : '';
            PL_LIVE_TIME_STR = raw;
            const sec = parseMmSs(raw);
            PL_LIVE_TIME_SEC = sec;
            if (!Number.isFinite(PL_LIVE_TIME_SEC)) {
                updateLiveMarker();
                return;
            }
            PL_LIVE_LAST_TS = Date.now();
            updateLiveMarker();
            startLiveTicker();
        }

        function resetLiveUi() {
            const btn = document.getElementById('plLiveBtn');
            const inline = document.getElementById('plLiveInline');
            const inp = document.getElementById('plLiveTime');
            const hint = document.getElementById('plLiveHint');
            PL_LIVE_ENABLED = false;
            PL_LIVE_TIME_SEC = null;
            if (PL_LIVE_TIMER) {
                clearInterval(PL_LIVE_TIMER);
                PL_LIVE_TIMER = null;
            }
            PL_LIVE_LAST_TS = null;
            if (btn) btn.classList.remove('active');
            if (inline) inline.style.display = 'none';
            if (inp) inp.value = '';
            if (hint) hint.textContent = 'Tempo Atual: —';
        }

        function bindLiveUiOnce() {
            const btn = document.getElementById('plLiveBtn');
            const inline = document.getElementById('plLiveInline');
            const inp = document.getElementById('plLiveTime');
            if (!btn || btn.dataset.bound === '1') return;
            btn.dataset.bound = '1';
            btn.addEventListener('click', () => {
                PL_LIVE_ENABLED = !PL_LIVE_ENABLED;
                btn.classList.toggle('active', PL_LIVE_ENABLED);
                if (inline) inline.style.display = PL_LIVE_ENABLED ? 'inline-flex' : 'none';
                if (!PL_LIVE_ENABLED) {
                    const ov = getOverlay();
                    if (ov) ov.dataset.plusAuto = '1';
                    if (PL_LIVE_TIMER) {
                        clearInterval(PL_LIVE_TIMER);
                        PL_LIVE_TIMER = null;
                    }
                    applyLiveMarkerToGrid(null);
                    return;
                }
                if (inp) {
                    inp.focus();
                    inp.select();
                }
                updateLiveFromInput();
            });
            if (inp && inp.dataset.bound !== '1') {
                inp.dataset.bound = '1';
                inp.addEventListener('input', () => {
                    let v = inp.value.replace(/[^\d:]/g, '');
                    if (v.length === 2 && !v.includes(':')) v = v + ':';
                    if (v.length > 5) v = v.slice(0, 5);
                    inp.value = v;
                    updateLiveFromInput();
                });
                inp.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        updateLiveFromInput();
                    }
                });
            }
        }

        function syncLiveUiToState() {
            const btn = document.getElementById('plLiveBtn');
            const inline = document.getElementById('plLiveInline');
            const inp = document.getElementById('plLiveTime');
            if (!btn || !inline || !inp) return;
            btn.classList.toggle('active', !!PL_LIVE_ENABLED);
            inline.style.display = PL_LIVE_ENABLED ? 'inline-flex' : 'none';
            if (PL_LIVE_ENABLED) {
                if (PL_LIVE_TIME_STR && !inp.value) inp.value = PL_LIVE_TIME_STR;
                if (Number.isFinite(PL_LIVE_TIME_SEC)) {
                    if (!PL_LIVE_TIMER) startLiveTicker();
                    updateLiveMarker();
                } else {
                    updateLiveMarker();
                }
            } else {
                applyLiveMarkerToGrid(null);
            }
        }

        function bindPlusUiOnce() {
            const btn = document.getElementById('plPlusBtn');
            const add = document.getElementById('plPlusAdd');
            const minus = document.getElementById('plPlusMinus');
            const plus = document.getElementById('plPlusPlus');
            if (!btn || btn.dataset.bound === '1') return;
            btn.dataset.bound = '1';
            btn.addEventListener('click', () => {
                const ov = getOverlay();
                if (!ov) return;
                ov.dataset.plusAuto = '0';
                ov.dataset.plusMode = (ov.dataset.plusMode === '1') ? '0' : '1';
                updatePlusUi();
                rerenderCurrentPriceLine();
                if (PL_LIVE_ENABLED) updateLiveMarker();
            });

            function setAddValue(next) {
                if (!add) return;
                const v = Math.max(0, Math.min(19, parseInt(next, 10) || 0));
                add.value = String(v);
                if (!isPlusMode()) return;
                rerenderCurrentPriceLine();
                if (PL_LIVE_ENABLED) updateLiveMarker();
            }
            if (add && add.dataset.bound !== '1') {
                add.dataset.bound = '1';
                add.addEventListener('input', () => {
                    let v = String(add.value || '').replace(/[^\d]/g, '');
                    if (v.length > 2) v = v.slice(0, 2);
                    add.value = v;
                    if (!isPlusMode()) return;
                    rerenderCurrentPriceLine();
                    if (PL_LIVE_ENABLED) updateLiveMarker();
                });
                add.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        if (!isPlusMode()) return;
                        rerenderCurrentPriceLine();
                        if (PL_LIVE_ENABLED) updateLiveMarker();
                    }
                });
            }
            if (minus && minus.dataset.bound !== '1') {
                minus.dataset.bound = '1';
                minus.addEventListener('click', () => {
                    const cur = parseInt(String(add ?.value ?? '0').replace(/[^\d]/g, ''), 10) || 0;
                    setAddValue(cur - 1);
                });
            }
            if (plus && plus.dataset.bound !== '1') {
                plus.dataset.bound = '1';
                plus.addEventListener('click', () => {
                    const cur = parseInt(String(add ?.value ?? '0').replace(/[^\d]/g, ''), 10) || 0;
                    setAddValue(cur + 1);
                });
            }
        }

        function abrirPriceLine(phase, odd, activeKind, showSwitch, customMode) {
            const overlay = document.getElementById('priceLineOverlay');
            const titleEl = document.getElementById('plTitle');
            const subEl = document.getElementById('plSub');
            const grid = document.getElementById('plGrid');
            const customWrap = document.getElementById('plCustomWrap');
            const switchEl = document.getElementById('plSwitch');
            if (!overlay || !titleEl || !subEl || !grid) return;

            const p = (phase === 'HT') ? 'HT' : 'FT';
            const isHT = (p === 'HT');
            const start = isHT ? 0 : 45;
            const end = isHT ? 35 : 80;
            const clsOdd = isHT ? 'pl-odd-ht' : 'pl-odd-ft';

            PL_CUSTOM_MODE = !!customMode;

            if (overlay.dataset.plusMode == null) overlay.dataset.plusMode = '0';
            overlay.dataset.phase = p;
            overlay.dataset.odd = Number(odd).toFixed(2);
            overlay.dataset.kind = (activeKind || '');
            overlay.dataset.showSwitch = showSwitch ? '1' : '0';
            overlay.dataset.customMode = PL_CUSTOM_MODE ? '1' : '0';

            if (PL_CUSTOM_MODE) {
                const baseOdd = parseFloat(document.getElementById('plCustomOdd') ?.value);
                if (Number.isFinite(baseOdd)) overlay.dataset.baseOdd = Number(baseOdd).toFixed(2);
            }

            if (customWrap) customWrap.style.display = PL_CUSTOM_MODE ? 'flex' : 'none';
            syncPriceLineCategoryFilterUi();

            const showMids = (!PL_CUSTOM_MODE && showSwitch);
            if (switchEl) {
                switchEl.style.display = showSwitch ? 'grid' : 'none';
                if (showSwitch) {
                    renderSwitchBar(p, activeKind || 'total', showMids);
                } else {
                    switchEl.innerHTML = '';
                    switchEl.classList.remove('grid');
                }
            }

            updatePlusUi();
            syncPriceLineGroupUi();

            const isPlus = (overlay.dataset.plusMode === '1');
            const add = isPlus ? getPlusAdd() : 0;
            const groupStep = getPriceLineGroupStep();

            const plusStart = (p === 'HT') ? 30 : 75;
            const plusBaseEnd = (p === 'HT') ? 45 : 90;
            const plusEnd = plusBaseEnd + add;

            let baseStr = Number(odd).toFixed(2);
            if (PL_CUSTOM_MODE) {
                const bo = parseFloat(overlay.dataset.baseOdd || '');
                if (Number.isFinite(bo)) baseStr = Number(bo).toFixed(2);
            }

            const kLbl = kindLabel(activeKind);
            titleEl.textContent = `${PL_CUSTOM_MODE ? 'Custom ' : ''}Linha de preço ${p}`;
            subEl.innerHTML = `${kLbl ? `<b>${kLbl}</b> • ` : ''}Linha: <b class="${clsOdd}">${Number(odd).toFixed(2)}</b> • Minutos ${isPlus ? `${plusStart}→${plusEnd}` : `${start}→${end}`}`;

            const targetMinute = getPriceLineTargetMinute(p, isPlus, add);
            const line = isPlus ? buildPriceLinePlus(p, odd, add) : buildPriceLine(p, odd);

            const plusRow = document.getElementById('plPlusInline');
            if (plusRow && plusRow.parentElement !== grid) grid.prepend(plusRow);
            const keep = plusRow || null;
            Array.from(grid.children).forEach(ch => {
                if (ch !== keep) ch.remove();
            });
            if (plusRow) plusRow.style.display = isPlus ? 'flex' : 'none';

            const oddHtml = (v, tickPerMin) => {
                const main = Number(v).toFixed(2);
                const uf = interpolateCorrelation(v);
                const ufVal = Number.isFinite(uf) ? Number(uf).toFixed(2) : '—';
                const tickVal = formatTickPerMinDisplay(tickPerMin);
                return `
      <span class="uf" title="Under A Frente">
        <span class="uf-tag">UF</span>
        <span class="uf-val">${ufVal}</span>
      </span>
      <span class="main ${clsOdd}">${main}</span>
      <span class="tkm" title="Odd por minuto restante = (odd atual - 1) / minutos restantes">
        <span class="tkm-tag">TK/M</span>
        <span class="tkm-val">${tickVal}</span>
      </span>
    `;
            };

            let idx2 = -1,
                idx3 = -1;
            if (!isPlus) {
                let best2 = Infinity,
                    best3 = Infinity;
                for (let i = 0; i < line.length; i++) {
                    if (!isHT && line[i].minute === 80) continue;
                    const d2 = Math.abs(line[i].odd - 2.00);
                    if (d2 < best2) {
                        best2 = d2;
                        idx2 = i;
                    }
                }
                if (idx2 < 0) {
                    for (let i = 0; i < line.length; i++) {
                        const d2 = Math.abs(line[i].odd - 2.00);
                        if (d2 < best2) {
                            best2 = d2;
                            idx2 = i;
                        }
                    }
                }
                for (let i = 0; i < line.length; i++) {
                    if (i === idx2) continue;
                    const d3 = Math.abs(line[i].odd - 3.00);
                    if (d3 < best3) {
                        best3 = d3;
                        idx3 = i;
                    }
                }
                if (idx2 >= 0) line[idx2].odd = 2.00;
                if (idx3 >= 0) line[idx3].odd = 3.00;
            }
            hydrateTickPerMin(line, targetMinute);
            const hit2Minute = idx2 >= 0 ? Number(line[idx2]?.minute) : null;
            const hit3Minute = idx3 >= 0 ? Number(line[idx3]?.minute) : null;
            const displayLine = buildGroupedPriceLine(line, groupStep);
            const columnCount = Math.max(1, Math.min(3, Math.ceil(displayLine.length / 12) || 1));
            grid.style.setProperty('--pl-column-count', String(columnCount));

            let html = '';
            for (let i = 0; i < displayLine.length; i++) {
                const row = displayLine[i];
                const containsHit3 = !isPlus && rangeContainsMinute(row, hit3Minute);
                const containsHit2 = !isPlus && rangeContainsMinute(row, hit2Minute);
                const hitCls = containsHit3 ? 'pl-hit-3' : (containsHit2 ? 'pl-hit-2' : '');
                const hit80 = (!isHT && (rangeContainsMinute(row, 80) || rangeContainsMinute(row, 90))) ? 'pl-hit-80' : '';
                const minuteStart = Number(row.minuteStart ?? row.minute);
                const minuteEnd = Number(row.minuteEnd ?? row.minute);
                const rangeLabel = escapeHtml(String(row.rangeLabel || formatPriceLineRange(minuteStart, minuteEnd)));
                html += `
      <div class="pl-cell ${hitCls} ${hit80}" data-minute-start="${minuteStart}" data-minute-end="${minuteEnd}" data-range-label="${rangeLabel}">
        <div class="m">${rangeLabel}</div>
        <div class="o">${oddHtml(row.odd, row.tickPerMin)}</div>
      </div>
    `;
            }
            if (!html) {
                html = `<div class="pl-cell"><div class="m">—</div><div class="o"><span class="uf"><span class="uf-tag">UF</span><span class="uf-val">—</span></span><span class="main">—</span><span class="tkm"><span class="tkm-tag">TK/M</span><span class="tkm-val">—</span></span></div></div>`;
            }
            grid.insertAdjacentHTML('beforeend', html);

            overlay.style.display = 'flex';
            bindLiveUiOnce();
            bindPlusUiOnce();
            syncLiveUiToState();

            if (!PL_LIVE_ENABLED) {
                applyLiveMarkerToGrid(null);
            } else {
                updateLiveMarker();
                if (!PL_LIVE_TIMER && Number.isFinite(PL_LIVE_TIME_SEC)) startLiveTicker();
            }
        }

        function fecharPriceLine() {
            const overlay = document.getElementById('priceLineOverlay');
            if (overlay) overlay.style.display = 'none';
            PL_CUSTOM_MODE = false;
            if (overlay) {
                overlay.dataset.plusAuto = '1';
                PL_LIVE_LAST_PLUSMODE = overlay.dataset.plusMode ?? PL_LIVE_LAST_PLUSMODE;
                overlay.dataset.phase = '';
                overlay.dataset.odd = '';
                overlay.dataset.kind = '';
                overlay.dataset.showSwitch = '0';
                overlay.dataset.customMode = '0';
                overlay.dataset.baseOdd = '';
            }
            applyLiveMarkerToGrid(null);
        }

        function debounce(fn, wait) {
            let t = null;
            return (...args) => {
                clearTimeout(t);
                t = setTimeout(() => fn(...args), wait);
            };
        }

        function clampOdd(v) {
            if (!Number.isFinite(v)) return NaN;
            if (v < 1.01) return NaN;
            return v;
        }

        function renderCustomLPFromInputs() {
            if (!PL_CUSTOM_MODE) return;
            const phase = (document.getElementById('plCustomPhase') ?.value || 'FT').toUpperCase();
            const oddRaw = parseFloat(document.getElementById('plCustomOdd') ?.value);
            const baseOdd = clampOdd(oddRaw);

            const ov = getOverlay();
            const kind = String(ov ?.dataset ?.kind || 'total').toLowerCase() || 'total';

            if (phase !== 'HT' && phase !== 'FT') return;

            if (!Number.isFinite(baseOdd)) {
                const titleEl = document.getElementById('plTitle');
                const subEl = document.getElementById('plSub');
                const grid = document.getElementById('plGrid');
                if (titleEl) titleEl.textContent = `Custom Linha de preço ${phase}`;
                if (subEl) subEl.innerHTML = `Digite uma odd base válida (>= 1.01)`;
                if (grid) {
                    const plusRow = document.getElementById('plPlusInline');
                    Array.from(grid.children).forEach(ch => {
                        if (ch !== plusRow) ch.remove();
                    });
                    if (plusRow) plusRow.style.display = 'none';
                    grid.insertAdjacentHTML('beforeend', `<div class="pl-cell"><div class="m">—</div><div class="o"><span class="uf"><span class="uf-tag">UF</span><span class="uf-val">—</span></span><span class="main">—</span><span class="tkm"><span class="tkm-tag">TK/M</span><span class="tkm-val">—</span></span></div></div>`);
                }
                    return;
            }

            const factor = customKindFactor(kind);
            const lineOdd = getLadderTick(baseOdd * factor);

            if (ov) ov.dataset.baseOdd = Number(baseOdd).toFixed(2);
            abrirPriceLine(phase, lineOdd, kind, true, true);
        }

        const renderCustomLPDebounced = debounce(renderCustomLPFromInputs, 60);

        function bindCustomLPLive() {
            const oddEl = document.getElementById('plCustomOdd');
            const phaseEl = document.getElementById('plCustomPhase');
            if (!oddEl || !phaseEl) return;
            if (oddEl.dataset.liveBound === "1") return;
            oddEl.dataset.liveBound = "1";
            oddEl.addEventListener('input', () => renderCustomLPDebounced());
            oddEl.addEventListener('change', () => renderCustomLPFromInputs());
            phaseEl.addEventListener('change', () => renderCustomLPFromInputs());
            renderCustomLPFromInputs();
        }

        function abrirCustomLP() {
            const phaseEl = document.getElementById('plCustomPhase');
            const oddEl = document.getElementById('plCustomOdd');
            const phase = (phaseEl && phaseEl.value) ? phaseEl.value : 'FT';
            let seed = (phase === 'HT') ? LAST_SUMMARY.HT.total : LAST_SUMMARY.FT.total;
            if (!Number.isFinite(seed)) seed = (phase === 'HT') ? 3.25 : 4.60;
            if (oddEl) {
                if (!oddEl.value) oddEl.value = Number(seed).toFixed(2);
            }
            const ov = getOverlay();
            if (ov) {
                if (ov.dataset.plusMode == null) ov.dataset.plusMode = '0';
                ov.dataset.kind = 'total';
                ov.dataset.baseOdd = Number(seed).toFixed(2);
            }
            abrirPriceLine(phase, seed, 'total', true, true);
            bindCustomLPLive();
            if (oddEl) {
                oddEl.focus();
                oddEl.select();
            }
        }

        function verificarNovaLiga(select) {
            const input = document.getElementById('newLeagueInput');
            if (select.value === 'NEW_LEAGUE_OPTION') {
                input.style.display = 'block';
                input.focus();
                input.required = true;
            } else {
                input.style.display = 'none';
                input.required = false;
            }
        }

        function calcularStatus(placar, mando) {
            if (!placar || !placar.toLowerCase().includes('x')) return null;
            const parts = placar.toLowerCase().split('x');
            if (parts.length !== 2) return null;
            const golsCasa = parseInt(parts[0]);
            const golsFora = parseInt(parts[1]);
            if (mando === 'Casa') {
                if (golsCasa > golsFora) return 'vencendo';
                if (golsCasa === golsFora) return 'empatando';
                return 'perdendo';
            } else {
                if (golsFora > golsCasa) return 'vencendo';
                if (golsCasa === golsFora) return 'empatando';
                return 'perdendo';
            }
        }

        function supportsStatusFilter(row) {
            const source = String(row?.source || 'manual').toLowerCase();
            if (source === 'manual') return true;
            const matchStatus = String(row?.match_status || '').toUpperCase();
            return ['LIVE', 'HT', 'FT', '1H', '2H', 'AET', 'ET', 'PEN'].includes(matchStatus);
        }

        function normalizeKey(s) {
            return normalizeSearch(s);
        }

        function leagueTopic(leagueName) {
            const n = normalizeKey(leagueName);
            const TOPICS = {
                'EUROPA': [
                    'alemanha', 'bélgica', 'bulgária', 'escócia', 'noruega', 'chéquia', 'suécia', 'letônia', 'finlândia', 'islândia',
                    'espanha', 'frança', 'grécia', 'holanda', 'inglaterra', 'sérvia', 'liga europa', 'champions league', 'liga conferência', 'supertaça',
                    'itália', 'italia', 'portugal', 'roménia', 'turquia', 'áustria', 'polónia', 'croácia', 'dinamarca', 'suíça', 'eslovénia', 'hungria', 'irlanda', 'países baixos'
                ],
                'AMÉRICA DO SUL': [
                    'libertadores', 'americana',
                    'brasil', 'argentina', 'chile', 'colômbia', 'peru', 'uruguai', 'paraguai', 'venezuela', 'conmebol', 'equador'
                ],
                'AMÉRICA DO NORTE': [
                    'méxico', 'eua', 'estados unidos', 'usa', 'concacaf'
                ],
                'ÁSIA': [
                    'arábia', 'china', 'coreia',
                    'japão', 'afc'
                ],
                'ÁFRICA': [
                    'áfrica do sul',
                    'egito', 'egipto'
                ],
                'OCEANIA': [
                    'nova zelândia', 'austrália'
                ],
                'MUNDO': [
                    'fifa'
                ],
                'SELEÇÕES': [
                    'nações', 'nação', 'copa do mundo', 'eurocopa', 'copa america', 'copa africana de nações', 'encontro internacional', 'europa', 'américa do norte', 'américa do sul', 'áfrica', 'ásia', 'oceania'
                ]
            };
            const ORDER = ['ÁSIA', 'EUROPA', 'AMÉRICA DO SUL', 'AMÉRICA DO NORTE', 'ÁFRICA', 'OCEANIA', 'MUNDO', 'SELEÇÕES'];
            for (const topic of ORDER) {
                const words = TOPICS[topic];
                for (let i = 0; i < words.length; i++) {
                    if (n.includes(normalizeSearch(words[i]))) return topic;
                }
            }
            return 'OUTROS';
        }

        function buildLeagueTopics(options, query) {
            const q = normalizeSearch(query || '');
            const groups = {};
            for (const l of options) {
                const name = String(l || '').trim();
                if (!name) continue;
                if (q) {
                    const hay = normalizeSearch(name);
                    if (!hay.includes(q)) continue;
                }
                const t = leagueTopic(name);
                if (!groups[t]) groups[t] = [];
                groups[t].push(name);
            }
            for (const k of Object.keys(groups)) groups[k].sort((a, b) => String(a).localeCompare(String(b)));
            const topicOrder = ['EUROPA', 'AMÉRICA DO SUL', 'AMÉRICA DO NORTE', 'ÁSIA', 'ÁFRICA', 'OCEANIA', 'MUNDO', 'SELEÇÕES', 'OUTROS'];
            const out = [];
            for (const t of topicOrder) {
                if (groups[t] && groups[t].length) {
                    out.push({
                        type: 'sep',
                        label: t
                    });
                    for (const l of groups[t]) out.push({
                        type: 'item',
                        value: l
                    });
                }
            }
            return out;
        }

        function getMsRoots(type) {
            return Array.from(document.querySelectorAll(`.ms[data-ms-root="${type}"]`));
        }

        function getMsSummaryText(type) {
            const set = ACTIVE_FILTERS[type];
            if (!set || !set.size) return 'Todas';
            if (set.size === 1) return [...set][0];
            return `${set.size} selecionadas`;
        }

        function toggleMsRoot(root, open) {
            if (!root) return;
            const btn = root.querySelector('.ms-btn');
            if (btn && btn.disabled) open = false;
            if (open) root.classList.add('open');
            else root.classList.remove('open');
            if (btn) btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        function updateMsSummary(type) {
            const summary = getMsSummaryText(type);
            const roots = getMsRoots(type);
            for (const root of roots) {
                const el = root.querySelector('.ms-summary');
                if (el) el.textContent = summary;
            }
        }

        function buildMsList(type, options) {
            const set = ACTIVE_FILTERS[type];
            const roots = getMsRoots(type);
            for (const root of roots) {
                const listEl = root.querySelector('.ms-list');
                if (!listEl) continue;
            if (!options || options.length === 0) {
                listEl.innerHTML = `<div style="color:#777; font-size:0.85rem; padding:8px;">Sem opções</div>`;
                return;
            }
            if (type === 'league') {
                const q = root.querySelector('.ms-search input') ?.value || '';
                const mixed = buildLeagueTopics(options, q);
                if (!mixed.length) {
                    listEl.innerHTML = `<div style="color:#777; font-size:0.85rem; padding:8px;">Nenhuma liga encontrada</div>`;
                    return;
                }
                listEl.innerHTML = mixed.map(row => {
                    if (row.type === 'sep') return `<div class="ms-sep">${escapeHtml(row.label)}</div>`;
                    const opt = row.value;
                    const checked = set.has(opt) ? 'checked' : '';
                    return `
        <label class="ms-item" data-ms-item="league" data-value="${escapeHtml(opt)}">
          <input type="checkbox" ${checked} data-ms-check="league" value="${escapeHtml(opt)}">
          <span title="${escapeHtml(opt)}">${escapeHtml(opt)}</span>
        </label>
      `;
                }).join("");
                return;
            }
            listEl.innerHTML = options.map(opt => {
                const checked = set.has(opt) ? 'checked' : '';
                return `
      <label class="ms-item" data-ms-item="${type}" data-value="${escapeHtml(opt)}">
        <input type="checkbox" ${checked} data-ms-check="${type}" value="${escapeHtml(opt)}">
        <span title="${escapeHtml(opt)}">${escapeHtml(opt)}</span>
      </label>
    `;
            }).join("");
        }

        }

        function setAllMs(type, options) {
            if (type === 'league') {
                const qRaw = document.getElementById('msLeagueSearch') ?.value || '';
                const q = normalizeSearch(qRaw);
                if (q) {
                    const listEl = document.getElementById('msLeagueList');
                    const visible = listEl ? Array.from(listEl.querySelectorAll('input[data-ms-check="league"]')).map(inp => String(inp.value || '').trim()).filter(Boolean) : [];
                    const set = ACTIVE_FILTERS.league;
                    for (const v of visible) set.add(v);
                    buildMsList('league', options);
                    updateMsSummary('league');
                    filtrarDashboard();
                    return;
                }
            }
            const set = ACTIVE_FILTERS[type];
            set.clear();
            for (const o of options) set.add(o);
            buildMsList(type, options);
            updateMsSummary(type);
            filtrarDashboard();
        }

        function clearMs(type, options) {
            ACTIVE_FILTERS[type].clear();
            buildMsList(type, options);
            updateMsSummary(type);
            filtrarDashboard();
        }

        function getLeagueOptionsFromData() {
            const ligas = [...new Set((TODOS_DADOS || []).map(d => String(d.league || '').trim()).filter(Boolean))];
            ligas.sort((a, b) => a.localeCompare(b));
            return ligas;
        }

        function atualizarOpcoesLigasModal() {
            const ligasUnicas = getLeagueOptionsFromData();
            const modalSelect = document.getElementById('leagueSelect');
            const savedLeague = localStorage.getItem('fpd_last_league');
            modalSelect.innerHTML = '<option value="" disabled selected>Selecione...</option>';
            ligasUnicas.forEach(liga => modalSelect.innerHTML += `<option value="${escapeHtml(liga)}">${escapeHtml(liga)}</option>`);
            modalSelect.innerHTML += '<option value="NEW_LEAGUE_OPTION" style="color:var(--accent); font-weight:bold;">+ Novo Campeonato</option>';
            if (savedLeague) {
                for (let i = 0; i < modalSelect.options.length; i++) {
                    if (modalSelect.options[i].value === savedLeague) {
                        modalSelect.selectedIndex = i;
                        break;
                    }
                }
            }
        }

        function updateShownCount(n) {
            const el = document.getElementById('shownCount');
            if (!el) return;
            el.innerHTML = `<b>${Number(n || 0)}</b> jogos`;
        }

        function limparFiltros() {
            document.getElementById('filterSearch').value = '';
            document.getElementById('filterStatus').value = '';
            document.getElementById('filterVenue').value = '';
            ACTIVE_FILTERS.league.clear();
            ACTIVE_FILTERS.category.clear();
            const ligas = getLeagueOptionsFromData();
            buildMsList('league', ligas);
            buildMsList('category', CATEGORY_OPTIONS);
            updateMsSummary('league');
            updateMsSummary('category');
            filtrarDashboard();
            if (PL_LIVE_ENABLED) {
                resetLiveUi();
                applyLiveMarkerToGrid(null);
            }
            const ov = getOverlay();
            if (ov && ov.dataset.plusMode === '1') {
                ov.dataset.plusMode = '0';
                updatePlusUi();
                if (ov.style.display === 'flex') rerenderCurrentPriceLine();
            }
        }

        function filtrarDashboard() {
            const busca = document.getElementById('filterSearch').value.toLowerCase();
            const statusFiltro = document.getElementById('filterStatus').value;
            const venueFiltro = document.getElementById('filterVenue').value;
            const leagueSet = ACTIVE_FILTERS.league;
            const catSet = ACTIVE_FILTERS.category;
            const dadosFiltrados = (TODOS_DADOS || []).filter(item => {
                const team = String(item.team || '').toLowerCase();
                const matchBusca = team.includes(busca);
                const matchLeague = leagueSet.size ? leagueSet.has(String(item.league || '').trim()) : true;
                const matchCat = catSet.size ? catSet.has(String(item.category || '').trim()) : true;
                const matchVenue = venueFiltro ? String(item.venue || '') === venueFiltro : true;
                let matchStatus = true;
                if (statusFiltro) matchStatus = supportsStatusFilter(item) && (calcularStatus(item.score_ht, item.venue) === statusFiltro);
                return matchBusca && matchLeague && matchCat && matchVenue && matchStatus;
            });
            updateShownCount(dadosFiltrados.length);
            atualizarResumoGlobal(dadosFiltrados);
            buildRenderQueue(dadosFiltrados);
            refreshOpenPriceLineFilterContext();
        }

        function buildRenderQueue(dados) {
            const dashboard = document.getElementById('dashboard');
            dashboard.innerHTML = '';
            RENDER_QUEUE = [];
            CURRENT_RENDER_INDEX = 0;
            CURRENT_TBODY_REF = null;
            if (!dados || dados.length === 0) {
                dashboard.innerHTML = getEmptyStateHtml();
                return;
            }
            const ligas = {};
            dados.forEach(d => {
                const k = String(d.league || '').trim();
                if (!ligas[k]) ligas[k] = [];
                ligas[k].push(d);
            });
            const sortedLeagues = Object.keys(ligas).sort();
            const ordemCat = ["Jogo parelho", "Jogo de favorito", "Jogo de super favorito"];
            const canManage = isLogged();
            sortedLeagues.forEach(nomeLiga => {
                RENDER_QUEUE.push({
                    type: 'league_header',
                    name: nomeLiga
                });
                const categorias = {};
                ligas[nomeLiga].forEach(item => {
                    const c = String(item.category || '').trim();
                    if (!categorias[c]) categorias[c] = [];
                    categorias[c].push(item);
                });
                ordemCat.forEach(cat => {
                    if (categorias[cat] && categorias[cat].length > 0) {
                        RENDER_QUEUE.push({
                            type: 'cat_start',
                            name: cat,
                            canManage: canManage
                        });
                        categorias[cat].forEach(jogo => {
                            RENDER_QUEUE.push({
                                type: 'row',
                                data: jogo,
                                canManage: canManage
                            });
                        });
                        const htVals = [];
                        const ftVals = [];
                        categorias[cat].forEach(j => {
                            const vHT = parseFloat(j.odd_ht);
                            const vFT = parseFloat(j.odd_ft);
                            if (!Number.isNaN(vHT)) htVals.push(vHT);
                            if (!Number.isNaN(vFT)) ftVals.push(vFT);
                        });
                        const avgHT = avg(htVals);
                        const avgFT = avg(ftVals);
                        const ht = avgUnderOverByMean(htVals, avgHT);
                        const ft = avgUnderOverByMean(ftVals, avgFT);
                        RENDER_QUEUE.push({
                            type: 'stats_and_end',
                            stats: {
                                ht,
                                ft,
                                avgHT,
                                avgFT
                            },
                            canManage: canManage
                        });
                    }
                });
                RENDER_QUEUE.push({
                    type: 'league_end'
                });
            });
            renderNextBatch();
        }

        function renderNextBatch() {
            if (IS_RENDERING) return;
            if (CURRENT_RENDER_INDEX >= RENDER_QUEUE.length) return;
            IS_RENDERING = true;
            const dashboard = document.getElementById('dashboard');
            let rowsProcessed = 0;
            while (CURRENT_RENDER_INDEX < RENDER_QUEUE.length && rowsProcessed < RENDER_BATCH_SIZE) {
                const item = RENDER_QUEUE[CURRENT_RENDER_INDEX];
                CURRENT_RENDER_INDEX++;
                if (item.type === 'league_header') {
                    const section = document.createElement('div');
                    section.className = 'league-section';
                    section.innerHTML = `<h2 class="league-title">${escapeHtml(item.name)}</h2>`;
                    dashboard.appendChild(section);
                } else if (item.type === 'league_end') {} else if (item.type === 'cat_start') {
                    const sections = dashboard.getElementsByClassName('league-section');
                    const lastSection = sections[sections.length - 1];
                    if (lastSection) {
                        const group = document.createElement('div');
                        group.className = 'category-group';
                        group.innerHTML = `
          <h4 class="category-name">${escapeHtml(item.name)}</h4>
          <table class="data-table">
            <thead>
              <tr>
                <th class="col-date text-left">Data</th>
                <th class="col-match text-center">Confronto</th>
                <th class="col-time text-center">Tempo</th>
                <th class="col-price text-center">Preço HT</th>
                <th class="col-price text-center">Preço FT</th>
                <th class="col-pay text-center">Pagamento / Min</th>
                ${item.canManage ? `<th class="col-actions text-center">Ações</th>` : ``}
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        `;
                        lastSection.appendChild(group);
                        CURRENT_TBODY_REF = group.querySelector('tbody');
                    }
                } else if (item.type === 'row') {
                    rowsProcessed++;
                    if (CURRENT_TBODY_REF) {
                        const tr = createRowElement(item.data, item.canManage);
                        CURRENT_TBODY_REF.appendChild(tr);
                    }
                } else if (item.type === 'stats_and_end') {
                    if (CURRENT_TBODY_REF) {
                        const statsHtml = createStatsRows(item.stats, item.canManage);
                        CURRENT_TBODY_REF.insertAdjacentHTML('beforeend', statsHtml);
                        CURRENT_TBODY_REF = null;
                    }
                }
            }
            IS_RENDERING = false;
            updateRenderedTimers();
        }

        function createRowElement(jogo, canManage) {
            const isCasa = String(jogo.venue || '') === 'Casa';
            const myTeam = `<span class="team-active">${escapeHtml(jogo.team)}</span>`;
            const opponent = `<span class="team-opponent">${escapeHtml(jogo.opponent || 'Adversário')}</span>`;
            const scoreLabel = String(jogo.score_ht || '').trim() || '--';
            const score = `<span class="score-box">${escapeHtml(scoreLabel)}</span>`;
            const source = String(jogo.source || 'manual').toLowerCase();
            const sourcePill = `<span class="source-pill ${source === 'api' ? 'api' : 'manual'}">${source === 'api' ? 'API' : 'MANUAL'}</span>`;
            const matchDisplay = `
                <div class="match-meta">
                    ${isCasa ? `${myTeam} ${score} ${opponent}` : `${opponent} ${score} ${myTeam}`}
                    ${sourcePill}
                </div>
            `;
            const vHT = parseFloat(jogo.odd_ht);
            const vFT = parseFloat(jogo.odd_ft);
            const rid = getRowId(jogo);
            const canEditRow = canManage && source === 'manual';
            const timeLabel = getTimeLabel(jogo);
            const payHT = calcPayment(vHT);
            const payFT = calcPayment(vFT);
            const htBadge = Number.isFinite(vHT)
                ? `<span class="odd-badge odd-badge-ht price-click" data-origin="table" data-phase="HT" data-odd="${Number(vHT).toFixed(2)}">${Number(vHT).toFixed(2)}</span>`
                : `<span class="odd-badge odd-badge-ht odd-badge-avg">--</span>`;
            const ftBadge = Number.isFinite(vFT)
                ? `<span class="odd-badge odd-badge-ft price-click" data-origin="table" data-phase="FT" data-odd="${Number(vFT).toFixed(2)}">${Number(vFT).toFixed(2)}</span>`
                : `<span class="odd-badge odd-badge-ft odd-badge-avg">--</span>`;
            const tr = document.createElement('tr');
            tr.dataset.rowId = rid;
            tr.innerHTML = `
    <td class="text-left" data-label="Data"><span class="date-val">${escapeHtml(jogo.date)}</span></td>
    <td class="text-center" data-label="Confronto"><div class="match-display">${matchDisplay}</div></td>
    <td class="text-center" data-label="Tempo">
      <span class="time-pill" data-role="time-pill">${escapeHtml(timeLabel)}</span>
    </td>
    <td class="text-center" data-label="Preço HT">
      ${htBadge}
    </td>
    <td class="text-center" data-label="Preço FT">
      ${ftBadge}
    </td>
    <td class="text-center" data-label="Pagamento / Min">
      <span class="metric-stack">
        <span class="metric-main">HT ${fmtPayment(payHT)}</span>
        <span class="metric-sub">FT ${fmtPayment(payFT)}</span>
      </span>
    </td>
    ${
      canManage
        ? `<td class="text-center" data-label="Ações">
            <div class="row-actions">
              ${canEditRow ? `<button class="row-btn edit" type="button" data-action="edit" data-id="${escapeHtml(rid)}">Editar</button>` : `<button class="row-btn edit" type="button" disabled title="Linha da API é somente leitura">API</button>`}
              ${canEditRow ? `<button class="row-btn del" type="button" data-action="del" data-id="${escapeHtml(rid)}">Excluir</button>` : ``}
            </div>
          </td>`
        : ``
    }
  `;
            return tr;
        }

        function createStatsRows(stats, canManage) {
            const {
                ht,
                ft,
                avgHT,
                avgFT
            } = stats;
            const row = (label, vHTa, vFTa, kind) => {
                const canClickHT = Number.isFinite(vHTa);
                const canClickFT = Number.isFinite(vFTa);
                const k = (kind || '');
                const payHT = calcPayment(vHTa);
                const payFT = calcPayment(vFTa);
                return `
      <tr class="avg-row" style="background-color:#040404;">
        <td class="text-left" data-label="Data" style="padding:5px 10px !important;"><span class="date-val">—</span></td>
        <td class="text-center" data-label="Confronto" style="padding:5px 10px !important;">
          <div class="match-display">
            <span class="team-opponent"></span>
            <span class="score-box score-box-avg">${escapeHtml(label)}</span>
            <span class="team-opponent"></span>
          </div>
        </td>
        <td class="text-center" data-label="Tempo" style="padding:5px 10px !important;">
          <span class="time-pill">Média</span>
        </td>
        <td class="text-center" data-label="Odd HT" style="padding:5px 10px !important;">
          <span class="odd-badge odd-badge-ht odd-badge-avg ${canClickHT ? 'price-click' : ''}"
            ${canClickHT ? `data-origin="table" data-phase="HT" data-kind="${k}" data-odd="${Number(vHTa).toFixed(2)}"` : ''}>
            ${fmt(vHTa)}
          </span>
        </td>
        <td class="text-center" data-label="Odd FT" style="padding:5px 10px !important;">
          <span class="odd-badge odd-badge-ft odd-badge-avg ${canClickFT ? 'price-click' : ''}"
            ${canClickFT ? `data-origin="table" data-phase="FT" data-kind="${k}" data-odd="${Number(vFTa).toFixed(2)}"` : ''}>
            ${fmt(vFTa)}
          </span>
        </td>
        <td class="text-center" data-label="Pagamento / Min" style="padding:5px 10px !important;">
          <span class="metric-stack">
            <span class="metric-main">HT ${fmtPayment(payHT)}</span>
            <span class="metric-sub">FT ${fmtPayment(payFT)}</span>
          </span>
        </td>
        ${canManage ? `<td data-label="Ações" style="padding:5px 10px !important;"></td>` : ``}
      </tr>
    `;
            };
            let html = '';
            html += row('Média Under ▼', ht.under, ft.under, 'under');
            html += row('Média Total -', avgHT, avgFT, 'total');
            html += row('Média Over ▲', ht.over, ft.over, 'over');
            return html;
        }

        window.addEventListener('scroll', () => {
            if ((window.innerHeight + window.scrollY) >= document.body.offsetHeight - 400) {
                renderNextBatch();
            }
        });

        async function carregarEstudos() {
            try {
                const response = await fetch('api.php?t=' + Date.now());
                const payload = await response.json().catch(() => ({}));
                TODOS_DADOS = Array.isArray(payload?.rows) ? payload.rows : [];
                LAST_API_META = payload?.meta || LAST_API_META;
                if (!Array.isArray(TODOS_DADOS)) TODOS_DADOS = [];
                renderApiWarning();
                const leagueOptions = getLeagueOptionsFromData();
                buildMsList('league', leagueOptions);
                buildMsList('category', CATEGORY_OPTIONS);
                updateMsSummary('league');
                updateMsSummary('category');
                atualizarOpcoesLigasModal();
                filtrarDashboard();
            } catch (err) {
                console.error(err);
                LAST_API_META = {
                    ...LAST_API_META,
                    stale: true,
                    warning: 'Falha ao carregar dados do backend local.'
                };
                renderApiWarning();
            }
        }

        function applyLastModalDefaults() {
            const lastDate = localStorage.getItem('fpd_last_date');
            const lastLeague = localStorage.getItem('fpd_last_league');
            const lastCategory = localStorage.getItem('fpd_last_category');
            if (lastDate) document.getElementById('date').value = lastDate;
            atualizarOpcoesLigasModal();
            if (lastLeague) {
                const sel = document.getElementById('leagueSelect');
                if ([...sel.options].some(o => o.value === lastLeague)) {
                    sel.value = lastLeague;
                    verificarNovaLiga(sel);
                }
            }
            if (lastCategory) {
                const cat = document.getElementById('category');
                if ([...cat.options].some(o => o.value === lastCategory)) cat.value = lastCategory;
            }
        }

        function abrirModal() {
            applyLastModalDefaults();
            document.getElementById('modalOverlay').style.display = 'flex';
        }

        function fecharModal() {
            document.getElementById('modalOverlay').style.display = 'none';
            document.getElementById('newLeagueInput').style.display = 'none';
        }

        function fecharEdit() {
            const ov = document.getElementById('editOverlay');
            if (ov) ov.style.display = 'none';
        }

        function findRowById(id) {
            const rid = String(id || '');
            return (TODOS_DADOS || []).find(j => getRowId(j) === rid) || null;
        }

        function openEditById(id) {
            if (!isLogged()) return;
            const row = findRowById(id);
            if (!row) return alert("Registro não encontrado.");
            if (String(row.source || 'manual') !== 'manual') return alert("Linhas da API são somente leitura.");
            document.getElementById('editId').value = String(id);
            document.getElementById('editLeague').value = String(row.league || '');
            document.getElementById('editCategory').value = String(row.category || 'Jogo parelho');
            document.getElementById('editDate').value = String(row.date || '');
            document.getElementById('editTeam').value = String(row.team || '');
            document.getElementById('editVenue').value = String(row.venue || 'Casa');
            document.getElementById('editScoreHT').value = String(row.score_ht || '');
            document.getElementById('editOddHT').value = String(row.odd_ht || '');
            document.getElementById('editOddFT').value = String(row.odd_ft || '');
            document.getElementById('editOverlay').style.display = 'flex';
        }
        async function deleteById(id) {
            if (!isLogged()) return;
            const row = findRowById(id);
            if (!row) return alert("Registro não encontrado.");
            if (String(row.source || 'manual') !== 'manual') return alert("Linhas da API são somente leitura.");
            const ok = confirm(`Excluir este registro?\n\n${row.team || ''} • ${row.league || ''} • ${row.date || ''}`);
            if (!ok) return;
            try {
                const resp = await fetch(`api.php?id=${encodeURIComponent(String(id))}`, {
                    method: 'DELETE'
                });
                let json = null;
                try {
                    json = await resp.json();
                } catch (e) {}
                if (resp.ok && (!json || json.status === 'sucesso' || json.ok === true)) {
                    carregarEstudos();
                } else {
                    alert("Erro ao excluir (backend precisa suportar delete).");
                }
            } catch (err) {
                console.error(err);
                alert("Erro ao conectar com servidor.");
            }
        }
        async function saveEdit(e) {
            e.preventDefault();
            if (!isLogged()) return;
            const id = document.getElementById('editId').value;
            const payload = {
                action: 'update',
                id: String(id),
                league: document.getElementById('editLeague').value,
                category: document.getElementById('editCategory').value,
                team: document.getElementById('editTeam').value,
                venue: document.getElementById('editVenue').value,
                date: document.getElementById('editDate').value,
                odd_ht: document.getElementById('editOddHT').value,
                score_ht: document.getElementById('editScoreHT').value,
                odd_ft: document.getElementById('editOddFT').value
            };
            try {
                const resp = await fetch(`api.php?id=${encodeURIComponent(String(id))}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });
                const json = await resp.json().catch(() => null);
                if (resp.ok && (json && (json.status === 'sucesso' || json.ok === true))) {
                    fecharEdit();
                    carregarEstudos();
                } else {
                    alert("Erro ao editar (backend precisa suportar update).");
                }
            } catch (err) {
                console.error(err);
                alert("Erro ao conectar com servidor.");
            }
        }

        function toggleMs(type, open) {
            const root = document.getElementById(type === 'league' ? 'msLeague' : 'msCategory');
            if (!root) return;
            if (open) root.classList.add('open');
            else root.classList.remove('open');
            const btn = root.querySelector('.ms-btn');
            if (btn) btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        function closeAllMs() {
            toggleMs('league', false);
            toggleMs('category', false);
        }

        function msOptionsFor(type) {
            if (type === 'league') return getLeagueOptionsFromData();
            return CATEGORY_OPTIONS;
        }

        function buildMsList(type, options) {
            const set = ACTIVE_FILTERS[type];
            const roots = getMsRoots(type);
            for (const root of roots) {
                const listEl = root.querySelector('.ms-list');
                if (!listEl) continue;
                if (!options || options.length === 0) {
                    listEl.innerHTML = `<div style="color:#777; font-size:0.85rem; padding:8px;">Sem opções</div>`;
                    continue;
                }
                if (type === 'league') {
                    const q = root.querySelector('.ms-search input') ?.value || '';
                    const mixed = buildLeagueTopics(options, q);
                    if (!mixed.length) {
                        listEl.innerHTML = `<div style="color:#777; font-size:0.85rem; padding:8px;">Nenhuma liga encontrada</div>`;
                        continue;
                    }
                    listEl.innerHTML = mixed.map(row => {
                        if (row.type === 'sep') return `<div class="ms-sep">${escapeHtml(row.label)}</div>`;
                        const opt = row.value;
                        const checked = set.has(opt) ? 'checked' : '';
                        return `
        <label class="ms-item" data-ms-item="league" data-value="${escapeHtml(opt)}">
          <input type="checkbox" ${checked} data-ms-check="league" value="${escapeHtml(opt)}">
          <span title="${escapeHtml(opt)}">${escapeHtml(opt)}</span>
        </label>
      `;
                    }).join("");
                    continue;
                }
                listEl.innerHTML = options.map(opt => {
                    const checked = set.has(opt) ? 'checked' : '';
                    return `
      <label class="ms-item" data-ms-item="${type}" data-value="${escapeHtml(opt)}">
        <input type="checkbox" ${checked} data-ms-check="${type}" value="${escapeHtml(opt)}">
        <span title="${escapeHtml(opt)}">${escapeHtml(opt)}</span>
      </label>
    `;
                }).join("");
            }
        }

        function setAllMs(type, options, root) {
            if (type === 'league') {
                const qRaw = root ?.querySelector('.ms-search input') ?.value || '';
                const q = normalizeSearch(qRaw);
                if (q) {
                    const listEl = root ?.querySelector('.ms-list');
                    const visible = listEl ? Array.from(listEl.querySelectorAll('input[data-ms-check="league"]')).map(inp => String(inp.value || '').trim()).filter(Boolean) : [];
                    const set = ACTIVE_FILTERS.league;
                    for (const v of visible) set.add(v);
                    buildMsList('league', options);
                    updateMsSummary('league');
                    filtrarDashboard();
                    return;
                }
            }
            const set = ACTIVE_FILTERS[type];
            set.clear();
            for (const o of options) set.add(o);
            buildMsList(type, options);
            updateMsSummary(type);
            filtrarDashboard();
        }

        function clearMs(type, options) {
            ACTIVE_FILTERS[type].clear();
            buildMsList(type, options);
            updateMsSummary(type);
            filtrarDashboard();
        }

        function toggleMs(type, open) {
            const roots = getMsRoots(type);
            for (const root of roots) toggleMsRoot(root, open);
        }

        function closeAllMs(exceptRoot = null) {
            const roots = Array.from(document.querySelectorAll('.ms.open'));
            for (const root of roots) {
                if (exceptRoot && root === exceptRoot) continue;
                toggleMsRoot(root, false);
            }
        }

        function syncPriceLineCategoryFilterUi() {
            const root = document.getElementById('plCategoryMs');
            if (!root) return;
            const btn = root.querySelector('.ms-btn');
            const overlay = getOverlay();
            const disabled = !!overlay && overlay.dataset.customMode === '1';
            root.classList.toggle('disabled', disabled);
            if (btn) {
                btn.disabled = disabled;
                btn.setAttribute('aria-disabled', disabled ? 'true' : 'false');
            }
            if (disabled) toggleMsRoot(root, false);
        }

        function refreshOpenPriceLineFilterContext() {
            const overlay = getOverlay();
            if (!overlay || overlay.style.display !== 'flex') return;
            syncPriceLineCategoryFilterUi();
            const switchEl = document.getElementById('plSwitch');
            if (!switchEl) return;
            const showSwitch = overlay.dataset.showSwitch === '1';
            if (!showSwitch) {
                switchEl.innerHTML = '';
                switchEl.classList.remove('grid');
                switchEl.style.display = 'none';
                return;
            }
            const phase = (overlay.dataset.phase || 'FT').toUpperCase() === 'HT' ? 'HT' : 'FT';
            const kind = String(overlay.dataset.kind || 'total').toLowerCase() || 'total';
            const showMids = overlay.dataset.customMode !== '1';
            renderSwitchBar(phase, kind, showMids);
            switchEl.style.display = 'grid';
        }

        document.addEventListener("DOMContentLoaded", () => {
            carregarEstudos();

            const lastDate = localStorage.getItem('fpd_last_date');
            if (lastDate) {
                document.getElementById('date').value = lastDate;
            } else {
                const hoje = new Date();
                const ano = hoje.getFullYear();
                const mes = String(hoje.getMonth() + 1).padStart(2, '0');
                document.getElementById('date').value = `${ano}-${mes}`;
            }

            const leagueSearch = document.getElementById('msLeagueSearch');
            if (leagueSearch) {
                leagueSearch.addEventListener('input', () => {
                    buildMsList('league', getLeagueOptionsFromData());
                });
            }

            document.getElementById('filterSearch').addEventListener('input', filtrarDashboard);
            document.getElementById('filterStatus').addEventListener('change', filtrarDashboard);
            document.getElementById('filterVenue').addEventListener('change', filtrarDashboard);
            const paymentWindow = document.getElementById('paymentWindow');
            const savedPaymentWindow = localStorage.getItem('pj_payment_window');
            if (savedPaymentWindow && ['5', '10', '15'].includes(savedPaymentWindow)) {
                paymentWindow.value = savedPaymentWindow;
            }
            SELECTED_PAYMENT_WINDOW = Number(paymentWindow.value || '5');
            paymentWindow.addEventListener('change', () => {
                SELECTED_PAYMENT_WINDOW = Number(paymentWindow.value || '5');
                localStorage.setItem('pj_payment_window', String(SELECTED_PAYMENT_WINDOW));
                filtrarDashboard();
            });
            const plGroupStep = document.getElementById('plGroupStep');
            const savedPlGroupStep = localStorage.getItem('pj_price_line_group_step');
            PRICE_LINE_GROUP_STEP = sanitizePriceLineGroupStep(savedPlGroupStep || plGroupStep?.value || '1');
            if (plGroupStep) {
                plGroupStep.value = String(PRICE_LINE_GROUP_STEP);
                plGroupStep.addEventListener('change', () => {
                    PRICE_LINE_GROUP_STEP = sanitizePriceLineGroupStep(plGroupStep.value || '1');
                    plGroupStep.value = String(PRICE_LINE_GROUP_STEP);
                    localStorage.setItem('pj_price_line_group_step', String(PRICE_LINE_GROUP_STEP));
                    rerenderCurrentPriceLine();
                    if (PL_LIVE_ENABLED) updateLiveMarker();
                });
            }

            document.getElementById('modalOverlay').addEventListener('click', (e) => {
                if (e.target === document.getElementById('modalOverlay')) fecharModal();
            });
            document.getElementById('editOverlay').addEventListener('click', (e) => {
                if (e.target === document.getElementById('editOverlay')) fecharEdit();
            });

            document.getElementById('formEdit').addEventListener('submit', saveEdit);
            document.getElementById('logoutBtn').addEventListener('click', async () => {
                try {
                    await fetch('api.php?action=logout', {
                        method: 'POST'
                    });
                } finally {
                    window.location.href = 'login.php';
                }
            });

            document.addEventListener('click', (e) => {
                const msBtn = e.target.closest('.ms-btn');
                if (msBtn) {
                    const root = msBtn.closest('.ms');
                    if (!root || msBtn.disabled || root.classList.contains('disabled')) return;
                    const type = root.dataset.msRoot || msBtn.dataset.ms;
                    const isOpen = root.classList.contains('open');
                    closeAllMs(root);
                    toggleMsRoot(root, !isOpen);
                    if (type === 'league' && !isOpen) {
                        const inp = root.querySelector('.ms-search input');
                        if (inp) {
                            inp.focus();
                            inp.select();
                        }
                    }
                    return;
                }
                if (!e.target.closest('.ms')) closeAllMs();
            });

            document.addEventListener('click', (e) => {
                const act = e.target.closest('[data-ms-action]');
                if (act) {
                    const root = act.closest('.ms');
                    const type = root ?.dataset.msRoot || act.dataset.ms;
                    const action = act.dataset.msAction;
                    const opts = msOptionsFor(type);
                    if (action === 'all') setAllMs(type, opts, root);
                    if (action === 'none') clearMs(type, opts);
                    return;
                }
            });

            document.addEventListener('change', (e) => {
                const chk = e.target.closest('[data-ms-check]');
                if (!chk) return;
                const root = chk.closest('.ms');
                const type = root ?.dataset.msRoot || chk.dataset.msCheck;
                const val = chk.value;
                const set = ACTIVE_FILTERS[type];
                if (chk.checked) set.add(val);
                else set.delete(val);
                buildMsList(type, msOptionsFor(type));
                updateMsSummary(type);
                filtrarDashboard();
            });

            document.addEventListener('click', (e) => {
                const el = e.target.closest('.price-click');
                if (!el) return;
                if (el.closest('#priceLineOverlay')) return;
                if (el.closest('.row-actions')) return;
                const phase = (el.dataset.phase || '').toUpperCase();
                const kind = (el.dataset.kind || '').toLowerCase();
                const origin = (el.dataset.origin || 'table').toLowerCase();
                const showSwitch = (origin === 'card');
                let odd = parseFloat(el.dataset.odd);
                const allowedKinds = new Set(['vvunder', 'vunder', 'under', 'munder', 'total', 'mover', 'over', 'vover', 'vvover']);
                if (!Number.isFinite(odd) && (phase === 'HT' || phase === 'FT') && allowedKinds.has(kind)) {
                    odd = LAST_SUMMARY[phase] ?.[kind];
                }
                if ((phase !== 'HT' && phase !== 'FT') || !Number.isFinite(odd)) return;
                const ov = getOverlay();
                if (ov && (origin === 'table')) ov.dataset.plusMode = ov.dataset.plusMode || '0';
                abrirPriceLine(phase, odd, allowedKinds.has(kind) ? kind : 'total', showSwitch, false);
            });

            document.getElementById('plSwitch').addEventListener('click', (e) => {
                const pill = e.target.closest('.pl-pill');
                if (!pill) return;
                if (pill.classList.contains('disabled')) return;
                const phase = (pill.dataset.phase || '').toUpperCase();
                const kind = (pill.dataset.kind || '').toLowerCase();
                const odd = parseFloat(pill.dataset.odd);
                const allowedKinds = new Set(['vvunder', 'vunder', 'under', 'munder', 'total', 'mover', 'over', 'vover', 'vvover']);
                if ((phase !== 'HT' && phase !== 'FT') || !allowedKinds.has(kind)) return;

                const ov = getOverlay();
                if (!ov) return;

                if (ov.dataset.customMode === '1') {
                    ov.dataset.kind = kind;
                    renderCustomLPFromInputs();
                    return;
                }

                if (!Number.isFinite(odd)) return;
                if (ov.dataset.plusMode == null) ov.dataset.plusMode = '0';
                abrirPriceLine(phase, odd, kind, true, false);
            });

            const priceOverlay = document.getElementById('priceLineOverlay');
            priceOverlay.addEventListener('click', (e) => {
                if (e.target === priceOverlay) fecharPriceLine();
            });

            document.addEventListener('click', (e) => {
                const btn = e.target.closest('[data-action]');
                if (!btn) return;
                const action = btn.dataset.action;
                const id = btn.dataset.id;
                if (!action || !id) return;
                if (action === 'edit') {
                    openEditById(id);
                    return;
                }
                if (action === 'del') {
                    deleteById(id);
                    return;
                }
            });

            window.setInterval(() => {
                carregarEstudos();
            }, (APP_CONFIG.refreshSeconds || 60) * 1000);
            window.setInterval(() => {
                updateRenderedTimers();
            }, 1000);
        });

        document.getElementById('formEstudo').addEventListener('submit', async (e) => {
            e.preventDefault();
            const leagueSelect = document.getElementById('leagueSelect');
            let leagueName = leagueSelect.value;
            if (leagueName === 'NEW_LEAGUE_OPTION') {
                leagueName = document.getElementById('newLeagueInput').value.trim();
                if (!leagueName) return alert("Digite o nome da liga");
            }
            const novo = {
                league: leagueName,
                category: document.getElementById('category').value,
                team: document.getElementById('team').value,
                venue: document.getElementById('venue').value,
                date: document.getElementById('date').value,
                odd_ht: document.getElementById('odd_ht').value,
                score_ht: document.getElementById('score_ht').value,
                odd_ft: document.getElementById('odd_ft').value
            };
            try {
                const resp = await fetch('api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(novo)
                });
                const json = await resp.json();
                if (json.status === 'sucesso') {
                    localStorage.setItem('fpd_last_league', novo.league);
                    localStorage.setItem('fpd_last_date', novo.date);
                    localStorage.setItem('fpd_last_category', novo.category);
                    fecharModal();
                    document.getElementById('formEstudo').reset();
                    applyLastModalDefaults();
                    document.getElementById('newLeagueInput').style.display = 'none';
                    carregarEstudos();
                } else {
                    alert('Erro ao salvar');
                }
            } catch (err) {
                console.error(err);
                alert("Erro ao conectar com servidor.");
            }
        });
    </script>
</body>

</html>
