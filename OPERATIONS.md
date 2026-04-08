# Next.js Manager 運用マニュアル

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

# フロントエンド
sudo systemctl status nextjs-manager-frontend

# 両方まとめて
sudo systemctl status nextjs-manager-backend nextjs-manager-frontend
```

## 🔄 サービス操作

### 起動
```bash
# 全体
sudo systemctl start nextjs-manager.target

# 個別
sudo systemctl start nextjs-manager-backend
sudo systemctl start nextjs-manager-frontend
```

### 停止
```bash
# 全体
sudo systemctl stop nextjs-manager.target

# 個別
sudo systemctl stop nextjs-manager-backend
sudo systemctl stop nextjs-manager-frontend
```

### 再起動
```bash
# 全体
sudo systemctl restart nextjs-manager.target

# 個別
sudo systemctl restart nextjs-manager-backend
sudo systemctl restart nextjs-manager-frontend
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

## 🌐 アクセスURL

- **フロントエンド**: http://サーバーIP:8080
- **バックエンドAPI**: http://サーバーIP:8070
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
