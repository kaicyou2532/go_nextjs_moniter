package main

import (
	"encoding/json"
	"fmt"
	"log"
	"net"
	"net/http"
	"net/url"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"sync"
	"time"

	"github.com/google/uuid"
	"github.com/joho/godotenv"
	"github.com/rs/cors"
	"golang.org/x/crypto/bcrypt"
)

const (
	sessionDuration    = 24 * time.Hour
	loginWindow        = 15 * time.Minute
	loginLockDuration  = 15 * time.Minute
	maxLoginAttempts   = 5
	minPasswordLength  = 12
	defaultProjectPort = "3000"
)

type User struct {
	Username     string    `json:"username"`
	PasswordHash string    `json:"password_hash"`
	Role         string    `json:"role"`
	CreatedAt    time.Time `json:"created_at"`
}

type Session struct {
	Token     string
	Username  string
	ExpiresAt time.Time
}

type LoginRequest struct {
	Username string `json:"username"`
	Password string `json:"password"`
}

type LoginResponse struct {
	Success bool   `json:"success"`
	Token   string `json:"token,omitempty"`
	Message string `json:"message,omitempty"`
}

type CreateUserRequest struct {
	Username        string `json:"username"`
	Password        string `json:"password"`
	ConfirmPassword string `json:"confirm_password"`
}

type CommandRequest struct {
	Command string `json:"command"`
	Path    string `json:"path"`
}

type CommandResponse struct {
	Success bool   `json:"success"`
	Output  string `json:"output,omitempty"`
	Error   string `json:"error,omitempty"`
}

type CronJob struct {
	ID       int    `json:"id"`
	Schedule string `json:"schedule"`
	Command  string `json:"command"`
}

type CronRequest struct {
	Schedule string `json:"schedule"`
	Command  string `json:"command"`
	ID       int    `json:"id,omitempty"`
}

type CleanupRequest struct {
	Path    string `json:"path"`
	Pattern string `json:"pattern"`
	Days    int    `json:"days"`
	Preview bool   `json:"preview"`
}

type FileInfo struct {
	Name    string `json:"name"`
	Path    string `json:"path"`
	Size    int64  `json:"size"`
	ModTime string `json:"mod_time"`
}

type RepositoryStatus struct {
	Branch     string `json:"branch"`
	Commit     string `json:"commit"`
	IsClean    bool   `json:"is_clean"`
	StatusText string `json:"status_text"`
}

type loginAttempt struct {
	Count       int
	WindowStart time.Time
	LockedUntil time.Time
}

var (
	users         = make(map[string]User)
	sessions      = make(map[string]Session)
	loginAttempts = make(map[string]loginAttempt)
	mu            sync.RWMutex
	usersFile     string
)

func healthHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method == http.MethodOptions {
		w.WriteHeader(http.StatusNoContent)
		return
	}
	w.WriteHeader(http.StatusOK)
	_, _ = w.Write([]byte("ok"))
}

func okHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method == http.MethodOptions {
		w.WriteHeader(http.StatusNoContent)
		return
	}
	respondJSON(w, http.StatusOK, map[string]interface{}{
		"success": true,
		"message": "OK",
		"status":  "ok",
	})
}

func defaultUsersFilePath() string {
	if customPath := strings.TrimSpace(os.Getenv("USERS_FILE")); customPath != "" {
		return customPath
	}

	executablePath, err := os.Executable()
	if err != nil {
		return "users.json"
	}

	return filepath.Join(filepath.Dir(executablePath), "users.json")
}

func loadUsers() error {
	usersFile = defaultUsersFilePath()
	data, err := os.ReadFile(usersFile)
	if err == nil {
		var storedUsers []User
		if err := json.Unmarshal(data, &storedUsers); err != nil {
			return fmt.Errorf("failed to parse users file: %w", err)
		}

		mu.Lock()
		defer mu.Unlock()
		for _, user := range storedUsers {
			users[user.Username] = user
		}
		return nil
	}

	if !os.IsNotExist(err) {
		return fmt.Errorf("failed to read users file: %w", err)
	}

	username := strings.TrimSpace(os.Getenv("ADMIN_USERNAME"))
	if username == "" {
		username = "admin"
	}

	password := strings.TrimSpace(os.Getenv("ADMIN_PASSWORD"))
	if password == "" {
		password = "admin"
		log.Printf("SECURITY WARNING: users file not found; bootstrapping legacy admin/admin account for compatibility. Set ADMIN_PASSWORD before deployment and rotate this account immediately.")
	}

	adminUser, err := newBootstrapUser(username, password)
	if err != nil {
		return err
	}

	mu.Lock()
	users[adminUser.Username] = adminUser
	mu.Unlock()

	return saveUsers()
}

func saveUsers() error {
	mu.RLock()
	storedUsers := make([]User, 0, len(users))
	for _, user := range users {
		storedUsers = append(storedUsers, user)
	}
	mu.RUnlock()

	data, err := json.MarshalIndent(storedUsers, "", "  ")
	if err != nil {
		return err
	}

	if err := os.MkdirAll(filepath.Dir(usersFile), 0o700); err != nil {
		return err
	}

	tempFile := usersFile + ".tmp"
	if err := os.WriteFile(tempFile, data, 0o600); err != nil {
		return err
	}

	return os.Rename(tempFile, usersFile)
}

func newUser(username, password string) (User, error) {
	username = strings.TrimSpace(username)
	if err := validateUsername(username); err != nil {
		return User{}, err
	}
	if err := validatePassword(password); err != nil {
		return User{}, err
	}

	passwordHash, err := hashPassword(password)
	if err != nil {
		return User{}, err
	}

	return User{
		Username:     username,
		PasswordHash: passwordHash,
		Role:         "admin",
		CreatedAt:    time.Now().UTC(),
	}, nil
}

func newBootstrapUser(username, password string) (User, error) {
	username = strings.TrimSpace(username)
	if err := validateUsername(username); err != nil {
		return User{}, err
	}

	passwordHash, err := hashPassword(password)
	if err != nil {
		return User{}, err
	}

	return User{
		Username:     username,
		PasswordHash: passwordHash,
		Role:         "admin",
		CreatedAt:    time.Now().UTC(),
	}, nil
}

func hashPassword(password string) (string, error) {
	hashed, err := bcrypt.GenerateFromPassword([]byte(password), bcrypt.DefaultCost)
	if err != nil {
		return "", err
	}
	return string(hashed), nil
}

func verifyPassword(password, passwordHash string) bool {
	return bcrypt.CompareHashAndPassword([]byte(passwordHash), []byte(password)) == nil
}

func validateUsername(username string) error {
	if len(username) < 3 || len(username) > 64 {
		return fmt.Errorf("username must be between 3 and 64 characters")
	}
	for _, char := range username {
		if (char >= 'a' && char <= 'z') || (char >= 'A' && char <= 'Z') || (char >= '0' && char <= '9') || char == '_' || char == '-' || char == '.' {
			continue
		}
		return fmt.Errorf("username may contain only letters, numbers, '.', '_' and '-'")
	}
	return nil
}

func validatePassword(password string) error {
	if len(password) < minPasswordLength {
		return fmt.Errorf("password must be at least %d characters", minPasswordLength)
	}

	var hasUpper, hasLower, hasDigit bool
	for _, char := range password {
		switch {
		case char >= 'a' && char <= 'z':
			hasLower = true
		case char >= 'A' && char <= 'Z':
			hasUpper = true
		case char >= '0' && char <= '9':
			hasDigit = true
		}
	}

	if !hasUpper || !hasLower || !hasDigit {
		return fmt.Errorf("password must include uppercase, lowercase and numeric characters")
	}

	return nil
}

func cleanupExpiredSessions(now time.Time) {
	mu.Lock()
	defer mu.Unlock()
	for token, session := range sessions {
		if now.After(session.ExpiresAt) {
			delete(sessions, token)
		}
	}
}

func parseAuthorizationToken(r *http.Request) string {
	token := strings.TrimSpace(r.Header.Get("Authorization"))
	if token == "" {
		return ""
	}
	if strings.HasPrefix(strings.ToLower(token), "bearer ") {
		return strings.TrimSpace(token[7:])
	}
	return token
}

func authenticate(next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		cleanupExpiredSessions(time.Now())

		token := parseAuthorizationToken(r)
		if token == "" {
			respondJSON(w, http.StatusUnauthorized, map[string]interface{}{
				"success": false,
				"error":   "Unauthorized",
			})
			return
		}

		mu.RLock()
		session, exists := sessions[token]
		mu.RUnlock()

		if !exists || time.Now().After(session.ExpiresAt) {
			respondJSON(w, http.StatusUnauthorized, map[string]interface{}{
				"success": false,
				"error":   "Invalid or expired token",
			})
			return
		}

		next(w, r)
	}
}

func requireMethod(w http.ResponseWriter, r *http.Request, method string) bool {
	if r.Method != method {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return false
	}
	return true
}

func getRemoteIP(r *http.Request) string {
	if forwardedFor := strings.TrimSpace(r.Header.Get("X-Forwarded-For")); forwardedFor != "" {
		return strings.Split(forwardedFor, ",")[0]
	}
	if realIP := strings.TrimSpace(r.Header.Get("X-Real-IP")); realIP != "" {
		return realIP
	}
	host, _, err := net.SplitHostPort(r.RemoteAddr)
	if err != nil {
		return r.RemoteAddr
	}
	return host
}

func attemptKey(username, remoteIP string) string {
	return strings.ToLower(strings.TrimSpace(username)) + "|" + remoteIP
}

func isLoginBlocked(username, remoteIP string, now time.Time) (bool, time.Duration) {
	mu.RLock()
	defer mu.RUnlock()

	attempt, exists := loginAttempts[attemptKey(username, remoteIP)]
	if !exists || now.After(attempt.LockedUntil) {
		return false, 0
	}

	return true, time.Until(attempt.LockedUntil).Round(time.Second)
}

func recordFailedLogin(username, remoteIP string, now time.Time) {
	key := attemptKey(username, remoteIP)

	mu.Lock()
	defer mu.Unlock()

	attempt := loginAttempts[key]
	if attempt.WindowStart.IsZero() || now.Sub(attempt.WindowStart) > loginWindow {
		attempt = loginAttempt{
			Count:       0,
			WindowStart: now,
		}
	}

	attempt.Count++
	if attempt.Count >= maxLoginAttempts {
		attempt.LockedUntil = now.Add(loginLockDuration)
		attempt.Count = 0
		attempt.WindowStart = now
	}

	loginAttempts[key] = attempt
}

func clearFailedLogin(username, remoteIP string) {
	mu.Lock()
	delete(loginAttempts, attemptKey(username, remoteIP))
	mu.Unlock()
}

func loginHandler(w http.ResponseWriter, r *http.Request) {
	if !requireMethod(w, r, http.MethodPost) {
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

	remoteIP := getRemoteIP(r)
	now := time.Now()
	if blocked, retryAfter := isLoginBlocked(req.Username, remoteIP, now); blocked {
		respondJSON(w, http.StatusTooManyRequests, LoginResponse{
			Success: false,
			Message: fmt.Sprintf("Too many login attempts. Try again in %s.", retryAfter),
		})
		return
	}

	mu.RLock()
	user, exists := users[strings.TrimSpace(req.Username)]
	mu.RUnlock()
	if !exists || !verifyPassword(req.Password, user.PasswordHash) {
		recordFailedLogin(req.Username, remoteIP, now)
		respondJSON(w, http.StatusUnauthorized, LoginResponse{
			Success: false,
			Message: "Invalid username or password",
		})
		return
	}

	clearFailedLogin(req.Username, remoteIP)

	token := uuid.NewString()
	session := Session{
		Token:     token,
		Username:  user.Username,
		ExpiresAt: now.Add(sessionDuration),
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

func createUserHandler(w http.ResponseWriter, r *http.Request) {
	if !requireMethod(w, r, http.MethodPost) {
		return
	}

	var req CreateUserRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		respondJSON(w, http.StatusBadRequest, map[string]interface{}{
			"success": false,
			"error":   "Invalid request",
		})
		return
	}

	if req.Password != req.ConfirmPassword {
		respondJSON(w, http.StatusBadRequest, map[string]interface{}{
			"success": false,
			"error":   "Passwords do not match",
		})
		return
	}

	user, err := newUser(req.Username, req.Password)
	if err != nil {
		respondJSON(w, http.StatusBadRequest, map[string]interface{}{
			"success": false,
			"error":   err.Error(),
		})
		return
	}

	mu.Lock()
	if _, exists := users[user.Username]; exists {
		mu.Unlock()
		respondJSON(w, http.StatusConflict, map[string]interface{}{
			"success": false,
			"error":   "Username already exists",
		})
		return
	}
	users[user.Username] = user
	mu.Unlock()

	if err := saveUsers(); err != nil {
		mu.Lock()
		delete(users, user.Username)
		mu.Unlock()
		respondJSON(w, http.StatusInternalServerError, map[string]interface{}{
			"success": false,
			"error":   "Failed to save user",
		})
		return
	}

	respondJSON(w, http.StatusCreated, map[string]interface{}{
		"success": true,
		"user": map[string]interface{}{
			"username":   user.Username,
			"role":       user.Role,
			"created_at": user.CreatedAt,
		},
	})
}

func logoutHandler(w http.ResponseWriter, r *http.Request) {
	if !requireMethod(w, r, http.MethodPost) {
		return
	}

	token := parseAuthorizationToken(r)
	if token != "" {
		mu.Lock()
		delete(sessions, token)
		mu.Unlock()
	}

	respondJSON(w, http.StatusOK, map[string]bool{"success": true})
}

func managedProjectPath(requestPath string) (string, error) {
	configuredPath := strings.TrimSpace(os.Getenv("NEXTJS_PROJECT_PATH"))
	if configuredPath == "" {
		return "", fmt.Errorf("NEXTJS_PROJECT_PATH is not configured")
	}

	cleanConfiguredPath := filepath.Clean(configuredPath)
	if !filepath.IsAbs(cleanConfiguredPath) {
		return "", fmt.Errorf("configured NEXTJS_PROJECT_PATH must be absolute")
	}

	if requestPath != "" && filepath.Clean(requestPath) != cleanConfiguredPath {
		return "", fmt.Errorf("path is not allowed")
	}

	info, err := os.Stat(cleanConfiguredPath)
	if err != nil || !info.IsDir() {
		return "", fmt.Errorf("configured project directory does not exist")
	}

	return cleanConfiguredPath, nil
}

func executeManagedCommand(command string, projectPath string) (*exec.Cmd, error) {
	switch command {
	case "nginx-start":
		return exec.Command("sudo", "systemctl", "start", "nginx"), nil
	case "nginx-stop":
		return exec.Command("sudo", "systemctl", "stop", "nginx"), nil
	case "nginx-reload":
		return exec.Command("sudo", "systemctl", "reload", "nginx"), nil
	case "nginx-status":
		return exec.Command("sudo", "systemctl", "status", "nginx"), nil
	case "nextjs-start":
		return exec.Command("sudo", "systemctl", "start", "nextjs-app"), nil
	case "nextjs-stop":
		return exec.Command("sudo", "systemctl", "stop", "nextjs-app"), nil
	case "nextjs-restart":
		return exec.Command("sudo", "systemctl", "restart", "nextjs-app"), nil
	case "nextjs-status":
		return exec.Command("sudo", "systemctl", "status", "nextjs-app"), nil
	case "npm-build":
		cmd := exec.Command("npm", "run", "build")
		cmd.Dir = projectPath
		return cmd, nil
	case "npm-start":
		cmd := exec.Command("npm", "run", "start", "--", "-p", defaultProjectPort)
		cmd.Dir = projectPath
		return cmd, nil
	case "npm-dev":
		cmd := exec.Command("npm", "run", "dev", "--", "-p", defaultProjectPort)
		cmd.Dir = projectPath
		return cmd, nil
	case "npm-stop":
		return exec.Command("pkill", "-f", "next"), nil
	case "nextjs-git-pull":
		cmd := exec.Command("git", "pull", "--ff-only")
		cmd.Dir = projectPath
		return cmd, nil
	default:
		return nil, fmt.Errorf("command not allowed")
	}
}

func executeCommandHandler(w http.ResponseWriter, r *http.Request) {
	if !requireMethod(w, r, http.MethodPost) {
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

	allowedCommands := map[string]bool{
		"nginx-start":        true,
		"nginx-stop":         true,
		"nginx-reload":       true,
		"nginx-status":       true,
		"npm-build":          true,
		"npm-start":          true,
		"npm-dev":            true,
		"npm-stop":           true,
		"nextjs-start":       true,
		"nextjs-stop":        true,
		"nextjs-restart":     true,
		"nextjs-status":      true,
		"nextjs-build-start": true,
		"nextjs-git-pull":    true,
	}

	if !allowedCommands[req.Command] {
		respondJSON(w, http.StatusBadRequest, CommandResponse{
			Success: false,
			Error:   "Command not allowed",
		})
		return
	}

	projectPath, err := managedProjectPath(req.Path)
	if err != nil && (strings.HasPrefix(req.Command, "nextjs") || strings.HasPrefix(req.Command, "npm")) {
		respondJSON(w, http.StatusBadRequest, CommandResponse{
			Success: false,
			Error:   err.Error(),
		})
		return
	}

	if req.Command == "nextjs-build-start" {
		output, err := buildAndStartProject(projectPath)
		if err != nil {
			respondJSON(w, http.StatusOK, CommandResponse{
				Success: false,
				Output:  output,
				Error:   err.Error(),
			})
			return
		}

		respondJSON(w, http.StatusOK, CommandResponse{
			Success: true,
			Output:  output,
		})
		return
	}

	cmd, err := executeManagedCommand(req.Command, projectPath)
	if err != nil {
		respondJSON(w, http.StatusBadRequest, CommandResponse{
			Success: false,
			Error:   err.Error(),
		})
		return
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

func buildAndStartProject(projectPath string) (string, error) {
	var outputs []string

	if stopOutput, err := exec.Command("sudo", "systemctl", "stop", "nextjs-app").CombinedOutput(); len(stopOutput) > 0 {
		outputs = append(outputs, string(stopOutput))
		if err != nil {
			outputs = append(outputs, fmt.Sprintf("stop warning: %v", err))
		}
	}

	nextDir := filepath.Join(projectPath, ".next")
	if removeOutput, err := exec.Command("sudo", "rm", "-rf", nextDir).CombinedOutput(); len(removeOutput) > 0 {
		outputs = append(outputs, string(removeOutput))
		if err != nil {
			return strings.Join(outputs, "\n"), fmt.Errorf("failed to remove .next directory: %w", err)
		}
	}

	buildCmd := exec.Command("npm", "run", "build")
	buildCmd.Dir = projectPath
	buildOutput, buildErr := buildCmd.CombinedOutput()
	outputs = append(outputs, string(buildOutput))
	if buildErr != nil {
		return strings.Join(outputs, "\n"), fmt.Errorf("build failed: %w", buildErr)
	}

	startOutput, startErr := exec.Command("sudo", "systemctl", "start", "nextjs-app").CombinedOutput()
	outputs = append(outputs, string(startOutput))
	if startErr != nil {
		return strings.Join(outputs, "\n"), fmt.Errorf("failed to start service: %w", startErr)
	}

	return strings.TrimSpace(strings.Join(outputs, "\n")), nil
}

func repositoryStatusHandler(w http.ResponseWriter, r *http.Request) {
	if !requireMethod(w, r, http.MethodGet) {
		return
	}

	projectPath, err := managedProjectPath("")
	if err != nil {
		respondJSON(w, http.StatusInternalServerError, map[string]interface{}{
			"success": false,
			"error":   err.Error(),
		})
		return
	}

	branchOutput, err := runGitCommand(projectPath, "rev-parse", "--abbrev-ref", "HEAD")
	if err != nil {
		respondJSON(w, http.StatusOK, map[string]interface{}{
			"success": false,
			"error":   err.Error(),
		})
		return
	}

	commitOutput, err := runGitCommand(projectPath, "rev-parse", "--short", "HEAD")
	if err != nil {
		respondJSON(w, http.StatusOK, map[string]interface{}{
			"success": false,
			"error":   err.Error(),
		})
		return
	}

	statusOutput, err := runGitCommand(projectPath, "status", "--short", "--branch")
	if err != nil {
		respondJSON(w, http.StatusOK, map[string]interface{}{
			"success": false,
			"error":   err.Error(),
		})
		return
	}

	status := RepositoryStatus{
		Branch:     strings.TrimSpace(branchOutput),
		Commit:     strings.TrimSpace(commitOutput),
		IsClean:    !strings.Contains(strings.TrimSpace(statusOutput), "\n"),
		StatusText: strings.TrimSpace(statusOutput),
	}

	respondJSON(w, http.StatusOK, map[string]interface{}{
		"success": true,
		"status":  status,
	})
}

func runGitCommand(projectPath string, args ...string) (string, error) {
	cmd := exec.Command("git", args...)
	cmd.Dir = projectPath
	output, err := cmd.CombinedOutput()
	if err != nil {
		message := strings.TrimSpace(string(output))
		if message == "" {
			message = err.Error()
		}
		return string(output), fmt.Errorf("git command failed: %s", message)
	}
	return string(output), nil
}

func validateHandler(w http.ResponseWriter, r *http.Request) {
	if !requireMethod(w, r, http.MethodGet) {
		return
	}

	respondJSON(w, http.StatusOK, map[string]bool{"valid": true})
}

func respondJSON(w http.ResponseWriter, status int, data interface{}) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(data)
}

func getCronJobsHandler(w http.ResponseWriter, r *http.Request) {
	if !requireMethod(w, r, http.MethodGet) {
		return
	}

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

func addCronJobHandler(w http.ResponseWriter, r *http.Request) {
	if !requireMethod(w, r, http.MethodPost) {
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

	cmd := exec.Command("crontab", "-l")
	output, _ := cmd.CombinedOutput()
	currentCron := string(output)

	newJob := fmt.Sprintf("%s %s\n", req.Schedule, req.Command)
	newCron := currentCron + newJob

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

func deleteCronJobHandler(w http.ResponseWriter, r *http.Request) {
	if !requireMethod(w, r, http.MethodPost) {
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

func cleanupFilesHandler(w http.ResponseWriter, r *http.Request) {
	if !requireMethod(w, r, http.MethodPost) {
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

	if !filepath.IsAbs(req.Path) {
		respondJSON(w, http.StatusBadRequest, map[string]interface{}{
			"success": false,
			"error":   "Path must be absolute",
		})
		return
	}

	info, err := os.Stat(req.Path)
	if err != nil || !info.IsDir() {
		respondJSON(w, http.StatusBadRequest, map[string]interface{}{
			"success": false,
			"error":   "Directory does not exist",
		})
		return
	}

	cutoffTime := time.Now().AddDate(0, 0, -req.Days)
	var filesToDelete []FileInfo
	var deletedCount int

	err = filepath.Walk(req.Path, func(path string, info os.FileInfo, err error) error {
		if err != nil {
			return nil
		}
		if info.IsDir() {
			return nil
		}

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
		return
	}

	respondJSON(w, http.StatusOK, map[string]interface{}{
		"success": true,
		"deleted": deletedCount,
		"message": fmt.Sprintf("%d files deleted", deletedCount),
	})
}

func logsHandler(w http.ResponseWriter, r *http.Request) {
	if !requireMethod(w, r, http.MethodGet) {
		return
	}

	var logs []string
	var errors []string

	errorLog := "/var/log/nginx/error.log"
	if data, err := exec.Command("sudo", "tail", "-n", "100", errorLog).CombinedOutput(); err == nil {
		logs = append(logs, "=== Nginx Error Log ===\n"+string(data))
	} else {
		errors = append(errors, fmt.Sprintf("Error reading nginx error.log: %v\n%s", err, string(data)))
	}

	accessLog := "/var/log/nginx/access.log"
	if data, err := exec.Command("sudo", "tail", "-n", "100", accessLog).CombinedOutput(); err == nil {
		logs = append(logs, "\n=== Nginx Access Log ===\n"+string(data))
	} else {
		errors = append(errors, fmt.Sprintf("Error reading nginx access.log: %v\n%s", err, string(data)))
	}

	if data, err := exec.Command("sudo", "journalctl", "-u", "nginx", "-n", "50", "--no-pager").CombinedOutput(); err == nil {
		logs = append(logs, "\n=== Systemd Journal (Nginx) ===\n"+string(data))
	} else {
		errors = append(errors, fmt.Sprintf("Error reading journalctl: %v\n%s", err, string(data)))
	}

	if len(errors) > 0 {
		logs = append([]string{"=== Errors ===\n" + strings.Join(errors, "\n")}, logs...)
	}

	allLogs := strings.Join(logs, "\n")
	if allLogs == "" {
		allLogs = "ログファイルが見つからないか、読み取り権限がありません。\nsudoers設定を確認してください。"
	}

	respondJSON(w, http.StatusOK, map[string]interface{}{
		"success": true,
		"logs":    allLogs,
	})
}

func statusHandler(w http.ResponseWriter, r *http.Request) {
	if !requireMethod(w, r, http.MethodGet) {
		return
	}

	cmd := exec.Command("systemctl", "is-active", "nextjs-app")
	output, _ := cmd.CombinedOutput()
	isRunning := strings.TrimSpace(string(output)) == "active"

	respondJSON(w, http.StatusOK, map[string]interface{}{
		"success": true,
		"running": isRunning,
		"status":  map[string]bool{"nextjs": isRunning},
	})
}

func allowedOrigin(origin string) bool {
	if origin == "" {
		return true
	}

	allowedOrigins := strings.TrimSpace(os.Getenv("ALLOWED_ORIGINS"))
	if allowedOrigins != "" {
		for _, candidate := range strings.Split(allowedOrigins, ",") {
			if strings.TrimSpace(candidate) == origin {
				return true
			}
		}
		return false
	}

	parsedURL, err := url.Parse(origin)
	if err != nil {
		return false
	}

	if parsedURL.Scheme != "http" && parsedURL.Scheme != "https" {
		return false
	}

	return parsedURL.Port() == "8080"
}

func main() {
	if err := godotenv.Load(); err != nil {
		log.Println("Warning: .env file not found, using environment variables")
	}

	if err := loadUsers(); err != nil {
		log.Fatal(err)
	}

	log.Printf("Loaded %d user(s) from %s", len(users), usersFile)

	mux := http.NewServeMux()
	mux.HandleFunc("/health", healthHandler)
	mux.HandleFunc("/api/health", healthHandler)
	mux.HandleFunc("/api/ok", okHandler)
	mux.HandleFunc("/api/login", loginHandler)
	mux.HandleFunc("/api/logout", authenticate(logoutHandler))
	mux.HandleFunc("/api/validate", authenticate(validateHandler))
	mux.HandleFunc("/api/execute", authenticate(executeCommandHandler))
	mux.HandleFunc("/api/admin/users", authenticate(createUserHandler))
	mux.HandleFunc("/api/repository/status", authenticate(repositoryStatusHandler))
	mux.HandleFunc("/api/cronjobs", authenticate(getCronJobsHandler))
	mux.HandleFunc("/api/cronjobs/add", authenticate(addCronJobHandler))
	mux.HandleFunc("/api/cronjobs/delete", authenticate(deleteCronJobHandler))
	mux.HandleFunc("/api/cleanup", authenticate(cleanupFilesHandler))
	mux.HandleFunc("/api/logs", authenticate(logsHandler))
	mux.HandleFunc("/api/status", authenticate(statusHandler))

	c := cors.New(cors.Options{
		AllowOriginFunc:  allowedOrigin,
		AllowedMethods:   []string{"GET", "POST", "PUT", "DELETE", "OPTIONS"},
		AllowedHeaders:   []string{"Authorization", "Content-Type"},
		AllowCredentials: true,
	})

	port := os.Getenv("PORT")
	if port == "" {
		port = "8000"
	}

	fmt.Printf("Server starting on 0.0.0.0:%s...\n", port)
	log.Fatal(http.ListenAndServe("0.0.0.0:"+port, c.Handler(mux)))
}
