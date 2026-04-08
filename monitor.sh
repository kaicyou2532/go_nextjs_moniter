#!/bin/bash

# Next.js Manager 監視スクリプト
# サービスの死活監視とディスク容量監視を行います

LOG_FILE="/var/log/nextjs-manager-monitor.log"
ALERT_THRESHOLD=80  # ディスク使用率の警告閾値（%）

# ログ記録関数
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# サービス死活監視
check_service() {
    local service=$1
    if ! systemctl is-active --quiet "$service"; then
        log "⚠️  エラー: $service が停止しています。再起動を試みます..."
        sudo systemctl restart "$service"
        sleep 3
        if systemctl is-active --quiet "$service"; then
            log "✅ $service を再起動しました"
        else
            log "❌ $service の再起動に失敗しました"
        fi
    else
        log "✅ $service は正常に稼働しています"
    fi
}

# ディスク容量監視
check_disk_space() {
    local usage=$(df -h / | awk 'NR==2 {print $5}' | sed 's/%//')
    log "📊 ディスク使用率: ${usage}%"
    
    if [ "$usage" -ge "$ALERT_THRESHOLD" ]; then
        log "⚠️  警告: ディスク使用率が ${ALERT_THRESHOLD}% を超えています（現在 ${usage}%）"
        
        # 古いログファイルを削除
        log "🧹 古いログファイルを削除中..."
        find /var/log -type f -name "*.log.*" -mtime +30 -delete 2>/dev/null
        sudo journalctl --vacuum-time=30d
        
        # Next.jsのビルドキャッシュを削除
        if [ -d "/home/sysmanager/next-website/.next" ]; then
            log "🧹 Next.jsキャッシュを削除中..."
            rm -rf /home/sysmanager/next-website/.next/cache
        fi
        
        local new_usage=$(df -h / | awk 'NR==2 {print $5}' | sed 's/%//')
        log "📊 クリーンアップ後のディスク使用率: ${new_usage}%"
    fi
}

# プロセスメモリ監視
check_memory() {
    local backend_mem=$(ps aux | grep '[n]extjs-manager' | awk '{print $4}')
    local php_mem=$(ps aux | grep '[p]hp -S 0.0.0.0:8080' | awk '{print $4}')
    
    if [ -n "$backend_mem" ]; then
        log "💾 バックエンドメモリ使用率: ${backend_mem}%"
    fi
    
    if [ -n "$php_mem" ]; then
        log "💾 フロントエンドメモリ使用率: ${php_mem}%"
    fi
}

# メイン処理
log "========================================="
log "🔍 監視を開始します"

# サービス監視
check_service "nextjs-manager-backend.service"
check_service "nextjs-manager-frontend.service"

# リソース監視
check_disk_space
check_memory

log "✨ 監視完了"
log "========================================="
