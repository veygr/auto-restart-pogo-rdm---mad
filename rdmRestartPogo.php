<?php
// config START
define('DB_HOST', 'localhost');
define('DB_LOGIN', 'root');
define('DB_PASS', '123');
define('DB_NAME', 'rdm');
define('TIME_TO_RESTART', 300);
define('MAD_URL', 'http://localhost:5000'); // eg http://127.0.0.1:5000
define('MAD_LOGIN', 'root');
define('MAD_PASS', '123');
define('DISCORD_WEBHOOK', '');
// config END

error_reporting(E_ALL);
ini_set('xdebug.var_display_max_depth', -1);
ini_set('xdebug.var_display_max_children', -1);
ini_set('xdebug.var_display_max_data', -1);
set_time_limit(0);

$sql = new \mysqli(DB_HOST, DB_LOGIN, DB_PASS);
$sql->query('SET NAMES utf8');

$devices = $sql->query("
SELECT uuid AS name, UNIX_TIMESTAMP() - last_seen AS diff 
FROM " . DB_NAME . ".device
WHERE UNIX_TIMESTAMP() - last_seen > " . TIME_TO_RESTART . "
AND last_seen > 0
ORDER BY uuid
");

$messages = '';

while ($row = $devices->fetch_assoc()) {
	$hours = (int) ($row['diff'] / 3600);
	$minutes = (int) (($row['diff'] - $hours * 3600) / 60);
	$messages .= $row['name'] . ' RDM ' . str_pad($hours, 2, 0, STR_PAD_LEFT) . ':' . str_pad($minutes, 2, 0, STR_PAD_LEFT) . PHP_EOL;
	
	$s = curl_init();

	curl_setopt($s, CURLOPT_URL, MAD_URL . '/quit_pogo?origin=' .$row['name'] . '&adb=False&restart=1');
	curl_setopt($s, CURLOPT_HTTPHEADER, [
		'Authorization: Basic ' . base64_encode(MAD_LOGIN . ':' . MAD_PASS),
	]);
	curl_setopt($s, CURLOPT_TIMEOUT, 30);
	curl_setopt($s, CURLOPT_MAXREDIRS, 0);
	curl_setopt($s, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($s, CURLOPT_HEADER, 1);

	$response = curl_exec($s);
}

if (empty($messages)) {
	return;
}

if (empty(DISCORD_WEBHOOK)) {
	return;
}

$s = curl_init();

curl_setopt($s, CURLOPT_URL, DISCORD_WEBHOOK);
curl_setopt($s, CURLOPT_POST, true);
curl_setopt($s, CURLOPT_POSTFIELDS, json_encode(
				[
					'content' => $messages,
				]
));
curl_setopt($s, CURLOPT_HTTPHEADER, [
	'Content-type: application/json;multipart/form-data',
]);

$res = curl_exec($s);
