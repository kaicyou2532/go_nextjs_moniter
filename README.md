# Nginx管理ツール

Nginxを管理するためのWebベースツールです。フロントエンドはPHP、バックエンドはGoで構築されています。

## 機能

- 🔐 **認証システム**: ユーザー名とパスワードによるログイン（SHA-256ハッシュ化）
- 🚀 **Nginx管理**: Nginxの起動、停止、リロード、ステータス確認
- 📋 **リアルタイムログ**: Nginxのエラーログ、アクセスログ、systemdジャーナルの表示
- ⏰ **Cronjob管理**: Crontabの追加、削除、一覧表示
- 🗑️ **ファイルクリーンアップ**: 古いログやキャッシュファイルの削除
- 🛑 **プロセス制御**: Nginxプロセスの管理
- 📊 **リアルタイム出力**: コマンド実行結果の表示
- 🔄 **Next.jsステータス**: プロセスの起動状態を自動監視

## 必要要件

### Docker環境（推奨）
- Docker 20.10以降
- Docker Compose 2.0以降

### または通常環境

#### バックエンド (Go)
- Go 1.21以降
- 必要なパッケージ:
  - github.com/google/uuid
  - github.com/rs/cors
  - github.com/joho/godotenv

#### フロントエンド (PHP)
- PHP 7.4以降
- cURL拡張機能が有効
- PHPビルトインサーバーまたはApache/Nginx

#### その他
- npm/Node.js（管理対象のNext.jsプロジェクト用）

## インストール

### Docker Composeで起動（推奨）

```bash
# リポジトリのクローン
git clone <repository-url>
cd go_nextjs_moniter

# 環境変数の設定
cd backend
cp .env.example .env
# .envファイルを編集してNEXTJS_PROJECT_PATHを設定
cd ..

# Next.jsプロジェクトをworkspaceディレクトリに配置
# または、シンボリックリンクを作成
ln -s /path/to/your/nextjs/project workspace

# Docker Composeで起動
chmod +x docker-start.sh
./docker-start.sh
```

### 通常起動

## インストール

### 1. リポジトリのクローン

```bash
cd /Users/nakamurakiichi/go_nextjs_moniter
```

### 2. 環境変数の設定

**バックエンド（Go）**
```bash
cd backend
cp .env.example .env
# .envファイルを編集してNEXTJS_PROJECT_PATHを設定
```

**フロントエンド（PHP）**
```bash
cd frontend
cp ..backend/.env .env
# または直接編集
```

`.env`ファイルの内容:
```
PORT=8000
NEXTJS_PROJECT_PATH=/path/to/your/nextjs/project
```

### 3. Goバックエンドのセットアップ

```bash
cd backend
go mod download
```

### 3. sudoコマンドの設定

Nginxの管理にはroot権限が必要です。パスワードなしでsudoを実行できるようにします：

```bash
sudo visudo
```

以下の行を最後に追加（`sysmanager`はあなたのユーザー名に置き換えてください）：

```
sysmanager ALL=(ALL) NOPASSWD: /usr/bin/systemctl start nginx
sysmanager ALL=(ALL) NOPASSWD: /usr/bin/systemctl stop nginx
sysmanager ALL=(ALL) NOPASSWD: /usr/bin/systemctl reload nginx
sysmanager ALL=(ALL) NOPASSWD: /usr/bin/systemctl status nginx
sysmanager ALL=(ALL) NOPASSWD: /usr/bin/tail
sysmanager ALL=(ALL) NOPASSWD: /usr/bin/journalctl
```

## 起動方法

### 簡単な起動（推奨）

プロジェクトルートで：

```bash
./start.sh
```

### 手動起動

**1. Goバックエンドの起動**

```bash
cd backend
go run main.go
```

バックエンドは `http://0.0.0.0:8000` で起動します。

### 2. PHPフロントエンドの起動

別のターミナルで:

```bash
cd frontend
php -S 0.0.0.0:8080
```

フロントエンドは `http://0.0.0.0:8080` で起動します。

### 3. アクセス

ブラウザで `http://your-server-ip:8080/login.php` にアクセスしてください。

### デフォルト認証情報

- **ユーザー名**: `admin`
- **パスワード**: `admin`

## 使い方

1. **ログイン**
   - ユーザー名: `admin`
   - パスワード: `admin`

2. **サービス選択**
   - ログイン後、サービス選択画面から機能を選択
   - **Nginx管理**: Nginxの起動・停止・リロード
   - **Cronjob管理**: Crontabの管理
   - **ファイルクリーンアップ**: 古いファイルの削除

3. **Nginx管理**
   - **Start**: Nginxを起動
   - **Stop**: Nginxを停止
   - **Reload**: 設定ファイルを再読み込み
   - **Status**: Nginxの状態を確認
   - **ログビューアー**: リアルタイムでログを確認

## API エンドポイント

### 認証
- `POST /api/login` - ログイン
- `POST /api/logout` - ログアウト
- `GET /api/validate` - セッション検証

### Nginx管理
- `POST /api/execute` - Nginxコマンド実行
- `GET /api/logs` - ログ取得

### Cronjob管理
- `GET /api/cronjobs` - Cronjob一覧取得
- `POST /api/cronjobs/add` - Cronjob追加
- `POST /api/cronjobs/delete` - Cronjob削除

### ファイルクリーンアップ
- `POST /api/cleanup` - ファイルクリーンアップ実行

#### リクエスト例（Nginx管理）:
```json
{
  "command": "start"
}
```

#### レスポンス例:
```json
{
  "success": true,
  "output": "nginx started successfully"
}
```

## セキュリティ

- パスワードはSHA-256でハッシュ化
- セッショントークンはUUID v4
- セッション有効期限: 24時間
- CORS設定済み
- CSRF対策実装

## カスタマイズ

### ユーザーの追加

`backend/main.go` の `init()` 関数でユーザーを追加:

```go
func init() {
    passwordHash := hashPassword("yourpassword")
    users["yourusername"] = User{
        Username:     "yourusername",
        PasswordHash: passwordHash,
    }
}
```

### ポート変更

**バックエンド**:
```bash
PORT=9000 go run main.go
```

**フロントエンド**:
```bash
php -S localhost:9090
```

`frontend/config.php` でAPIのURLも更新してください:
```php
define('API_URL', 'http://localhost:9000/api');
```

## プロジェクト構造

```
go_nextjs_moniter/
├── backend/
│   ├── main.go          # Goバックエンドサーバー
│   └── go.mod           # Go依存関係
├── frontend/
│   ├── config.php       # PHP設定
│   ├── login.php        # ログインページ
│   └── dashboard.php    # メインダッシュボード
└── README.md            # このファイル
```

## トラブルシューティング

### ポートが使用中の場合
別のポートを使用するか、既存のプロセスを停止してください。

### CORSエラーが発生する場合
`backend/main.go` の `AllowedOrigins` にフロントエンドのURLが含まれているか確認してください。

### コマンドが実行されない場合
- プロジェクトパスが正しいか確認
- npmがインストールされているか確認
- package.jsonに対応するスクリプトが存在するか確認

## ライセンス

MIT License

## 開発者

このツールは、Next.jsプロジェクトの管理を簡単にするために開発されました。
