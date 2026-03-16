<?php
session_start();
require_once '../models/UserModel.php';

class LoginController {
    private $userModel;

    public function __construct() {
        $this->userModel = new UserModel();
    }

    public function login() {
        $username = $_POST['username'];
        $password = $_POST['password'];

        $data = $this->userModel->login($username, $password);

        if ($data) {
            $_SESSION['nama'] = $data['nama'];
            $_SESSION['username'] = $username;
            $_SESSION['role'] = $data['role'];

            // Map application roles to existing dashboard pages
            if ($data['role'] == "owner") {
                header("location:../views/dashboard_owner.php");
            } else if ($data['role'] == "petugas") {
                header("location:../views/dashboard_petugas.php");
            } else {
                header("location:../views/login.php?pesan=gagal");
            }
        } else {
            header("location:../views/login.php?pesan=gagal");
        }
    }
}

// Instantiate and call
$controller = new LoginController();
$controller->login();
?>
