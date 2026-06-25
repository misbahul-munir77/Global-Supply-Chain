<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>login</title>
</head>
<body>
    <h2>Login</h2>
    <form action="/masuk" method="post">
        @csrf
        @error('login')
            {{ $message }}
        @enderror
        <label for="">Email</label>
        <input type="email" name="email" value="{{old('email')}}" placeholder="Masukkan email">
        @error('email')
            {{ $message }}
        @enderror
        
        <label for="">Password</label>
        <input type="password" name="password" placeholder="Masukkan password">
        @error('password')
            {{ $message }}
        @enderror
        <button type="submit">Login</button>
    </form>
    <p>belum mempunyai akun?<a href="/register">daftar sekarang</a></p>
</body>
</html>