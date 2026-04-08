#!/bin/bash

# Next.js Manager デプロイスクリプト

set -e

echo "🚀 デプロイ開始..."

# プロジェクトディレクトリ
PROJECT_DIR="/home/sysmanager/go_nextjs_moniter"
SERVICE_NAME="nextjs-manager"

# Gitから最新版を取得
echo "📥 最新版を取得中..."
cd $PROJECT_DIR
git pull

# Goのモジュールを更新
echo "📦 依存関係を更新中..."
cd $PROJECT_DIR/backend
go mod tidy

# バイナリをビルド（本番環境用）
echo "🔨 バックエンドをビルド中..."
go build -o nextjs-manager main.go

# systemdサービスファイルをコピー（初回のみ）
if [ ! -f /etc/systemd/system/$SERVICE_NAME.service ]; then
    echo "📋 systemdサービスを登録中..."
    sudo cp $PROJECT_DIR/nextjs-manager.service /etc/systemd/system/
    sudo systemctl daemon-reload
    sudo systemctl enable $SERVICE_NAME
fi

# サービスを再起動
echo "🔄 サービスを再起動中..."
sudo systemctl restart $SERVICE_NAME

# 状態確認
echo "✅ サービス状態:"
sudo systemctl status $SERVICE_NAME --no-pager

echo "✨ デプロイ完了！"
echo ""
echo "📊 ログ確認: sudo journalctl -u $SERVICE_NAME -f"
echo "🔄 再起動: sudo systemctl restart $SERVICE_NAME"
echo "🛑 停止: sudo systemctl stop $SERVICE_NAME"
