<!DOCTYPE html>
<html>
<head>
    <title>Register</title>
    <link rel="stylesheet" type="text/css" href="css/style.css">
</head>
<body>

<h2>Register Akun</h2>

<form method="post" action="../controllers/RegisterController.php">
    
    <input type="text" name="nama" placeholder="Nama Lengkap" required><br><br>
    
    <input type="text" name="username" placeholder="Username" required><br><br>
    
    <input 
        type="password" 
        name="password" 
        placeholder="Password (minimal 8 karakter)" 
        required 
        minlength="8"
        pattern=".{8,}"
        title="Password harus minimal 8 karakter"
    ><br><br>

    <select name="role" required>
        <option value="">Pilih Role</option>
        <option value="owner">Owner</option>
        <option value="petugas">Petugas</option>
    </select><br><br>

    <button type="submit">Register</button>
</form>

<p>Sudah punya akun? <a href="login.php">Login</a></p>

</body>
</html>