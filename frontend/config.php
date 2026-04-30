<?php
// .envファイルを読み込み
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (!getenv($key)) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

// API設定 - 動的にホストを取得
$apiHost = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
$apiHost = preg_replace('/:\d+$/', '', $apiHost); // ポート番号を削除
$apiUrl = 'http://' . $apiHost . ':8070/api';
define('API_URL', $apiUrl);

// セッションディレクトリを設定
$sessionDir = __DIR__ . '/sessions';
if (!file_exists($sessionDir)) {
    mkdir($sessionDir, 0700, true);
}

// セッション設定
ini_set('session.save_path', $sessionDir);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? '1' : '0');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', 1);

session_start();

// CSRF対策トークン生成
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function verifyCsrfToken(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        exit('不正なリクエストです');
    }
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') . '">';
}

function apiRequest($endpoint, $method = 'GET', $data = null) {
    $url = API_URL . $endpoint;
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    $headers = ['Content-Type: application/json'];
    
    if (isset($_SESSION['token'])) {
        $headers[] = 'Authorization: ' . $_SESSION['token'];
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'data' => json_decode($response, true)
    ];
}

function requireLogin() {
    if (!isset($_SESSION['token']) || !isset($_SESSION['username'])) {
        header('Location: login.php');
        exit;
    }
}

function logoutUser(): void {
    apiRequest('/logout', 'POST');
    $_SESSION = [];

    if (session_id() !== '' || isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }

    session_destroy();
    header('Location: login.php');
    exit;
}

function handleLogoutRequest(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logout') {
        verifyCsrfToken();
        logoutUser();
    }
}
?>
