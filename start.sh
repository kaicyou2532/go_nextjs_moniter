#!/bin/bash

echo "Next.js管理ツールを起動します..."

# .envファイルの確認
if [ ! -f "backend/.env" ]; then
    echo "警告: backend/.env ファイルが見つかりません"
    echo "以下のコマンドで作成してください:"
    echo "cp backend/.env.example backend/.env"
    echo ""
    exit 1
fi

# 環境変数を読み込み
if [ -f "backend/.env" ]; then
    export $(cat backend/.env | grep -v '^#' | xargs)
fi

# バックエンドを起動
echo "Goバックエンドを起動中..."
cd backend
go run main.go &
BACKEND_PID=$!

# フロントエンドを起動
echo "PHPフロントエンドを起動中..."
cd ../frontend
php -S 0.0.0.0:8080 &
FRONTEND_PID=$!

echo ""
echo "======================================"
echo "Next.js管理ツールが起動しました！"
echo "======================================"
echo ""
echo "バックエンド: http://0.0.0.0:8070 (外部アクセス可能)"
echo "フロントエンド: http://0.0.0.0:8080 (外部アクセス可能)"
echo ""
echo "ログイン情報:"
echo "  ユーザー名: admin"
echo "  パスワード: admin"
echo ""
if [ -n "$NEXTJS_PROJECT_PATH" ]; then
    echo "Next.jsプロジェクト: $NEXTJS_PROJECT_PATH"
else
    echo "Next.jsプロジェクト: 未設定（.envファイルを確認してください）"
fi
echo ""
echo "終了するには Ctrl+C を押してください"
echo ""

# 終了処理
trap "kill $BACKEND_PID $FRONTEND_PID; exit" INT TERM

wait
