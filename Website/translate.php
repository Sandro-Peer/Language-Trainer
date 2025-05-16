<?php
session_start();
header("Content-Type: application/json");

// Konstanten definieren
define("DB_HOST", "localhost");
define("DB_USER", "root");
define("DB_PASS", "");
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

// Wörter für das Training abrufen
function getWordsForTraining($conn) {
    $result = $conn->query("SELECT original, uebersetzung FROM " . TABLE_NAME);
    if (!$result) {
        respondWithError("Datenbankfehler: " . $conn->error);
    }

    $words = [];
    while ($row = $result->fetch_assoc()) {
        $words[] = $row;
    }
    return $words;
}

// POST: Falsche Antworten speichern
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $data = json_decode(file_get_contents("php://input"), true);

    if (isset($data["falsch"])) {
        $falsch = $data["falsch"];
        if (!isset($_SESSION["falsch"])) {
            $_SESSION["falsch"] = [];
        }
        $_SESSION["falsch"][] = $falsch;
        respondWithSuccess();
    }

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

// GET: falsch übersetzte Wörter anzeigen
if ($_SERVER["REQUEST_METHOD"] === "GET" && isset($_GET["showWrong"])) {
    $conn = getDatabaseConnection();
    $wrongWords = isset($_SESSION['falsch']) ? $_SESSION['falsch'] : [];
    $wrongWordsWithTranslations = [];

    foreach ($wrongWords as $wrongWord) {
        $stmt = $conn->prepare("SELECT original, uebersetzung FROM " . TABLE_NAME . " WHERE original = ?");
        if ($stmt) {
            $stmt->bind_param("s", $wrongWord);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $wrongWordsWithTranslations[] = $row;
            }
            $stmt->close();
        }
    }

    $conn->close();
    respondWithSuccess(["wrongWords" => $wrongWordsWithTranslations]);
}



// GET: Wörter für das Training abrufen
if ($_SERVER["REQUEST_METHOD"] === "GET" && isset($_GET["getWords"])) {
    $conn = getDatabaseConnection();

    // Fetch wrong words from the session
    $wrongWords = isset($_SESSION['falsch']) ? $_SESSION['falsch'] : [];

    // Fetch all words from the database
    $result = $conn->query("SELECT original, uebersetzung FROM " . TABLE_NAME);
    if (!$result) {
        respondWithError("Datenbankfehler: " . $conn->error);
    }

    $allWords = [];
    while ($row = $result->fetch_assoc()) {
        $allWords[] = $row;
    }

    // Prioritize wrong words
    $words = [];
    foreach ($wrongWords as $wrongWord) {
        foreach ($allWords as $key => $word) {
            if ($word['original'] === $wrongWord) {
                $words[] = $word;
                unset($allWords[$key]); // Remove to avoid duplicates
                break;
            }
        }
    }

    // Add remaining words and shuffle
    $words = array_merge($words, $allWords);
    shuffle($words);

    // Limit to 20 words
    $words = array_slice($words, 0, 20);

    $conn->close();
    respondWithSuccess(["words" => $words]);
}

respondWithError("Ungültige Anfrage.");
