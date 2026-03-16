<?php
require_once '../models/Database.php';

class ParkingController {
    private $db;
    private $conn;

    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
    }

    public function checkIn() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $card_id = isset($_POST['card_id']) ? trim($_POST['card_id']) : '';
            $jenis = isset($_POST['jenis']) ? trim($_POST['jenis']) : 'Motor';

            if (empty($card_id)) {
                return array('status' => 'error', 'message' => 'Card ID tidak boleh kosong!');
            }

            // Check if card already checked in
            $checkQuery = "SELECT * FROM parkir WHERE card_id='$card_id' AND status='IN'";
            $checkResult = mysqli_query($this->conn, $checkQuery);

            if (mysqli_num_rows($checkResult) > 0) {
                return array('status' => 'error', 'message' => 'Kendaraan ini sudah parkir!');
            }

            // Insert check-in data (include vehicle type `jenis`)
            $checkin_time = date('Y-m-d H:i:s');
            $card_id_esc = mysqli_real_escape_string($this->conn, $card_id);
            $jenis_esc = mysqli_real_escape_string($this->conn, $jenis);
            $query = "INSERT INTO parkir (card_id, checkin_time, status, jenis) VALUES ('$card_id_esc', '$checkin_time', 'IN', '$jenis_esc')";
            
            if (mysqli_query($this->conn, $query)) {
                // return message for LCD and gate open command
                return array('status' => 'success', 'message' => 'Check-in berhasil! Card ID: ' . $card_id, 'lcd_message' => 'Selamat Datang Silakan Masuk', 'open_gate' => true);
            } else {
                return array('status' => 'error', 'message' => 'Gagal insert data: ' . mysqli_error($this->conn));
            }
        }
    }

    public function checkOut() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $card_id = isset($_POST['card_id']) ? trim($_POST['card_id']) : '';

            if (empty($card_id)) {
                return array('status' => 'error', 'message' => 'Card ID tidak boleh kosong!');
            }

            // Get the check-in record
            $query = "SELECT * FROM parkir WHERE card_id='$card_id' AND status='IN' LIMIT 1";
            $result = mysqli_query($this->conn, $query);

            if (mysqli_num_rows($result) == 0) {
                return array('status' => 'error', 'message' => 'Kendaraan tidak ada dalam sistem atau sudah keluar!');
            }

            $row = mysqli_fetch_assoc($result);
            $id = $row['id'];

            // Calculate duration and fee server-side using RATE rules (Rp 2.000 per hour, min Rp 2.000, extra hour if remainder >10 minutes)
            $checkin_time = strtotime($row['checkin_time']);
            $checkout_time = time();
            $duration = intval(round(($checkout_time - $checkin_time) / 60)); // minutes
            if ($duration < 0) $duration = 0;

            $RATE = 2000;
            if ($duration <= 60) {
                $fee_calculated = $RATE;
            } else {
                $hours = intval(floor($duration / 60));
                $rem = $duration % 60;
                $fee_calculated = $hours * $RATE;
                if ($rem > 10) $fee_calculated += $RATE;
            }

            // Update with check-out info
            $checkout_time_str = date('Y-m-d H:i:s');
            $updateQuery = "UPDATE parkir SET status='OUT', checkout_time='$checkout_time_str', duration=$duration, fee=$fee_calculated WHERE id=$id";

            if (mysqli_query($this->conn, $updateQuery)) {
                return array('status' => 'success', 'message' => 'Check-out berhasil! Durasi: ' . $duration . ' menit, Biaya: Rp ' . number_format($fee_calculated, 0, ',', '.'), 'lcd_message' => 'Terima Kasih Selamat Jalan', 'open_gate' => true);
            } else {
                return array('status' => 'error', 'message' => 'Gagal update data: ' . mysqli_error($this->conn));
            }
        }
    }

    // Return computed fee/duration without finalizing checkout (used when user scans at Exit to show LCD)
    public function requestCheckout() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $card_id = isset($_POST['card_id']) ? trim($_POST['card_id']) : '';
            if (empty($card_id)) {
                return array('status' => 'error', 'message' => 'Card ID tidak boleh kosong!');
            }

            $card_id_esc = mysqli_real_escape_string($this->conn, $card_id);
            $query = "SELECT * FROM parkir WHERE card_id='$card_id_esc' AND status='IN' LIMIT 1";
            $result = mysqli_query($this->conn, $query);
            if (!$result || mysqli_num_rows($result) == 0) {
                return array('status' => 'error', 'message' => 'Kendaraan tidak ada dalam sistem atau sudah keluar!');
            }

            $row = mysqli_fetch_assoc($result);
            $checkin_time = strtotime($row['checkin_time']);
            $now = time();
            $duration = intval(round(($now - $checkin_time) / 60));
            if ($duration < 0) $duration = 0;

            $RATE = 2000;
            if ($duration <= 60) {
                $fee = $RATE;
            } else {
                $hours = intval(floor($duration / 60));
                $rem = $duration % 60;
                $fee = $hours * $RATE;
                if ($rem > 10) $fee += $RATE;
            }

            return array('status' => 'success', 'card_id' => $card_id, 'duration' => $duration, 'fee' => $fee, 'message' => 'Total biaya Rp ' . number_format($fee,0,',','.'));
        }
    }
}

// Instantiate and check action
$action = isset($_POST['action']) ? $_POST['action'] : '';
$controller = new ParkingController();
$response = array();

if ($action == 'checkin') {
    $response = $controller->checkIn();
} elseif ($action == 'request_checkout') {
    $response = $controller->requestCheckout();
} elseif ($action == 'checkout_confirm') {
    $response = $controller->checkOut();
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>
