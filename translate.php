<?php
header("Content-Type: application/json");

// Konstanten definieren
define("DB_HOST", "localhost");
define("DB_USER", "root");
define("DB_PASS", "password");
define("DB_NAME", "sprachtrainer");
define("TABLE_NAME", "woerterbuch");

// Datenbankverbindung herstellen
function getDatabaseConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        respondWithError("DB-Verbindung fehlgeschlagen.");
    }
    return $conn;
}

// Einheitliche JSON-Antworten
function respondWithError($message) {
    echo json_encode(["success" => false, "error" => $message]);
    exit;
}

function respondWithSuccess($data = []) {
    echo json_encode(array_merge(["success" => true], $data));
    exit;
}

// Übersetzung speichern
function saveTranslation($conn, $original, $translation) {
    $stmt = $conn->prepare("REPLACE INTO " . TABLE_NAME . " (original, uebersetzung) VALUES (?, ?)");
    if (!$stmt) {
        respondWithError("Datenbankfehler: " . $conn->error);
    }
    $stmt->bind_param("ss", $original, $translation);
    $stmt->execute();
    $stmt->close();
}

// Übersetzung abrufen
function getTranslation($conn, $word) {
    $stmt = $conn->prepare("SELECT uebersetzung FROM " . TABLE_NAME . " WHERE original = ?");
    if (!$stmt) {
        respondWithError("Datenbankfehler: " . $conn->error);
    }
    $stmt->bind_param("s", $word);
    $stmt->execute();
    $result = $stmt->get_result();
    $translation = $result->fetch_assoc()["uebersetzung"] ?? null;
    $stmt->close();
    return $translation;
}

// POST: Satzweise Übersetzung speichern
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data || !isset($data["word"]) || !isset($data["translation"])) {
        respondWithError("Ungültiges JSON oder fehlende Felder.");
    }

    $originalSatz = strtolower(trim($data["word"]));
    $uebersetzungSatz = trim($data["translation"]);

    if (empty($originalSatz) || empty($uebersetzungSatz)) {
        respondWithError("Leere Eingabe.");
    }

    $conn = getDatabaseConnection();

    $originalWoerter = preg_split('/\s+/', $originalSatz);
    $uebersetzteWoerter = preg_split('/\s+/', $uebersetzungSatz);

    if (count($originalWoerter) === count($uebersetzteWoerter)) {
        foreach ($originalWoerter as $i => $original) {
            saveTranslation($conn, $original, $uebersetzteWoerter[$i]);
        }
    } else {
        saveTranslation($conn, $originalSatz, $uebersetzungSatz);
    }

    $conn->close();
    respondWithSuccess();
}

// GET: Satz oder Wörter übersetzen
if ($_SERVER["REQUEST_METHOD"] === "GET" && isset($_GET["word"])) {
    $satz = strtolower(trim($_GET["word"]));
    if (empty($satz)) {
        respondWithError("Leere Eingabe.");
    }

    $conn = getDatabaseConnection();

    // Ganze Phrase abrufen
    $translation = getTranslation($conn, $satz);
    if ($translation) {
        $conn->close();
        respondWithSuccess(["translation" => $translation]);
    }

    // Satz in Wörter aufteilen und einzeln übersetzen
    $woerter = preg_split('/\s+/', $satz);
    $uebersetzteWoerter = [];

    foreach ($woerter as $wort) {
        $translation = getTranslation($conn, $wort);
        $uebersetzteWoerter[] = $translation ?? "[$wort]";
    }

    $conn->close();
    $uebersetzterSatz = implode(" ", $uebersetzteWoerter);
    respondWithSuccess(["translation" => $uebersetzterSatz]);
}

respondWithError("Ungültige Anfrage.");
