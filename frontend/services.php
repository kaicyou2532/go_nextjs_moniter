<?php
require_once 'config.php';
requireLogin();
handleLogoutRequest();

$accountError = '';
$accountSuccess = '';
$initialTab = 'applications';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_admin') {
    verifyCsrfToken();

    $username = trim($_POST['new_username'] ?? '');
    $password = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    $response = apiRequest('/admin/users', 'POST', [
        'username' => $username,
        'password' => $password,
        'confirm_password' => $confirmPassword,
    ]);

    if ($response['code'] === 201 && !empty($response['data']['success'])) {
        $createdUsername = $response['data']['user']['username'] ?? $username;
        $accountSuccess = '管理者アカウントを作成しました: ' . $createdUsername;
        $initialTab = 'account';
    } else {
        $accountError = $response['data']['error'] ?? '管理者アカウントの作成に失敗しました';
        $initialTab = 'account';
    }
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

        .logout-form {
            margin: 0;
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

        .account-panel {
            margin-top: 24px;
            background: #ffffff;
            padding: 30px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }

        .section-title {
            margin-bottom: 16px;
            color: #333;
            font-size: 20px;
        }

        .form-grid {
            display: grid;
            gap: 16px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #374151;
            font-weight: 600;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
        }

        .form-group input:focus {
            outline: none;
            border-color: #5fb5a8;
            box-shadow: 0 0 0 3px rgba(95, 181, 168, 0.15);
        }

        .primary-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            background: #5fb5a8;
            color: white;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }

        .primary-btn:hover {
            background: #4a9d8e;
        }

        .message {
            margin-bottom: 16px;
            padding: 12px 14px;
            border-radius: 6px;
            font-size: 14px;
        }

        .message.success {
            background: #ecfdf5;
            color: #166534;
            border: 1px solid #a7f3d0;
        }

        .message.error {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .form-note {
            margin-top: 12px;
            color: #6b7280;
            font-size: 13px;
            line-height: 1.6;
        }

        @media (max-width: 768px) {
            .header,
            .content {
                padding: 20px;
            }

            .header-content,
            .user-section {
                flex-direction: column;
                align-items: flex-start;
            }

            .tabs {
                flex-direction: column;
            }
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
                <form method="POST" class="logout-form">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="logout-btn">ログアウト / Logout</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="tabs">
        <button class="tab <?php echo $initialTab === 'applications' ? 'active' : ''; ?>" onclick="switchTab(this, 'applications')">Applications</button>
        <button class="tab <?php echo $initialTab === 'account' ? 'active' : ''; ?>" onclick="switchTab(this, 'account')">Account Management</button>
    </div>
    
    <div class="content">
        <div id="applications" class="tab-content <?php echo $initialTab === 'applications' ? 'active' : ''; ?>">
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

                <a href="gitpull.php" class="service-item">
                    <div class="service-icon">⇅</div>
                    <div class="service-name">Git Pull 管理</div>
                    <div class="service-subtitle">Next.js 更新の取得</div>
                </a>

                <a href="<?php echo htmlspecialchars(API_URL . '/ok'); ?>" class="service-item" target="_blank" rel="noopener noreferrer">
                    <div class="service-icon">OK</div>
                    <div class="service-name">API疎通確認</div>
                    <div class="service-subtitle">200 OK を表示</div>
                </a>
                
                <div class="service-item" style="opacity: 0.5; cursor: not-allowed;">
                    <div class="service-icon"></div>
                    <div class="service-name">インスタ自動投稿管理</div>
                    <div class="service-subtitle"></div>
                </div>

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
        
        <div id="account" class="tab-content <?php echo $initialTab === 'account' ? 'active' : ''; ?>">
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

                <div class="account-panel">
                    <h3 class="section-title">管理者アカウントを追加</h3>

                    <?php if ($accountSuccess): ?>
                        <div class="message success"><?php echo htmlspecialchars($accountSuccess); ?></div>
                    <?php endif; ?>

                    <?php if ($accountError): ?>
                        <div class="message error"><?php echo htmlspecialchars($accountError); ?></div>
                    <?php endif; ?>

                    <form method="POST" class="form-grid" autocomplete="off">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="create_admin">

                        <div class="form-group">
                            <label for="new_username">新しい管理者ユーザー名</label>
                            <input type="text" id="new_username" name="new_username" required minlength="3" maxlength="64">
                        </div>

                        <div class="form-group">
                            <label for="new_password">パスワード</label>
                            <input type="password" id="new_password" name="new_password" required minlength="12">
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">パスワード確認</label>
                            <input type="password" id="confirm_password" name="confirm_password" required minlength="12">
                        </div>

                        <button type="submit" class="primary-btn">管理者を作成</button>
                    </form>

                    <p class="form-note">12文字以上で、大文字・小文字・数字を含むパスワードを設定してください。</p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        const API_URL = '<?php echo API_URL; ?>';
        const token = '<?php echo $_SESSION['token']; ?>';
        
        function switchTab(button, tabName) {
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            button.classList.add('active');
            
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
        setInterval(validateSession, 60000); // 1分ごと
    </script>
</body>
</html>
