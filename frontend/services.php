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
    <title>サービス選択 - AIMcommonsシステム運用ポータル</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f0f0f0;
            min-height: 100vh;
        }
        
        .header {
            background: white;
            border-bottom: 4px solid #5fb5a8;
            padding: 15px 40px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logo {
            width: 50px;
            height: 50px;
            background: #5fb5a8;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 20px;
        }
        
        .site-title {
            font-size: 18px;
            color: #333;
        }
        
        .site-title .en {
            font-size: 14px;
            color: #666;
        }
        
        .user-section {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-icon {
            width: 24px;
            height: 24px;
            background: #5fb5a8;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
        }
        
        .username {
            color: #333;
            font-weight: 500;
        }
        
        .logout-btn {
            background: #e5e7eb;
            color: #333;
            padding: 8px 20px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
            transition: background 0.3s;
            cursor: pointer;
        }
        
        .logout-btn:hover {
            background: #d1d5db;
        }
        
        .tabs {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            background: white;
            margin-top: 20px;
            border-radius: 8px 8px 0 0;
            overflow: hidden;
        }
        
        .tab {
            flex: 1;
            padding: 20px;
            text-align: center;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            background: #e5e7eb;
            color: #6b7280;
            transition: all 0.3s;
        }
        
        .tab.active {
            background: #5fb5a8;
            color: white;
        }
        
        .tab:hover:not(.active) {
            background: #d1d5db;
        }
        
        .content {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            padding: 50px;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 40px;
            max-width: 900px;
        }
        
        .service-item {
            text-align: center;
            text-decoration: none;
            color: #333;
            transition: transform 0.3s;
        }
        
        .service-item:hover {
            transform: translateY(-5px);
        }
        
        .service-icon {
            width: 100px;
            height: 100px;
            margin: 0 auto 15px;
            border: 3px solid #333;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            font-weight: bold;
            color: #333;
            background: white;
            transition: all 0.3s;
        }
        
        .service-item:hover .service-icon {
            border-color: #5fb5a8;
            color: #5fb5a8;
        }
        
        .service-icon.shield {
            position: relative;
            border: none;
        }
        
        .service-icon.shield::before {
            content: '';
            position: absolute;
            width: 70px;
            height: 80px;
            border: 3px solid #5fb5a8;
            border-radius: 50% 50% 50% 50% / 60% 60% 40% 40%;
            background: #5fb5a8;
        }
        
        .service-icon.shield::after {
            content: 'S';
            position: relative;
            z-index: 1;
            color: white;
            font-size: 36px;
            font-weight: bold;
        }
        
        .service-name {
            font-size: 14px;
            font-weight: 500;
            margin-top: 10px;
        }
        
        .service-subtitle {
            font-size: 12px;
            color: #666;
            margin-top: 3px;
        }
        
        .account-section {
            max-width: 600px;
        }
        
        .account-info {
            background: #f9fafb;
            padding: 30px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }
        
        .info-row {
            display: flex;
            padding: 15px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            width: 150px;
            font-weight: 600;
            color: #374151;
        }
        
        .info-value {
            flex: 1;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo-section">
                <div class="logo">A</div>
                <div class="site-title">
                    <div>AIM Commonsインフラ運用システム</div>
                    <div class="en"></div>
                </div>
            </div>
            <div class="user-section">
                <div class="user-icon">👤</div>
                <span class="username"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a href="?logout" class="logout-btn">ログアウト / Logout</a>
            </div>
        </div>
    </div>
    
    <div class="tabs">
        <button class="tab active" onclick="switchTab('applications')">Applications</button>
        <button class="tab" onclick="switchTab('account')">Account Management</button>
    </div>
    
    <div class="content">
        <div id="applications" class="tab-content active">
            <div class="services-grid">
                <a href="dashboard.php" class="service-item">
                    <div class="service-icon shield"></div>
                    <div class="service-name">ウェブサイトの管理</div>
                    <div class="service-subtitle">記事の更新など</div>
                </a>
                
                <a href="cronjob.php" class="service-item">
                    <div class="service-icon">⏰</div>
                    <div class="service-name">Cronjob管理</div>
                    <div class="service-subtitle">定期実行タスクの管理</div>
                </a>
                
                <a href="cleanup.php" class="service-item">
                    <div class="service-icon">🗑️</div>
                    <div class="service-name">ファイルクリーンアップ</div>
                    <div class="service-subtitle">古いファイル・ログの削除</div>
                </a>
                
                <div class="service-item" style="opacity: 0.5; cursor: not-allowed;">
                    <div class="service-icon"></div>
                    <div class="service-name">インスタ自動投稿管理</div>
                    <div class="service-subtitle"></div>
                </div>
                <br>

                 <div class="service-item" style="opacity: 0.5; cursor: not-allowed;">
                    <div class="service-icon">📚</div>
                    <div class="service-name">ドキュメント</div>
                    <div class="service-subtitle">(Docs)</div>
                </div>
                <div class="service-item" style="opacity: 0.5; cursor: not-allowed;">
                    <div class="service-icon"></div>
                    <div class="service-name">設定</div>
                    <div class="service-subtitle">(Settings)</div>
                </div>
                
                <div class="service-item" style="opacity: 0.5; cursor: not-allowed;">
                    <div class="service-icon"></div>
                    <div class="service-name">ログ</div>
                    <div class="service-subtitle">(Logs)</div>
                </div>
                

            </div>
        </div>
        
        <div id="account" class="tab-content">
            <div class="account-section">
                <h2 style="margin-bottom: 20px; color: #333;">アカウント情報</h2>
                <div class="account-info">
                    <div class="info-row">
                        <div class="info-label">ユーザー名</div>
                        <div class="info-value"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">セッション状態</div>
                        <div class="info-value">アクティブ</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">ログイン時刻</div>
                        <div class="info-value"><?php echo date('Y年m月d日 H:i:s'); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">権限</div>
                        <div class="info-value">管理者</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        const API_URL = 'http://localhost:8000/api';
        const token = '<?php echo $_SESSION['token']; ?>';
        
        function switchTab(tabName) {
            // タブボタンの切り替え
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // コンテンツの切り替え
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.getElementById(tabName).classList.add('active');
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
