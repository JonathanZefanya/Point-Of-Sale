<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><title>{{ $title }}</title></head>
<body style="margin:0;background:#F7F7F5;font-family:Arial,sans-serif;color:#1C1C1A;font-size:14px">
<div style="max-width:560px;margin:0 auto;padding:24px 16px">
    <div style="background:#FFFFFF;border:1px solid #E4E4E0;border-radius:6px;padding:20px">
        <h1 style="font-size:18px;margin:0 0 4px">{{ $title }}</h1>
        <p style="color:#6B6B66;margin:0 0 16px">{{ $subtitle }}</p>
        @yield('content')
    </div>
    <p style="color:#6B6B66;font-size:12px;margin-top:12px">Email otomatis dari aplikasi POS. Ubah penerima atau matikan di Pengaturan → Notifikasi email.</p>
</div>
</body>
</html>
