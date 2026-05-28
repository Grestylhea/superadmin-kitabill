<!DOCTYPE html>
<html>
<head>
    <title>Welcome! - YourBilling</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto py-20">
        <div class="max-w-2xl mx-auto bg-white p-12 rounded-lg shadow-lg text-center">
            <div class="text-6xl mb-4">🎉</div>
            <h1 class="text-4xl font-bold mb-4">Selamat! Billing Anda Sudah Siap!</h1>
            <p class="text-xl mb-8">Trial 14 hari Anda sudah aktif</p>
            
            @if(session('tenant'))
            <div class="bg-blue-50 p-6 rounded-lg mb-8">
                <h2 class="font-bold mb-4 text-lg">Informasi Login:</h2>
                <div class="text-left space-y-2">
                    <p><strong>URL:</strong> <a href="https://{{ session('tenant')->subdomain }}.yourdomain.com" class="text-blue-600">https://{{ session('tenant')->subdomain }}.yourdomain.com</a></p>
                    <p><strong>Email:</strong> {{ session('tenant')->email }}</p>
                    <p><strong>Password:</strong> <code class="bg-gray-200 px-2 py-1 rounded">{{ session('password') }}</code></p>
                </div>
            </div>
            
            <a href="https://{{ session('tenant')->subdomain }}.yourdomain.com" class="bg-blue-600 text-white px-8 py-3 rounded-lg inline-block hover:bg-blue-700">
                Login Sekarang
            </a>
            
            <p class="text-gray-600 mt-6 text-sm">
                ⚠️ Simpan password Anda! Kami sudah mengirim email dengan detail login.
            </p>
            @endif
        </div>
    </div>
</body>
</html>
