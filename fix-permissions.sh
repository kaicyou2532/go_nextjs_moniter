#!/bin/bash

# セッションディレクトリの権限修正スクリプト

echo "🔧 権限を修正中..."

# セッションディレクトリの権限を修正
SESSIONS_DIR="/home/sysmanager/go_nextjs_moniter/frontend/sessions"

if [ ! -d "$SESSIONS_DIR" ]; then
    mkdir -p "$SESSIONS_DIR"
fi

chmod 700 "$SESSIONS_DIR"
chown sysmanager:sysmanager "$SESSIONS_DIR"

echo "✅ セッションディレクトリの権限を修正しました"

# sudoersの設定を追加
SUDOERS_FILE="/etc/sudoers.d/nextjs-manager"

if [ ! -f "$SUDOERS_FILE" ]; then
    echo "📋 sudoers設定を追加中..."
    cat > /tmp/nextjs-manager-sudoers << 'EOF'
# Next.js Manager - パスワードなしsudo設定
sysmanager ALL=(ALL) NOPASSWD: /usr/bin/systemctl start nginx
sysmanager ALL=(ALL) NOPASSWD: /usr/bin/systemctl stop nginx
sysmanager ALL=(ALL) NOPASSWD: /usr/bin/systemctl reload nginx
sysmanager ALL=(ALL) NOPASSWD: /usr/bin/systemctl status nginx
sysmanager ALL=(ALL) NOPASSWD: /usr/bin/journalctl
sysmanager ALL=(ALL) NOPASSWD: /usr/bin/tail
sysmanager ALL=(ALL) NOPASSWD: /usr/bin/pkill
EOF
    sudo cp /tmp/nextjs-manager-sudoers "$SUDOERS_FILE"
    sudo chmod 440 "$SUDOERS_FILE"
    sudo visudo -c
    echo "✅ sudoers設定を追加しました"
else
    echo "ℹ️  sudoers設定は既に存在します"
fi

echo "✨ 完了！"
