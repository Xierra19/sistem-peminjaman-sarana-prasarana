@php
    $title = $context === \App\Models\OtpCode::CONTEXT_REGISTRATION
        ? 'Verifikasi akun Anda'
        : 'Reset kata sandi Anda';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f4f5; padding: 24px;">
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" role="presentation" style="background-color:#ffffff;border-radius:12px;padding:32px;">
                    <tr>
                        <td>
                            <h1 style="font-size:20px;color:#111827;margin-bottom:16px;">{{ $title }}</h1>
                            <p style="color:#4b5563;margin-bottom:16px;">
                                Gunakan kode berikut untuk melanjutkan proses. Kode berlaku hingga
                                <strong>{{ $expiresAt->timezone(config('app.timezone'))->format('d M Y H:i') }}</strong>.
                            </p>
                            <p style="font-size:32px;letter-spacing:8px;color:#111827;margin:24px 0;">
                                <strong>{{ $code }}</strong>
                            </p>
                            <p style="color:#4b5563;margin-bottom:24px;">
                                Jika tombol berikut tidak berfungsi, salin dan tempel tautan di bawah ini ke peramban Anda.
                            </p>
                            <p style="text-align:center;margin-bottom:32px;">
                                <a href="{{ $magicLink }}" style="background-color:#2563eb;color:#ffffff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;">
                                    Buka tautan verifikasi
                                </a>
                            </p>
                            <p style="font-size:12px;color:#9ca3af;word-break:break-all;">{{ $magicLink }}</p>
                            <p style="color:#6b7280;margin-top:32px;font-size:12px;">
                                Abaikan email ini jika Anda tidak meminta tindakan tersebut.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
