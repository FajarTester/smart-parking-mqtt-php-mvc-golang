<?php
class LogoutController {
    public function logout() {
        session_start();
        session_destroy();
        header("Location: ../views/login.php");
    }
}

// Instantiate and call
$controller = new LogoutController();
$controller->logout();
?>
