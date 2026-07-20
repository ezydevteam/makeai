@echo off
REM Thin wrapper - the real build lives in build-release.php so that Windows,
REM macOS and Linux all run byte-identical logic. The previous rsync/robocopy
REM pair had silently drifted into producing two different packages.
REM
REM Usage: scripts\build-release.bat <version> [--skip-deps] [--no-slim]

setlocal

where php >nul 2>nul
if %errorlevel% equ 0 (
    set "PHP_CMD=php"
) else if exist "D:\laragon\bin\php\php-8.3\php.exe" (
    set "PHP_CMD=D:\laragon\bin\php\php-8.3\php.exe"
) else (
    echo ERROR: php not found in PATH.
    pause
    exit /b 1
)

call %PHP_CMD% "%~dp0build-release.php" %*
set "EXIT_CODE=%errorlevel%"

if %EXIT_CODE% neq 0 (
    echo.
    echo Build failed with exit code %EXIT_CODE%.
)

pause
exit /b %EXIT_CODE%
