<?php
require_once 'Database.php';

class UserModel {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    public function login($username, $password) {
        $conn = $this->db->getConnection();
        $query = "SELECT * FROM user WHERE username='$username' AND password='$password'";
        $result = mysqli_query($conn, $query);
        return mysqli_fetch_assoc($result);
    }

    public function register($nama, $username, $password, $role) {
        $conn = $this->db->getConnection();
        $query = "INSERT INTO user (nama, username, password, role) VALUES ('$nama', '$username', '$password', '$role')";
        return mysqli_query($conn, $query);
    }

    public function checkUsername($username) {
        $conn = $this->db->getConnection();
        $query = "SELECT * FROM user WHERE username='$username'";
        $result = mysqli_query($conn, $query);
        return mysqli_num_rows($result) > 0;
    }
}
?>