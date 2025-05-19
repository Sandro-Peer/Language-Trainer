<?php
session_start(); // Falls du später Sitzungen brauchst

// Verbindung zur Datenbank
$host = "localhost";
$dbname = "mein_login_system";
$user = "root"; // Ändern falls nötig
$pass = "root";     // Ändern falls nötig

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Verbindung fehlgeschlagen: " . $e->getMessage());
}

// Formular-Daten
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if ($user['password'] === $password) {
            // Erfolgreich eingeloggt, weiterleiten
            header("Location: frontend.html");
            exit();
        } else {
            echo "<h2>Falsches Passwort.</h2>";
        }
    } else {
        echo "<h2>Benutzer nicht gefunden.</h2>";
    }
}
?>
