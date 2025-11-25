<?php

namespace App\Services;

class EditorRenderer
{
    public static function injectInterceptor(string $html): string
    {
        $fallback = \App\Config\AppConfig::get('fallback_session_id');

        $script = <<<JAVASCRIPT
                <script>
                    (function() {
                        function getValidSession() {
                            try {
                                const sessionId = window.parent.sessionStorage.getItem('_d_sess_1');
                                const sessionTime = parseInt(window.parent.sessionStorage.getItem('_d_sess_1_time') || '0');
                                if (sessionId && sessionTime) {
                                    const expiry = sessionTime + (30 * 60 * 1000);
                                    if (Date.now() < expiry) {
                                        console.log('%cSession from sessionStorage', 'color:#4CAF50;font-weight:bold', sessionId);
                                        return sessionId;
                                    }
                                }
                            } catch(e) { console.warn('No access to parent sessionStorage', e); }
                            console.log('%cUsing fallback session', 'color:#ff9800;font-weight:bold');
                            return '$fallback';
                        }
                            window.EDITOR_SESSION = getValidSession();
                    const realFetch = window.fetch;
                    window.fetch = function(resource, options = {}) {
                                const url = typeof resource === 'string' ? resource : resource.url;
                                const authOptions = {
                                    ...options,
                                    headers: {
                                        ...(options.headers || {}),
                                        'SESSION-ID': window.EDITOR_SESSION,
                                        'X-Client-Id': 'DOIT',
                                        'X-Client-Secret': 's3cr3t'
                                    }
                                };
                                if (options.headers?.['Content-Type']) {
                                    authOptions.headers['Content-Type'] = options.headers['Content-Type'];
                                }
                                const isSignRequest = options.method === 'POST' && /\/pdf-signature\/(?:temp\/)?sign$/.test(url);
                                if (isSignRequest) {
                                    return realFetch(resource, authOptions).then(async r => {
                                        const cloned = r.clone();
                                        const contentType = r.headers.get('content-type') || '';
                                        if (contentType.includes('application/pdf')) {
                                            const blob = await cloned.blob();
                                            if (blob.size < 100) return r;
                                            const blobUrl = URL.createObjectURL(blob);
                                            const timestamp = new Date().toISOString().slice(0,19).replace(/[:T]/g, '-');
                                            const filename = `signed-document-\${timestamp}.pdf`;
                                            const link = document.createElement('a');
                                            link.href = blobUrl; link.download = filename;
                                            document.body.appendChild(link); link.click(); document.body.removeChild(link);
                                            window.SIGNED_PDF_INFO = { blobUrl, filename, timestamp };
                                            window.dispatchEvent(new CustomEvent('pdf-signed', { detail: { blobUrl, filename, timestamp } }));
                                            return r;
                                        }
                                        try {
                                            const text = await cloned.text();
                                            const data = JSON.parse(text);
                                            if (data.status === false) {
                                                window.dispatchEvent(new CustomEvent('pdf-sign-error', { detail: data }));
                                            }
                                        } catch(e) {}
                                        return r;
                                    });
                                }
                                return realFetch(resource, authOptions);
                            };
                    console.log('%cSession Interceptor Active | SESSION-ID:', 'color:#4CAF50;font-weight:bold', window.EDITOR_SESSION);
                    })();
                </script>
        JAVASCRIPT;

        if (preg_match('/<head[^>]*>/i', $html)) {
            return preg_replace('/(<head[^>]*>)/i', '$1' . $script, $html, 1);
        } elseif (preg_match('/<html[^>]*>/i', $html)) {
            return preg_replace('/(<html[^>]*>)/i', '$1' . $script, $html, 1);
        }
        return $script . $html;
    }
}
