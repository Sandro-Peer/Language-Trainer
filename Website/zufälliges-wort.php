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

$sql = "SELECT deutsch, englisch FROM " . TABLE_NAME . " ORDER BY RAND() LIMIT 10";
$result = $conn->query($sql);

$woerter = [];
while ($row = $result->fetch_assoc()) {
  $woerter[] = [
    "deutsch" => $row["deutsch"],
    "englisch" => $row["englisch"]
  ];
}

echo json_encode([
  "success" => true,
  "woerter" => $woerter
]);

$conn->close();
?>
