<?php
// init.php - Run once to set up the SQLite database

$dbFile = 'meals.sqlite';

try {
    // Check if the database file already exists
    $isNewFile = !file_exists($dbFile);

    // Create a PDO connection to the SQLite database file
    $pdo = new PDO("sqlite:$dbFile");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($isNewFile) {
        // Create the 'meals' table
        $pdo->exec("
            CREATE TABLE meals (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                date TEXT NOT NULL,
                food_name TEXT NOT NULL,
                meal_type TEXT NOT NULL,
                calories INTEGER,
                protein INTEGER,
                sugar INTEGER,
                fat INTEGER
            );
        ");
        echo "SQLite database '$dbFile' and 'meals' table created successfully.";
    } else {
        echo "SQLite database '$dbFile' already exists. No action taken.";
    }

} catch (PDOException $e) {
    // Handle database connection or creation errors
    http_response_code(500);
    die("Database Error: " . $e->getMessage());
}
?>