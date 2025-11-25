<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Config\AppConfig;
use App\Services\SessionService;

AppConfig::load(dirname(__DIR__));
SessionService::start();

$csrfToken = SessionService::getCsrfToken();
$filePath = AppConfig::get('static_file_path');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDF Digital Signature</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
            color: #333;
            margin: 0;
            padding: 40px;
            line-height: 1.6;
        }

        .container {
            max-width: 500px;
            margin: 40px auto;
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        h1 {
            font-size: 28px;
            margin-bottom: 16px;
            color: #222;
        }

        .file {
            background: #f0f0f0;
            padding: 12px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 14px;
            margin: 20px 0;
        }

        button {
            background: #007bff;
            color: white;
            border: none;
            padding: 14px 32px;
            font-size: 16px;
            border-radius: 8px;
            cursor: pointer;
            transition: background .2s;
        }

        button:hover {
            background: #0056b3;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Sign PDF Document</h1>
        <p>Securely sign your PDF using digital signature.</p>
        <div class="file">File: <?= htmlspecialchars($filePath) ?></div>
        <form id="signForm" action="upload.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <button type="submit">Open Signing Editor</button>
        </form>
    </div>

    <script>
        document.getElementById('signForm').addEventListener('submit', function(e) {
            e.preventDefault();
            let sessionId = null;
            let sessionTime = null;
            for (const key of Object.keys(sessionStorage)) {
                if (key.includes('_d_sess_1') && !key.includes('_time')) {
                    const id = sessionStorage.getItem(key);
                    const timeStr = sessionStorage.getItem(key + '_time');
                    if (id && timeStr) {
                        const expiry = parseInt(timeStr) + 30 * 60 * 1000;
                        if (Date.now() < expiry) {
                            sessionId = id;
                            sessionTime = parseInt(timeStr);
                            console.log('%c[Session] Using active session: ' + id.substring(0, 10) + '...', 'color:green;font-weight:bold');
                            break;
                        } else {
                            sessionStorage.removeItem(key);
                            sessionStorage.removeItem(key + '_time');
                        }
                    }
                }
            }
            this.querySelectorAll('input[name^="_d_sess_1"]').forEach(el => el.remove());
            if (sessionId && sessionTime) {
                const inputId = document.createElement('input');
                inputId.type = 'hidden';
                inputId.name = '_d_sess_1';
                inputId.value = sessionId;
                this.appendChild(inputId);
                const inputTime = document.createElement('input');
                inputTime.type = 'hidden';
                inputTime.name = '_d_sess_1_time';
                inputTime.value = sessionTime;
                this.appendChild(inputTime);
            }
            this.submit();
        });
    </script>
</body>

</html>
