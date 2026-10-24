<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? ($store_name ?? 'Traz Cloth') }}</title>
</head>
<body style="margin: 0; background: #f6f6f6; color: #222; font-family: Arial, sans-serif; line-height: 1.5;">
    <div style="max-width: 620px; margin: 24px auto; padding: 24px; background: #fff;">
        <header style="border-bottom: 1px solid #e5e5e5; margin-bottom: 24px; padding-bottom: 16px;">
            <strong style="font-size: 22px;">{{ $store_name ?? 'Traz Cloth' }}</strong>
        </header>

        @yield('content')

        <footer style="border-top: 1px solid #e5e5e5; color: #666; font-size: 12px; margin-top: 24px; padding-top: 16px;">
            Email ini dikirim otomatis untuk keperluan transaksi. Terima kasih telah berbelanja di {{ $store_name ?? 'Traz Cloth' }}.
        </footer>
    </div>
</body>
</html>
