<?php
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/vendor/autoload.php',
];

$autoloadFound = false;
foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $autoloadFound = true;
        break;
    }
}

if (!$autoloadFound) {
    die('<h1>Fatal Error</h1><p>Composer autoloader not found. Did you run <code>composer install</code>?</p>');
}

// Load .env (create it from .env.example if missing)
$basePath = dirname(__DIR__);  // project root

if (!file_exists($basePath . '/.env')) {
    // Auto-create .env from .env.example if it exists
    if (file_exists($basePath . '/.env.example')) {
        copy($basePath . '/.env.example', $basePath . '/.env');
        echo "<h3>.env created automatically from .env.example</h3>";
    } else {
        die('<h1>Missing .env</h1><p>Please copy <code>.env.example</code> → <code>.env</code> and configure it.</p>');
    }
}

use App\Config\AppConfig;
use App\Services\SessionService;
use App\Helpers\Logger;

AppConfig::load($basePath);
SessionService::start();

if (isset($_GET['debug']) && false) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    echo "<pre style='background:#000;color:#0f0;padding:20px;font-size:14px;'>DEBUG MODE ON\n";
    echo "PHP Version: " . PHP_VERSION . "\n";
    echo "Base path: $basePath\n";
    echo "Session ID: " . session_id() . "\n";
    echo "Config loaded:\n";
    print_r(AppConfig::get(''));
    echo "</pre>";
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// CSRF check (optional but recommended)
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    // die('CSRF token missing or invalid');
}

$token = SessionService::getToken();
$editorFile = AppConfig::get('paths')['rendered'] . "/editor_$token.html";

Logger::log("=== Starting PDF signing process for token: $token ===");

$html = \App\Services\ApiClient::callEditorApi();

if (!$html) {
    http_response_code(500);
    //include __DIR__ . '/../src/Views/error.php';
    exit;
}

$html = \App\Services\EditorRenderer::injectInterceptor($html);

// Save rendered file (optional, for debugging)
file_put_contents($editorFile, $html);
Logger::log("Editor HTML generated and saved to $editorFile");

// Render the iframe page
include __DIR__ . '/../src/Views/editor.php';
