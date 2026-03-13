<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Server Error Alert</title>
</head>
<body style="font-family: Arial, sans-serif; background:#0b1220; color:#e2e8f0; padding:16px;">
    <h2 style="color:#f87171; margin-bottom:8px;">Server Error Detected</h2>
    <p style="margin:4px 0;"><strong>Status:</strong> {{ $status }}</p>
    <p style="margin:4px 0;"><strong>Exception:</strong> {{ $exceptionClass }}</p>
    <p style="margin:4px 0;"><strong>Message:</strong> {{ $messageText ?: '(empty message)' }}</p>
    <p style="margin:4px 0;"><strong>URL:</strong> {{ $url }}</p>
    <p style="margin:4px 0;"><strong>Trace ID:</strong> {{ $traceId }}</p>
    <p style="margin-top:16px; font-size:12px; color:#94a3b8;">Email ini dikirim otomatis saat aplikasi memunculkan 5xx error.</p>
</body>
</html>
