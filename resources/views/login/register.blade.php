<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
</head>
<body>
    <h2>Daftar</h2>
    <form action="/daftar" method="post">
        @csrf
        <label for="name">Nama</label>
        <input type="text" name="name" placeholder="Masukkan nama">
        @error('name')
            {{ $message }}
        @enderror

        <label for="">Email</label>
        <input type="email" name="email" placeholder="Masukkan email">
        @error('email')
            {{ $message }}
        @enderror
        
        <label for="">Password</label>
        <input type="password" name="password" placeholder="Masukkan password">
        @error('password')
            {{ $message }}
        @enderror

        <label for="">Konfirmasi password</label>
        <input type="password" name="confirm_password" placeholder="Konfirmasi password">
        @error('confirm_password')
            {{ $message }}
        @enderror

        <button type="submit">Daftar</button>
    </form>
    <p>sudah mempunyai akun?<a href="/login">login sekarang</a></p>
</body>
</html>