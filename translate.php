<?php
session_start();
header("Content-Type: application/json");

define("DB_HOST", "localhost");
define("DB_USER", "root");
define("DB_PASS", "root");
define("DB_NAME", "sprachtrainer");       // EINHEITLICHE Datenbank
define("TABLE_NAME", "woerterbuch");

function getDatabaseConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        respondWithError("DB-Verbindung fehlgeschlagen: " . $conn->connect_error);
    }
    return $conn;
}

function respondWithError($message) {
    echo json_encode(["success" => false, "error" => $message]);
    exit;
}

function respondWithSuccess($data = []) {
    echo json_encode(array_merge(["success" => true], $data));
    exit;
}

function saveTranslation($conn, $original, $translation) {
    // Schritt 1: Prüfen, ob Eintrag bereits existiert
    $checkStmt = $conn->prepare("SELECT 1 FROM " . TABLE_NAME . " WHERE original = ? AND uebersetzung = ?");
    if (!$checkStmt) {
        respondWithError("Datenbankfehler (Prüfung): " . $conn->error);
    }
    $checkStmt->bind_param("ss", $original, $translation);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows > 0) {
        // Schon vorhanden – nichts tun oder Nachricht ausgeben
        $checkStmt->close();
        return; // oder ggf. eine Info zurückgeben
    }
    $checkStmt->close();

    // Schritt 2: Nur einfügen, wenn es noch nicht existiert
    $insertStmt = $conn->prepare("INSERT INTO " . TABLE_NAME . " (original, uebersetzung) VALUES (?, ?)");
    if (!$insertStmt) {
        respondWithError("Datenbankfehler (Einfügen): " . $conn->error);
    }
    $insertStmt->bind_param("ss", $original, $translation);
    $insertStmt->execute();
    $insertStmt->close();
}

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

// POST: Falsche Antwort speichern
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $data = json_decode(file_get_contents("php://input"), true);

    // Falsches Wort speichern (ID des Wortes in woerterbuch)
    if (isset($data["falsch"])) {
        $falsch = $data["falsch"];
        if (!isset($_SESSION["falsch"])) {
            $_SESSION["falsch"] = [];
        }
        $_SESSION["falsch"][] = $falsch;

        $conn = getDatabaseConnection();

        if (isset($_SESSION["user_id"])) {
            $userId = intval($_SESSION["user_id"]);

            $checkStmt = $conn->prepare("SELECT id FROM user_wrong_words WHERE user_id = ? AND wrong_word = ?");
            $checkStmt->bind_param("is", $userId, $falsch);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            $num = $result->num_rows;
             if ($result->num_rows < 1) 
            {
                // Wenn der Eintrag nicht existiert, füge ihn hinzu
                $insertStmt = $conn->prepare("INSERT INTO user_wrong_words (user_id, wrong_word) VALUES (?, ?)");
                $insertStmt->bind_param("is", $userId, $falsch);
                $insertStmt->execute();
                $insertStmt->close();
            } 
            

            $checkStmt->close();
        }

        $conn->close();
        respondWithSuccess();
    }

    // Neue Übersetzung speichern
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

// GET: Übersetzung abrufen
if ($_SERVER["REQUEST_METHOD"] === "GET" && isset($_GET["word"])) {
    $word = strtolower(trim($_GET["word"]));
    if (empty($word)) {
        respondWithError("Leere Eingabe.");
    }

    $conn = getDatabaseConnection();
    $translation = getTranslation($conn, $word);
    $conn->close();

    if ($translation) {
        respondWithSuccess(["translation" => $translation]);
    } else {
        respondWithError("Keine Übersetzung gefunden.");
    }
}

// GET: Falsche Wörter anzeigen
if ($_SERVER["REQUEST_METHOD"] === "GET" && isset($_GET["showWrong"])) {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403);
        echo json_encode(["error" => "Nicht eingeloggt"]);
        exit;
    }

    $conn = getDatabaseConnection();
    $userId = $_SESSION['user_id'];

    $stmt = $conn->prepare("
        SELECT woerterbuch.original, woerterbuch.uebersetzung 
        FROM user_wrong_words 
        JOIN woerterbuch ON user_wrong_words.wrong_word = woerterbuch.original
        WHERE user_wrong_words.user_id = ?
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $wrongWords = [];
    while ($row = $result->fetch_assoc()) {
        $wrongWords[] = $row;
    }

    echo json_encode([
        "success" => true,
        "wrongWords" => $wrongWords
    ]);
    exit;
}

// GET: Trainingswörter abrufen
if ($_SERVER["REQUEST_METHOD"] === "GET" && isset($_GET["getWords"])) {
    $conn = getDatabaseConnection();
    $wrongWords = isset($_SESSION['falsch']) ? $_SESSION['falsch'] : [];

    $result = $conn->query("SELECT id, original, uebersetzung FROM " . TABLE_NAME);
    if (!$result) {
        respondWithError("Datenbankfehler: " . $conn->error);
    }

    $allWords = [];
    while ($row = $result->fetch_assoc()) {
        $allWords[] = $row;
    }

    $words = [];
    foreach ($wrongWords as $wrongId) {
        foreach ($allWords as $key => $word) {
            if ($word['id'] == $wrongId) {
                $words[] = $word;
                unset($allWords[$key]);
                break;
            }
        }
    }

    $words = array_merge($words, $allWords);
    shuffle($words);
    $words = array_slice($words, 0, 20);

    $conn->close();
    respondWithSuccess(["words" => $words]);
}

// PUT: Übersetzung aktualisieren und aus "falsch" entfernen
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $data = json_decode(file_get_contents("php://input"), true);

    if (isset($data['id']) && isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
        $original = $data['id'];  // id = Wort (z. B. "wie")

        // 1. Wort aus der Session entfernen
        if (isset($_SESSION['falsch']) && is_array($_SESSION['falsch'])) {
            $_SESSION['falsch'] = array_filter($_SESSION['falsch'], function($wortId) use ($original) {
                return $wortId !== $original;
            });
        }

        // 2. Wort aus der user_wrong_words-Tabelle entfernen
        $conn = getDatabaseConnection();
        $stmt = $conn->prepare("DELETE FROM user_wrong_words WHERE user_id = ? AND wrong_word = ?");
        if (!$stmt) {
            respondWithError("Datenbankfehler: " . $conn->error);
        }
        $stmt->bind_param("is", $userId, $original);
        $stmt->execute();
        $stmt->close();
        $conn->close();

        respondWithSuccess();
    } else {
        respondWithError("Fehlende ID oder nicht eingeloggt.");
    }
}

respondWithError("Ungültige Anfrage.");

