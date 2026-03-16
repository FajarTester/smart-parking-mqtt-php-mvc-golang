<?php 
session_start();
require_once '../models/Database.php';

if(!isset($_SESSION['role']) || $_SESSION['role'] != "owner") {
	header("location:login.php?pesan=gagal");
	exit();
}

$db = new Database();
$conn = $db->getConnection();

$totalTransaksi = 0;
$totalPendapatan = 0;
$kendaraanAktif = 0;

$queryTotal = "SELECT COUNT(*) as count FROM parkir";
$resultTotal = mysqli_query($conn, $queryTotal);
if($resultTotal) {
	$rowTotal = mysqli_fetch_assoc($resultTotal);
	$totalTransaksi = $rowTotal['count'];
}

$queryRevenue = "SELECT SUM(fee) as total FROM parkir";
$resultRevenue = mysqli_query($conn, $queryRevenue);
if($resultRevenue) {
	$rowRevenue = mysqli_fetch_assoc($resultRevenue);
	$totalPendapatan = $rowRevenue['total'] ? $rowRevenue['total'] : 0;
}

$queryActive = "SELECT COUNT(*) as count FROM parkir WHERE status='IN'";
$resultActive = mysqli_query($conn, $queryActive);
if($resultActive) {
	$rowActive = mysqli_fetch_assoc($resultActive);
	$kendaraanAktif = $rowActive['count'];
}
?>
<!DOCTYPE html>
<html>
<head>
	<title>Dashboard Owner - Smart Parkir</title>
	<link rel="stylesheet" type="text/css" href="css/style.css">
	<style>
		* {
			margin: 0;
			padding: 0;
			box-sizing: border-box;
		}

		body {
			font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
			background-color: #f4f7fa;
			display: flex;
			min-height: 100vh;
		}

		/* SIDEBAR */
		.sidebar {
			width: 250px;
			background: linear-gradient(135deg, #27a8cf 0%, #1b65d4 100%);
			color: white;
			padding: 20px 0;
			position: fixed;
			left: 0;
			top: 0;
			height: 100vh;
			box-shadow: 2px 0 10px rgba(0,0,0,0.2);
		}

		.sidebar-brand {
			padding: 20px;
			text-align: center;
			border-bottom: 1px solid rgba(207, 19, 19, 0.1);
			margin-bottom: 20px;
		}

		.sidebar-brand h2 {
			font-size: 20px;
			margin-bottom: 5px;
		}

		.sidebar-brand .icon {
			font-size: 30px;
			margin-bottom: 10px;
		}

		.sidebar-menu {
			list-style: none;
		}

		.sidebar-menu li {
			padding: 0;
		}

		.sidebar-menu a {
			display: block;
			padding: 15px 20px;
			color: white;
			text-decoration: none;
			transition: all 0.3s ease;
			border-left: 3px solid transparent;
		}

		.sidebar-menu a:hover {
			background-color: rgba(255,255,255,0.1);
			border-left-color: #0ac423;
		}

		.sidebar-menu a.active {
			background-color: rgba(255,255,255,0.2);
			border-left-color: #f39c12;
			font-weight: bold;
		}

		.sidebar-logout {
			position: absolute;
			bottom: 20px;
			width: calc(100% - 40px);
			margin: 0 20px;
		}

		.sidebar-logout a {
			background-color: rgba(255,255,255,0.2);
			text-align: center;
			margin-top: 10px;
			border-radius: 5px;
			border: 2px solid white;
			padding: 12px !important;
		}

		.sidebar-logout a:hover {
			background-color: white;
			color: #f01919;
		}

		/* MAIN CONTENT */
		.main-content {
			margin-left: 250px;
			flex: 1;
			padding: 30px;
		}

		.top-bar {
			background: white;
			padding: 15px 20px;
			border-radius: 8px;
			margin-bottom: 30px;
			box-shadow: 0 2px 5px rgba(0,0,0,0.05);
			display: flex;
			justify-content: space-between;
			align-items: center;
		}

		.top-bar h1 {
			color: #2c3e50;
			font-size: 24px;
		}

		.user-info {
			font-size: 14px;
			color: #7f8c8d;
		}

		/* STATISTICS CARDS */
		.stats {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
			gap: 20px;
			margin-bottom: 30px;
		}

		.stat-card {
			padding: 25px;
			border-radius: 10px;
			color: white;
			box-shadow: 0 4px 15px rgba(0,0,0,0.1);
			display: flex;
			align-items: center;
			justify-content: space-between;
		}

		.stat-card-green {
			background: linear-gradient(135deg, #a310c0 0%, #2248b3 100%);
		}

		.stat-card-blue {
			background: linear-gradient(135deg, #2980b9 0%, #1a5c7a 100%);
		}

		.stat-card-purple {
			background: linear-gradient(135deg, #8e44ad 0%, #6c3483 100%);
		}

		.stat-content h3 {
			font-size: 32px;
			margin-bottom: 5px;
		}

		.stat-content p {
			font-size: 14px;
			opacity: 0.9;
		}

		.stat-icon {
			font-size: 50px;
			opacity: 0.2;
		}

		/* TABLE SECTION */
		.table-section {
			background: white;
			padding: 25px;
			border-radius: 10px;
			margin-bottom: 30px;
			box-shadow: 0 4px 15px rgba(0,0,0,0.1);
		}

		.table-section h3 {
			color: #2c3e50;
			margin-bottom: 20px;
			font-size: 18px;
			border-bottom: 2px solid #27ae60;
			padding-bottom: 10px;
		}

		.table-container {
			overflow-x: auto;
		}

		table {
			width: 100%;
			border-collapse: collapse;
		}

		table th {
			background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
			color: white;
			padding: 15px;
			text-align: left;
			font-weight: bold;
		}

		table td {
			padding: 12px 15px;
			border-bottom: 1px solid #ecf0f1;
		}

		table tr:hover {
			background-color: #f8f9fa;
		}

		.empty-message {
			padding: 30px;
			text-align: center;
			color: #7f8c8d;
			font-size: 16px;
		}

		.status-badge {
			padding: 8px 12px;
			border-radius: 20px;
			font-size: 12px;
			font-weight: bold;
			text-align: center;
		}

		.status-in {
			background-color: #d4edda;
			color: #155724;
		}

		.status-out {
			background-color: #fff3cd;
			color: #856404;
		}

		@media (max-width: 768px) {
			.sidebar {
				width: 80px;
			}

			.main-content {
				margin-left: 80px;
				padding: 15px;
			}

			.stats {
				grid-template-columns: 1fr;
			}
		}
	</style>
</head>
<body>

	<!-- SIDEBAR -->
	<div class="sidebar">
		<div class="sidebar-brand">
			<div class="icon"></div>
			<h2>Smart Parkir</h2>
		</div>
		<ul class="sidebar-menu">
			<li><a href="#dashboard" class="active"> Dashboard</a></li>
			<li><a href="#report"> Laporan</a></li>
		</ul>
		<div class="sidebar-logout">
			<a href="../controllers/LogoutController.php"> Logout</a>
		</div>
	</div>

	<!-- MAIN CONTENT -->
	<div class="main-content">
		<!-- TOP BAR -->
		<div class="top-bar">
			<div>
				<h1>Dashboard Owner</h1>
				<div class="user-info">Halo, <b><?php echo htmlspecialchars($_SESSION['nama']); ?></b></div>
			</div>
		</div>

		<!-- STATISTICS CARDS -->
		<div class="stats">
			<div class="stat-card stat-card-green">
				<div class="stat-content">
					<h3><?php echo $kendaraanAktif; ?></h3>
					<p>Parkir Aktif</p>
				</div>
				<div class="stat-icon"></div>
			</div>
			<div class="stat-card stat-card-blue">
				<div class="stat-content">
					<h3><?php echo $totalTransaksi; ?></h3>
					<p>Total Transaksi</p>
				</div>
				<div class="stat-icon"></div>
			</div>
			<div class="stat-card stat-card-purple">
				<div class="stat-content">
					<h3>Rp <?php echo number_format($totalPendapatan, 0, ',', '.'); ?></h3>
					<p>Total Pendapatan</p>
				</div>
				<div class="stat-icon"></div>
			</div>
		</div>

		<!-- LAPORAN TRANSAKSI -->
		<div class="table-section" id="report">
			<h3> Laporan Transaksi Parkir</h3>
			<div class="table-container">
				<?php
				$queryReport = "SELECT * FROM parkir ORDER BY checkin_time DESC LIMIT 100";
				$resultReport = mysqli_query($conn, $queryReport);

				if($resultReport && mysqli_num_rows($resultReport) > 0) {
					?>
					<table>
						<thead>
							<tr>
								<th>ID</th>
								<th>Card ID</th>
								<th>Waktu Check-In</th>
								<th>Waktu Check-Out</th>
								<th>Status</th>
								<th>Durasi (menit)</th>
								<th>Biaya</th>
							</tr>
						</thead>
						<tbody>
							<?php
							while($row = mysqli_fetch_assoc($resultReport)) {
								$checkinTime = date('d-m-Y H:i:s', strtotime($row['checkin_time']));
								$checkoutTime = $row['checkout_time'] ? date('d-m-Y H:i:s', strtotime($row['checkout_time'])) : '-';
								$duration = $row['duration'] ? $row['duration'] : '-';
								$fee = $row['fee'] ? 'Rp ' . number_format($row['fee'], 0, ',', '.') : '-';
								$statusClass = $row['status'] == 'IN' ? 'status-in' : 'status-out';
								?>
								<tr>
									<td><?php echo $row['id']; ?></td>
									<td><?php echo $row['card_id']; ?></td>
									<td><?php echo $checkinTime; ?></td>
									<td><?php echo $checkoutTime; ?></td>
									<td><span class="status-badge <?php echo $statusClass; ?>"><?php echo $row['status']; ?></span></td>
									<td><?php echo $duration; ?></td>
									<td><?php echo $fee; ?></td>
								</tr>
								<?php
							}
							?>
						</tbody>
					</table>
					<?php
				} else {
					echo '<div class="empty-message">Tidak ada data transaksi</div>';
				}
				?>
			</div>
		</div>
	</div>
</body>
</html>