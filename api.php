<?php
// api.php - PHP Backend for CRUD operations with SQLite

header('Content-Type: application/json');

// Load environment variables
$env = parse_ini_file('.env');
$googleApiKey = $env['GOOGLE_API_KEY'] ?? '';

$dbFile = 'meals.sqlite';
$pdo = null;

try {
    // Connect to the SQLite database
    $pdo = new PDO("sqlite:$dbFile");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed. Run init.php first.']);
    exit();
}

// Function to handle raw JSON input for POST/PUT requests
function getJsonInput() {
    $input = file_get_contents('php://input');
    return json_decode($input, true);
}

// Function to process AI text and get food analysis
function processAI($text, $apiKey) {
    $today = date('Y-m-d');
    // $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=$apiKey";
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-09-2025:generateContent?key=$apiKey";

    $prompt = "
    You are a nutrition expert. Today's date is $today.
    Extract food items from this text: \"$text\".
    For each item, deduce the \"meal_type\" (Breakfast, Lunch, Dinner, Snack) based on the food type or text context. Default to \"Snack\" if ambiguous.
    Provide a best-guess estimate for calories, protein (g), sugar (g), and fat (g).
    Also, analyze the text and return the date of the entry in YYYY-MM-DD format. Calculate this date relative to TODAY's DATE ($today). If no date is specified (e.g., 'yesterday', 'last Friday', etc.), use TODAY's DATE.
    Return ONLY a JSON object with this structure:
    {
        \"date\": \"YYYY-MM-DD\",
        \"foods\": [
            {\"food_name\": \"String\", \"meal_type\": \"String\", \"calories\": Number, \"protein\": Number, \"sugar\": Number, \"fat\": Number}
        ]
    }
    Do not include markdown formatting or backticks.";

    $data = [
        'contents' => [
            ['parts' => [['text' => $prompt]]]
        ]
    ];

    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => json_encode($data)
        ]
    ];

    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);

    if ($response === false) {
        throw new Exception("Failed to call Google API");
    }

    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON response from Google API");
    }

    $aiText = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $cleanJson = str_replace(['```json', '```'], '', $aiText);
    $cleanJson = trim($cleanJson);
    $parsed = json_decode($cleanJson, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON from AI text");
    }
    return $parsed;
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Fetch all meals, ordered by date descending
        $stmt = $pdo->query("SELECT * FROM meals ORDER BY date DESC, id DESC");
        $meals = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $meals]);
        break;

    case 'POST':
        // Insert new meal(s) or process AI text
        $input = getJsonInput();

        $foods = [];
        $date = date('Y-m-d');

        if (isset($input['text'])) {
            // Process AI text
            try {
                $aiResult = processAI($input['text'], $googleApiKey);
                $foods = $aiResult['foods'] ?? [];
                $date = $aiResult['date'] ?? $date;
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => 'AI processing failed: ' . $e->getMessage()]);
                exit();
            }
        } elseif (isset($input['foods']) && is_array($input['foods'])) {
            // Direct insert
            $foods = $input['foods'];
            $date = $input['date'] ?? $date;
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid input format. Expected text or foods array.']);
            exit();
        }

        if (empty($foods)) {
            http_response_code(400);
            echo json_encode(['error' => 'No foods to insert.']);
            exit();
        }

        $pdo->beginTransaction();
        try {
            $sql = "INSERT INTO meals (date, food_name, meal_type, calories, protein, sugar, fat) VALUES (:date, :food_name, :meal_type, :calories, :protein, :sugar, :fat)";
            $stmt = $pdo->prepare($sql);

            foreach ($foods as $food) {
                $stmt->execute([
                    ':date' => $date,
                    ':food_name' => $food['food_name'] ?? 'Unknown Food',
                    ':meal_type' => $food['meal_type'] ?? 'Snack',
                    ':calories' => $food['calories'] ?? 0,
                    ':protein' => $food['protein'] ?? 0,
                    ':sugar' => $food['sugar'] ?? 0,
                    ':fat' => $food['fat'] ?? 0,
                ]);
            }

            $pdo->commit();
            http_response_code(201);
            echo json_encode(['success' => true, 'message' => 'Meals added successfully.']);
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Failed to insert meals: ' . $e->getMessage()]);
        }
        break;

    case 'PUT':
        // Update an existing meal (used by drag-and-drop and edit modal)
        $input = getJsonInput();
        $id = $input['id'] ?? null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing meal ID for update.']);
            exit();
        }

        $sql = "UPDATE meals SET date = :date, food_name = :food_name, meal_type = :meal_type, calories = :calories, protein = :protein, sugar = :sugar, fat = :fat WHERE id = :id";
        $stmt = $pdo->prepare($sql);

        try {
            $stmt->execute([
                ':id' => $id,
                ':date' => $input['date'] ?? date('Y-m-d'),
                ':food_name' => $input['food_name'] ?? 'Unknown Food',
                ':meal_type' => $input['meal_type'] ?? 'Snack',
                ':calories' => $input['calories'] ?? 0,
                ':protein' => $input['protein'] ?? 0,
                ':sugar' => $input['sugar'] ?? 0,
                ':fat' => $input['fat'] ?? 0,
            ]);
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Meal updated successfully.']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update meal: ' . $e->getMessage()]);
        }
        break;

    case 'DELETE':
        // Delete a meal
        $id = $_GET['id'] ?? null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing meal ID for deletion.']);
            exit();
        }

        $sql = "DELETE FROM meals WHERE id = :id";
        $stmt = $pdo->prepare($sql);

        try {
            $stmt->execute([':id' => $id]);
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Meal deleted successfully.']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete meal: ' . $e->getMessage()]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed.']);
        break;
}

?>