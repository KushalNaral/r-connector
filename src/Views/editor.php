<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDF Signing Editor</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f4f6f9;
        }

        iframe {
            width: 100%;
            height: 100vh;
            border: none;
            display: block;
        }

        .loading-overlay {
            position: fixed;
            inset: 0;
            background: rgba(255, 255, 255, 0.98);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            transition: opacity .3s;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #e0e0e0;
            border-top-color: #007bff;
            border-radius: 50%;
            animation: spin .8s linear infinite;
            margin-bottom: 20px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body>
    <div id="loading" class="loading-overlay">
        <div class="spinner"></div>
        <div style="font-size:16px;color:#555;font-weight:500;">Loading signing editor...</div>
    </div>
    <iframe id="editor" srcdoc="" sandbox="allow-scripts allow-same-origin allow-modals allow-popups allow-forms allow-downloads"></iframe>

    <script>
        const iframe = document.getElementById('editor');
        const loading = document.getElementById('loading');
        const htmlContent = <?= json_encode($html) ?>;

        iframe.srcdoc = htmlContent;
        iframe.onload = () => {
            setTimeout(() => {
                loading.style.opacity = '0';
                setTimeout(() => loading.remove(), 300);
            }, 500);

            const win = iframe.contentWindow;
            win.addEventListener('pdf-signed', (e) => {
                const {
                    blobUrl,
                    filename
                } = e.detail;
                document.body.innerHTML = `
                    <div style="max-width:1000px;margin:40px auto;padding:30px;background:white;border-radius:16px;box-shadow:0 4px 30px rgba(0,0,0,0.1);text-align:center;">
                        <h1 style="color:#28a745;font-size:32px;margin-bottom:10px;">Document Signed Successfully</h1>
                        <p style="color:#666;font-size:16px;margin-bottom:30px;">Your PDF has been signed and downloaded automatically.</p>
                        <embed src="${blobUrl}#toolbar=1&navpanes=1&scrollbar=1" type="application/pdf" width="100%" height="700px" style="border:1px solid #ddd;border-radius:8px;">
                        <div style="margin-top:40px;">
                            <a href="${blobUrl}" download="${filename}" style="background:#28a745;color:white;padding:14px 32px;border-radius:8px;text-decoration:none;margin:0 10px;font-weight:500;">Download Again</a>
                            <a href="index.php" style="background:#6c757d;color:white;padding:14px 32px;border-radius:8px;text-decoration:none;margin:0 10px;font-weight:500;">Sign Another</a>
                        </div>
                    </div>`;
            });
            win.addEventListener('pdf-sign-error', (e) => {
                alert('Signing failed: ' + (e.detail.message || 'Unknown error'));
            });
        };
    </script>
</body>

</html>
