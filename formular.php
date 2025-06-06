<?php

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = ($_POST["name"] ?? '');
    $email = ($_POST["email"] ?? '');
    $message = ($_POST["message"] ?? '');
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <title>Vielen Dank</title>
        <style>
                a {
      color: black;
    }
            body {
                background-color: #121212;
                color: #e0e0e0;
                font-family: Arial, sans-serif;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
            }
            .response-container {
                background: #1e1e1e;
                padding: 2rem;
                border-radius: 10px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
                text-align: center;
                max-width: 500px;
            }
        </style>
    </head>
    <body>
        <div class="response-container">
            <h1>Vielen Dank, <?= $name ?>!</h1>
            <p>Wir haben deine Nachricht erhalten und melden uns bei dir unter <strong><?= $email ?></strong>.</p>
            <a href="Frontend.html">zurück</a>
          
        </div>
    </body>
    </html>
    <?php

}
