package main

import (
	"os"
	"path/filepath"
	"testing"
)

func TestValidatePassword(t *testing.T) {
	t.Parallel()

	if err := validatePassword("Short1"); err == nil {
		t.Fatal("expected short password to fail validation")
	}

	if err := validatePassword("alllowercase123"); err == nil {
		t.Fatal("expected password without uppercase to fail validation")
	}

	if err := validatePassword("ValidPassword123"); err != nil {
		t.Fatalf("expected strong password to pass validation: %v", err)
	}
}

func TestManagedProjectPath(t *testing.T) {
	projectDir := t.TempDir()
	t.Setenv("NEXTJS_PROJECT_PATH", projectDir)

	allowedPath, err := managedProjectPath(projectDir)
	if err != nil {
		t.Fatalf("expected configured path to be allowed: %v", err)
	}

	if allowedPath != filepath.Clean(projectDir) {
		t.Fatalf("expected cleaned project path, got %s", allowedPath)
	}

	disallowedDir := filepath.Join(os.TempDir(), "some-other-project")
	if _, err := managedProjectPath(disallowedDir); err == nil {
		t.Fatal("expected different path to be rejected")
	}
}
