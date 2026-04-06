# Next.js管理ツール

Next.jsプロジェクトを管理するためのWebベースツールです。フロントエンドはPHP、バックエンドはGoで構築されています。

## 機能

- 🔐 **認証システム**: ユーザー名とパスワードによるログイン（SHA-256ハッシュ化）
- 🚀 **Next.js管理**: npm run build, npm run start, npm run devコマンドの実行
- 🛑 **プロセス制御**: Next.jsプロセスの停止
- 📊 **リアルタイム出力**: コマンド実行結果の表示

## 必要要件

### バックエンド (Go)
- Go 1.21以降
- 必要なパッケージ:
  - github.com/google/uuid
  - github.com/rs/cors

### フロントエンド (PHP)
- PHP 7.4以降
- cURL拡張機能が有効
- PHPビルトインサーバーまたはApache/Nginx

### その他
- npm/Node.js（管理対象のNext.jsプロジェクト用）

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

### 3. デフォルト認証情報

- **ユーザー名**: `admin`
- **パスワード**: `admin`

## 起動方法

### 1. Goバックエンドの起動

```bash
cd backend
go run main.go
```

バックエンドは `http://localhost:8000` で起動します。

### 2. PHPフロントエンドの起動

別のターミナルで:

```bash
cd frontend
php -S localhost:8080
```

フロントエンドは `http://localhost:8080` で起動します。

### 3. アクセス

ブラウザで `http://localhost:8080/login.php` にアクセスしてください。

## 使い方

1. **環境変数の設定**
   - `.env`ファイルにNext.jsプロジェクトのパスを設定:
   ```
   NEXTJS_PROJECT_PATH=/path/to/your/nextjs/project
   ```

2. **ログイン**
   - ユーザー名: `admin`
   - パスワード: `admin`

3. **サービス選択**
   - ログイン後、サービス選択画面から「Next.js管理」をクリック

4. **コマンドの実行**
   - **Build**: Next.jsアプリケーションをビルド
   - **Start**: ビルド済みアプリケーションを本番モードで起動
   - **Dev**: 開発モードで起動
   - **Stop**: 実行中のNext.jsプロセスを停止

## API エンドポイント

### 認証
- `POST /api/login` - ログイン
- `POST /api/logout` - ログアウト
- `GET /api/validate` - セッション検証

### コマンド実行
- `POST /api/execute` - NPMコマンド実行

#### リクエスト例:
```json
{
  "command": "build",
  "path": "/path/to/nextjs/project"
}
```

#### レスポンス例:
```json
{
  "success": true,
  "output": "Build completed successfully"
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
