<?php
header('Content-Type: application/json');

// Verbindung zur Datenbank herstellen (Daten anpassen)
$host = "localhost";
$username = "root"; // Standard bei XAMPP
$password = "Simi";       // Standard kein Passwort bei XAMPP
$database = "sprachtrainer";

// Prüfen, ob ein Wort übergeben wurde
if (!isset($_GET['word'])) {
    echo json_encode(['success' => false, 'message' => 'Kein Wort übergeben.']);
    exit;
}

// Wort bereinigen
$word = trim($_GET['word']);

// Verbindung aufbauen
$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Verbindung fehlgeschlagen.']);
    exit;
}

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
$conn->close();
?>
