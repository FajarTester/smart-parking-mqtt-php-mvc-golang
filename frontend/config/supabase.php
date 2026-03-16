<?php
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$project_url = $_ENV['SUPABASE_URL'];
$anon_key = $_ENV['SUPABASE_ANON_KEY'];

$base_url = $project_url . "/rest/v1/";

function koneksi_supabase($method, $endpoint, $payload = null)
{
    global $base_url, $anon_key;

    $url = $base_url . $endpoint;
    $ch = curl_init($url);

    $headers = [
        "apikey: " . $anon_key,
        "Authorization: Bearer " . $anon_key,
        "Content-Type: application/json",
        "Prefer: return=representation"
    ];

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    if ($payload) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        die("Error Koneksi: " . curl_error($ch));
    }

    return json_decode($response, true);
}
?>