<?php
require_once 'config.php';

// すでにログインしている場合はサービス選択ページにリダイレクト
if (isset($_SESSION['token'])) {
    header('Location: services.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($username && $password) {
        $response = apiRequest('/login', 'POST', [
            'username' => $username,
            'password' => $password
        ]);
        
        if ($response['code'] === 200 && $response['data']['success']) {
            $_SESSION['token'] = $response['data']['token'];
            $_SESSION['username'] = $username;
            header('Location: services.php');
            exit;
        } else {
            $error = $response['data']['message'] ?? 'ログインに失敗しました';
        }
    } else {
        $error = 'ユーザー名とパスワードを入力してください';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン - AIMcommonsインフラ運用システム</title>
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
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: 1px solid #e0e0e0;
            width: 100%;
            max-width: 400px;
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
        }
        
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #5fb5a8;
        }
        
        .btn {
            width: 100%;
            padding: 12px;
            background: #5fb5a8;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn:hover {
            background: #4a9d8e;
            transform: translateY(-2px);
        }
        
        .error {
            background: #fee;
            color: #c33;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .info {
            background: #f3f4f6;
            color: #6b7280;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>AIMcommonsインフラ運用システム</h1>
        <p class="subtitle">ログインしてください</p>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">ユーザー名</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="password">パスワード</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn">ログイン</button>
        </form>
        

    </div>
</body>
</html>
