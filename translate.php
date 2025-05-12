<?php
header("Content-Type: application/json");

// Datenbankverbindung (bitte Zugangsdaten anpassen)
$host = "localhost";
$username = "root";
$password = "Simi";
$database = "sprachtrainer";

$conn = new mysqli($host, $username, $password, $database);

// Verbindung prüfen
if ($conn->connect_error) {
    echo json_encode(["success" => false, "error" => "DB-Verbindung fehlgeschlagen."]);
    exit;
}

// POST-Anfrage: neue Übersetzung speichern
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $data = json_decode(file_get_contents("php://input"), true);
    $original = strtolower(trim($data["word"]));
    $uebersetzung = trim($data["translation"]);

    if ($original && $uebersetzung) {
        // Bestehenden Eintrag ersetzen oder neuen hinzufügen
        $stmt = $conn->prepare("REPLACE INTO woerterbuch (original, uebersetzung) VALUES (?, ?)");
        $stmt->bind_param("ss", $original, $uebersetzung);
        $success = $stmt->execute();
        echo json_encode(["success" => $success]);
    } else {
        echo json_encode(["success" => false, "error" => "Ungültige Eingabe."]);
    }
    exit;
}

// GET-Anfrage: Satz übersetzen
if (isset($_GET["word"])) {
    $satz = strtolower(trim($_GET["word"]));
    $woerter = preg_split('/\s+/', $satz); // Satz in Wörter aufteilen
    $uebersetzteWoerter = [];

    $stmt = $conn->prepare("SELECT uebersetzung FROM woerterbuch WHERE original = ?");

    foreach ($woerter as $wort) {
        $stmt->bind_param("s", $wort);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $uebersetzteWoerter[] = $row["uebersetzung"];
        } else {
            $uebersetzteWoerter[] = "[$wort]"; // Unbekanntes Wort markieren
        }
    }

    $uebersetzterSatz = implode(" ", $uebersetzteWoerter);
    echo json_encode(["success" => true, "translation" => $uebersetzterSatz]);
    exit;
}

// Fallback
echo json_encode(["success" => false, "error" => "Ungültige Anfrage."]);
