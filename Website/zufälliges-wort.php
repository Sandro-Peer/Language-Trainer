<?php
header('Content-Type: application/json');

define("DB_HOST", "localhost");
define("DB_USER", "root");
define("DB_PASS", "password");
define("DB_NAME", "sprachtrainer");
define("TABLE_NAME", "woerterbuch");

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
  echo json_encode(["success" => false, "message" => "Verbindung fehlgeschlagen"]);
  exit;
}

$sql = "SELECT deutsch, englisch FROM " . TABLE_NAME . " ORDER BY RAND() LIMIT 1";
$result = $conn->query($sql);

if ($result && $row = $result->fetch_assoc()) {
  echo json_encode([
    "success" => true,
    "deutsch" => $row["deutsch"],
    "englisch" => $row["englisch"]
  ]);
} else {
  echo json_encode(["success" => false, "message" => "Kein Wort gefunden"]);
}

$conn->close();
?>
