# Next.js Manager 運用マニュアル

## ⚙️ 初回セットアップ（必須）

デプロイ前に一度だけ実行してください：

```bash
cd ~/go_nextjs_moniter

# sudoers設定を追加（パスワードなしsudo実行のため）
sudo visudo -f /etc/sudoers.d/nextjs-manager
```

以下を追加して保存（Ctrl+X → Y → Enter）：
```
sysmanager ALL=(ALL) NOPASSWD: /usr/bin/systemctl start nginx
sysmanager ALL=(ALL) NOPASSWD: /usr/bin/systemctl stop nginx
sysmanager ALL=(ALL) NOPASSWD: /usr/bin/systemctl reload nginx
sysmanager ALL=(ALL) NOPASSWD: /usr/bin/systemctl status nginx
sysmanager ALL=(ALL) NOPASSWD: /usr/bin/systemctl start nextjs-app
sysmanager ALL=(ALL) NOPASSWD: /usr/bin/systemctl stop nextjs-app
sysmanager ALL=(ALL) NOPASSWD: /usr/bin/systemctl restart nextjs-app
sysmanager ALL=(ALL) NOPASSWD: /usr/bin/systemctl status nextjs-app
sysmanager ALL=(ALL) NOPASSWD: /usr/bin/systemctl is-active nextjs-app
sysmanager ALL=(ALL) NOPASSWD: /usr/bin/journalctl *
sysmanager ALL=(ALL) NOPASSWD: /usr/bin/tail *
sysmanager ALL=(ALL) NOPASSWD: /usr/bin/pkill *
sysmanager ALL=(ALL) NOPASSWD: /bin/rm -rf /home/sysmanager/next-website/.next
```

権限設定：
```bash
# セッションディレクトリの権限修正
chmod 770 ~/go_nextjs_moniter/frontend/sessions
```

## 🚀 デプロイ

```bash
cd ~/go_nextjs_moniter
git pull
chmod +x deploy.sh
./deploy.sh
```

## 📊 サービス状態確認

### 全体の状態確認
```bash
sudo systemctl status nextjs-manager.target
```

### 個別サービスの状態確認
```bash
# バックエンド
sudo systemctl status nextjs-manager-backend

# フロントエンド（管理画面）
sudo systemctl status nextjs-manager-frontend

# Next.jsアプリ
sudo systemctl status nextjs-app

# まとめて確認
sudo systemctl status nextjs-manager-backend nextjs-manager-frontend nextjs-app
```

## 🔄 サービス操作

### 起動
```bash
# 全体
sudo systemctl start nextjs-manager.target nextjs-app

# 個別
sudo systemctl start nextjs-manager-backend
sudo systemctl start nextjs-manager-frontend
sudo systemctl start nextjs-app
```

### 停止
```bash
# 全体
sudo systemctl stop nextjs-manager.target nextjs-app

# 個別
sudo systemctl stop nextjs-manager-backend
sudo systemctl stop nextjs-manager-frontend
sudo systemctl stop nextjs-app
```

### 再起動
```bash
# 全体
sudo systemctl restart nextjs-manager.target nextjs-app

### バックエンドのヘルスチェック（/health）
```bash
curl -i http://サーバーIP:8070/health
```
※サーバーIPは環境に合わせて置き換えてください。

# 個別
sudo systemctl restart nextjs-manager-backend
sudo systemctl restart nextjs-manager-frontend
sudo systemctl restart nextjs-app
```

## 📝 ログ確認

### リアルタイムログ表示
```bash
# バックエンドのログ
sudo journalctl -u nextjs-manager-backend -f

# フロントエンドのログ
sudo journalctl -u nextjs-manager-frontend -f

# 両方のログを同時表示
sudo journalctl -u nextjs-manager-backend -u nextjs-manager-frontend -f
```

### 過去のログ表示
```bash
# 最新100行
sudo journalctl -u nextjs-manager-backend -n 100

# 最近1時間
sudo journalctl -u nextjs-manager-backend --since "1 hour ago"

# 今日のログ
sudo journalctl -u nextjs-manager-backend --since today

# 特定期間
sudo journalctl -u nextjs-manager-backend --since "2026-04-08 00:00:00" --until "2026-04-08 23:59:59"
```

## 🐛 トラブルシューティング

### ログインできない場合

1. **バックエンドが起動しているか確認**
```bash
sudo systemctl status nextjs-manager-backend
```

2. **ポートが使用中か確認**
```bash
# バックエンド (8070)
sudo lsof -i :8070

# フロントエンド (8080)
sudo lsof -i :8080
```

3. **プロセスを強制終了**
```bash
# ポート8070を使用しているプロセスを終了
sudo lsof -ti:8070 | xargs kill -9

# ポート8080を使用しているプロセスを終了
sudo lsof -ti:8080 | xargs kill -9

# その後サービスを再起動
sudo systemctl restart nextjs-manager.target
```

4. **セッションファイルをクリア**
```bash
rm -rf ~/go_nextjs_moniter/frontend/sessions/*
```

5. **ログでエラーを確認**
```bash
sudo journalctl -u nextjs-manager-backend -n 50
sudo journalctl -u nextjs-manager-frontend -n 50
```

### サービスが起動しない場合

1. **設定ファイルを確認**
```bash
# systemdの設定を再読み込み
sudo systemctl daemon-reload

# サービスを有効化
sudo systemctl enable nextjs-manager-backend
sudo systemctl enable nextjs-manager-frontend
sudo systemctl enable nextjs-manager.target
```

2. **手動でバックエンドを起動してエラー確認**
```bash
cd ~/go_nextjs_moniter/backend
./nextjs-manager
```

3. **環境変数を確認**
```bash
cat ~/go_nextjs_moniter/backend/.env
```

### Next.jsプロジェクトが見つからないエラー

```bash
# パスを確認
ls -la /home/sysmanager/next-website

# 環境変数を確認
cat ~/go_nextjs_moniter/backend/.env

# 正しいパスに修正
nano ~/go_nextjs_moniter/backend/.env
# NEXTJS_PROJECT_PATH=/home/sysmanager/next-website

# サービス再起動
sudo systemctl restart nextjs-manager-backend
```

## 🔍 監視機能

### 自動監視（5分ごと）

システムは以下を自動的に監視します：
- サービスの死活監視（停止時は自動再起動）
- ディスク使用率監視（80%超過時に自動クリーンアップ）
- メモリ使用状況の記録

```bash
# 監視タイマーの状態確認
sudo systemctl status nextjs-manager-monitor.timer

# 監視ログの確認
sudo tail -f /var/log/nextjs-manager-monitor.log

# 手動で監視を実行
sudo systemctl start nextjs-manager-monitor.service
```

### 監視タイマーの操作

```bash
# 起動
sudo systemctl start nextjs-manager-monitor.timer

# 停止
sudo systemctl stop nextjs-manager-monitor.timer

# 有効化（サーバー起動時に自動開始）
sudo systemctl enable nextjs-manager-monitor.timer
```

## 🌐 アクセスURL

- **管理画面**: http://サーバーIP:8080
- **バックエンドAPI**: http://サーバーIP:8070
- **Next.jsアプリ**: http://サーバーIP:3000
- **Next.jsプロジェクト**: /home/sysmanager/next-website

## 🔑 デフォルトログイン情報

- **ユーザー名**: admin
- **パスワード**: admin

## 📁 重要なファイル・ディレクトリ

```
~/go_nextjs_moniter/
├── backend/
│   ├── main.go                 # バックエンドソースコード
│   ├── nextjs-manager          # ビルド済みバイナリ
│   └── .env                    # バックエンド環境変数
├── frontend/
│   ├── *.php                   # フロントエンドPHPファイル
│   ├── sessions/               # セッションデータ
│   └── .env                    # フロントエンド環境変数
├── deploy.sh                   # デプロイスクリプト
├── nextjs-manager-backend.service
├── nextjs-manager-frontend.service
└── nextjs-manager.target

/etc/systemd/system/
├── nextjs-manager-backend.service
├── nextjs-manager-frontend.service
└── nextjs-manager.target
```

## 🔧 メンテナンス

### バックエンドを手動でビルド
```bash
cd ~/go_nextjs_moniter/backend
go build -o nextjs-manager main.go
sudo systemctl restart nextjs-manager-backend
```

### systemd設定を更新
```bash
# 設定ファイルをコピー
sudo cp ~/go_nextjs_moniter/nextjs-manager-backend.service /etc/systemd/system/
sudo cp ~/go_nextjs_moniter/nextjs-manager-frontend.service /etc/systemd/system/
sudo cp ~/go_nextjs_moniter/nextjs-manager.target /etc/systemd/system/

# 再読み込み
sudo systemctl daemon-reload

# サービス再起動
sudo systemctl restart nextjs-manager.target
```

### サーバー再起動後の自動起動確認
```bash
# 有効化されているか確認
sudo systemctl is-enabled nextjs-manager-backend
sudo systemctl is-enabled nextjs-manager-frontend

# 有効化
sudo systemctl enable nextjs-manager-backend
sudo systemctl enable nextjs-manager-frontend
sudo systemctl enable nextjs-manager.target
```

## 📈 モニタリング

### リソース使用状況
```bash
# CPU・メモリ使用率
systemctl status nextjs-manager-backend | grep -E "Memory|CPU"

# プロセス詳細
ps aux | grep nextjs-manager
```

### ネットワーク確認
```bash
# リッスンしているポートを確認
sudo netstat -tlnp | grep -E "8070|8080"

# または
sudo ss -tlnp | grep -E "8070|8080"
```

## 🔒 セキュリティ

### ファイアウォール設定（必要に応じて）
```bash
# ポートを開放
sudo ufw allow 8070/tcp
sudo ufw allow 8080/tcp

# 状態確認
sudo ufw status
```

### パスワード変更
現在、パスワードはソースコードに埋め込まれています。変更する場合は:
1. `backend/main.go` を編集
2. 再ビルド: `cd ~/go_nextjs_moniter/backend && go build -o nextjs-manager main.go`
3. 再起動: `sudo systemctl restart nextjs-manager-backend`
