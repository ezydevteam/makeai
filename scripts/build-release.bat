@echo off
setlocal enabledelayedexpansion

if "%~1"=="" (
    echo Usage: build-release.bat ^<version^>
    pause
    exit /b 1
)

set VERSION=%~1
for %%I in ("%~dp0..") do set "SRC_DIR=%%~fI"
set BUILD_DIR=%TEMP%\makeai-release-build
set ZIP_NAME=makeai-v%VERSION%.zip

echo ==^> Cleaning up previous build directory
if exist "%BUILD_DIR%" rmdir /s /q "%BUILD_DIR%"
mkdir "%BUILD_DIR%\public_html\makeai"

echo ==> 1. Installing PHP dependencies (production)
cd /d "%SRC_DIR%"
where composer >nul 2>nul
if %errorlevel% equ 0 (
    set COMPOSER_CMD=composer
) else if exist "D:\laragon\bin\composer\composer.bat" (
    set COMPOSER_CMD=D:\laragon\bin\composer\composer.bat
) else (
    echo ERROR: composer not found in PATH or Laragon directory!
    pause
    exit /b 1
)
call %COMPOSER_CMD% install --no-dev --optimize-autoloader --no-interaction
if %errorlevel% neq 0 (
    echo ERROR: Composer install failed!
    pause
    exit /b 1
)

echo ==> 2. Installing JS dependencies
call npm ci
if %errorlevel% neq 0 (
    echo ERROR: npm ci failed!
    pause
    exit /b 1
)

echo ==^> 3. Building frontend assets
call npm run build
if %errorlevel% neq 0 (
    echo ERROR: npm run build failed!
    pause
    exit /b 1
)

echo ==^> 4. Copying application source into makeai/ (excluding public/)
robocopy "%SRC_DIR%" "%BUILD_DIR%\public_html\makeai" /E /XD "%SRC_DIR%\.git" "%SRC_DIR%\node_modules" "%SRC_DIR%\tests" "%SRC_DIR%\scripts" "%SRC_DIR%\caveman" "%SRC_DIR%\.commandcode" "%SRC_DIR%\.cursor" "%SRC_DIR%\.vscode" "%SRC_DIR%\.idea" "%SRC_DIR%\public" "%SRC_DIR%\dist" "%SRC_DIR%\storage" "%SRC_DIR%\bootstrap\cache" /XF "%SRC_DIR%\.env" "%SRC_DIR%\.env.testing" "%SRC_DIR%\database.sqlite" .gitignore .gitattributes .gitkeep
if %errorlevel% gtr 7 (
    echo ERROR: Robocopy failed!
    pause
    exit /b 1
)

echo ==^> 5. Moving public/ contents to zip root
robocopy "%SRC_DIR%\public" "%BUILD_DIR%\public_html" /E /XD build
if %errorlevel% gtr 7 (
    echo ERROR: Robocopy failed!
    pause
    exit /b 1
)

echo ==^> 6. Placing pre-built Vite assets at root /build/
xcopy /E /I /Y "%SRC_DIR%\public\build" "%BUILD_DIR%\public_html\build"
if exist "%BUILD_DIR%\public_html\build\.vite\manifest.json" (
    copy /Y "%BUILD_DIR%\public_html\build\.vite\manifest.json" "%BUILD_DIR%\public_html\build\manifest.json"
)

echo ==^> 7. Writing fixed files from distribution/ to root
copy /Y "%SRC_DIR%\distribution\index.php" "%BUILD_DIR%\public_html\index.php"
copy /Y "%SRC_DIR%\distribution\.htaccess" "%BUILD_DIR%\public_html\.htaccess"
copy /Y "%SRC_DIR%\distribution\robots.txt" "%BUILD_DIR%\public_html\robots.txt"
copy /Y "%SRC_DIR%\distribution\favicon.ico" "%BUILD_DIR%\public_html\favicon.ico"

echo ==^> 8. Creating empty root-level storage/ tree
mkdir "%BUILD_DIR%\public_html\storage"
type nul > "%BUILD_DIR%\public_html\storage\.gitkeep"

echo ==^> 9. Ensuring writable directories exist inside makeai/
mkdir "%BUILD_DIR%\public_html\makeai\storage\framework\cache"
mkdir "%BUILD_DIR%\public_html\makeai\storage\framework\sessions"
mkdir "%BUILD_DIR%\public_html\makeai\storage\framework\testing"
mkdir "%BUILD_DIR%\public_html\makeai\storage\framework\views"
mkdir "%BUILD_DIR%\public_html\makeai\storage\logs"
mkdir "%BUILD_DIR%\public_html\makeai\bootstrap\cache"

echo ==^> 10. Removing files that must not ship
del /F /Q "%BUILD_DIR%\public_html\makeai\.env" 2>nul
del /F /Q "%BUILD_DIR%\public_html\makeai\.env.testing" 2>nul
rmdir /S /Q "%BUILD_DIR%\public_html\makeai\public" 2>nul

echo ==^> 11. Sanity checks
if not exist "%BUILD_DIR%\public_html\makeai\vendor\autoload.php" (
    echo FATAL: vendor/ missing
    pause
    exit /b 1
)
if not exist "%BUILD_DIR%\public_html\build\manifest.json" (
    if not exist "%BUILD_DIR%\public_html\build\.vite\manifest.json" (
        echo FATAL: build manifest missing
        pause
        exit /b 1
    )
)
if not exist "%BUILD_DIR%\public_html\makeai\.env.example" (
    echo FATAL: .env.example missing
    pause
    exit /b 1
)
if not exist "%BUILD_DIR%\public_html\index.php" (
    echo FATAL: index.php missing
    pause
    exit /b 1
)

echo ==> 12. Zipping
mkdir "%SRC_DIR%\dist" 2>nul
php "%SRC_DIR%\scripts\zip.php" "%BUILD_DIR%\public_html" "%SRC_DIR%\dist\%ZIP_NAME%"

echo.
echo ==========================================
echo ==^> SUCCESS: .\dist\%ZIP_NAME%
echo ==========================================
pause