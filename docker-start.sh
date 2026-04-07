#!/bin/bash

echo "Docker Compose環境を起動します..."

# .envファイルの確認
if [ ! -f "backend/.env" ]; then
    echo "警告: backend/.env ファイルが見つかりません"
    echo "以下のコマンドで作成してください:"
    echo "cp backend/.env.example backend/.env"
    echo ""
    exit 1
fi

# workspaceディレクトリの作成
mkdir -p workspace

# Docker Composeでビルド＆起動
docker-compose up --build -d

echo ""
echo "======================================"
echo "Next.js管理ツールが起動しました！"
echo "======================================"
echo ""
echo "バックエンド: http://localhost:8070"
echo "フロントエンド: http://localhost:8080"
echo ""
echo "ログイン情報:"
echo "  ユーザー名: admin"
echo "  パスワード: admin"
echo ""
echo "ログを確認:"
echo "  docker-compose logs -f"
echo ""
echo "停止:"
echo "  docker-compose down"
echo ""
