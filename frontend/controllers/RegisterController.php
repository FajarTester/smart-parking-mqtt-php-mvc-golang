<?php
require_once '../models/UserModel.php';

class RegisterController {
    private $userModel;

    public function __construct() {
        $this->userModel = new UserModel();
    }

    public function register() {
        $nama = $_POST['nama'];
        $username = $_POST['username'];
        $password = $_POST['password'];
        $role = isset($_POST['role']) ? trim(strtolower($_POST['role'])) : '';

        // Allow only 'owner' and 'petugas'. Default to 'petugas' for any other value.
        $allowed = array('owner', 'petugas');
        if (!in_array($role, $allowed)) {
            $role = 'petugas';
        }

        if ($this->userModel->checkUsername($username)) {
            echo "Username sudah ada! <a href='../views/register.php'>Coba lagi</a>";
        } else {
            if ($this->userModel->register($nama, $username, $password, $role)) {
                echo "<script>
                        alert('register berhasil');
                        window.location='../views/login.php';
                        </script>";
            } else {
                echo "Gagal register!";
            }
        }
    }
}

// Instantiate and call
$controller = new RegisterController();
$controller->register();
?>
