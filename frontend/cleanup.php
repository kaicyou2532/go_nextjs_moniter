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
    <title>ファイルクリーンアップ - Next.js管理ツール</title>
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

        .logout-form {
            margin: 0;
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
            margin-bottom: 5px;
            color: #555;
            font-weight: 500;
            font-size: 14px;
        }
        
        input[type="text"],
        input[type="number"] {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 14px;
        }
        
        input[type="text"]:focus,
        input[type="number"]:focus {
            outline: none;
            border-color: #5fb5a8;
        }
        
        .help-text {
            font-size: 12px;
            color: #6b7280;
            margin-top: 5px;
        }
        
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
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
        
        .btn-secondary {
            background: #9ca3af;
            color: white;
        }
        
        .btn-danger {
            background: #6b7280;
            color: white;
        }
        
        .btn:hover {
            opacity: 0.9;
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
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
        
        .alert-info {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .preview-section {
            margin-top: 30px;
            display: none;
        }
        
        .preview-section.show {
            display: block;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        th, td {
            padding: 10px;
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
            font-size: 13px;
        }
        
        .file-path {
            font-family: 'Courier New', monospace;
            font-size: 12px;
        }
        
        .file-size {
            text-align: right;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: #f9fafb;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }
        
        .stat-label {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 5px;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #333;
        }
        
        .loading {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s linear infinite;
            margin-right: 8px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .templates {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .template-card {
            background: #f9fafb;
            padding: 15px;
            border-radius: 8px;
            border: 2px solid #e5e7eb;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .template-card:hover {
            border-color: #5fb5a8;
            background: white;
        }
        
        .template-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .template-desc {
            font-size: 12px;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div>
                <a href="services.php" style="color: #5fb5a8; text-decoration: none; font-size: 14px; margin-right: 20px;">← サービス一覧に戻る</a>
                <span style="font-size: 24px; font-weight: bold;">ファイルクリーンアップ</span>
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
            <h2>よく使うテンプレート</h2>
            <div class="templates">
                <div class="template-card" onclick="applyTemplate('/var/log', '*.log', 30)">
                    <div class="template-title">📝 ログファイル</div>
                    <div class="template-desc">/var/log の30日以上前の.logファイル</div>
                </div>
                <div class="template-card" onclick="applyTemplate('/tmp', '*', 7)">
                    <div class="template-title">🗑️ 一時ファイル</div>
                    <div class="template-desc">/tmp の7日以上前のファイル</div>
                </div>
                <div class="template-card" onclick="applyTemplate('/home', '*.tmp', 14)">
                    <div class="template-title">📁 .tmpファイル</div>
                    <div class="template-desc">/home の14日以上前の.tmpファイル</div>
                </div>
                <div class="template-card" onclick="applyTemplate('/var/cache', '*', 60)">
                    <div class="template-title">💾 キャッシュ</div>
                    <div class="template-desc">/var/cache の60日以上前のファイル</div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <h2>クリーンアップ設定</h2>
            
            <div id="message"></div>
            
            <div class="form-group">
                <label for="path">対象ディレクトリ (絶対パス)</label>
                <input type="text" id="path" placeholder="例: /var/log">
                <div class="help-text">
                    削除対象ファイルがあるディレクトリの絶対パスを入力
                </div>
            </div>
            
            <div class="form-group">
                <label for="pattern">ファイルパターン</label>
                <input type="text" id="pattern" placeholder="例: *.log または * (全て)">
                <div class="help-text">
                    削除対象のファイル名パターン（* = すべて、*.log = .logファイルのみ）
                </div>
            </div>
            
            <div class="form-group">
                <label for="days">保持日数</label>
                <input type="number" id="days" placeholder="例: 30" value="30" min="1">
                <div class="help-text">
                    この日数より古いファイルを削除対象とします
                </div>
            </div>
            
            <div class="button-group">
                <button class="btn btn-secondary" onclick="previewCleanup()">
                    プレビュー
                </button>
                <button class="btn btn-danger" onclick="executeCleanup()" id="executeBtn" disabled>
                    実行
                </button>
            </div>
        </div>
        
        <div class="card preview-section" id="previewSection">
            <h2>削除予定ファイル</h2>
            
            <div class="stats" id="stats" style="display: none;">
                <div class="stat-card">
                    <div class="stat-label">対象ファイル数</div>
                    <div class="stat-value" id="fileCount">0</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">合計サイズ</div>
                    <div class="stat-value" id="totalSize">0 KB</div>
                </div>
            </div>
            
            <div id="previewList"></div>
        </div>
    </div>
    
    <script>
        const API_URL = '<?php echo API_URL; ?>';
        const token = '<?php echo $_SESSION['token']; ?>';
        
        let previewData = null;
        
        function applyTemplate(path, pattern, days) {
            document.getElementById('path').value = path;
            document.getElementById('pattern').value = pattern;
            document.getElementById('days').value = days;
        }
        
        async function previewCleanup() {
            const path = document.getElementById('path').value.trim();
            const pattern = document.getElementById('pattern').value.trim();
            const days = parseInt(document.getElementById('days').value);
            
            if (!path || !days) {
                showMessage('パス and 保持日数を入力してください', 'error');
                return;
            }
            
            try {
                const response = await fetch(`${API_URL}/cleanup`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': token
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        path: path,
                        pattern: pattern,
                        days: days,
                        preview: true
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    previewData = data;
                    displayPreview(data);
                    document.getElementById('executeBtn').disabled = false;
                    showMessage(`${data.count}件のファイルが見つかりました`, 'info');
                } else {
                    showMessage('エラー: ' + (data.error || 'プレビューに失敗しました'), 'error');
                }
            } catch (error) {
                showMessage('エラー: ' + error.message, 'error');
            }
        }
        
        async function executeCleanup() {
            if (!previewData || !confirm('本当に削除しますか？この操作は取り消せません。')) {
                return;
            }
            
            const path = document.getElementById('path').value.trim();
            const pattern = document.getElementById('pattern').value.trim();
            const days = parseInt(document.getElementById('days').value);
            
            try {
                const response = await fetch(`${API_URL}/cleanup`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': token
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        path: path,
                        pattern: pattern,
                        days: days,
                        preview: false
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showMessage(`${data.deleted}件のファイルを削除しました`, 'success');
                    document.getElementById('previewSection').classList.remove('show');
                    document.getElementById('executeBtn').disabled = true;
                    previewData = null;
                } else {
                    showMessage('エラー: ' + (data.error || '削除に失敗しました'), 'error');
                }
            } catch (error) {
                showMessage('エラー: ' + error.message, 'error');
            }
        }
        
        function displayPreview(data) {
            const previewSection = document.getElementById('previewSection');
            const stats = document.getElementById('stats');
            const previewList = document.getElementById('previewList');
            
            previewSection.classList.add('show');
            stats.style.display = 'grid';
            
            // 統計情報
            document.getElementById('fileCount').textContent = data.count;
            const totalSize = data.files.reduce((sum, file) => sum + file.size, 0);
            document.getElementById('totalSize').textContent = formatBytes(totalSize);
            
            // ファイル一覧
            if (data.files && data.files.length > 0) {
                let html = '<table><thead><tr><th>ファイル名</th><th>パス</th><th>サイズ</th><th>更新日時</th></tr></thead><tbody>';
                
                data.files.forEach(file => {
                    html += `
                        <tr>
                            <td>${escapeHtml(file.name)}</td>
                            <td class="file-path">${escapeHtml(file.path)}</td>
                            <td class="file-size">${formatBytes(file.size)}</td>
                            <td>${escapeHtml(file.mod_time)}</td>
                        </tr>
                    `;
                });
                
                html += '</tbody></table>';
                previewList.innerHTML = html;
            } else {
                previewList.innerHTML = '<div style="text-align: center; padding: 20px; color: #9ca3af;">削除対象のファイルはありません</div>';
            }
        }
        
        function formatBytes(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }
        
        function showMessage(message, type) {
            const messageDiv = document.getElementById('message');
            messageDiv.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
            setTimeout(() => {
                messageDiv.innerHTML = '';
            }, 5000);
        }
        
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
        
        setInterval(validateSession, 60000);
    </script>
</body>
</html>
