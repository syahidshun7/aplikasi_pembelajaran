<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email</title>
</head>
<body style="margin:0;padding:0;background:#f8fafc;font-family:Verdana,Arial,sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;padding:30px 14px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:680px;background:#ffffff;border:2px solid #cbd5e1;box-shadow:0 10px 25px rgba(15,23,42,0.12);">
                    <tr>
                        <td style="padding:0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:linear-gradient(90deg,#0ea5e9,#4f46e5,#10b981);">
                                <tr>
                                    <td style="padding:14px 20px;color:#ecfeff;font-family:'Courier New',monospace;font-size:12px;letter-spacing:2px;text-transform:uppercase;">
                                        &gt; AUTHENTICATION_REQUIRED &lt;
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:22px 24px 10px;">
                            <h1 style="margin:0;color:#334155;font-family:'Courier New',monospace;font-size:20px;line-height:1.4;text-transform:uppercase;">
                                -- VERIFY_EMAIL_ACCESS --
                            </h1>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:2px 24px 0;color:#334155;font-size:14px;line-height:1.8;">
                            Halo <strong>{{ $userName }}</strong>,<br>
                            untuk membuka semua fitur di <strong>{{ $appName }}</strong>, silakan verifikasi email akun kamu.
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:20px 24px 0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;border:1px solid #cbd5e1;">
                                <tr>
                                    <td style="padding:14px 14px;color:#334155;font-size:12px;line-height:1.7;">
                                        Langkah cepat:
                                        <br>1. Klik tombol verifikasi di bawah.
                                        <br>2. Jika tidak terbuka, copy link manual.
                                        <br>3. Cek folder spam/promotions jika email lanjutan tidak terlihat.
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:22px 24px 8px;">
                            <a href="{{ $verificationUrl }}" style="display:inline-block;background:#10b981;color:#052e2b;text-decoration:none;font-weight:700;padding:12px 20px;border:2px solid #047857;font-family:'Courier New',monospace;font-size:12px;text-transform:uppercase;">
                                Verify Email Now
                            </a>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:10px 24px 22px;color:#64748b;font-size:12px;line-height:1.7;">
                            Link manual:
                            <div style="margin-top:6px;word-break:break-all;color:#0ea5e9;">{{ $verificationUrl }}</div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:14px 24px;background:#f8fafc;border-top:1px solid #e2e8f0;color:#64748b;font-size:11px;line-height:1.7;">
                            Jika kamu tidak merasa membuat akun, abaikan email ini.
                            <br>{{ $appName }} // Build_Ver_1.1.0
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
