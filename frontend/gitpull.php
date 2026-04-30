<?php
require_once 'config.php';
requireLogin();
handleLogoutRequest();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Webサイトソースコード管理 - Next.js管理ツール</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f8f9fa;
            min-height: 100vh;
            color: #333;
        }

        .header {
            background: white;
            padding: 20px 40px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-bottom: 1px solid #e0e0e0;
        }

        .header-content {
            max-width: 1100px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .logout-form {
            margin: 0;
        }

        .logout-btn {
            background: #e5e7eb;
            color: #333;
            padding: 8px 16px;
            border: 1px solid #d1d5db;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
        }

        .logout-btn:hover {
            background: #d1d5db;
        }

        .container {
            max-width: 1100px;
            margin: 40px auto;
            padding: 0 24px 40px;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 28px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 24px;
        }

        .card h2 {
            margin-bottom: 16px;
            font-size: 22px;
        }

        .card p {
            color: #6b7280;
            line-height: 1.7;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-top: 20px;
        }

        .info-box {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 16px;
        }

        .info-box .label {
            display: block;
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .info-box .value {
            font-size: 16px;
            font-weight: 600;
            word-break: break-word;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 20px;
        }

        .primary-btn,
        .secondary-btn {
            padding: 12px 18px;
            border-radius: 8px;
            border: none;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }

        .primary-btn {
            background: #5fb5a8;
            color: white;
        }

        .primary-btn:hover {
            background: #4a9d8e;
        }

        .secondary-btn {
            background: #e5e7eb;
            color: #374151;
        }

        .secondary-btn:hover {
            background: #d1d5db;
        }

        .output {
            margin-top: 20px;
            padding: 16px;
            border-radius: 10px;
            background: #111827;
            color: #f9fafb;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            white-space: pre-wrap;
            word-break: break-word;
            min-height: 160px;
        }

        .output.error {
            background: #451a1a;
        }

        .status-clean {
            color: #15803d;
        }

        .status-dirty {
            color: #b45309;
        }

        .subtle {
            font-size: 13px;
            color: #6b7280;
            margin-top: 10px;
        }

        @media (max-width: 768px) {
            .header {
                padding: 20px;
            }

            .header-content,
            .user-info {
                flex-direction: column;
                align-items: flex-start;
            }

            .container {
                padding: 0 16px 32px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div>
                <a href="services.php" style="color: #5fb5a8; text-decoration: none; font-size: 14px;">← サービス一覧に戻る</a>
                <h1 style="margin-top: 10px; font-size: 24px;">Git Pull 管理</h1>
            </div>
            <div class="user-info">
                <span>ようこそ、<?php echo htmlspecialchars($_SESSION['username']); ?>さん</span>
                <form method="POST" class="logout-form">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="logout-btn">ログアウト</button>
                </form>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="card">
            <h2>Next.js リポジトリ更新</h2>
            <p>管理対象の Next.js プロジェクトに対して、`git pull --ff-only` を実行します。ローカル変更が残っている場合は失敗するため、意図しない上書きを避けられます。</p>

            <div class="info-grid">
                <div class="info-box">
                    <span class="label">プロジェクトパス</span>
                    <span class="value"><?php echo htmlspecialchars(getenv('NEXTJS_PROJECT_PATH') ?: '未設定'); ?></span>
                </div>
                <div class="info-box">
                    <span class="label">ブランチ</span>
                    <span class="value" id="branch">読み込み中...</span>
                </div>
                <div class="info-box">
                    <span class="label">コミット</span>
                    <span class="value" id="commit">読み込み中...</span>
                </div>
                <div class="info-box">
                    <span class="label">作業ツリー</span>
                    <span class="value" id="cleanState">読み込み中...</span>
                </div>
            </div>

            <div class="actions">
                <button type="button" class="primary-btn" id="pullButton" onclick="executeGitPull()">git pull を実行</button>
                <button type="button" class="secondary-btn" onclick="loadRepositoryStatus()">状態を再取得</button>
            </div>

            <p class="subtle">更新取得後にビルドやサービス再起動が必要な場合は、ウェブサイト管理ページから実行してください。</p>
            <div id="output" class="output">実行ログがここに表示されます。</div>
        </div>

        <div class="card">
            <h2>現在の git status</h2>
            <div id="statusText" class="output">読み込み中...</div>
        </div>
    </div>

    <script>
        const API_URL = '<?php echo API_URL; ?>';
        const token = '<?php echo $_SESSION['token']; ?>';
        const projectPath = '<?php echo getenv("NEXTJS_PROJECT_PATH") ?: ""; ?>';

        async function loadRepositoryStatus() {
            try {
                const response = await fetch(`${API_URL}/repository/status`, {
                    headers: {
                        'Authorization': token
                    },
                    credentials: 'include'
                });

                const data = await response.json();
                if (!data.success) {
                    throw new Error(data.error || '状態の取得に失敗しました');
                }

                document.getElementById('branch').textContent = data.status.branch || '-';
                document.getElementById('commit').textContent = data.status.commit || '-';
                document.getElementById('cleanState').textContent = data.status.is_clean ? 'クリーン' : '未コミット変更あり';
                document.getElementById('cleanState').className = `value ${data.status.is_clean ? 'status-clean' : 'status-dirty'}`;
                document.getElementById('statusText').textContent = data.status.status_text || '状態を取得できませんでした';
                document.getElementById('statusText').className = 'output';
            } catch (error) {
                document.getElementById('branch').textContent = '取得失敗';
                document.getElementById('commit').textContent = '-';
                document.getElementById('cleanState').textContent = '確認不可';
                document.getElementById('statusText').textContent = `エラー: ${error.message}`;
                document.getElementById('statusText').className = 'output error';
            }
        }

        async function executeGitPull() {
            const button = document.getElementById('pullButton');
            const output = document.getElementById('output');

            if (!projectPath) {
                output.textContent = 'エラー: NEXTJS_PROJECT_PATH が設定されていません';
                output.className = 'output error';
                return;
            }

            button.disabled = true;
            button.textContent = '実行中...';
            output.textContent = 'git pull を実行しています...';
            output.className = 'output';

            try {
                const response = await fetch(`${API_URL}/execute`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': token
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        command: 'nextjs-git-pull',
                        path: projectPath
                    })
                });

                const data = await response.json();
                if (!data.success) {
                    throw new Error((data.error || 'git pull に失敗しました') + (data.output ? `\n\n${data.output}` : ''));
                }

                output.textContent = data.output || 'Already up to date.';
                output.className = 'output';
                await loadRepositoryStatus();
            } catch (error) {
                output.textContent = `エラー: ${error.message}`;
                output.className = 'output error';
            } finally {
                button.disabled = false;
                button.textContent = 'git pull を実行';
            }
        }

        async function validateSession() {
            try {
                const response = await fetch(`${API_URL}/validate`, {
                    headers: {
                        'Authorization': token
                    },
                    credentials: 'include'
                });

                if (response.status === 401) {
                    window.location.href = 'login.php';
                }
            } catch (error) {
                console.error('Session validation failed:', error);
            }
        }

        window.addEventListener('DOMContentLoaded', () => {
            loadRepositoryStatus();
            setInterval(validateSession, 60000);
        });
    </script>
</body>
</html>
