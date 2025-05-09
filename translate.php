<?php
header('Content-Type: application/json');

// Verbindung zur Datenbank herstellen (Daten anpassen)
$host = "localhost";
$username = "root"; // Standard bei XAMPP
$password = "Simi"; // Standard kein Passwort bei XAMPP
$database = "sprachtrainer";

// Verbindung aufbauen
$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Verbindung fehlgeschlagen.']);
    exit;
}

// Prüfen, ob es sich um eine GET- oder POST-Anfrage handelt
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Prüfen, ob ein Wort übergeben wurde
    if (!isset($_GET['word'])) {
        echo json_encode(['success' => false, 'message' => 'Kein Wort übergeben.']);
        exit;
    }

    // Wort bereinigen
    $word = trim($_GET['word']);

    // SQL-Abfrage mit Prepared Statement
    $stmt = $conn->prepare("SELECT uebersetzung FROM woerterbuch WHERE original = ?");
    $stmt->bind_param("s", $word);
    $stmt->execute();
    $stmt->bind_result($translation);

    // Ergebnis zurückgeben
    if ($stmt->fetch()) {
        echo json_encode(['success' => true, 'translation' => $translation]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Keine Übersetzung gefunden.']);
    }

    $stmt->close();
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Prüfen, ob die notwendigen Daten übergeben wurden
    $data = json_decode(file_get_contents('php://input'), true);
    if (isset($data['word']) && isset($data['translation'])) {
        $word = trim($data['word']);
        $translation = trim($data['translation']);

        // SQL-Abfrage zum Hinzufügen der Übersetzung
        $stmt = $conn->prepare("INSERT INTO woerterbuch (original, uebersetzung) VALUES (?, ?)");
        $stmt->bind_param("ss", $word, $translation);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Übersetzung gespeichert.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Fehler beim Speichern der Übersetzung.']);
        }

        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Fehlende Daten für das Speichern.']);
    }
}

$conn->close();
?>
