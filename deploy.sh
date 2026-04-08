#!/bin/bash

# Next.js Manager デプロイスクリプト

set -e

echo "🚀 デプロイ開始..."

# プロジェクトディレクトリ
PROJECT_DIR="/home/sysmanager/go_nextjs_moniter"
BACKEND_SERVICE="nextjs-manager-backend"
FRONTEND_SERVICE="nextjs-manager-frontend"
TARGET_SERVICE="nextjs-manager.target"

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
if [ ! -f /etc/systemd/system/$BACKEND_SERVICE.service ]; then
    echo "📋 バックエンドサービスを登録中..."
    sudo cp $PROJECT_DIR/nextjs-manager-backend.service /etc/systemd/system/
fi

if [ ! -f /etc/systemd/system/$FRONTEND_SERVICE.service ]; then
    echo "📋 フロントエンドサービスを登録中..."
    sudo cp $PROJECT_DIR/nextjs-manager-frontend.service /etc/systemd/system/
fi

if [ ! -f /etc/systemd/system/$TARGET_SERVICE ]; then
    echo "📋 targetを登録中..."
    sudo cp $PROJECT_DIR/nextjs-manager.target /etc/systemd/system/
fi

# systemdをリロードして有効化
sudo systemctl daemon-reload
sudo systemctl enable $BACKEND_SERVICE
sudo systemctl enable $FRONTEND_SERVICE
sudo systemctl enable $TARGET_SERVICE

# サービスを再起動
echo "🔄 サービスを再起動中..."
sudo systemctl restart $TARGET_SERVICE

# 状態確認
echo ""
echo "✅ サービス状態:"
sudo systemctl status $TARGET_SERVICE --no-pager | head -5

echo ""
echo "✅ バックエンド:"
sudo systemctl status $BACKEND_SERVICE --no-pager | head -8

echo ""
echo "✅ フロントエンド:"
sudo systemctl status $FRONTEND_SERVICE --no-pager | head -8

echo ""
echo "✨ デプロイ完了！"
echo ""
echo "📍 アクセス URL:"
echo "   フロントエンド: http://サーバーIP:8080"
echo "   バックエンドAPI: http://サーバーIP:8070"
echo ""
echo "🎯 統合コマンド（推奨）:"
echo "   起動: sudo systemctl start $TARGET_SERVICE"
echo "   停止: sudo systemctl stop $TARGET_SERVICE"
echo "   再起動: sudo systemctl restart $TARGET_SERVICE"
echo "   状態確認: sudo systemctl status $TARGET_SERVICE"
echo ""
echo "📊 ログ確認:"
echo "   バックエンド: sudo journalctl -u $BACKEND_SERVICE -f"
echo "   フロントエンド: sudo journalctl -u $FRONTEND_SERVICE -f"
echo "   両方: sudo journalctl -u $BACKEND_SERVICE -u $FRONTEND_SERVICE -f"
