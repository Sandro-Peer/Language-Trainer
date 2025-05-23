<?php
// Datenbankverbindung
$host = 'localhost';
$user = 'root';
$password = 'root';
$database = 'mein_login_system';

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("Verbindung fehlgeschlagen: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Einfache Validierung
    if (empty($username) || empty($email) || empty($password)) {
        echo "Alle Felder sind erforderlich.";
        exit();
    }

    // Achtung: Passwort wird hier im Klartext gespeichert
    $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $email, $password);

    if ($stmt->execute()) {
        header("Location: Perfekt.html");
        exit();
    } else {
        if ($conn->errno === 1062) {
            echo "Diese E-Mail-Adresse ist bereits registriert.";
        } else {
            echo "Fehler: " . $stmt->error;
        }
    }

    $stmt->close();
}

$conn->close();
?>
