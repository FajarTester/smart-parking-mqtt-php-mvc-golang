<?php
session_start();
require_once '../models/Database.php';
require_once '../config/supabase.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != "petugas") {
    header("location:login.php?pesan=gagal");
    exit();
}

// ============================================================
//  SUPABASE — Ambil semua transaksi (status IN maupun OUT)
// ============================================================
$supabaseAll = koneksi_supabase("GET", "parkir_tb_transaksi?select=*&order=checkin_time.desc");
if (!is_array($supabaseAll))
    $supabaseAll = [];

// ============================================================
//  MYSQL
// ============================================================
$db = new Database();
$conn = $db->getConnection();

// Active parked vehicles (status IN)
$resultActiveList = mysqli_query($conn, "SELECT * FROM parkir WHERE status='IN' ORDER BY checkin_time ASC");

// History log
$resultHistory = mysqli_query($conn, "SELECT * FROM parkir ORDER BY checkin_time DESC LIMIT 200");

// Summary statistics — MySQL
$activeCount = 0;
$todayCount = 0;
$todayRevenue = 0;

$res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM parkir WHERE status='IN'");
if ($res) {
    $r = mysqli_fetch_assoc($res);
    $activeCount = intval($r['cnt']);
}

$res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM parkir WHERE DATE(checkin_time)=CURDATE()");
if ($res) {
    $r = mysqli_fetch_assoc($res);
    $todayCount = intval($r['cnt']);
}

$res = mysqli_query($conn, "SELECT SUM(fee) as total FROM parkir WHERE DATE(checkout_time)=CURDATE()");
if ($res) {
    $r = mysqli_fetch_assoc($res);
    $todayRevenue = $r['total'] ? intval($r['total']) : 0;
}

// Summary statistics — Supabase (supaya total lebih lengkap)
$supabaseActive = 0;
$supabaseRevenue = 0;
foreach ($supabaseAll as $sb) {
    if (isset($sb['status']) && strtoupper($sb['status']) === 'IN')
        $supabaseActive++;
    if (isset($sb['fee']) && $sb['fee'])
        $supabaseRevenue += intval($sb['fee']);
}

// ============================================================
//  Gabungkan data MySQL + Supabase ke satu array untuk tabel
//  gabungan (History). Supabase tidak punya kolom 'jenis',
//  jadi kita isi '-' sebagai nilai default.
// ============================================================
$mysqlRows = [];
$mysqlHistory = mysqli_query($conn, "SELECT * FROM parkir ORDER BY checkin_time DESC LIMIT 200");
while ($h = mysqli_fetch_assoc($mysqlHistory)) {
    $mysqlRows[] = [
        'source' => 'MySQL',
        'id' => $h['id'],
        'card_id' => $h['card_id'],
        'checkin_time' => $h['checkin_time'],
        'checkout_time' => $h['checkout_time'] ?? null,
        'duration' => $h['duration'] ?? null,
        'fee' => $h['fee'] ?? null,
        'status' => $h['status'],
        'jenis' => $h['jenis'] ?? '-',
    ];
}

$supabaseRows = [];
foreach ($supabaseAll as $sb) {
    $supabaseRows[] = [
        'source' => 'Supabase',
        'id' => $sb['id'] ?? '-',
        'card_id' => $sb['card_id'] ?? '-',
        'checkin_time' => $sb['checkin_time'] ?? null,
        'checkout_time' => $sb['checkout_time'] ?? null,
        'duration' => $sb['duration'] ?? null,
        'fee' => $sb['fee'] ?? null,
        'status' => $sb['status'] ?? '-',
        'jenis' => '-',   // Su
        // miliki kolom jenis
    ];
}

// Merge dan urutkan berdasarkan checkin_time DESC
$mergedHistory = array_merge($mysqlRows, $supabaseRows);
usort($mergedHistory, function ($a, $b) {
    return strtotime($b['checkin_time']) - strtotime($a['checkin_time']);
});

// Total gabungan
$totalActiveCount = $activeCount + $supabaseActive;
$totalRevenue = $todayRevenue + $supabaseRevenue;
$totalTodayCount = $todayCount; // Supabase tidak ada filter hari ini yg bisa kita pastikan, pakai MySQL saja
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Petugas – Smart Parkir</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@500&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <style>
        :root {
            --bg: #0d1117;
            --surface: #161b22;
            --surface2: #1f2937;
            --border: #30363d;
            --accent: #58a6ff;
            --green: #3fb950;
            --red: #f85149;
            --yellow: #d29922;
            --purple: #bc8cff;
            --text: #e6edf3;
            --muted: #8b949e;
            --radius: 12px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 14px;
        }

        /* ── SIDEBAR ── */
        .sidebar {
            width: 230px;
            background: var(--surface);
            border-right: 1px solid var(--border);
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
            padding: 24px 16px;
            gap: 6px;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 10px 20px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 8px;
        }

        .sidebar-brand .icon {
            font-size: 22px;
        }

        .sidebar-brand .name {
            font-weight: 700;
            font-size: 15px;
            letter-spacing: -.3px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 8px;
            color: var(--muted);
            text-decoration: none;
            transition: background .15s, color .15s;
            font-size: 13.5px;
            font-weight: 500;
        }

        .nav-item:hover,
        .nav-item.active {
            background: var(--surface2);
            color: var(--text);
        }

        .nav-item .badge-count {
            margin-left: auto;
            background: var(--accent);
            color: #0d1117;
            border-radius: 20px;
            padding: 1px 7px;
            font-size: 11px;
            font-weight: 700;
        }

        .nav-spacer {
            flex: 1;
        }

        /* ── MAIN ── */
        .main {
            margin-left: 230px;
            padding: 28px 32px;
            min-height: 100vh;
        }

        /* ── HEADER ── */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 24px;
        }

        .page-header h3 {
            font-size: 20px;
            font-weight: 700;
        }

        .page-header .sub {
            color: var(--muted);
            font-size: 13px;
            margin-top: 4px;
        }

        .clock {
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            color: var(--muted);
        }

        /* ── STAT CARDS ── */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 18px 20px;
        }

        .stat-card .label {
            font-size: 12px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .6px;
            margin-bottom: 8px;
        }

        .stat-card .value {
            font-size: 26px;
            font-weight: 700;
            font-family: 'JetBrains Mono', monospace;
        }

        .stat-card .accent-line {
            height: 3px;
            border-radius: 2px;
            margin-top: 10px;
        }

        .al-green {
            background: var(--green);
        }

        .al-blue {
            background: var(--accent);
        }

        .al-purple {
            background: var(--purple);
        }

        .al-yellow {
            background: var(--yellow);
        }

        /* ── SOURCE BADGE ── */
        .src-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .src-mysql {
            background: rgba(88, 166, 255, .15);
            color: var(--accent);
        }

        .src-supabase {
            background: rgba(63, 185, 80, .15);
            color: var(--green);
        }

        /* ── STATUS BADGE ── */
        .status-badge {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .status-in {
            background: rgba(63, 185, 80, .15);
            color: var(--green);
        }

        .status-out {
            background: rgba(248, 81, 73, .15);
            color: var(--red);
        }

        /* ── CARDS / PANELS ── */
        .panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
            margin-bottom: 20px;
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 18px;
            border-bottom: 1px solid var(--border);
            font-weight: 600;
            font-size: 13.5px;
        }

        .panel-body {
            padding: 16px 18px;
        }

        /* ── TABLE ── */
        .custom-table {
            width: 100%;
            border-collapse: collapse;
        }

        .custom-table th {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: var(--muted);
            padding: 8px 12px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        .custom-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #21262d;
            font-size: 13px;
            vertical-align: middle;
        }

        .custom-table tr:last-child td {
            border-bottom: none;
        }

        .custom-table tr:hover td {
            background: #1a2030;
        }

        .mono {
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
        }

        /* ── FORM ── */
        .form-control-dark,
        .form-select-dark {
            background: var(--surface2);
            border: 1px solid var(--border);
            color: var(--text);
            border-radius: 8px;
            padding: 9px 14px;
            font-size: 13.5px;
            width: 100%;
            outline: none;
            transition: border-color .2s;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .form-control-dark:focus,
        .form-select-dark:focus {
            border-color: var(--accent);
        }

        .form-select-dark option {
            background: var(--surface2);
        }

        /* ── BUTTONS ── */
        .btn-acc {
            background: var(--accent);
            color: #0d1117;
            border: none;
            border-radius: 8px;
            padding: 9px 18px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: opacity .15s;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .btn-acc:hover {
            opacity: .85;
        }

        .btn-danger-sm {
            background: rgba(248, 81, 73, .15);
            color: var(--red);
            border: 1px solid rgba(248, 81, 73, .3);
            border-radius: 7px;
            padding: 5px 12px;
            font-weight: 600;
            font-size: 12px;
            cursor: pointer;
            transition: background .15s;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .btn-danger-sm:hover {
            background: rgba(248, 81, 73, .3);
        }

        .btn-warning-sm {
            background: rgba(210, 153, 34, .12);
            color: var(--yellow);
            border: 1px solid rgba(210, 153, 34, .3);
            border-radius: 7px;
            padding: 5px 12px;
            font-weight: 600;
            font-size: 12px;
            cursor: pointer;
            transition: background .15s;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .btn-warning-sm:hover {
            background: rgba(210, 153, 34, .25);
        }

        /* ── SEARCH / FILTER BAR ── */
        .filter-bar {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 14px;
            flex-wrap: wrap;
        }

        .filter-bar .form-control-dark {
            max-width: 220px;
        }

        /* ── TOAST ── */
        #toast-container {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .toast-msg {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px 18px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn .3s ease;
            min-width: 260px;
        }

        .toast-msg.success {
            border-color: var(--green);
        }

        .toast-msg.error {
            border-color: var(--red);
        }

        @keyframes slideIn {
            from {
                transform: translateX(30px);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* ── MODAL ── */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .65);
            z-index: 8000;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal-box {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 28px;
            max-width: 420px;
            width: 90%;
        }

        .modal-box h5 {
            font-weight: 700;
            margin-bottom: 14px;
        }

        .modal-detail {
            background: var(--surface2);
            border-radius: 8px;
            padding: 14px 16px;
            margin-bottom: 16px;
        }

        .modal-detail .row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            font-size: 13.5px;
        }

        .modal-detail .row .k {
            color: var(--muted);
        }

        .modal-detail .row .v {
            font-weight: 600;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .btn-cancel {
            background: var(--surface2);
            color: var(--text);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 9px 18px;
            cursor: pointer;
            font-family: inherit;
            font-size: 13px;
        }

        /* ── SCROLLABLE TABLE ── */
        .table-scroll {
            max-height: 380px;
            overflow-y: auto;
        }

        /* ── TABS ── */
        .tabs {
            display: flex;
            gap: 2px;
            background: var(--surface2);
            padding: 4px;
            border-radius: 10px;
            margin-bottom: 16px;
            width: fit-content;
        }

        .tab-btn {
            padding: 7px 18px;
            border-radius: 7px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            background: transparent;
            color: var(--muted);
            transition: all .15s;
            font-family: inherit;
        }

        .tab-btn.active {
            background: var(--surface);
            color: var(--text);
        }

        @media (max-width: 900px) {
            .stat-grid {
                grid-template-columns: 1fr 1fr;
            }

            .sidebar {
                width: 60px;
                padding: 16px 8px;
            }

            .sidebar .sidebar-brand .name,
            .nav-item span {
                display: none;
            }

            .main {
                margin-left: 60px;
                padding: 18px;
            }
        }
    </style>
</head>

<body>

    <!-- ===== SIDEBAR ===== -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="icon">🅿️</div>
            <div class="name">Smart Parkir</div>
        </div>
        <a class="nav-item active" href="#">
            📋 <span>Dashboard</span>
            <span class="badge-count"><?php echo $totalActiveCount; ?></span>
        </a>
        <a class="nav-item" href="#gabungan">
            🔗 <span>Log Gabungan</span>
        </a>
        <div class="nav-spacer"></div>
        <a class="nav-item" href="../controllers/LogoutController.php">
            🚪 <span>Logout</span>
        </a>
    </aside>

    <!-- ===== MAIN ===== -->
    <main class="main">

        <!-- Header -->
        <div class="page-header">
            <div>
                <h3>Dashboard Petugas</h3>
                <div class="sub">Halo, <strong><?php echo htmlspecialchars($_SESSION['nama']); ?></strong> &mdash; Data
                    dari MySQL &amp; Supabase</div>
            </div>
            <div class="clock" id="liveClock"></div>
        </div>

        <!-- Stat Cards -->
        <div class="stat-grid">
            <div class="stat-card">
                <div class="label">Kendaraan Aktif</div>
                <div class="value" style="color:var(--green)"><?php echo $totalActiveCount; ?></div>
                <div class="accent-line al-green"></div>
            </div>
            <div class="stat-card">
                <div class="label">Kendaraan Hari Ini</div>
                <div class="value" style="color:var(--accent)"><?php echo $totalTodayCount; ?></div>
                <div class="accent-line al-blue"></div>
            </div>
            <div class="stat-card">
                <div class="label">Pendapatan Hari Ini</div>
                <div class="value" style="color:var(--purple); font-size:18px;">Rp
                    <?php echo number_format($todayRevenue, 0, ',', '.'); ?></div>
                <div class="accent-line al-purple"></div>
            </div>
            <div class="stat-card">
                <div class="label">Total Pendapatan (Supabase)</div>
                <div class="value" style="color:var(--yellow); font-size:18px;">Rp
                    <?php echo number_format($supabaseRevenue, 0, ',', '.'); ?></div>
                <div class="accent-line al-yellow"></div>
            </div>
        </div>

        <!-- Manual Check-In -->
        <div class="panel">
            <div class="panel-header">
                <span>✋ Input Manual Check-In</span>
            </div>
            <div class="panel-body">
                <div style="display:grid; grid-template-columns:1fr 180px 160px 140px; gap:10px; align-items:center;">
                    <input id="manual_card_id" class="form-control-dark" placeholder="Masukkan Card ID" />
                    <select id="manual_jenis" class="form-select-dark">
                        <option value="Motor">Motor</option>
                        <option value="Mobil">Mobil</option>
                        <option value="Truck">Truck</option>
                    </select>
                    <select id="manual_source" class="form-select-dark">
                        <option value="mysql">Simpan ke MySQL</option>
                        <option value="supabase">Simpan ke Supabase</option>
                        <option value="both">Simpan ke Keduanya</option>
                    </select>
                    <button class="btn-acc" onclick="manualCheckin()">Check-In</button>
                </div>
            </div>
        </div>

        <!-- Two-column: Active Parking + Checkout List -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px;">

            <!-- Active Parking -->
            <div class="panel">
                <div class="panel-header">
                    <span>🟢 Kendaraan Sedang Parkir</span>
                    <span style="font-size:12px; color:var(--muted); font-weight:400;">MySQL</span>
                </div>
                <div class="panel-body" style="padding:0;">
                    <div class="table-scroll">
                        <?php if ($resultActiveList && mysqli_num_rows($resultActiveList) > 0): ?>
                            <table class="custom-table">
                                <thead>
                                    <tr>
                                        <th>Card ID</th>
                                        <th>Check-In</th>
                                        <th>Jenis</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // reset pointer
                                    mysqli_data_seek($resultActiveList, 0);
                                    while ($r = mysqli_fetch_assoc($resultActiveList)):
                                        ?>
                                        <tr>
                                            <td class="mono"><?php echo htmlspecialchars($r['card_id']); ?></td>
                                            <td><?php echo date('d/m H:i', strtotime($r['checkin_time'])); ?></td>
                                            <td><?php echo htmlspecialchars($r['jenis'] ?? '-'); ?></td>
                                            <td><span class="status-badge status-in">IN</span></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div style="padding:20px; color:var(--muted); text-align:center;">Tidak ada kendaraan parkir.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Checkout List -->
            <div class="panel">
                <div class="panel-header">
                    <span>🔴 Proses Check-Out</span>
                    <span style="font-size:12px; color:var(--muted); font-weight:400;">MySQL</span>
                </div>
                <div class="panel-body" style="padding:0;">
                    <div class="table-scroll">
                        <?php
                        $res = mysqli_query($conn, "SELECT * FROM parkir WHERE status='IN' ORDER BY checkin_time ASC");
                        if ($res && mysqli_num_rows($res) > 0):
                            ?>
                            <table class="custom-table">
                                <thead>
                                    <tr>
                                        <th>Card ID</th>
                                        <th>Check-In</th>
                                        <th>Durasi</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = mysqli_fetch_assoc($res)): ?>
                                        <tr>
                                            <td class="mono"><?php echo htmlspecialchars($row['card_id']); ?></td>
                                            <td><?php echo date('d/m H:i', strtotime($row['checkin_time'])); ?></td>
                                            <td><?php echo isset($row['duration']) && $row['duration'] ? $row['duration'] . ' mnt' : '-'; ?>
                                            </td>
                                            <td>
                                                <button class="btn-danger-sm"
                                                    onclick="confirmExit('<?php echo htmlspecialchars($row['card_id']); ?>', '<?php echo date('d/m/Y H:i', strtotime($row['checkin_time'])); ?>')">
                                                    Keluar
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div style="padding:20px; color:var(--muted); text-align:center;">Tidak ada antrian checkout.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Supabase Active -->
        <?php if (!empty($supabaseAll)): ?>
            <div class="panel" style="margin-bottom:20px;">
                <div class="panel-header">
                    <span>🟢 Kendaraan Aktif — Supabase</span>
                    <span style="font-size:12px; color:var(--green); font-weight:600;">● Live</span>
                </div>
                <div class="panel-body" style="padding:0;">
                    <div class="table-scroll">
                        <?php
                        $sbActive = array_filter($supabaseAll, fn($s) => strtoupper($s['status'] ?? '') === 'IN');
                        if (!empty($sbActive)):
                            ?>
                            <table class="custom-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Card ID</th>
                                        <th>Check-In</th>
                                        <th>Check-Out</th>
                                        <th>Fee</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sbActive as $sb): ?>
                                        <tr>
                                            <td class="mono" style="color:var(--muted)"><?php echo $sb['id'] ?? '-'; ?></td>
                                            <td class="mono"><?php echo htmlspecialchars($sb['card_id'] ?? '-'); ?></td>
                                            <td><?php echo $sb['checkin_time'] ? date('d/m H:i', strtotime($sb['checkin_time'])) : '-'; ?>
                                            </td>
                                            <td><?php echo $sb['checkout_time'] ? date('d/m H:i', strtotime($sb['checkout_time'])) : '-'; ?>
                                            </td>
                                            <td><?php echo $sb['fee'] ? 'Rp ' . number_format($sb['fee'], 0, ',', '.') : '-'; ?>
                                            </td>
                                            <td><span class="status-badge status-in">IN</span></td>
                                            <td>
                                                <button class="btn-danger-sm"
                                                    onclick="confirmExitSupabase('<?php echo htmlspecialchars($sb['card_id'] ?? ''); ?>', '<?php echo $sb['id'] ?? ''; ?>')">
                                                    Keluar
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div style="padding:20px; color:var(--muted); text-align:center;">Tidak ada kendaraan aktif di
                                Supabase.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- ====== LOG GABUNGAN MYSQL + SUPABASE ====== -->
        <div class="panel" id="gabungan">
            <div class="panel-header">
                <span>📊 Log Gabungan — MySQL &amp; Supabase</span>
                <span style="font-size:12px; color:var(--muted);"><?php echo count($mergedHistory); ?> entri</span>
            </div>
            <div class="panel-body">

                <!-- Tabs: All / MySQL / Supabase -->
                <div
                    style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:12px;">
                    <div class="tabs">
                        <button class="tab-btn active" onclick="filterTab('all', this)">Semua</button>
                        <button class="tab-btn" onclick="filterTab('MySQL', this)">MySQL</button>
                        <button class="tab-btn" onclick="filterTab('Supabase', this)">Supabase</button>
                    </div>
                    <div class="filter-bar" style="margin-bottom:0;">
                        <input class="form-control-dark" id="searchInput" placeholder="🔍 Cari card ID…"
                            oninput="filterSearch()" style="max-width:200px;">
                        <select class="form-select-dark" id="statusFilter" onchange="filterSearch()"
                            style="max-width:140px;">
                            <option value="">Semua Status</option>
                            <option value="IN">IN</option>
                            <option value="OUT">OUT</option>
                        </select>
                        <button class="btn-acc" onclick="exportCSV()" style="padding:8px 14px; font-size:12px;">⬇ Export
                            CSV</button>
                    </div>
                </div>

                <div class="table-scroll" id="mergedTableWrapper">
                    <table class="custom-table" id="mergedTable">
                        <thead>
                            <tr>
                                <th>Sumber</th>
                                <th>ID</th>
                                <th>Card ID</th>
                                <th>Check-In</th>
                                <th>Check-Out</th>
                                <th>Durasi</th>
                                <th>Fee</th>
                                <th>Jenis</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="mergedTbody">
                            <?php foreach ($mergedHistory as $row): ?>
                                <tr data-source="<?php echo $row['source']; ?>"
                                    data-cardid="<?php echo strtolower(htmlspecialchars($row['card_id'])); ?>"
                                    data-status="<?php echo strtoupper($row['status']); ?>">
                                    <td>
                                        <span
                                            class="src-badge <?php echo $row['source'] === 'MySQL' ? 'src-mysql' : 'src-supabase'; ?>">
                                            <?php echo $row['source'] === 'MySQL' ? '🗄 MySQL' : '☁ Supabase'; ?>
                                        </span>
                                    </td>
                                    <td class="mono" style="color:var(--muted)"><?php echo $row['id']; ?></td>
                                    <td class="mono"><?php echo htmlspecialchars($row['card_id']); ?></td>
                                    <td><?php echo $row['checkin_time'] ? date('d/m/Y H:i', strtotime($row['checkin_time'])) : '-'; ?>
                                    </td>
                                    <td><?php echo $row['checkout_time'] ? date('d/m/Y H:i', strtotime($row['checkout_time'])) : '-'; ?>
                                    </td>
                                    <td><?php echo $row['duration'] ? $row['duration'] . ' mnt' : '-'; ?></td>
                                    <td><?php echo $row['fee'] ? 'Rp ' . number_format($row['fee'], 0, ',', '.') : '-'; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['jenis']); ?></td>
                                    <td>
                                        <span
                                            class="status-badge <?php echo strtoupper($row['status']) === 'IN' ? 'status-in' : 'status-out'; ?>">
                                            <?php echo strtoupper($row['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>

    </main>

    <!-- ===== CHECKOUT CONFIRM MODAL ===== -->
    <div class="modal-overlay" id="checkoutModal">
        <div class="modal-box">
            <h5>Konfirmasi Check-Out</h5>
            <div class="modal-detail">
                <div class="row"><span class="k">Card ID</span><span class="v" id="m-cardid">-</span></div>
                <div class="row"><span class="k">Check-In</span><span class="v" id="m-checkin">-</span></div>
                <div class="row"><span class="k">Durasi</span><span class="v" id="m-duration">Menghitung…</span></div>
                <div class="row"><span class="k">Biaya</span><span class="v" id="m-fee">Menghitung…</span></div>
            </div>
            <div class="modal-actions">
                <button class="btn-cancel" onclick="closeModal()">Batal</button>
                <button class="btn-acc" id="m-confirmBtn" onclick="finalizeCheckout()">Konfirmasi Keluar</button>
            </div>
        </div>
    </div>

    <!-- ===== TOAST CONTAINER ===== -->
    <div id="toast-container"></div>

    <script>
        // ──────────────────────────────────────────────
        //  LIVE CLOCK
        // ──────────────────────────────────────────────
        function updateClock() {
            const now = new Date();
            const str = now.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
                + ' · ' + now.toLocaleTimeString('id-ID');
            document.getElementById('liveClock').textContent = str;
        }
        updateClock();
        setInterval(updateClock, 1000);

        // ──────────────────────────────────────────────
        //  TOAST
        // ──────────────────────────────────────────────
        function showToast(msg, type = 'success') {
            const el = document.createElement('div');
            el.className = `toast-msg ${type}`;
            el.innerHTML = (type === 'success' ? '✅' : '❌') + ' ' + msg;
            document.getElementById('toast-container').appendChild(el);
            setTimeout(() => el.remove(), 4000);
        }

        // ──────────────────────────────────────────────
        //  MANUAL CHECK-IN
        // ──────────────────────────────────────────────
        function manualCheckin() {
            const card = document.getElementById('manual_card_id').value.trim();
            const jenis = document.getElementById('manual_jenis').value;
            const source = document.getElementById('manual_source').value;
            if (!card) { showToast('Masukkan Card ID terlebih dahulu', 'error'); return; }
            const body = `action=checkin&card_id=${encodeURIComponent(card)}&jenis=${encodeURIComponent(jenis)}&source=${encodeURIComponent(source)}`;
            fetch('../controllers/ParkingController.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body
            }).then(r => r.json()).then(json => {
                if (json.status === 'success') { showToast(json.message || 'Check-in berhasil'); setTimeout(() => location.reload(), 1500); }
                else showToast(json.message || 'Gagal check-in', 'error');
            }).catch(() => showToast('Kesalahan jaringan', 'error'));
        }

        // ──────────────────────────────────────────────
        //  CHECKOUT MODAL — MySQL
        // ──────────────────────────────────────────────
        let _activeCardId = null;

        function confirmExit(cardId, checkinStr) {
            _activeCardId = cardId;
            document.getElementById('m-cardid').textContent = cardId;
            document.getElementById('m-checkin').textContent = checkinStr;
            document.getElementById('m-duration').textContent = 'Menghitung…';
            document.getElementById('m-fee').textContent = 'Menghitung…';
            document.getElementById('checkoutModal').classList.add('show');

            // Request preview biaya dulu
            fetch('../controllers/ParkingController.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=request_checkout&card_id=${encodeURIComponent(cardId)}`
            }).then(r => r.json()).then(json => {
                if (json.status === 'success') {
                    document.getElementById('m-duration').textContent = json.duration + ' menit';
                    document.getElementById('m-fee').textContent = 'Rp ' + new Intl.NumberFormat('id-ID').format(json.fee);
                } else {
                    document.getElementById('m-duration').textContent = '-';
                    document.getElementById('m-fee').textContent = json.message || 'Error';
                }
            }).catch(() => { document.getElementById('m-fee').textContent = 'Error'; });
        }

        function closeModal() {
            document.getElementById('checkoutModal').classList.remove('show');
            _activeCardId = null;
        }

        function finalizeCheckout() {
            if (!_activeCardId) return;
            fetch('../controllers/ParkingController.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=checkout_confirm&card_id=${encodeURIComponent(_activeCardId)}`
            }).then(r => r.json()).then(json => {
                closeModal();
                if (json.status === 'success') { showToast(json.message || 'Check-out berhasil. Palang dibuka.'); setTimeout(() => location.reload(), 1500); }
                else showToast(json.message || 'Gagal checkout', 'error');
            }).catch(() => showToast('Kesalahan jaringan', 'error'));
        }

        // ──────────────────────────────────────────────
        //  CHECKOUT — Supabase
        // ──────────────────────────────────────────────
        function confirmExitSupabase(cardId, supabaseId) {
            if (!confirm(`Proses keluar untuk Card ID: ${cardId}?`)) return;
            fetch('../controllers/ParkingController.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=checkout_supabase&card_id=${encodeURIComponent(cardId)}&supabase_id=${encodeURIComponent(supabaseId)}`
            }).then(r => r.json()).then(json => {
                if (json.status === 'success') { showToast('Check-out Supabase berhasil'); setTimeout(() => location.reload(), 1500); }
                else showToast(json.message || 'Gagal checkout Supabase', 'error');
            }).catch(() => showToast('Kesalahan jaringan', 'error'));
        }

        // ──────────────────────────────────────────────
        //  TAB FILTER
        // ──────────────────────────────────────────────
        let _activeTab = 'all';
        function filterTab(src, btn) {
            _activeTab = src;
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            filterSearch();
        }

        function filterSearch() {
            const q = document.getElementById('searchInput').value.toLowerCase();
            const status = document.getElementById('statusFilter').value.toUpperCase();
            document.querySelectorAll('#mergedTbody tr').forEach(tr => {
                const matchSrc = _activeTab === 'all' || tr.dataset.source === _activeTab;
                const matchCard = !q || tr.dataset.cardid.includes(q);
                const matchStatus = !status || tr.dataset.status === status;
                tr.style.display = (matchSrc && matchCard && matchStatus) ? '' : 'none';
            });
        }

        // ──────────────────────────────────────────────
        //  EXPORT CSV
        // ──────────────────────────────────────────────
        function exportCSV() {
            const rows = [['Sumber', 'ID', 'Card ID', 'Check-In', 'Check-Out', 'Durasi', 'Fee', 'Jenis', 'Status']];
            document.querySelectorAll('#mergedTbody tr').forEach(tr => {
                if (tr.style.display === 'none') return;
                const cells = tr.querySelectorAll('td');
                rows.push([
                    tr.dataset.source,
                    cells[1].textContent.trim(),
                    cells[2].textContent.trim(),
                    cells[3].textContent.trim(),
                    cells[4].textContent.trim(),
                    cells[5].textContent.trim(),
                    cells[6].textContent.trim(),
                    cells[7].textContent.trim(),
                    cells[8].textContent.trim(),
                ]);
            });
            const csv = rows.map(r => r.map(c => `"${c.replace(/"/g, '""')}"`).join(',')).join('\n');
            const a = document.createElement('a');
            a.href = 'data:text/csv;charset=utf-8,\uFEFF' + encodeURIComponent(csv);
            a.download = `log_parkir_${new Date().toISOString().slice(0, 10)}.csv`;
            a.click();
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>