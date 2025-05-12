<?php
header("Content-Type: application/json");

$host = "localhost";
$username = "root";
$password = "Simi";
$database = "sprachtrainer";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    echo json_encode(["success" => false, "error" => "DB-Verbindung fehlgeschlagen."]);
    exit;
}

// POST: Satzweise Übersetzung speichern
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data || !isset($data["word"]) || !isset($data["translation"])) {
        echo json_encode(["success" => false, "error" => "Ungültiges JSON oder fehlende Felder."]);
        exit;
    }

    $originalSatz = strtolower(trim($data["word"]));
    $uebersetzungSatz = trim($data["translation"]);

    if ($originalSatz && $uebersetzungSatz) {
        $originalWoerter = preg_split('/\s+/', $originalSatz);
        $uebersetzteWoerter = preg_split('/\s+/', $uebersetzungSatz);

        if (count($originalWoerter) === count($uebersetzteWoerter)) {
            $stmt = $conn->prepare("REPLACE INTO woerterbuch (original, uebersetzung) VALUES (?, ?)");
            for ($i = 0; $i < count($originalWoerter); $i++) {
                $original = $originalWoerter[$i];
                $uebersetzung = $uebersetzteWoerter[$i];
                $stmt->bind_param("ss", $original, $uebersetzung);
                $stmt->execute();
            }
        } else {
            // Ganze Phrase speichern
            $stmt = $conn->prepare("REPLACE INTO woerterbuch (original, uebersetzung) VALUES (?, ?)");
            $stmt->bind_param("ss", $originalSatz, $uebersetzungSatz);
            $stmt->execute();
        }

        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "error" => "Leere Eingabe."]);
    }

    exit;
}


// GET: Satz oder Wörter übersetzen
if (isset($_GET["word"])) {
    $satz = strtolower(trim($_GET["word"]));

    // 1. Versuch: ganze Phrase in der Datenbank finden
    $stmt = $conn->prepare("SELECT uebersetzung FROM woerterbuch WHERE original = ?");
    $stmt->bind_param("s", $satz);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Ganze Phrase gefunden → direkt zurückgeben
        echo json_encode(["success" => true, "translation" => $row["uebersetzung"]]);
        exit;
    }

    // 2. Versuch: Satz in Wörter aufteilen und einzeln übersetzen
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

echo json_encode(["success" => false, "error" => "Ungültige Anfrage."]);
