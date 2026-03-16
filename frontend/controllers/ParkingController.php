<?php
require_once '../models/Database.php';
require_once '../config/supabase.php';

class ParkingController
{
    private $db;
    private $conn;

    // Tarif parkir per jam
    private const RATE = 2000;

    public function __construct()
    {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
    }

    // ================================================================
    //  HELPER: Hitung durasi (menit) & biaya dari checkin_time
    // ================================================================
    private function calculateFee(string $checkin_time_str): array
    {
        $checkin = strtotime($checkin_time_str);
        $now = time();
        $duration = max(0, intval(round(($now - $checkin) / 60)));

        if ($duration <= 60) {
            $fee = self::RATE;
        } else {
            $hours = intval(floor($duration / 60));
            $rem = $duration % 60;
            $fee = $hours * self::RATE;
            if ($rem > 10)
                $fee += self::RATE;
        }

        return ['duration' => $duration, 'fee' => $fee];
    }

    // ================================================================
    //  CHECK-IN
    //  source: 'mysql' | 'supabase' | 'both'  (default: 'mysql')
    // ================================================================
    public function checkIn(): array
    {
        $card_id = trim($_POST['card_id'] ?? '');
        $jenis = trim($_POST['jenis'] ?? 'Motor');
        $source = trim($_POST['source'] ?? 'mysql');

        if (empty($card_id)) {
            return ['status' => 'error', 'message' => 'Card ID tidak boleh kosong!'];
        }

        $checkin_time = date('Y-m-d H:i:s');
        $errors = [];

        // ---- MySQL ----
        if ($source === 'mysql' || $source === 'both') {
            $card_esc = mysqli_real_escape_string($this->conn, $card_id);
            $jenis_esc = mysqli_real_escape_string($this->conn, $jenis);

            // Cek duplikat
            $dup = mysqli_query($this->conn, "SELECT id FROM parkir WHERE card_id='$card_esc' AND status='IN' LIMIT 1");
            if (mysqli_num_rows($dup) > 0) {
                $errors[] = 'MySQL: Kendaraan sudah tercatat parkir!';
            } else {
                $q = "INSERT INTO parkir (card_id, checkin_time, status, jenis)
                      VALUES ('$card_esc', '$checkin_time', 'IN', '$jenis_esc')";
                if (!mysqli_query($this->conn, $q)) {
                    $errors[] = 'MySQL: ' . mysqli_error($this->conn);
                }
            }
        }

        // ---- Supabase ----
        // Supabase tidak memiliki kolom jenis, jadi tidak disertakan
        if ($source === 'supabase' || $source === 'both') {
            // Cek duplikat di Supabase
            $sbCheck = koneksi_supabase(
                "GET",
                "parkir_tb_transaksi?card_id=eq." . urlencode($card_id) . "&status=eq.IN&select=id"
            );
            if (!empty($sbCheck)) {
                $errors[] = 'Supabase: Kendaraan sudah tercatat parkir!';
            } else {
                $payload = [
                    'card_id' => $card_id,
                    'checkin_time' => $checkin_time,
                    'status' => 'IN',
                ];
                $sbResult = koneksi_supabase("POST", "parkir_tb_transaksi", $payload);
                if (empty($sbResult)) {
                    $errors[] = 'Supabase: Gagal menyimpan data.';
                }
            }
        }

        if (!empty($errors)) {
            return ['status' => 'error', 'message' => implode(' | ', $errors)];
        }

        return [
            'status' => 'success',
            'message' => "Check-in berhasil! Card ID: $card_id (Sumber: $source)",
            'lcd_message' => 'Selamat Datang Silakan Masuk',
            'open_gate' => true,
        ];
    }

    // ================================================================
    //  REQUEST CHECKOUT — preview biaya tanpa finalisasi (MySQL)
    // ================================================================
    public function requestCheckout(): array
    {
        $card_id = trim($_POST['card_id'] ?? '');
        if (empty($card_id)) {
            return ['status' => 'error', 'message' => 'Card ID tidak boleh kosong!'];
        }

        $card_esc = mysqli_real_escape_string($this->conn, $card_id);
        $result = mysqli_query($this->conn, "SELECT * FROM parkir WHERE card_id='$card_esc' AND status='IN' LIMIT 1");

        if (!$result || mysqli_num_rows($result) === 0) {
            return ['status' => 'error', 'message' => 'Kendaraan tidak ada dalam sistem atau sudah keluar!'];
        }

        $row = mysqli_fetch_assoc($result);
        $calc = $this->calculateFee($row['checkin_time']);

        return [
            'status' => 'success',
            'card_id' => $card_id,
            'duration' => $calc['duration'],
            'fee' => $calc['fee'],
            'message' => 'Total biaya Rp ' . number_format($calc['fee'], 0, ',', '.'),
        ];
    }

    // ================================================================
    //  CHECKOUT CONFIRM — finalisasi & simpan ke MySQL
    // ================================================================
    public function checkOut(): array
    {
        $card_id = trim($_POST['card_id'] ?? '');
        if (empty($card_id)) {
            return ['status' => 'error', 'message' => 'Card ID tidak boleh kosong!'];
        }

        $card_esc = mysqli_real_escape_string($this->conn, $card_id);
        $result = mysqli_query($this->conn, "SELECT * FROM parkir WHERE card_id='$card_esc' AND status='IN' LIMIT 1");

        if (!$result || mysqli_num_rows($result) === 0) {
            return ['status' => 'error', 'message' => 'Kendaraan tidak ada dalam sistem atau sudah keluar!'];
        }

        $row = mysqli_fetch_assoc($result);
        $id = $row['id'];
        $calc = $this->calculateFee($row['checkin_time']);

        $checkout_str = date('Y-m-d H:i:s');
        $updateQuery = "UPDATE parkir
                         SET status='OUT',
                             checkout_time='$checkout_str',
                             duration={$calc['duration']},
                             fee={$calc['fee']}
                         WHERE id=$id";

        if (!mysqli_query($this->conn, $updateQuery)) {
            return ['status' => 'error', 'message' => 'Gagal update data: ' . mysqli_error($this->conn)];
        }

        return [
            'status' => 'success',
            'message' => 'Check-out berhasil! Durasi: ' . $calc['duration'] . ' menit, Biaya: Rp ' . number_format($calc['fee'], 0, ',', '.'),
            'duration' => $calc['duration'],
            'fee' => $calc['fee'],
            'lcd_message' => 'Terima Kasih Selamat Jalan',
            'open_gate' => true,
        ];
    }

    // ================================================================
    //  CHECKOUT SUPABASE — finalisasi data di Supabase
    //  Menggunakan PATCH dengan filter ?id=eq.{supabase_id}
    // ================================================================
    public function checkOutSupabase(): array
    {
        $card_id = trim($_POST['card_id'] ?? '');
        $supabase_id = trim($_POST['supabase_id'] ?? '');

        if (empty($card_id)) {
            return ['status' => 'error', 'message' => 'Card ID tidak boleh kosong!'];
        }

        // Ambil data dari Supabase untuk hitung durasi
        if (!empty($supabase_id)) {
            $sbData = koneksi_supabase("GET", "parkir_tb_transaksi?id=eq." . urlencode($supabase_id) . "&select=*");
        } else {
            // Fallback cari berdasarkan card_id + status IN
            $sbData = koneksi_supabase(
                "GET",
                "parkir_tb_transaksi?card_id=eq." . urlencode($card_id) . "&status=eq.IN&select=*&limit=1"
            );
        }

        if (empty($sbData) || !is_array($sbData) || empty($sbData[0])) {
            return ['status' => 'error', 'message' => 'Data tidak ditemukan di Supabase!'];
        }

        $record = $sbData[0];
        $checkin_str = $record['checkin_time'] ?? null;

        if (!$checkin_str) {
            return ['status' => 'error', 'message' => 'checkin_time tidak valid di Supabase!'];
        }

        $calc = $this->calculateFee($checkin_str);
        $checkout_str = date('Y-m-d H:i:s');

        // Tentukan filter endpoint: pakai id jika ada, fallback ke card_id
        $filterEndpoint = !empty($supabase_id)
            ? "parkir_tb_transaksi?id=eq." . urlencode($supabase_id)
            : "parkir_tb_transaksi?card_id=eq." . urlencode($card_id) . "&status=eq.IN";

        $payload = [
            'status' => 'OUT',
            'checkout_time' => $checkout_str,
            'duration' => $calc['duration'],
            'fee' => $calc['fee'],
        ];

        $sbResult = koneksi_supabase("PATCH", $filterEndpoint, $payload);

        // Supabase PATCH mengembalikan array kosong [] jika sukses tanpa prefer=return=representation,
        // atau array berisi row yang diupdate. Anggap sukses jika tidak null.
        if ($sbResult === null) {
            return ['status' => 'error', 'message' => 'Gagal update data di Supabase!'];
        }

        return [
            'status' => 'success',
            'message' => 'Check-out Supabase berhasil! Durasi: ' . $calc['duration'] . ' menit, Biaya: Rp ' . number_format($calc['fee'], 0, ',', '.'),
            'duration' => $calc['duration'],
            'fee' => $calc['fee'],
            'lcd_message' => 'Terima Kasih Selamat Jalan',
            'open_gate' => true,
        ];
    }
}

// ================================================================
//  ROUTER — Baca action dari POST, panggil method yang sesuai
// ================================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Method tidak didukung.']);
    exit;
}

$action = trim($_POST['action'] ?? '');
$controller = new ParkingController();
$response = ['status' => 'error', 'message' => 'Action tidak dikenali.'];

switch ($action) {
    case 'checkin':
        $response = $controller->checkIn();
        break;

    case 'request_checkout':
        $response = $controller->requestCheckout();
        break;

    case 'checkout_confirm':
        $response = $controller->checkOut();
        break;

    case 'checkout_supabase':
        $response = $controller->checkOutSupabase();
        break;
}

header('Content-Type: application/json');
echo json_encode($response);