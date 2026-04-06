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
    <title>Cronjob管理 - Next.js管理ツール</title>
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
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: 500;
            font-size: 14px;
        }
        
        input[type="text"] {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 14px;
        }
        
        input[type="text"]:focus {
            outline: none;
            border-color: #5fb5a8;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #5fb5a8;
            color: white;
        }
        
        .btn-primary:hover {
            opacity: 0.9;
        }
        
        .btn-danger {
            background: #6b7280;
            color: white;
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .btn-danger:hover {
            opacity: 0.9;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        th {
            background: #f9fafb;
            color: #374151;
            font-weight: 600;
            font-size: 13px;
        }
        
        td {
            color: #6b7280;
            font-size: 14px;
        }
        
        .code {
            font-family: 'Courier New', monospace;
            background: #f3f4f6;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 13px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #9ca3af;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .help-text {
            font-size: 12px;
            color: #6b7280;
            margin-top: 5px;
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
                <span style="font-size: 24px; font-weight: bold;">Cronjob管理</span>
            </div>
            <div class="user-info">
                <span>ようこそ、<?php echo htmlspecialchars($_SESSION['username']); ?>さん</span>
                <a href="services.php?logout" class="logout-btn">ログアウト</a>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="card">
            <h2>新規Cronjob追加</h2>
            
            <div id="message"></div>
            
            <div class="form-group">
                <label for="schedule">スケジュール (Cron形式)</label>
                <input type="text" id="schedule" placeholder="例: 0 3 * * * (毎日3時に実行)">
                <div class="help-text">
                    形式: 分 時 日 月 曜日 (例: 0 3 * * * = 毎日午前3時、*/5 * * * * = 5分おき)
                </div>
            </div>
            
            <div class="form-group">
                <label for="command">実行コマンド</label>
                <input type="text" id="command" placeholder="例: /usr/bin/php /path/to/script.php">
                <div class="help-text">
                    実行したいコマンドの完全なパスを入力してください
                </div>
            </div>
            
            <button class="btn btn-primary" onclick="addCronJob()">
                追加
            </button>
        </div>
        
        <div class="card">
            <h2>登録済みCronjob一覧</h2>
            
            <div id="cronJobsList">
                <div class="empty-state">読み込み中...</div>
            </div>
        </div>
    </div>
    
    <script>
        const API_URL = '<?php echo API_URL; ?>';
        const token = '<?php echo $_SESSION['token']; ?>';
        
        // ページロード時にCronjob一覧を取得
        document.addEventListener('DOMContentLoaded', function() {
            loadCronJobs();
        });
        
        // Cronjob一覧を読み込み
        async function loadCronJobs() {
            try {
                const response = await fetch(`${API_URL}/cronjobs`, {
                    headers: {
                        'Authorization': token
                    },
                    credentials: 'include'
                });
                
                const data = await response.json();
                
                if (data.success && data.jobs && data.jobs.length > 0) {
                    let html = '<table><thead><tr><th>ID</th><th>スケジュール</th><th>コマンド</th><th>操作</th></tr></thead><tbody>';
                    
                    data.jobs.forEach(job => {
                        html += `
                            <tr>
                                <td>${job.id}</td>
                                <td><span class="code">${escapeHtml(job.schedule)}</span></td>
                                <td><span class="code">${escapeHtml(job.command)}</span></td>
                                <td>
                                    <button class="btn btn-danger" onclick="deleteCronJob(${job.id})">削除</button>
                                </td>
                            </tr>
                        `;
                    });
                    
                    html += '</tbody></table>';
                    document.getElementById('cronJobsList').innerHTML = html;
                } else {
                    document.getElementById('cronJobsList').innerHTML = '<div class="empty-state">登録されているCronjobはありません</div>';
                }
            } catch (error) {
                console.error('Error loading cron jobs:', error);
                document.getElementById('cronJobsList').innerHTML = '<div class="empty-state">エラーが発生しました</div>';
            }
        }
        
        // Cronjobを追加
        async function addCronJob() {
            const schedule = document.getElementById('schedule').value.trim();
            const command = document.getElementById('command').value.trim();
            const messageDiv = document.getElementById('message');
            
            if (!schedule || !command) {
                showMessage('スケジュールとコマンドを入力してください', 'error');
                return;
            }
            
            try {
                const response = await fetch(`${API_URL}/cronjobs/add`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': token
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        schedule: schedule,
                        command: command
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showMessage('Cronjobを追加しました', 'success');
                    document.getElementById('schedule').value = '';
                    document.getElementById('command').value = '';
                    loadCronJobs();
                } else {
                    showMessage('エラー: ' + (data.error || '追加に失敗しました'), 'error');
                }
            } catch (error) {
                showMessage('エラー: ' + error.message, 'error');
            }
        }
        
        // Cronjobを削除
        async function deleteCronJob(id) {
            if (!confirm('このCronjobを削除しますか?')) {
                return;
            }
            
            try {
                const response = await fetch(`${API_URL}/cronjobs/delete`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': token
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        id: id
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showMessage('Cronjobを削除しました', 'success');
                    loadCronJobs();
                } else {
                    showMessage('エラー: ' + (data.error || '削除に失敗しました'), 'error');
                }
            } catch (error) {
                showMessage('エラー: ' + error.message, 'error');
            }
        }
        
        // メッセージを表示
        function showMessage(message, type) {
            const messageDiv = document.getElementById('message');
            messageDiv.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
            setTimeout(() => {
                messageDiv.innerHTML = '';
            }, 5000);
        }
        
        // HTMLエスケープ
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // セッション検証
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
        
        // 定期的にセッションを検証
        setInterval(validateSession, 60000);
    </script>
</body>
</html>
