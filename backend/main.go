package main

import (
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"log"
	"net/http"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"sync"
	"time"

	"github.com/google/uuid"
	"github.com/joho/godotenv"
	"github.com/rs/cors"
)

// User represents a user account
type User struct {
	Username     string `json:"username"`
	PasswordHash string `json:"password_hash"`
}

// Session represents a user session
type Session struct {
	Token     string
	Username  string
	ExpiresAt time.Time
}

// LoginRequest represents the login request
type LoginRequest struct {
	Username string `json:"username"`
	Password string `json:"password"`
}

// LoginResponse represents the login response
type LoginResponse struct {
	Success bool   `json:"success"`
	Token   string `json:"token,omitempty"`
	Message string `json:"message,omitempty"`
}

// CommandRequest represents a command execution request
type CommandRequest struct {
	Command string `json:"command"`
	Path    string `json:"path"`
}

// CommandResponse represents a command execution response
type CommandResponse struct {
	Success bool   `json:"success"`
	Output  string `json:"output,omitempty"`
	Error   string `json:"error,omitempty"`
}

// CronJob represents a cron job entry
type CronJob struct {
	ID       int    `json:"id"`
	Schedule string `json:"schedule"`
	Command  string `json:"command"`
}

// CronRequest represents a cron job request
type CronRequest struct {
	Schedule string `json:"schedule"`
	Command  string `json:"command"`
	ID       int    `json:"id,omitempty"`
}

// CleanupRequest represents a file cleanup request
type CleanupRequest struct {
	Path    string `json:"path"`
	Pattern string `json:"pattern"`
	Days    int    `json:"days"`
	Preview bool   `json:"preview"`
}

// FileInfo represents file information
type FileInfo struct {
	Name    string `json:"name"`
	Path    string `json:"path"`
	Size    int64  `json:"size"`
	ModTime string `json:"mod_time"`
}

var (
	users    = make(map[string]User)
	sessions = make(map[string]Session)
	mu       sync.RWMutex
)

func init() {
	// デフォルトユーザーを作成 (username: admin, password: admin)
	passwordHash := hashPassword("admin")
	users["admin"] = User{
		Username:     "admin",
		PasswordHash: passwordHash,
	}
}

// hashPassword creates SHA256 hash of password
func hashPassword(password string) string {
	hash := sha256.Sum256([]byte(password))
	return hex.EncodeToString(hash[:])
}

// authenticate checks if the request has a valid session
func authenticate(next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		token := r.Header.Get("Authorization")
		if token == "" {
			log.Printf("Authentication failed: No token provided")
			respondJSON(w, http.StatusUnauthorized, map[string]interface{}{
				"success": false,
				"error":   "Unauthorized",
			})
			return
		}

		mu.RLock()
		session, exists := sessions[token]
		mu.RUnlock()

		if !exists {
			log.Printf("Authentication failed: Token not found: %s", token)
			respondJSON(w, http.StatusUnauthorized, map[string]interface{}{
				"success": false,
				"error":   "Invalid or expired token",
			})
			return
		}

		if time.Now().After(session.ExpiresAt) {
			log.Printf("Authentication failed: Token expired for user: %s", session.Username)
			respondJSON(w, http.StatusUnauthorized, map[string]interface{}{
				"success": false,
				"error":   "Invalid or expired token",
			})
			return
		}

		log.Printf("Authentication successful for user: %s", session.Username)
		next(w, r)
	}
}

// loginHandler handles user login
func loginHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}

	var req LoginRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		respondJSON(w, http.StatusBadRequest, LoginResponse{
			Success: false,
			Message: "Invalid request",
		})
		return
	}

	user, exists := users[req.Username]
	if !exists || user.PasswordHash != hashPassword(req.Password) {
		respondJSON(w, http.StatusUnauthorized, LoginResponse{
			Success: false,
			Message: "Invalid username or password",
		})
		return
	}

	// Create new session
	token := uuid.New().String()
	session := Session{
		Token:     token,
		Username:  req.Username,
		ExpiresAt: time.Now().Add(24 * time.Hour),
	}

	mu.Lock()
	sessions[token] = session
	mu.Unlock()

	respondJSON(w, http.StatusOK, LoginResponse{
		Success: true,
		Token:   token,
		Message: "Login successful",
	})
}

// logoutHandler handles user logout
func logoutHandler(w http.ResponseWriter, r *http.Request) {
	token := r.Header.Get("Authorization")
	if token != "" {
		mu.Lock()
		delete(sessions, token)
		mu.Unlock()
	}

	respondJSON(w, http.StatusOK, map[string]bool{"success": true})
}

// executeCommandHandler handles npm command execution
func executeCommandHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}

	var req CommandRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		respondJSON(w, http.StatusBadRequest, CommandResponse{
			Success: false,
			Error:   "Invalid request",
		})
		return
	}

	// Validate command
	allowedCommands := map[string]bool{
		"nginx-start":  true,
		"nginx-stop":   true,
		"nginx-reload": true,
		"nginx-status": true,
		"npm-build":    true,
		"npm-start":    true,
		"npm-dev":      true,
		"npm-stop":     true,
	}

	if !allowedCommands[req.Command] {
		respondJSON(w, http.StatusBadRequest, CommandResponse{
			Success: false,
			Error:   "Command not allowed",
		})
		return
	}

	// Execute command
	var cmd *exec.Cmd
	switch req.Command {
	// Nginx commands
	case "nginx-start":
		cmd = exec.Command("sudo", "systemctl", "start", "nginx")
	case "nginx-stop":
		cmd = exec.Command("sudo", "systemctl", "stop", "nginx")
	case "nginx-reload":
		cmd = exec.Command("sudo", "systemctl", "reload", "nginx")
	case "nginx-status":
		cmd = exec.Command("sudo", "systemctl", "status", "nginx")
	// NPM commands
	case "npm-build":
		cmd = exec.Command("npm", "run", "build")
	case "npm-start":
		cmd = exec.Command("npm", "run", "start", "--", "-p", "3000")
	case "npm-dev":
		cmd = exec.Command("npm", "run", "dev", "--", "-p", "3000")
	case "npm-stop":
		cmd = exec.Command("pkill", "-f", "next")
	}

	if req.Path != "" {
		cmd.Dir = req.Path
	}

	output, err := cmd.CombinedOutput()
	if err != nil {
		respondJSON(w, http.StatusOK, CommandResponse{
			Success: false,
			Output:  string(output),
			Error:   err.Error(),
		})
		return
	}

	respondJSON(w, http.StatusOK, CommandResponse{
		Success: true,
		Output:  string(output),
	})
}

// validateHandler checks if the session is valid
func validateHandler(w http.ResponseWriter, r *http.Request) {
	respondJSON(w, http.StatusOK, map[string]bool{"valid": true})
}

// respondJSON sends a JSON response
func respondJSON(w http.ResponseWriter, status int, data interface{}) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	json.NewEncoder(w).Encode(data)
}

// getCronJobsHandler retrieves all cron jobs
func getCronJobsHandler(w http.ResponseWriter, r *http.Request) {
	cmd := exec.Command("crontab", "-l")
	output, err := cmd.CombinedOutput()

	jobs := []CronJob{}
	if err == nil {
		lines := strings.Split(string(output), "\n")
		id := 1
		for _, line := range lines {
			line = strings.TrimSpace(line)
			if line == "" || strings.HasPrefix(line, "#") {
				continue
			}
			// Parse cron line: schedule + command
			parts := strings.SplitN(line, " ", 6)
			if len(parts) >= 6 {
				schedule := strings.Join(parts[:5], " ")
				command := parts[5]
				jobs = append(jobs, CronJob{
					ID:       id,
					Schedule: schedule,
					Command:  command,
				})
				id++
			}
		}
	}

	respondJSON(w, http.StatusOK, map[string]interface{}{
		"success": true,
		"jobs":    jobs,
	})
}

// addCronJobHandler adds a new cron job
func addCronJobHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}

	var req CronRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		respondJSON(w, http.StatusBadRequest, map[string]interface{}{
			"success": false,
			"error":   "Invalid request",
		})
		return
	}

	// Get current crontab
	cmd := exec.Command("crontab", "-l")
	output, _ := cmd.CombinedOutput()
	currentCron := string(output)

	// Add new job
	newJob := fmt.Sprintf("%s %s\n", req.Schedule, req.Command)
	newCron := currentCron + newJob

	// Write back to crontab
	cmd = exec.Command("crontab", "-")
	cmd.Stdin = strings.NewReader(newCron)
	if err := cmd.Run(); err != nil {
		respondJSON(w, http.StatusInternalServerError, map[string]interface{}{
			"success": false,
			"error":   "Failed to add cron job",
		})
		return
	}

	respondJSON(w, http.StatusOK, map[string]interface{}{
		"success": true,
		"message": "Cron job added successfully",
	})
}

// deleteCronJobHandler deletes a cron job
func deleteCronJobHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}

	var req CronRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		respondJSON(w, http.StatusBadRequest, map[string]interface{}{
			"success": false,
			"error":   "Invalid request",
		})
		return
	}

	// Get current crontab
	cmd := exec.Command("crontab", "-l")
	output, err := cmd.CombinedOutput()
	if err != nil {
		respondJSON(w, http.StatusOK, map[string]interface{}{
			"success": true,
			"message": "No cron jobs to delete",
		})
		return
	}

	lines := strings.Split(string(output), "\n")
	newLines := []string{}
	currentID := 1

	for _, line := range lines {
		trimmed := strings.TrimSpace(line)
		if trimmed == "" || strings.HasPrefix(trimmed, "#") {
			newLines = append(newLines, line)
			continue
		}

		if currentID != req.ID {
			newLines = append(newLines, line)
		}
		currentID++
	}

	// Write back to crontab
	newCron := strings.Join(newLines, "\n")
	cmd = exec.Command("crontab", "-")
	cmd.Stdin = strings.NewReader(newCron)
	if err := cmd.Run(); err != nil {
		respondJSON(w, http.StatusInternalServerError, map[string]interface{}{
			"success": false,
			"error":   "Failed to delete cron job",
		})
		return
	}

	respondJSON(w, http.StatusOK, map[string]interface{}{
		"success": true,
		"message": "Cron job deleted successfully",
	})
}

// cleanupFilesHandler handles file cleanup operations
func cleanupFilesHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}

	var req CleanupRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		respondJSON(w, http.StatusBadRequest, map[string]interface{}{
			"success": false,
			"error":   "Invalid request",
		})
		return
	}

	// セキュリティチェック: パスが絶対パスであることを確認
	if !filepath.IsAbs(req.Path) {
		respondJSON(w, http.StatusBadRequest, map[string]interface{}{
			"success": false,
			"error":   "Path must be absolute",
		})
		return
	}

	// ディレクトリが存在するか確認
	info, err := os.Stat(req.Path)
	if err != nil || !info.IsDir() {
		respondJSON(w, http.StatusBadRequest, map[string]interface{}{
			"success": false,
			"error":   "Directory does not exist",
		})
		return
	}

	// ファイルを検索
	cutoffTime := time.Now().AddDate(0, 0, -req.Days)
	var filesToDelete []FileInfo
	var deletedCount int

	err = filepath.Walk(req.Path, func(path string, info os.FileInfo, err error) error {
		if err != nil {
			return nil // エラーは無視
		}
		if info.IsDir() {
			return nil // ディレクトリはスキップ
		}

		// パターンマッチング
		matched := true
		if req.Pattern != "" {
			matched, _ = filepath.Match(req.Pattern, filepath.Base(path))
		}

		if matched && info.ModTime().Before(cutoffTime) {
			fileInfo := FileInfo{
				Name:    info.Name(),
				Path:    path,
				Size:    info.Size(),
				ModTime: info.ModTime().Format("2006-01-02 15:04:05"),
			}
			filesToDelete = append(filesToDelete, fileInfo)

			// プレビューモードでなければ削除実行
			if !req.Preview {
				if err := os.Remove(path); err == nil {
					deletedCount++
				}
			}
		}
		return nil
	})

	if err != nil {
		respondJSON(w, http.StatusInternalServerError, map[string]interface{}{
			"success": false,
			"error":   "Failed to scan directory",
		})
		return
	}

	if req.Preview {
		respondJSON(w, http.StatusOK, map[string]interface{}{
			"success": true,
			"preview": true,
			"files":   filesToDelete,
			"count":   len(filesToDelete),
		})
	} else {
		respondJSON(w, http.StatusOK, map[string]interface{}{
			"success": true,
			"deleted": deletedCount,
			"message": fmt.Sprintf("%d files deleted", deletedCount),
		})
	}
}

// logsHandler retrieves system logs
func logsHandler(w http.ResponseWriter, r *http.Request) {
	var logs []string
	var errors []string

	// error.logを読み込み
	errorLog := "/var/log/nginx/error.log"
	if data, err := exec.Command("sudo", "tail", "-n", "100", errorLog).CombinedOutput(); err == nil {
		logs = append(logs, "=== Nginx Error Log ===\n"+string(data))
	} else {
		errors = append(errors, fmt.Sprintf("Error reading nginx error.log: %v\n%s", err, string(data)))
	}

	// access.logを読み込み
	accessLog := "/var/log/nginx/access.log"
	if data, err := exec.Command("sudo", "tail", "-n", "100", accessLog).CombinedOutput(); err == nil {
		logs = append(logs, "\n=== Nginx Access Log ===\n"+string(data))
	} else {
		errors = append(errors, fmt.Sprintf("Error reading nginx access.log: %v\n%s", err, string(data)))
	}

	// systemd journal (nginx関連)
	if data, err := exec.Command("sudo", "journalctl", "-u", "nginx", "-n", "50", "--no-pager").CombinedOutput(); err == nil {
		logs = append(logs, "\n=== Systemd Journal (Nginx) ===\n"+string(data))
	} else {
		errors = append(errors, fmt.Sprintf("Error reading journalctl: %v\n%s", err, string(data)))
	}

	// エラーがある場合はログに追加
	if len(errors) > 0 {
		logs = append([]string{"=== Errors ===\n" + strings.Join(errors, "\n")}, logs...)
	}

	allLogs := strings.Join(logs, "\n")

	// ログが空の場合
	if allLogs == "" {
		allLogs = "ログファイルが見つからないか、読み取り権限がありません。\nsudoers設定を確認してください。"
	}

	respondJSON(w, http.StatusOK, map[string]interface{}{
		"success": true,
		"logs":    allLogs,
	})
}

func main() {
	// .envファイルを読み込み
	if err := godotenv.Load(); err != nil {
		log.Println("Warning: .env file not found, using environment variables")
	}

	mux := http.NewServeMux()

	mux.HandleFunc("/api/login", loginHandler)
	mux.HandleFunc("/api/logout", authenticate(logoutHandler))
	mux.HandleFunc("/api/validate", authenticate(validateHandler))
	mux.HandleFunc("/api/execute", authenticate(executeCommandHandler))
	mux.HandleFunc("/api/cronjobs", authenticate(getCronJobsHandler))
	mux.HandleFunc("/api/cronjobs/add", authenticate(addCronJobHandler))
	mux.HandleFunc("/api/cronjobs/delete", authenticate(deleteCronJobHandler))
	mux.HandleFunc("/api/cleanup", authenticate(cleanupFilesHandler))
	mux.HandleFunc("/api/logs", authenticate(logsHandler))

	// CORS設定 - すべてのオリジンを動的に許可
	c := cors.New(cors.Options{
		AllowOriginFunc: func(origin string) bool {
			return true // すべてのオリジンを許可
		},
		AllowedMethods:   []string{"GET", "POST", "PUT", "DELETE", "OPTIONS"},
		AllowedHeaders:   []string{"*"},
		AllowCredentials: true,
	})

	handler := c.Handler(mux)

	port := os.Getenv("PORT")
	if port == "" {
		port = "8000"
	}

	fmt.Printf("Server starting on 0.0.0.0:%s...\n", port)
	log.Fatal(http.ListenAndServe("0.0.0.0:"+port, handler))
}
