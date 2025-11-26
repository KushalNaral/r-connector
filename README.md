# PDF Digital Signature Connector (PHP Adaptor)

## Project Overview

This is a lightweight PHP web application that acts as a secure bridge between your browser and a protected PDF digital signing server.

The signing server requires authentication (Client ID, Secret, and a session token) on every request, but when it returns its editor as a full HTML page inside an iframe, the browser loses that session context.

This project solves that problem by:

- Generating a secure session
- Calling the real signing API
- Injecting a small JavaScript interceptor into the returned editor page
- Automatically adding authentication headers to all requests made by the editor
- Forcing automatic download of the signed PDF with a proper filename

When finished, the user gets a cleanly named signed PDF downloaded automatically — no extra clicks needed.

## Folder Structure

```
.
├── public/ ← Web-accessible files
│ ├── index.php ← Main landing page with "Open Editor" button
│ └── upload.php ← Handles the request and renders the editor
├── src/
│ ├── Config/
│ │ └── AppConfig.php ← Loads .env and centralizes configuration
│ ├── Services/
│ │ ├── ApiClient.php ← Talks to the real signing backend
│ │ ├── SessionService.php ← Manages PHP sessions and tokens
│ │ └── EditorRenderer.php ← Injects JavaScript interceptor
│ └── Views/
│ └── editor.php ← Displays the final editor in fullscreen
├── storage/
│ ├── logs/app.log ← All activity and errors are logged here
│ └── rendered/ ← Saved copies of generated editor pages (for debug)
├── .env.example ← Template for environment configuration
└── composer.json
```

## Setup Instructions

1. Copy .env.example to .env
   cp .env.example .env

2. Edit .env and set your values:

```
   API_URL=http://your-signing-server:8075/protected/api/v1/pdf-visualize
   CLIENT_ID=DOIT
   CLIENT_SECRET=s3cr3t
   STATIC_FILE_PATH=/home/user/documents/contract.pdf
   SESSION_VALIDITY_MINUTES=30
   FALLBACK_SESSION_ID=optional-fallback-if-needed
```

3. Install dependencies:

   ```
   composer install
   ```

4. Start the server:

   ```
   composer dev # accessible only from your computer
   ```

   # or

   ```
   composer dev:host # accessible from local network
   ```

5. Open browser: http://localhost:8000

## How It Works - Step by Step

1. User opens public/index.php
   Simple form with one button: "Open Signing Editor"

2. Form posts to public/upload.php

3. upload.php does this:

   ```
   // Start session and generate unique token
   SessionService::start();
   $token = SessionService::getToken();

   // Call the real signing server
   $html = \App\Services\ApiClient::callEditorApi();

   // Inject JavaScript that restores session and forces PDF download
   $html = \App\Services\EditorRenderer::injectInterceptor($html);

   // Optional: save a copy for debugging
   file*put_contents("../storage/rendered/editor*$token.html", $html);

   // Show the editor
   include '../src/Views/editor.php';
   ```

## Key Code Snippets Explained

1.  AppConfig.php - Loading settings

This static method initializes the configuration by loading variables from the .env file using phpdotenv. It sets fallback defaults for critical values like API URL, client credentials, and PDF path. The config is stored in a static array for easy access anywhere in the app. It also automatically creates essential directories (storage/rendered and storage/logs) with secure permissions (0755) if they don't exist, ensuring the app runs without manual setup.

    ```
    public static function load($basePath = null)
    {
       $basePath = $basePath ?? dirname(__DIR__, 2);
       $dotenv = \Dotenv\Dotenv::createImmutable($basePath);
    $dotenv->load();

            self::$config = [
                'api_url' => $_ENV['API_URL'] ?? 'http://localhost:8075/protected/api/v1/pdf-visualize',
                'client_id' => $_ENV['CLIENT_ID'] ?? 'DOIT',
                'client_secret' => $_ENV['CLIENT_SECRET'] ?? 's3cr3t',
                'static_file_path' => $_ENV['STATIC_FILE_PATH'] ?? '',
                'paths' => [
                    'rendered' => $basePath . '/storage/rendered',
                    'logs' => $basePath . '/storage/logs',
                ]
            ];

            foreach (self::$config['paths'] as $dir) {
                if (!is_dir($dir)) mkdir($dir, 0755, true);
            }

    }
    ```

2.  ApiClient.php - Calling the real signing server

This snippet prepares and executes a cURL POST request to the protected signing API endpoint. It sends the PDF file path in the request body and includes authentication via custom headers (X-Client-Id and X-Client-Secret). The full method (not shown here) captures the full response, logs request/response headers, HTTP code, and any cURL errors to app.log for debugging, and returns the HTML body only if the response is successful (HTTP 200).

    ```
    $config = AppConfig::get('');
    $ch = curl_init($config['api_url']);
    curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query(['filePath' => $config['static_file_path']]),
       CURLOPT_RETURNTRANSFER => true,
       CURLOPT_HTTPHEADER => [
           "X-Client-Id: " . $config['client_id'],
           "X-Client-Secret: " . $config['client_secret'],
       ],
    ]);
    $response = curl_exec($ch);
    // ... error handling and logging
    ```

3.  EditorRenderer.php - The magic JavaScript injection

This self-invoking JavaScript function (injected into the <head> of the editor HTML) restores the session context lost in the iframe. It first attempts to read a valid session ID and timestamp from the parent window's sessionStorage (with a 30-minute expiry check). If inaccessible (e.g., cross-origin issues), it falls back to a configured value. It then monkey-patches the browser's fetch API to inject SESSION-ID, X-Client-Id, and X-Client-Secret headers into every outgoing request. For the final signing POST (matching /sign), it detects PDF responses, creates a downloadable blob, generates a timestamped filename (e.g., signed-document-2025-11-26-14-30-25.pdf), and triggers an automatic click to download—no user interaction needed.

```
 (function() {
 function getValidSession() {
 try {
 const sessionId = window.parent.sessionStorage.getItem('\_d_sess_1');
 if (sessionId && Date.now() < expiry) return sessionId;
 } catch(e) {}
 return 'FALLBACK_SESSION_ID_HERE';
 }

     window.EDITOR_SESSION = getValidSession();

     const realFetch = window.fetch;
     window.fetch = function(resource, options = {}) {
         const authHeaders = {
             'SESSION-ID': window.EDITOR_SESSION,
             'X-Client-Id': 'DOIT',
             'X-Client-Secret': 's3cr3t'
         };

         options.headers = { ...options.headers, ...authHeaders };

         // When signing completes → auto download PDF
         if (options.method === 'POST' && /sign$/.test(resource)) {
             return realFetch(resource, options).then(async r => {
                 if (r.headers.get('content-type')?.includes('application/pdf')) {
                     const blob = await r.clone().blob();
                     const url = URL.createObjectURL(blob);
                     const a = document.createElement('a');
                     a.href = url;
                     a.download = `signed-document-${new Date().toISOString().slice(0,19).replace(/[:T]/g, '-')}.pdf`;
                     a.click();
                 }
                 return r;
             });
         }

         return realFetch(resource, options);
     };

 })();
```

4.  editor.php - Final display

This minimal view template creates a borderless, full-screen HTML page to host the editor without distractions. It uses CSS to remove margins/padding and make the content fill 100% of the viewport. The <?php echo $html; ?> directly outputs the fully modified editor HTML (including the injected JavaScript), which typically contains an <iframe> or embedded editor—resulting in a clean, immersive signing experience.

```
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Signing Editor</title>
        <style>
            body, html { margin:0; padding:0; height:100%; overflow:hidden; }
            iframe { width:100%; height:100%; border:none; }
        </style>
    </head>
    <body>
        <?php echo $html; // This contains the full editor with injected script ?>
    </body>
    </html>
```

## Logging & Debugging

All actions are logged to: storage/logs/app.log

Example log entries:

```
=== Starting PDF signing process for token: 7e25dd51b64e8ed51d87fb6fc211efe7 ===
REQUEST HEADERS SENT: ...
RESPONSE HTTP CODE: 200
Editor HTML generated and saved to .../editor_7e25dd51b64e8ed51d87fb6fc211efe7.html
```

You can also open the saved HTML file directly to see exactly what was sent to the browser.

## Security Features

- All secrets stored in .env (never in code)
- Automatic directory creation with safe permissions
- Session-based unique tokens per user
- CSRF protection ready (currently commented out - enable if needed)
- Fallback session mechanism if parent storage is inaccessible
