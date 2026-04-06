<?php
require_once 'config.php';
requireLogin();

// ログアウト処理
if (isset($_GET['logout'])) {
    apiRequest('/logout', 'POST');
    session_destroy();
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ダッシュボード - ウェブサイト管理ツール</title>
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
        }
        
        .header {
            background: white;
            color: #333;
            padding: 20px 40px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-bottom: 1px solid #e0e0e0;
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        h1 {
            font-size: 24px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .logout-btn {
            background: #e5e7eb;
            color: #333;
            padding: 8px 16px;
            border: 1px solid #d1d5db;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .logout-btn:hover {
            background: #d1d5db;
        }
        
        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 40px;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .card h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }
        
        input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 14px;
        }
        
        input[type="text"]:focus {
            outline: none;
            border-color: #5fb5a8;
        }
        
        .button-group {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 30px;
        }
        
        .btn {
            padding: 30px 40px;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 12px;
            min-height: 120px;
        }
        
        .btn .icon {
            font-size: 48px;
        }
        
        .btn .description {
            font-size: 13px;
            font-weight: 400;
            color: rgba(255,255,255,0.9);
            margin-top: 5px;
        }
        
        .btn-primary {
            background: #5fb5a8;
            color: white;
        }
        
        .btn-success {
            background: #5fb5a8;
            color: white;
        }
        
        .btn-warning {
            background: #9ca3af;
            color: white;
        }
        
        .btn-danger {
            background: #6b7280;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            opacity: 0.9;
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        .output {
            margin-top: 20px;
            padding: 15px;
            background: #f9f9f9;
            border-left: 4px solid #5fb5a8;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            max-height: 400px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
            display: none;
        }
        
        .output.show {
            display: block;
        }
        
        .output.error {
            border-left-color: #e74c3c;
            background: #fee;
        }
        
        .output.success {
            border-left-color: #27ae60;
            background: #efe;
        }
        
        .status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }
        
        .status.running {
            background: #d4edda;
            color: #155724;
        }
        
        .status.stopped {
            background: #f8d7da;
            color: #721c24;
        }
        
        .loading {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div>
                <a href="services.php" style="color: #5fb5a8; text-decoration: none; font-size: 14px; margin-right: 20px;">← サービス一覧に戻る</a>
                <span style="font-size: 24px; font-weight: bold;">ウェブサイト管理ツール</span>
            </div>
            <div class="user-info">
                <span>ようこそ、<?php echo htmlspecialchars($_SESSION['username']); ?>さん</span>
                <a href="services.php?logout" class="logout-btn">ログアウト</a>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="card">
            <h2>ウェブサイト管理ツール</h2>
            
            <div class="button-group">
                <button class="btn btn-primary" onclick="executeCommand('build')">
                    <span class="icon">🔨</span>
                    <span>Build</span>
                    <span class="description">記事を更新</span>
                </button>
                <button class="btn btn-success" onclick="executeCommand('start')">
                    <span class="icon">▶️</span>
                    <span>Start</span>
                    <span class="description">サイトを立ち上げ</span>
                </button>
                <button class="btn btn-warning" onclick="executeCommand('dev')">
                    <span class="icon">🚀</span>
                    <span>Dev</span>
                    <span class="description">開発モードで起動 (エンジニア専用)</span>
                </button>
                <button class="btn btn-danger" onclick="executeCommand('stop')">
                    <span class="icon">⏹️</span>
                    <span>Stop</span>
                    <span class="description">ウェブサイトを停止</span>
                </button>
            </div>
            
            <div id="output" class="output"></div>
        </div>
    </div>
    
    <script>
        const API_URL = 'http://localhost:8000/api';
        const token = '<?php echo $_SESSION['token']; ?>';
        
        async function executeCommand(command) {
            // 環境変数からプロジェクトパスを取得
            const projectPath = '<?php echo getenv("NEXTJS_PROJECT_PATH") ?: ""; ?>';
            const outputDiv = document.getElementById('output');
            const button = event.target.closest('.btn');
            
            if (!projectPath) {
                showOutput('エラー: NEXTJS_PROJECT_PATH環境変数が設定されていません', 'error');
                return;
            }
            
            // ボタンを無効化
            button.disabled = true;
            button.innerHTML = '<div class="loading"></div><span>実行中...</span>';
            
            try {
                const response = await fetch(`${API_URL}/execute`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': token
                    },
                    body: JSON.stringify({
                        command: command,
                        path: projectPath
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showOutput(`コマンド '${command}' が正常に実行されました\n\n${data.output}`, 'success');
                } else {
                    showOutput(`エラー: ${data.error}\n\n${data.output || ''}`, 'error');
                }
            } catch (error) {
                showOutput(`エラー: ${error.message}`, 'error');
            } finally {
                // ボタンを元に戻す
                button.disabled = false;
                updateButtonContent(button, command);
            }
        }
        
        function updateButtonContent(button, command) {
            const configs = {
                'build': {
                    icon: '🔨',
                    label: 'Build',
                    desc: 'プロジェクトをビルド (npm run build)'
                },
                'start': {
                    icon: '▶️',
                    label: 'Start',
                    desc: '本番モードで起動 (npm run start)'
                },
                'dev': {
                    icon: '🚀',
                    label: 'Dev',
                    desc: '開発モードで起動 (npm run dev)'
                },
                'stop': {
                    icon: '⏹️',
                    label: 'Stop',
                    desc: 'プロセスを停止'
                }
            };
            const config = configs[command];
            button.innerHTML = `<span class="icon">${config.icon}</span><span>${config.label}</span><span class="description">${config.desc}</span>`;
        }
        
        function showOutput(message, type) {
            const outputDiv = document.getElementById('output');
            outputDiv.textContent = message;
            outputDiv.className = `output show ${type}`;
        }
        
        // セッション検証
        async function validateSession() {
            try {
                const response = await fetch(`${API_URL}/validate`, {
                    headers: {
                        'Authorization': token
                    }
                });
                
                if (response.status === 401) {
                    window.location.href = 'login.php';
                }
            } catch (error) {
                console.error('Session validation failed:', error);
            }
        }
        
        // 定期的にセッションを検証
        setInterval(validateSession, 60000); // 1分ごと
    </script>
</body>
</html>
