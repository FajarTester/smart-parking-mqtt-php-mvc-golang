<?php
session_start();
require_once '../models/Database.php';

if(!isset($_SESSION['role']) || $_SESSION['role'] != "petugas") {
    header("location:login.php?pesan=gagal");
    exit();
}

$db = new Database();
$conn = $db->getConnection();

// Active parked vehicles (status IN)
$queryActiveList = "SELECT * FROM parkir WHERE status='IN' ORDER BY checkin_time ASC";
$resultActiveList = mysqli_query($conn, $queryActiveList);

// History / log
$queryHistory = "SELECT * FROM parkir ORDER BY checkin_time DESC LIMIT 200";
$resultHistory = mysqli_query($conn, $queryHistory);

// Summary statistics
$activeCount = 0;
$todayCount = 0;
$todayRevenue = 0;

$res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM parkir WHERE status='IN'");
if($res) { $r = mysqli_fetch_assoc($res); $activeCount = intval($r['cnt']); }

$res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM parkir WHERE DATE(checkin_time)=CURDATE()");
if($res) { $r = mysqli_fetch_assoc($res); $todayCount = intval($r['cnt']); }

$res = mysqli_query($conn, "SELECT SUM(fee) as total FROM parkir WHERE DATE(checkout_time)=CURDATE()");
if($res) { $r = mysqli_fetch_assoc($res); $todayRevenue = $r['total'] ? intval($r['total']) : 0; }
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Petugas - Smart Parkir</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="css/style.css">
    <style>
body {
    background: #eef2f7;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* ===== SIDEBAR ===== */
.sidebar {
    width: 250px;
    background: #1e293b;
    color: #fff;
    padding: 24px 18px;
    position: fixed;
    left: 0;
    top: 0;
    height: 100vh;
    box-shadow: 4px 0 12px rgba(0,0,0,0.08);
}

.sidebar h4 {
    font-weight: 600;
    letter-spacing: 0.5px;
}

.sidebar .nav-link {
    color: #cbd5e1;
    border-radius: 8px;
    padding: 10px 12px;
    transition: all 0.2s ease;
}

.sidebar .nav-link:hover {
    background: #334155;
    color: #fff;
}

.sidebar .nav-link:last-child {
    margin-top: auto;
}

/* ===== MAIN CONTENT ===== */
.main {
    margin-left: 250px;
    padding: 35px;
    min-height: 100vh;
}

/* ===== CARD STYLE ===== */
.card {
    border: none;
    border-radius: 14px;
    box-shadow: 0 6px 18px rgba(0,0,0,0.06);
}

.card-header {
    border-bottom: 1px solid #f1f1f1;
    font-weight: 600;
}

/* ===== STAT CARD ===== */
.card.bg-success,
.card[style*="#2980b9"],
.card[style*="#8e44ad"] {
    border-radius: 14px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}

/* ===== TABLE ===== */
.table thead {
    background: #f8fafc;
}

.table th {
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: .4px;
    color: #64748b;
}

.table td {
    vertical-align: middle;
}

/* ===== STATUS BADGE ===== */
.status-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.status-in {
    background: #dcfce7;
    color: #166534;
}

.status-out {
    background: #fee2e2;
    color: #991b1b;
}

/* ===== BUTTON ===== */
.btn-primary {
    border-radius: 8px;
}

.btn-danger {
    border-radius: 8px;
}

.btn-primary:hover,
.btn-danger:hover {
    opacity: 0.9;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
    .sidebar {
        width: 80px;
        padding: 15px 8px;
    }

    .sidebar h4 {
        display: none;
    }

    .main {
        margin-left: 80px;
        padding: 20px;
    }
}
</style>
</head>
<body>

    <div class="sidebar d-flex flex-column">
        <div class="brand text-center mb-3">
            <div style="font-size:28px">🅿️</div>
            <h4 class="mb-0">Smart Parkir</h4>
        </div>
        <nav class="nav flex-column">
            <a class="nav-link text-white" href="#">📋 Dashboard Petugas</a>
            <a class="nav-link text-white mt-auto" href="../controllers/LogoutController.php">🚪 Logout</a>
        </nav>
    </div>

    <div class="main">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h3 class="mb-0">Dashboard Petugas</h3>
                    <div class="text-muted small">Halo, <strong><?php echo htmlspecialchars($_SESSION['nama']); ?></strong></div>
                </div>
                <div class="text-muted small">Gunakan tombol pada daftar untuk menghitung biaya dan membuka palang</div>
            </div>

        <!-- Summary cards -->
        <div class="row mb-3">
            <div class="col-md-4">
                <div class="card text-white bg-success mb-2">
                    <div class="card-body">
                        <div class="small">Kendaraan Aktif</div>
                        <div class="h4 mb-0"><?php echo $activeCount; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white" style="background:linear-gradient(135deg,#2980b9,#1a5c7a);">
                    <div class="card-body">
                        <div class="small">Kendaraan Hari Ini</div>
                        <div class="h4 mb-0"><?php echo $todayCount; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white" style="background:linear-gradient(135deg,#8e44ad,#6c3483);">
                    <div class="card-body">
                        <div class="small">Pendapatan Hari Ini</div>
                        <div class="h5 mb-0">Rp <?php echo number_format($todayRevenue,0,',','.'); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Manual Check-In (for testing without hardware) -->
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title"> Input Manual Card</h5>
                <div class="row g-2 align-items-center">
                    <div class="col-md-6">
                        <input id="manual_card_id" class="form-control" placeholder="Masukkan Card ID" />
                    </div>
                    <div class="col-md-3">
                        <select id="manual_jenis" class="form-select">
                            <option value="Motor">Motor</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-primary w-100" onclick="manualCheckin()">Check-In</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"> Kendaraan Sedang Parkir (Check-In)</h6>
                    </div>
                    <div class="card-body" style="padding-top:12px;">
                    <?php if($resultActiveList && mysqli_num_rows($resultActiveList) > 0): ?>
                        <div class="table-responsive">
                        <table class="table table-striped table-sm">
                            <thead>
                                <tr><th>ID</th><th>Card ID</th><th>Check-In</th><th>Jenis</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                <?php while($r = mysqli_fetch_assoc($resultActiveList)): ?>
                                    <tr>
                                        <td><?php echo $r['id']; ?></td>
                                        <td><?php echo htmlspecialchars($r['card_id']); ?></td>
                                        <td><?php echo date('d-m-Y H:i:s', strtotime($r['checkin_time'])); ?></td>
                                        <td><?php echo isset($r['jenis']) ? htmlspecialchars($r['jenis']) : '-'; ?></td>
                                        <td><span class="status-badge status-in"><?php echo $r['status']; ?></span></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                        </div>
                    <?php else: ?>
                        <div style="padding:18px; color:#666">Tidak ada kendaraan sedang parkir.</div>
                    <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"> Daftar untuk Check-Out (Proses)</h6>
                    </div>
                    <div class="card-body" style="padding-top:12px;">
                    <?php
                    // reuse active list for checkout actions
                    $res = mysqli_query($conn, "SELECT * FROM parkir WHERE status='IN' ORDER BY checkin_time ASC");
                    if($res && mysqli_num_rows($res) > 0):
                    ?>
                        <div class="table-responsive">
                        <table class="table table-striped table-sm">
                            <thead>
                                <tr><th>Card ID</th><th>Check-In</th><th>Durasi</th><th>Aksi</th></tr>
                            </thead>
                            <tbody>
                                <?php while($row = mysqli_fetch_assoc($res)): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['card_id']); ?></td>
                                        <td><?php echo date('d-m-Y H:i:s', strtotime($row['checkin_time'])); ?></td>
                                        <td><?php echo isset($row['duration']) && $row['duration'] ? $row['duration'] . ' menit' : '-'; ?></td>
                                        <td>
                                            <button class="btn btn-danger" onclick="confirmExit('<?php echo htmlspecialchars($row['card_id']); ?>')">Keluar</button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                        </div>
                    <?php else: ?>
                        <div style="padding:18px; color:#666">Tidak ada kendaraan untuk diproses keluar.</div>
                    <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header bg-white">
                <h6 class="mb-0"> Log Riwayat Parkir</h6>
            </div>
            <div class="card-body" style="padding-top:12px;">
                    <?php if($resultHistory && mysqli_num_rows($resultHistory) > 0): ?>
                    <div class="table-responsive" style="max-height:420px; overflow:auto;">
                    <table class="table table-striped table-sm align-middle">
                        <thead>
                            <tr><th>ID</th><th>Card ID</th><th>Check-In</th><th>Check-Out</th><th>Durasi (menit)</th><th>Fee</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            <?php while($h = mysqli_fetch_assoc($resultHistory)): ?>
                                <tr>
                                    <td><?php echo $h['id']; ?></td>
                                    <td><?php echo htmlspecialchars($h['card_id']); ?></td>
                                    <td><?php echo date('d-m-Y H:i:s', strtotime($h['checkin_time'])); ?></td>
                                    <td><?php echo $h['checkout_time'] ? date('d-m-Y H:i:s', strtotime($h['checkout_time'])) : '-'; ?></td>
                                    <td><?php echo $h['duration'] ? $h['duration'] : '-'; ?></td>
                                    <td><?php echo $h['fee'] ? 'Rp ' . number_format($h['fee'],0,',','.') : '-'; ?></td>
                                    <td><?php echo $h['status']; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    </div>
                <?php else: ?>
                    <div class="text-muted py-3">Belum ada riwayat transaksi.</div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <script>
        function manualCheckin() {
            var card = document.getElementById('manual_card_id').value.trim();
            var jenis = document.getElementById('manual_jenis').value;
            if (!card) { alert('Masukkan Card ID terlebih dahulu'); return; }
            var data = 'action=checkin&card_id=' + encodeURIComponent(card) + '&jenis=' + encodeURIComponent(jenis);
            fetch('../controllers/ParkingController.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: data
            }).then(res => res.json()).then(json => {
                if (json.status === 'success') {
                    alert(json.message || 'Check-in berhasil');
                    location.reload();
                } else {
                    alert(json.message || 'Gagal check-in');
                }
            }).catch(err => { console.error(err); alert('Kesalahan jaringan'); });
        }

        function processCheckout(cardId) {
            if(!cardId) return;
            var data = 'action=request_checkout&card_id=' + encodeURIComponent(cardId);
            fetch('../controllers/ParkingController.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: data
            }).then(res => res.json()).then(json => {
                if(json.status === 'success') {
                    var msg = 'Card: ' + json.card_id + '\nDurasi: ' + json.duration + ' menit\nBiaya: Rp ' + new Intl.NumberFormat('id-ID').format(json.fee) + '\n\nLanjutkan proses checkout dan buka palang?';
                    if(confirm(msg)) {
                        finalizeCheckout(cardId);
                    }
                } else {
                    alert(json.message || 'Gagal menghitung biaya');
                }
            }).catch(err => { console.error(err); alert('Kesalahan jaringan'); });
        }

        function confirmExit(cardId) {
            if (!cardId) return;
            var sure = confirm('Anda yakin ingin memproses KELUAR untuk card ' + cardId + '? Tekan "OK" untuk melanjutkan atau "Cancel" untuk membatalkan.');
            if (sure) {
                finalizeCheckout(cardId);
            }
        }

        function finalizeCheckout(cardId) {
            var data = 'action=checkout_confirm&card_id=' + encodeURIComponent(cardId);
            fetch('../controllers/ParkingController.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: data
            }).then(res => res.json()).then(json => {
                if(json.status === 'success') {
                    alert(json.message || 'Check-out berhasil. Palang dibuka.');
                    // refresh page to update lists
                    location.reload();
                } else {
                    alert(json.message || 'Gagal memproses check-out');
                }
            }).catch(err => { console.error(err); alert('Kesalahan jaringan'); });
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
      
</body>
</html>
