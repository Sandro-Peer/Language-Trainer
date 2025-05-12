<?php
header("Content-Type: application/json");

$host = "localhost";
$username = "dein_benutzername";
$password = "dein_passwort";
$database = "deine_datenbank";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    echo json_encode(["success" => false, "error" => "DB-Verbindung fehlgeschlagen."]);
    exit;
}

// POST: Satzweise Übersetzung speichern
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $data = json_decode(file_get_contents("php://input"), true);
    $originalSatz = strtolower(trim($data["word"]));
    $uebersetzungSatz = trim($data["translation"]);

    if ($originalSatz && $uebersetzungSatz) {
        $originalWoerter = preg_split('/\s+/', $originalSatz);
        $uebersetzteWoerter = preg_split('/\s+/', $uebersetzungSatz);

        if (count($originalWoerter) !== count($uebersetzteWoerter)) {
            echo json_encode(["success" => false, "error" => "Wortanzahl stimmt nicht überein."]);
            exit;
        }

        $stmt = $conn->prepare("REPLACE INTO woerterbuch (original, uebersetzung) VALUES (?, ?)");

        for ($i = 0; $i < count($originalWoerter); $i++) {
            $original = $originalWoerter[$i];
            $uebersetzung = $uebersetzteWoerter[$i];
            $stmt->bind_param("ss", $original, $uebersetzung);
            $stmt->execute();
        }

        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "error" => "Ungültige Eingabe."]);
    }
    exit;
}

// GET: Satz übersetzen
if (isset($_GET["word"])) {
    $satz = strtolower(trim($_GET["word"]));
    $woerter = preg_split('/\s+/', $satz);
    $uebersetzteWoerter = [];

    $stmt = $conn->prepare("SELECT uebersetzung FROM woerterbuch WHERE original = ?");

    foreach ($woerter as $wort) {
        $stmt->bind_param("s", $wort);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $uebersetzteWoerter[] = $row["uebersetzung"];
        } else {
            $uebersetzteWoerter[] = "[$wort]";
        }
    }

    $uebersetzterSatz = implode(" ", $uebersetzteWoerter);
    echo json_encode(["success" => true, "translation" => $uebersetzterSatz]);
    exit;
}

echo json_encode(["success" => false, "error" => "Ungültige Anfrage."]);
