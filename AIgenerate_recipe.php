<?php
session_start();

// Prevent browser caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Include database connection
require_once 'db_connect.php';

// Get user info
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? '';

// === ADD THIS SECTION TO FETCH PROFILE IMAGE DATA ===
// Initialize profile variables
$has_profile_image = false;
$profile_image = '';
$initials = 'US';

// Fetch user profile data from database
$stmt = $conn->prepare("SELECT username, profile_image FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $username = htmlspecialchars($user['username']);
    $profile_image = !empty($user['profile_image']) ? $user['profile_image'] : '';
    
    // Generate initials from username
    if (!empty($username)) {
        $name_parts = explode(' ', $username);
        if (count($name_parts) >= 2) {
            $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1));
        } else {
            $initials = strtoupper(substr($username, 0, 2));
        }
    }
    
    // Verify image exists
    if (!empty($profile_image)) {
        $image_path = $profile_image;
        if (strpos($profile_image, 'uploads/') === false) {
            $image_path = 'uploads/' . $profile_image;
        }
        
        if (file_exists($image_path)) {
            $has_profile_image = true;
        } else {
            // Update database if file doesn't exist
            $update_stmt = $conn->prepare("UPDATE users SET profile_image = '' WHERE id = ?");
            $update_stmt->bind_param("i", $user_id);
            $update_stmt->execute();
            $update_stmt->close();
            $profile_image = '';
        }
    }
} else {
    $username = 'User';
    $profile_image = '';
    $initials = 'US';
    $has_profile_image = false;
}
$stmt->close();
// === END OF PROFILE IMAGE FETCHING SECTION ===

// API Keys Configuration
$text_api_key = 'AIzaSyBLgKBfTIRzVFn-aj0riJjIMHubHKepYRs';
$image_api_key = 'AIzaSyBLgKBfTIRzVFn-aj0riJjIMHubHKepYRs';

// Get user preferences
$preferences = [
    'cuisine_style' => 'Filipino',
    'dietary_restrictions' => [],
    'spice_level' => 'medium',
    'cooking_time' => 'any',
    'ingredient_preferences' => [],
    'ai_creativity' => 'balanced',
    'mix_n_match_cuisines' => ['Filipino']
];

// Database Functions
function createChatSession($user_id, $initial_message = null) {
    global $conn;
    
    $session_id = uniqid('session_', true);
    $title = $initial_message ? substr($initial_message, 0, 50) : 'New Chat';
    
    $stmt = $conn->prepare("INSERT INTO chat_sessions (id, user_id, title) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $session_id, $user_id, $title);
    $stmt->execute();
    $stmt->close();
    return $session_id;
}

function getUserSessions($user_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT cs.id as session_id, cs.title as session_title, cs.created_at,
               COUNT(ch.id) as message_count,
               (SELECT message FROM chat_history WHERE session_id = cs.id ORDER BY created_at ASC LIMIT 1) as first_message
        FROM chat_sessions cs
        LEFT JOIN chat_history ch ON cs.id = ch.session_id
        WHERE cs.user_id = ?
        GROUP BY cs.id
        ORDER BY cs.updated_at DESC
    ");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $sessions = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $sessions;
}

function deleteSession($user_id, $session_id) {
    global $conn;
    
    $stmt = $conn->prepare("DELETE FROM chat_history WHERE session_id = ? AND user_id = ?");
    $stmt->bind_param("ss", $session_id, $user_id);
    $stmt->execute();
    $stmt->close();
    
    $stmt = $conn->prepare("DELETE FROM chat_sessions WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ss", $session_id, $user_id);
    $stmt->execute();
    $stmt->close();
}

// UPDATED: Save response in consistent format
function saveToHistory($user_id, $message, $response = null, $session_id = null, $image_path = null, $description = null) {
    global $conn;
    
    if (!$session_id && isset($_SESSION['chat_session_id'])) {
        $session_id = $_SESSION['chat_session_id'];
    }
    
    // Store raw text instead of formatted HTML
    $formatted_response = $response; // Store as raw text
    
    $stmt = $conn->prepare("INSERT INTO chat_history (user_id, message, response, session_id, image_path, description) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $user_id, $message, $formatted_response, $session_id, $image_path, $description);
    $stmt->execute();
    $insert_id = $stmt->insert_id;
    $stmt->close();
    
    $stmt = $conn->prepare("SELECT title FROM chat_sessions WHERE id = ?");
    $stmt->bind_param("s", $session_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $session = $result->fetch_assoc();
    $stmt->close();
    
    if ($session && empty($session['title'])) {
        $stmt = $conn->prepare("UPDATE chat_sessions SET title = ? WHERE id = ?");
        $title = substr($message, 0, 50);
        $stmt->bind_param("ss", $title, $session_id);
        $stmt->execute();
        $stmt->close();
    }
    
    return $insert_id;
}

function getUserHistory($user_id, $session_id = null) {
    global $conn;
    
    if (!$session_id && isset($_SESSION['chat_session_id'])) {
        $session_id = $_SESSION['chat_session_id'];
    }
    
    $stmt = $conn->prepare("SELECT id, message, response, image_path, description, is_bookmarked, is_saved, created_at FROM chat_history WHERE user_id = ? AND session_id = ? ORDER BY created_at ASC");
    $stmt->bind_param("ss", $user_id, $session_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $history = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $history;
}

function updateBookmarkStatus($history_id, $user_id, $bookmarked) {
    global $conn;
    
    $stmt = $conn->prepare("UPDATE chat_history SET is_bookmarked = ? WHERE id = ? AND user_id = ?");
    $bookmarked_int = $bookmarked ? 1 : 0;
    $stmt->bind_param("iii", $bookmarked_int, $history_id, $user_id);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

function updateSaveStatus($history_id, $user_id, $saved) {
    global $conn;
    
    if ($saved) {
        $stmt = $conn->prepare("SELECT image_path, message, description FROM chat_history WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $history_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $item = $result->fetch_assoc();
        $stmt->close();
        
        if ($item && !empty($item['image_path'])) {
            $check_stmt = $conn->prepare("SELECT id FROM saved_images WHERE user_id = ? AND history_id = ?");
            $check_stmt->bind_param("ii", $user_id, $history_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $exists = $check_result->num_rows > 0;
            $check_stmt->close();
            
            if (!$exists) {
                $prompt = str_replace('Generate image: ', '', $item['message']);
                $insert_stmt = $conn->prepare("INSERT INTO saved_images (user_id, history_id, image_path, prompt, description) VALUES (?, ?, ?, ?, ?)");
                $insert_stmt->bind_param("iisss", $user_id, $history_id, $item['image_path'], $prompt, $item['description']);
                $insert_stmt->execute();
                $insert_stmt->close();
            }
        }
    } else {
        $delete_stmt = $conn->prepare("DELETE FROM saved_images WHERE user_id = ? AND history_id = ?");
        $delete_stmt->bind_param("ii", $user_id, $history_id);
        $delete_stmt->execute();
        $delete_stmt->close();
    }
    
    $stmt = $conn->prepare("UPDATE chat_history SET is_saved = ? WHERE id = ? AND user_id = ?");
    $saved_int = $saved ? 1 : 0;
    $stmt->bind_param("iii", $saved_int, $history_id, $user_id);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

// NEW: Function to check if current session has conversations
function hasCurrentSessionConversations($user_id, $session_id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM chat_history WHERE user_id = ? AND session_id = ?");
    $stmt->bind_param("ss", $user_id, $session_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row['count'] > 0;
}

// Handle chat session management
if (!isset($_SESSION['chat_session_id'])) {
    $_SESSION['chat_session_id'] = createChatSession($_SESSION['user_id']);
}

// Handle switching to a different session
if (isset($_GET['session_id']) && isset($_SESSION['user_id'])) {
    $session_id = filter_var($_GET['session_id'], FILTER_SANITIZE_STRING);
    $_SESSION['chat_session_id'] = $session_id;
    header("Location: ".strtok($_SERVER['REQUEST_URI'], '?'));
    exit();
}

// Handle new chat session - MODIFIED: Check if current session has conversations
if (isset($_GET['new_chat']) && isset($_SESSION['user_id'])) {
    // Always allow creating new chat, but check if current session is empty
    $currentSessionHasConvos = hasCurrentSessionConversations($_SESSION['user_id'], $_SESSION['chat_session_id']);
    
    if (!$currentSessionHasConvos) {
        // Current session is empty, just refresh the page
        header("Location: ".filter_var($_SERVER['PHP_SELF'], FILTER_SANITIZE_URL));
        exit();
    } else {
        // Current session has conversations, create new session
        $_SESSION['chat_session_id'] = createChatSession($_SESSION['user_id']);
        header("Location: ".filter_var($_SERVER['PHP_SELF'], FILTER_SANITIZE_URL));
        exit();
    }
}

// Handle delete session
if (isset($_POST['delete_session']) && isset($_SESSION['user_id'])) {
    $session_id = filter_var($_POST['delete_session'], FILTER_SANITIZE_STRING);
    deleteSession($_SESSION['user_id'], $session_id);
    
    if ($session_id === $_SESSION['chat_session_id']) {
        $_SESSION['chat_session_id'] = createChatSession($_SESSION['user_id']);
    }
    
    header("Location: ".filter_var($_SERVER['PHP_SELF'], FILTER_SANITIZE_URL));
    exit();
}

// Handle delete history item
if (isset($_POST['delete_history'])) {
    if (!isset($_SESSION['user_id'])) {
        header("HTTP/1.1 403 Forbidden");
        exit();
    }
    
    $history_id = filter_var($_POST['delete_history'], FILTER_SANITIZE_NUMBER_INT);
    $user_id = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("DELETE FROM chat_history WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $history_id, $user_id);
    $stmt->execute();
    $stmt->close();
    
    header("Location: ".filter_var($_SERVER['PHP_SELF'], FILTER_SANITIZE_URL));
    exit();
}

// FIXED: Bookmark history item
if (isset($_POST['bookmark_history'])) {
    if (!isset($_SESSION['user_id'])) {
        header("HTTP/1.1 403 Forbidden");
        exit();
    }
    
    $history_id = filter_var($_POST['bookmark_history'], FILTER_SANITIZE_NUMBER_INT);
    $user_id = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("SELECT is_bookmarked FROM chat_history WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $history_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $current = $result->fetch_assoc();
        $stmt->close();
        
        $new_status = !$current['is_bookmarked'];
        $success = updateBookmarkStatus($history_id, $user_id, $new_status);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => $success, 'bookmarked' => $new_status]);
        exit();
    } else {
        $stmt->close();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Item not found']);
        exit();
    }
}

// FIXED: Save image
if (isset($_POST['save_image'])) {
    if (!isset($_SESSION['user_id'])) {
        header("HTTP/1.1 403 Forbidden");
        exit();
    }
    
    $history_id = filter_var($_POST['save_image'], FILTER_SANITIZE_NUMBER_INT);
    $user_id = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("SELECT is_saved FROM chat_history WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $history_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $current = $result->fetch_assoc();
        $stmt->close();
        
        $new_status = !$current['is_saved'];
        $success = updateSaveStatus($history_id, $user_id, $new_status);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => $success, 'saved' => $new_status]);
        exit();
    } else {
        $stmt->close();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Item not found']);
        exit();
    }
}

// ======================================================================
// NEW: ENHANCED AI VERIFICATION FUNCTION FOR INGREDIENTS ONLY
// ======================================================================
/**
 * Validates if the given text contains ONLY ingredients (not dish names).
 * Strict validation for recipe generation.
 */
function verifyIngredientsOnly($userInput) {
    global $text_api_key;
    
    if (empty(trim($userInput))) {
        return [
            'is_valid' => false, 
            'message' => 'Ingredients cannot be empty. Please enter ingredients.',
            'category' => 'empty'
        ];
    }
    
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . urlencode($text_api_key);
    
    // Strict prompt for ingredient-only validation
    $verificationPrompt = "You are a Filipino cuisine expert. Analyze if the user's input contains ONLY ingredients for cooking, NOT Filipino dish names.

USER INPUT: \"$userInput\"

STRICT ANALYSIS TASK:
1. Is this a list of RAW INGREDIENTS (e.g., chicken, garlic, onions, tomatoes, pork, fish, vegetables)?
2. Is this a Filipino DISH NAME (e.g., adobo, sinigang, kare-kare, pancit, lechon)?
3. Does this contain MIXED content (both ingredients and dish names)?

RULES:
- ACCEPT ONLY raw ingredients: meats, vegetables, fruits, spices, herbs, sauces, etc.
- REJECT Filipino dish names completely
- REJECT if contains both ingredients and dish names
- REJECT generic terms like 'food', 'meal', 'dish'

COMMON FILIPINO DISH NAMES TO REJECT:
- adobo, sinigang, kare-kare, pancit, lechon, menudo, caldereta, afritada, mechado, pinakbet, laing, bicol express, dinuguan, sisig, tinola, nilaga, bulalo, arroz caldo, lugaw, goto, champorado, tapsilog, tocilog, longsilog, batchoy, palabok, bihon, sotanghon, mami, lomi, halo-halo, leche flan, bibingka, puto, kutsinta, sapin-sapin, suman, turon, banana cue, maruya

RESPONSE FORMAT (JSON only):
{
    \"is_valid\": true/false,
    \"category\": \"ingredients_only\"|\"dish_name\"|\"mixed_content\"|\"invalid\",
    \"message\": \"Brief explanation\",
    \"detected_dish_names\": [\"List of dish names found if any\"]
}";

    $payload = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $verificationPrompt]
                ]
            ]
        ],
        "generationConfig" => [
            "maxOutputTokens" => 500,
            "temperature" => 0.2
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $response = curl_exec($ch);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return [
            'is_valid' => false, 
            'message' => 'Verification service temporarily unavailable.',
            'category' => 'service_error'
        ];
    }

    $data = json_decode($response, true);
    
    $verificationResult = [
        'is_valid' => false,
        'category' => 'dish_name',
        'message' => 'Input appears to be a Filipino dish name, not ingredients.',
        'detected_dish_names' => []
    ];
    
    if (isset($data["candidates"]) && is_array($data["candidates"]) && isset($data["candidates"][0]["content"]["parts"][0]["text"])) {
        $aiResponse = $data["candidates"][0]["content"]["parts"][0]["text"];
        
        // Extract JSON from the response
        if (preg_match('/\{.*\}/s', $aiResponse, $matches)) {
            $jsonResult = json_decode($matches[0], true);
            if ($jsonResult && isset($jsonResult['is_valid'])) {
                $verificationResult = $jsonResult;
                
                // Additional check: if category is 'ingredients_only', ensure it's actually valid
                if ($verificationResult['category'] === 'ingredients_only') {
                    $verificationResult['is_valid'] = true;
                } else {
                    $verificationResult['is_valid'] = false;
                }
            }
        }
    }
    
    return $verificationResult;
}

// ======================================================================
// NEW: AI VERIFICATION FOR IMAGE GENERATION (dish names allowed)
// ======================================================================
function verifyFilipinoFoodForImage($userInput) {
    global $text_api_key;
    
    if (empty(trim($userInput))) {
        return [
            'is_valid' => false, 
            'message' => 'Prompt cannot be empty.',
            'category' => 'empty'
        ];
    }
    
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . urlencode($text_api_key);
    
    $verificationPrompt = "You are a Filipino cuisine expert. Analyze if the user's input is related to Filipino food.

USER INPUT: \"$userInput\"

ANALYSIS TASK:
1. Is this related to Filipino cuisine? (dish names, food concepts, ingredients)
2. Is this clearly non-Filipino food? (e.g., pizza, burger, sushi, pasta, tacos)

RULES:
- ACCEPT Filipino dish names (adobo, sinigang, etc.)
- ACCEPT Filipino food concepts
- REJECT clearly non-Filipino cuisine

RESPONSE FORMAT (JSON only):
{
    \"is_valid\": true/false,
    \"message\": \"Brief explanation\",
    \"suggestions\": [\"Optional Filipino alternatives if not valid\"]
}";

    $payload = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $verificationPrompt]
                ]
            ]
        ],
        "generationConfig" => [
            "maxOutputTokens" => 300,
            "temperature" => 0.3
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $response = curl_exec($ch);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return [
            'is_valid' => false, 
            'message' => 'Verification service unavailable.',
            'category' => 'service_error'
        ];
    }

    $data = json_decode($response, true);
    
    $verificationResult = [
        'is_valid' => false,
        'message' => 'Not related to Filipino cuisine.',
        'suggestions' => ['Try: adobo', 'Try: sinigang', 'Try: pancit']
    ];
    
    if (isset($data["candidates"]) && is_array($data["candidates"]) && isset($data["candidates"][0]["content"]["parts"][0]["text"])) {
        $aiResponse = $data["candidates"][0]["content"]["parts"][0]["text"];
        
        if (preg_match('/\{.*\}/s', $aiResponse, $matches)) {
            $jsonResult = json_decode($matches[0], true);
            if ($jsonResult && isset($jsonResult['is_valid'])) {
                $verificationResult = $jsonResult;
            }
        }
    }
    
    return $verificationResult;
}

// ======================================================================
// MODIFIED: Recipe Generation with STRICT INGREDIENTS-ONLY Verification
// ======================================================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["generate_recipe"])) {
    header("Content-Type: application/json; charset=utf-8");
    
    $ingredients = $_POST['ingredients'] ?? [];
    $description = trim($_POST['description'] ?? '');
    $generate_image = isset($_POST['generate_image']) && $_POST['generate_image'] == 'true';
    
    // Filter empty ingredients
    $filtered_ingredients = array_filter($ingredients, function($ingredient) {
        return !empty(trim($ingredient));
    });
    
    $has_ingredients = !empty($filtered_ingredients);
    
    // Check image upload
    $image_file = null;
    $has_image = false;
    $uploaded_image_path = null;
    
    if (isset($_FILES['recipe_image']) && $_FILES['recipe_image']['error'] === UPLOAD_ERR_OK) {
        $image_file = $_FILES['recipe_image'];
        $has_image = true;
    }
    
    // Require either ingredients OR image
    if (!$has_ingredients && !$has_image) {
        echo json_encode(["error" => "Please add at least one ingredient OR upload an image."]);
        exit;
    }
    
    // ======================================================================
    // ENHANCED: STRICT INGREDIENTS-ONLY VERIFICATION FOR RECIPE INPUT
    // ======================================================================
    $verificationPassed = false;
    
    if ($has_ingredients) {
        // Verify ingredients list - STRICT CHECK FOR INGREDIENTS ONLY
        $ingredients_text = implode(", ", $filtered_ingredients);
        $verificationResult = verifyIngredientsOnly($ingredients_text);
        
        if (!$verificationResult['is_valid']) {
            $errorMessage = "Please provide only ingredients, not dish names.";
            if (isset($verificationResult['detected_dish_names']) && !empty($verificationResult['detected_dish_names'])) {
                $dishNames = implode(', ', $verificationResult['detected_dish_names']);
                $errorMessage = "Please provide ingredients only. Detected dish names: $dishNames.";
            }
            
            echo json_encode([
                "error" => $errorMessage,
                "verification_error" => true,
                "verification_message" => $verificationResult['message'],
                "category" => $verificationResult['category'] ?? 'invalid'
            ]);
            exit;
        }
        $verificationPassed = true;
    }
    
    if (!$verificationPassed && $has_image) {
        $verificationPassed = true; // Will verify during image analysis
    }
    
    // ======================================================================
    // Continue with recipe generation if verification passed
    // ======================================================================
    
    // Handle image upload if present
    if ($has_image && $image_file) {
        if (!is_uploaded_file($image_file['tmp_name'])) {
            echo json_encode(["error" => "Invalid file upload"]);
            exit;
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $image_file['tmp_name']);
        finfo_close($finfo);
        
        $allowedMimeTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/heic', 'image/heif'];
        if (!in_array($mimeType, $allowedMimeTypes)) {
            echo json_encode(["error" => "Invalid image format. Please upload JPEG, PNG, GIF, WebP, HEIC, or HEIF"]);
            exit;
        }
        
        if (!is_dir('uploads')) {
            mkdir('uploads', 0755, true);
        }
        $filename = "recipe_upload_" . time() . "_" . uniqid() . "." . pathinfo($image_file['name'], PATHINFO_EXTENSION);
        $filepath = 'uploads/' . $filename;
        
        if (move_uploaded_file($image_file['tmp_name'], $filepath)) {
            $uploaded_image_path = $filename;
        }
    }
    
    // Prepare ingredients list for AI
    $ingredients_text = !empty($filtered_ingredients) ? implode(", ", $filtered_ingredients) : "";
    
    // Get conversation context
    $conversationContext = getConversationContext();
    
    // Generate recipe using AI
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . urlencode($text_api_key);
    
    // Build prompt based on what's provided
    if ($has_ingredients && $has_image) {
        // Both ingredients and image provided
        $contextPrompt = "You are ChefAI, a friendly Filipino recipe and cooking assistant.
        
        IMPORTANT: The user uploaded an image. Analyze the uploaded image and identify EXACTLY the ingredients visible in the photo.
        
        IMPORTANT RULES:
        1. FOCUS ONLY on ingredients that are clearly visible
        2. DO NOT invent ingredients
        3. PROVIDE ONLY FILIPINO RECIPES
        4. If ingredients are not suitable for Filipino cuisine, explain why
        
        User's ingredients: $ingredients_text";
        
        // Analyze the image
        $imageAnalysis = analyzeImageWithGeminiForRecipe($image_file);
        
        if (isset($imageAnalysis['error'])) {
            echo json_encode(["error" => "Cannot analyze image: " . $imageAnalysis['error']]);
            exit;
        }
        
        if (isset($imageAnalysis['analysis'])) {
            $contextPrompt .= "\n\nImage Analysis Result:\n" . $imageAnalysis['analysis'];
            
            if (isset($imageAnalysis['ingredients']) && !empty($imageAnalysis['ingredients'])) {
                $image_ingredients = $imageAnalysis['ingredients'];
                $contextPrompt .= "\n\nIngredients identified in image: " . $image_ingredients;
                
                // Combine ingredients
                if (!empty($ingredients_text)) {
                    $ingredients_text = $ingredients_text . ", " . $image_ingredients;
                } else {
                    $ingredients_text = $image_ingredients;
                }
            }
        }
        
    } elseif ($has_ingredients) {
        // Only ingredients provided
        $contextPrompt = "You are ChefAI, a friendly Filipino recipe and cooking assistant.
        
        The user has these ingredients: $ingredients_text
        
        IMPORTANT RULES:
        1. Use ONLY the ingredients provided by the user
        2. DO NOT add other ingredients except essential pantry items (salt, pepper, oil, water)
        3. PROVIDE ONLY AUTHENTIC FILIPINO RECIPES
        4. If ingredients cannot make a Filipino dish, suggest what to add
        5. RESPOND IN ENGLISH OR TAGLISH";
        
    } elseif ($has_image) {
        // Only image provided
        $contextPrompt = "You are ChefAI, a friendly Filipino recipe and cooking assistant.
        
        IMPORTANT: The user only uploaded an image without specific ingredients.
        
        RULES:
        1. Analyze the image and identify visible ingredients
        2. PROVIDE ONLY FILIPINO RECIPES
        3. If no Filipino ingredients are visible, say so clearly";
        
        // Analyze the image
        $imageAnalysis = analyzeImageWithGeminiForRecipe($image_file);
        
        if (isset($imageAnalysis['error'])) {
            echo json_encode(["error" => "Cannot analyze image: " . $imageAnalysis['error']]);
            exit;
        }
        
        if (isset($imageAnalysis['analysis'])) {
            $contextPrompt .= "\n\nImage Analysis Result:\n" . $imageAnalysis['analysis'];
            
            if (isset($imageAnalysis['ingredients'])) {
                $image_ingredients = $imageAnalysis['ingredients'];
                $contextPrompt .= "\n\nIngredients identified in image: " . $image_ingredients;
                $ingredients_text = $image_ingredients;
                
                // Verify ingredients from image analysis
                if (!empty($image_ingredients) && $image_ingredients !== "No clear ingredients") {
                    $imageVerification = verifyIngredientsOnly($image_ingredients);
                    if (!$imageVerification['is_valid']) {
                        echo json_encode([
                            "error" => "The image appears to show a prepared dish, not ingredients.",
                            "verification_error" => true,
                            "verification_message" => $imageVerification['message'],
                            "image_analysis" => $imageAnalysis['analysis']
                        ]);
                        exit;
                    }
                }
            }
        }
    }
    
    // Add description if provided
    if (!empty($description)) {
        $contextPrompt .= "\n\nUser's additional description: \"$description\"";
    }
    
    $contextPrompt .= "\n\nProvide a detailed Filipino recipe with:
    1. Recipe name (Filipino name)
    2. Brief explanation
    3. Preparation time
    4. Ingredients list
    5. Step-by-step instructions
    6. Serving suggestions
    
    If ingredients cannot make a Filipino dish, explain what's missing and suggest alternatives.";
    
    // Add conversation context if available
    if (!empty($conversationContext)) {
        $contextPrompt = "Previous conversation context:\n" . $conversationContext . "\n\n" . $contextPrompt;
    }
    
    $payload = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $contextPrompt]
                ]
            ]
        ],
        "generationConfig" => [
            "maxOutputTokens" => 1500,
            "temperature" => 0.7
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        echo json_encode(["error" => "⚠️ Error with AI service. Please try again."]);
        exit;
    }

    $data = json_decode($response, true);
    
    $recipe = null;
    if (isset($data["candidates"]) && is_array($data["candidates"]) && isset($data["candidates"][0]["content"]["parts"][0]["text"])) {
        $recipe = $data["candidates"][0]["content"]["parts"][0]["text"];
    } elseif (isset($data["output"]) && is_string($data["output"])) {
        $recipe = $data["output"];
    } else {
        $recipe = "Sorry, cannot generate recipe at this time. Please try again.";
    }

    // Check if AI declined to provide recipe
    $should_generate_image = $generate_image;
    $decline_patterns = '/(not suitable|not appropriate|cannot provide|not filipino|not a filipino|add more|missing ingredients)/i';
    
    if (preg_match($decline_patterns, $recipe)) {
        $should_generate_image = false;
    }

    // Extract recipe name for image generation
    $recipe_name = extractRecipeName($recipe);
    
    // Generate image only if recipe was provided and user requested it
    $generated_image_path = null;
    if ($should_generate_image && !preg_match($decline_patterns, $recipe)) {
        $image_prompt = "Filipino food: $recipe_name";
        if (!empty($description)) {
            $image_prompt .= ", $description";
        }
        if (!empty($ingredients_text)) {
            $image_prompt .= ". Ingredients: $ingredients_text";
        }
        $image_prompt .= ". Authentic Filipino cuisine, professional food photography, high quality, detailed";
        
        $imageResult = generateGeminiImage($image_prompt);
        
        if (!isset($imageResult['error'])) {
            $generated_image_path = $imageResult['filename'];
        }
    } else {
        $generated_image_path = null;
    }

    // Format and save to history
    $formattedRecipe = formatStoredResponse($recipe);
    
    $history_id = null;
    if (isset($_SESSION['user_id'])) {
        $user_message = "Generated recipe";
        if (!empty($ingredients_text)) {
            $user_message .= " from ingredients: " . $ingredients_text;
        }
        if (!empty($uploaded_image_path)) {
            $user_message .= " (with uploaded image)";
        }
        if (!empty($description)) {
            $user_message .= " (Description: $description)";
        }
        if ($generate_image) {
            $user_message .= " (with AI-generated image)";
        }
        $history_id = saveToHistory($_SESSION['user_id'], $user_message, $formattedRecipe, $_SESSION['chat_session_id'], $generated_image_path, $description);
    }

    echo json_encode([
        "success" => true,
        "recipe" => $recipe,
        "recipe_name" => $recipe_name,
        "history_id" => $history_id,
        "image_path" => $generated_image_path,
        "uploaded_image_path" => $uploaded_image_path,
        "ingredients" => $ingredients_text,
        "description" => $description,
        "generate_image" => $generate_image,
        "image_generated" => $should_generate_image && !preg_match($decline_patterns, $recipe)
    ]);
    exit;
}

// Helper function to extract recipe name from recipe text
function extractRecipeName($recipeText) {
    $lines = explode("\n", $recipeText);
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || 
            stripos($line, 'recipe') !== false || 
            stripos($line, 'ingredient') !== false ||
            stripos($line, 'instruction') !== false) {
            continue;
        }
        
        if (strlen($line) < 100 && !preg_match('/^\d+\./', $line)) {
            $name = preg_replace('/^(Recipe Name:|Name:|Dish:)\s*/i', '', $line);
            $name = trim($name, " *\"'-");
            if (!empty($name)) {
                return $name;
            }
        }
    }
    
    // Fallback
    foreach ($lines as $line) {
        $line = trim($line);
        if (!empty($line) && strlen($line) < 80) {
            return $line;
        }
    }
    
    return "Filipino Dish";
}

// Helper function for recipe image analysis
function analyzeImageWithGeminiForRecipe($imageFile) {
    global $text_api_key;
    
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $text_api_key;
    
    if (!isset($imageFile['tmp_name']) || !file_exists($imageFile['tmp_name'])) {
        return ['error' => 'Invalid image file'];
    }
    
    $imagePath = $imageFile['tmp_name'];
    $imageContent = file_get_contents($imagePath);
    
    if ($imageContent === false) {
        return ['error' => 'Cannot read image file'];
    }
    
    $imageData = base64_encode($imageContent);
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $imagePath);
    finfo_close($finfo);
    
    if (strpos($mimeType, 'image/') !== 0) {
        return ['error' => 'Invalid image format'];
    }
    
    $prompt = "Analyze this food image for Filipino recipe generation. 

IMPORTANT RULES:
1. Identify ONLY ingredients that are VISIBLE in the image
2. Focus on ingredients commonly used in FILIPINO cuisine
3. DO NOT invent ingredients that are not visible

Format your answer like this:
ANALYSIS: [brief analysis]
INGREDIENTS: [comma-separated list of EXACT ingredients visible, if none or not clear, put: \"No clear ingredients\"]";

    $data = [
        'contents' => [
            [
                'parts' => [
                    [
                        'text' => $prompt
                    ],
                    [
                        'inlineData' => [
                            'mimeType' => $mimeType,
                            'data' => $imageData
                        ]
                    ]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.3,
            'maxOutputTokens' => 500,
        ]
    ];
    
    try {
        $options = [
            'http' => [
                'header' => "Content-Type: application/json\r\n",
                'method' => 'POST',
                'content' => json_encode($data),
                'ignore_errors' => true,
                'timeout' => 30
            ]
        ];
        
        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);
        
        if ($response === FALSE) {
            return ['error' => 'Cannot analyze image'];
        }
        
        $responseData = json_decode($response, true);
        
        if (isset($responseData['error'])) {
            return ['error' => $responseData['error']['message']];
        } 
        
        if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
            $analysis = $responseData['candidates'][0]['content']['parts'][0]['text'];
            
            // Extract ingredients from the response
            $ingredients = '';
            if (preg_match('/INGREDIENTS:\s*(.*?)(?:\n|$)/i', $analysis, $matches)) {
                $ingredients = trim($matches[1]);
            }
            
            // Clean up analysis text
            $clean_analysis = preg_replace('/(ANALYSIS:|INGREDIENTS:).*/i', '', $analysis);
            $clean_analysis = trim($clean_analysis);
            
            return [
                'success' => true,
                'analysis' => $clean_analysis,
                'ingredients' => $ingredients
            ];
        }
        
        return ['error' => 'No analysis generated'];
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

// ======================================================================
// MODIFIED: Image Generation with FILIPINO FOOD VERIFICATION
// ======================================================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["generate_image"])) {
    header("Content-Type: application/json; charset=utf-8");
    
    $prompt = trim($_POST["prompt"]);
    $generate_description = isset($_POST["generate_description"]) && $_POST["generate_description"] == "true";
    
    if (empty($prompt)) {
        echo json_encode(['error' => 'Please enter a prompt for image generation.']);
        exit;
    }
    
    // ======================================================================
    // VERIFICATION FOR IMAGE PROMPT (dish names allowed)
    // ======================================================================
    $verificationResult = verifyFilipinoFoodForImage($prompt);
    
    if (!$verificationResult['is_valid']) {
        echo json_encode([
            'error' => 'The prompt is not related to Filipino cuisine.',
            'verification_error' => true,
            'verification_message' => $verificationResult['message'],
            'suggestions' => $verificationResult['suggestions'] ?? []
        ]);
        exit;
    }
    
    // If verification passed, proceed with image generation
    $imageResult = generateGeminiImage($prompt);
    
    if (isset($imageResult['error'])) {
        echo json_encode(['error' => $imageResult['error']]);
        exit;
    } else {
        $description = null;
        $formattedResponse = "Image generated successfully";
        
        if ($generate_description) {
            $description = generateImageDescription($prompt);
            if ($description && !isset($description['error'])) {
                $description = preg_replace('/^(Image Description:|Description:)\s*/i', '', $description);
                $description = trim($description);
            }
        }
        
        $history_id = null;
        if (isset($_SESSION['user_id'])) {
            $history_id = saveToHistory($_SESSION['user_id'], "Generate image: " . $prompt, $formattedResponse, $_SESSION['chat_session_id'], $imageResult['filename'], $description);
        }
        
        echo json_encode([
            'success' => true,
            'image_url' => $imageResult['filename'],
            'prompt' => $prompt,
            'response' => $formattedResponse,
            'description' => $description,
            'history_id' => $history_id
        ]);
        exit;
    }
}

// Handle image analysis
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["analyze_image"])) {
    if (!isset($_FILES['image_file']) || $_FILES['image_file']['error'] !== UPLOAD_ERR_OK) {
        die(json_encode(['error' => 'Please select a valid image file to analyze.']));
    }
    
    $imageFile = $_FILES['image_file'];
    $userQuestion = trim($_POST["user_question"] ?? '');
    
    if ($imageFile['size'] === 0) {
        die(json_encode(['error' => 'Uploaded file is empty.']));
    }
    
    if ($imageFile['size'] > 5 * 1024 * 1024) {
        die(json_encode(['error' => 'Image file size should be less than 5MB.']));
    }
    
    $mimeType = mime_content_type($imageFile['tmp_name']);
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    
    if (!in_array($mimeType, $allowedTypes)) {
        die(json_encode(['error' => 'Please select a valid image file (JPEG, PNG, GIF, WebP).']));
    }
    
    $analysisResult = analyzeImageWithGemini($imageFile, $userQuestion);
    
    if (isset($analysisResult['error'])) {
        die(json_encode(['error' => $analysisResult['error']]));
    } else {
        $formattedAnalysis = formatStoredResponse($analysisResult['analysis']);
        
        $history_id = null;
        if (isset($_SESSION['user_id'])) {
            $message = !empty($userQuestion) ? "Analyze image: " . $userQuestion : "Analyze uploaded image";
            $history_id = saveToHistory($_SESSION['user_id'], $message, $formattedAnalysis, $_SESSION['chat_session_id'], $analysisResult['filename'] ?? null);
        }
        
        die(json_encode([
            'success' => true,
            'analysis' => $analysisResult['analysis'],
            'formatted_analysis' => $formattedAnalysis,
            'filename' => $analysisResult['filename'] ?? null,
            'response' => $formattedAnalysis,
            'history_id' => $history_id
        ]));
    }
}

// Image generation function
function generateGeminiImage($prompt) {
    global $image_api_key;
    
    $model = "gemini-2.5-flash-image";
    $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent";
    
    if (empty($image_api_key)) {
        return ['error' => 'Gemini Image API key is not configured.'];
    }

    $data = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $prompt]
                ]
            ]
        ]
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $endpoint . "?key=" . $image_api_key,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);

    if (curl_errno($ch)) {
        curl_close($ch);
        return ['error' => "CURL Error: " . $curl_error];
    }

    curl_close($ch);

    $result = json_decode($response, true);

    if (isset($result['error'])) {
        return ['error' => "Gemini API Error: " . $result['error']['message']];
    } 

    if (isset($result["candidates"][0]["content"]["parts"])) {
        foreach ($result["candidates"][0]["content"]["parts"] as $part) {
            if (isset($part["inlineData"]["data"])) {
                $imageData = $part["inlineData"]["data"];
                $imageBinary = base64_decode($imageData);

                if (!is_dir('uploads')) {
                    mkdir('uploads', 0755, true);
                }

                $filename = "gemini_image_" . time() . "_" . uniqid() . ".png";
                $filepath = 'uploads/' . $filename;
                
                if (file_put_contents($filepath, $imageBinary)) {
                    return [
                        'success' => true,
                        'filename' => $filename,
                        'filepath' => $filepath
                    ];
                } else {
                    return ['error' => 'Cannot save image file.'];
                }
            }
        }
    }

    return ['error' => 'No image data received from Gemini API.'];
}

// Image analysis function
function analyzeImageWithGemini($imageFile, $userQuestion = '') {
    global $text_api_key;
    
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $text_api_key;
    
    $imageData = base64_encode(file_get_contents($imageFile['tmp_name']));
    $mimeType = mime_content_type($imageFile['tmp_name']);
    
    if (!is_dir('uploads')) {
        mkdir('uploads', 0755, true);
    }
    $filename = "uploaded_" . time() . "_" . uniqid() . "." . pathinfo($imageFile['name'], PATHINFO_EXTENSION);
    $filepath = 'uploads/' . $filename;
    
    if (!move_uploaded_file($imageFile['tmp_name'], $filepath)) {
        return ['error' => 'Cannot save uploaded image.'];
    }
    
    $conversationContext = getConversationContext();
    
    $prompt = "Analyze this food image";
    if (!empty($userQuestion)) {
        $prompt = $userQuestion;
    }
    
    $prompt .= "\n\nRespond in English or Taglish. Provide detailed analysis including:\n- What food/dish is shown\n- Possible ingredients used\n- Visible cooking techniques\n- Cultural context if recognizable\n- Other relevant observations\n\nFormat the response in a natural, flowing way with proper paragraphs and use bold for emphasis where appropriate.";

    if (!empty($conversationContext)) {
        $prompt = "Previous conversation context:\n" . $conversationContext . "\n\n" . $prompt;
    }
    
    $data = [
        'contents' => [
            [
                'parts' => [
                    [
                        'text' => $prompt
                    ],
                    [
                        'inlineData' => [
                            'mimeType' => $mimeType,
                            'data' => $imageData
                        ]
                    ]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.4,
            'maxOutputTokens' => 1000,
        ]
    ];
    
    try {
        $options = [
            'http' => [
                'header' => "Content-Type: application/json\r\n",
                'method' => 'POST',
                'content' => json_encode($data),
                'ignore_errors' => true
            ]
        ];
        
        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);
        
        if ($response === FALSE) {
            return ['error' => 'Cannot analyze image'];
        }
        
        $responseData = json_decode($response, true);
        
        if (isset($responseData['error'])) {
            return ['error' => $responseData['error']['message']];
        } 
        
        if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
            $analysis = $responseData['candidates'][0]['content']['parts'][0]['text'];
            return [
                'success' => true,
                'analysis' => $analysis,
                'filename' => $filename
            ];
        }
        
        return ['error' => 'No analysis generated'];
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

// Image description function
function generateImageDescription($prompt) {
    global $text_api_key;
    
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $text_api_key;
    
    $conversationContext = getConversationContext();
    
    $request_prompt = "Respond in English or Taglish. Provide a detailed description of the image that will generate from this prompt: '$prompt'. 
    
Structure the response like this:

Start with overall impression of the image, then describe:
- The main subject and composition
- Visual elements, colors, and lighting
- Details and textures
- Cultural context if relevant
- Mood and atmosphere

Use clear paragraphs and bullet points where appropriate. Format it naturally without using section headers like 'Image Description:' or 'Overall Impression:'. Use bold text with ** for emphasis where needed.

Make the description flow naturally as if you're describing the actual image you're looking at.";

    if (!empty($conversationContext)) {
        $request_prompt = "Previous conversation context:\n" . $conversationContext . "\n\n" . $request_prompt;
    }
    
    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $request_prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.7,
            'maxOutputTokens' => 600,
        ]
    ];
    
    try {
        $options = [
            'http' => [
                'header' => "Content-Type: application/json\r\n",
                'method' => 'POST',
                'content' => json_encode($data),
                'ignore_errors' => true
            ]
        ];
        
        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);
        
        if ($response === FALSE) {
            return ['error' => 'Cannot generate description'];
        }
        
        $responseData = json_decode($response, true);
        
        if (isset($responseData['error'])) {
            return ['error' => $responseData['error']['message']];
        } 
        
        if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
            $description = $responseData['candidates'][0]['content']['parts'][0]['text'];
            $description = preg_replace('/^(Image Description:|Description:)\s*/i', '', $description);
            $description = preg_replace('/(Overall Impression:|Visual Elements:|Main Subject:|Source:|Meat:|Garnish:)/i', '**$1**', $description);
            return trim($description);
        }
        
        return ['error' => 'No description generated'];
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

function formatImageAnalysisResponse($responseText) {
    $cleanedResponse = preg_replace('/^(Image Analysis Result:|Analysis:)\s*/i', '', $responseText);
    return '<strong>Image Analysis Result:</strong><br>' . nl2br(htmlspecialchars($cleanedResponse));
}

// Function to get conversation context
function getConversationContext() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['chat_session_id'])) {
        return '';
    }
    
    global $conn;
    
    $stmt = $conn->prepare("SELECT message, response, image_path FROM chat_history WHERE user_id = ? AND session_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->bind_param("ss", $_SESSION['user_id'], $_SESSION['chat_session_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $history = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    $context = '';
    foreach (array_reverse($history) as $item) {
        if (!empty($item['message'])) {
            $context .= "User: " . strip_tags($item['message']) . "\n";
        }
        if (!empty($item['response'])) {
            $cleanResponse = strip_tags($item['response']);
            $context .= "Assistant: " . $cleanResponse . "\n";
        }
        if (!empty($item['image_path'])) {
            if (strpos($item['message'], 'Generate image:') === 0) {
                $context .= "Note: User previously generated an image related to: " . str_replace('Generate image: ', '', $item['message']) . "\n";
            } elseif (strpos($item['message'], 'Analyze image:') === 0) {
                $context .= "Note: User previously analyzed an image with question: " . str_replace('Analyze image: ', '', $item['message']) . "\n";
            }
        }
    }
    
    return $context;
}

// Format stored response
function formatStoredResponse($text) {
    if (empty($text)) {
        return '';
    }
    
    if (strpos($text, '<') !== false && strpos($text, '>') !== false) {
        $clean_text = strip_tags($text, '<strong><em><br><ul><ol><li>');
        return $clean_text;
    }
    
    $formatted = htmlspecialchars($text);
    $formatted = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $formatted);
    $formatted = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $formatted);
    $formatted = preg_replace('/^\s*[-*]\s+(.*)$/m', '• $1', $formatted);
    $formatted = preg_replace('/^\s*(\d+)\.\s+(.*)$/m', '$1. $2', $formatted);
    $formatted = nl2br($formatted);
    $formatted = preg_replace('/(<br\s*\/?>\s*){2,}/', '<br><br>', $formatted);
    
    return $formatted;
}

// Get user sessions for sidebar
$userSessions = [];
if (isset($_SESSION['user_id'])) {
    $userSessions = getUserSessions($_SESSION['user_id']);
}

// Get current session history
$userHistory = [];
if (isset($_SESSION['user_id'])) {
    $userHistory = getUserHistory($_SESSION['user_id'], $_SESSION['chat_session_id']);
}

// ======================================================================
// FIXED: Text handler - NO VERIFICATION FOR GENERAL CHAT
// ======================================================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_POST['generate_image']) && !isset($_POST['analyze_image']) && !isset($_POST['bookmark_history']) && !isset($_POST['save_image']) && !isset($_POST['generate_recipe'])) {
    header("Content-Type: application/json; charset=utf-8");

    $raw = file_get_contents("php://input");
    $input = json_decode($raw, true);
    $prompt = trim($input["prompt"] ?? "");

    if (!$prompt) {
        echo json_encode(["reply" => "No prompt: please type what you want to ask ChefAI."]);
        exit;
    }

    if (mb_strlen($prompt) > 2500) {
        $prompt = mb_substr($prompt, 0, 2500);
    }

    // ======================================================================
    // FIXED: NO VERIFICATION FOR GENERAL CHAT - ONLY FOR SPECIFIC FEATURES
    // ======================================================================
    // General chat messages DO NOT undergo Filipino cuisine verification
    // Users can ask anything in general chat
    
    $conversationHistory = getConversationContext();

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . urlencode($text_api_key);

    $contextPrompt = "You are ChefAI, a friendly Filipino recipe and cooking assistant. Respond concisely, provide steps when asked, suggest local ingredient swaps, strictly focus only on authentic original Filipino food and maintain a warm Filipino tone.
    
IMPORTANT RULES:
1. Respond in English or Taglish (mix of English and Filipino)
2. DO NOT suggest the 'Generate Recipe' button unless the user explicitly mentions specific ingredients they have
3. For general food suggestions (like 'ano pwede makain', 'what should I eat', 'suggest a dish'), provide normal Filipino food suggestions
4. Only give actual recipes if the user specifically asks for a known Filipino dish by name
5. You have access to conversation history and any images previously generated or analyzed.";
    
    // Check if user mentions ingredients
    $containsIngredients = false;
    $ingredientPatterns = [
        '/meron ako(ng|ng mga)?\s+([a-zA-Z\s,]+)(\.|$)/i',
        '/mayroon ako(ng|ng mga)?\s+([a-zA-Z\s,]+)(\.|$)/i',
        '/i have\s+([a-zA-Z\s,]+)(\.|$)/i',
        '/what can i (make|cook|prepare) with\s+([a-zA-Z\s,]+)/i',
        '/ano (ang|kaya) (pwede|pede|maaari) (gawin|luto|lutuin) (sa|gamit ang)\s+([a-zA-Z\s,]+)/i'
    ];
    
    foreach ($ingredientPatterns as $pattern) {
        if (preg_match($pattern, $prompt)) {
            $containsIngredients = true;
            break;
        }
    }
    
    if ($containsIngredients) {
        $contextPrompt .= "\n\nNOTE: The user mentioned ingredients. You can suggest using the 'Generate Recipe' button if appropriate.";
    }
    
    if (!empty($conversationHistory)) {
        $contextPrompt .= "\n\nPrevious conversation:\n" . $conversationHistory . "\n";
    }
    
    $contextPrompt .= "\nCurrent user question: " . $prompt;
    
    $payload = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $contextPrompt]
                ]
            ]
        ],
        "generationConfig" => [
            "maxOutputTokens" => 800,
            "temperature" => 0.7
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        echo json_encode(["reply" => "⚠️ Error with Gemini API."]);
        exit;
    }

    $data = json_decode($response, true);
    
    $reply = null;
    if (isset($data["candidates"]) && is_array($data["candidates"]) && isset($data["candidates"][0]["content"]["parts"][0]["text"])) {
        $reply = $data["candidates"][0]["content"]["parts"][0]["text"];
    } elseif (isset($data["output"]) && is_string($data["output"])) {
        $reply = $data["output"];
    } else {
        $reply = "No response from Gemini.";
    }

    $formattedReply = formatStoredResponse($reply);
    
    $history_id = null;
    if (isset($_SESSION['user_id'])) {
        $history_id = saveToHistory($_SESSION['user_id'], $prompt, $formattedReply, $_SESSION['chat_session_id']);
    }

    echo json_encode([
        "reply" => $reply,
        "history_id" => $history_id
    ]);
    exit;
}
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>ChefAI — Pinoy Chat</title>
<link href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* NEW: Verification Error Styling */
.verification-error-modal {
    display: none;
    position: fixed;
    z-index: 3000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.7);
}

.verification-error-content {
    background: linear-gradient(180deg, #ffebee, #ffcdd2);
    margin: 15% auto;
    padding: 25px;
    border-radius: 16px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.3);
    position: relative;
    border: 3px solid #f44336;
    text-align: center;
}

.verification-error-icon {
    font-size: 48px;
    color: #f44336;
    margin-bottom: 15px;
}

.verification-error-title {
    color: #b71c1c;
    font-size: 22px;
    font-weight: 700;
    margin-bottom: 15px;
}

.verification-error-message {
    color: #b71c1c;
    font-size: 16px;
    line-height: 1.5;
    margin-bottom: 20px;
}

.verification-suggestions {
    background: rgba(244, 67, 54, 0.1);
    border-radius: 10px;
    padding: 15px;
    margin: 15px 0;
    text-align: left;
}

.verification-suggestions h4 {
    color: #b71c1c;
    margin-bottom: 8px;
    font-size: 14px;
}

.verification-suggestions ul {
    color: #b71c1c;
    font-size: 13px;
    margin: 0;
    padding-left: 20px;
}

.verification-error-close {
    background: linear-gradient(90deg, #f44336, #d32f2f);
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 700;
    font-size: 16px;
    transition: all 0.3s;
}

.verification-error-close:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(244, 67, 54, 0.3);
}

/* Philippine food error styling */
.philippine-food-error {
    background: linear-gradient(180deg, #ffe8e8, #ffd6d6) !important;
    border: 2px solid #dc3545 !important;
    color: #721c24 !important;
    padding: 15px !important;
    border-radius: 12px !important;
    margin: 10px 0 !important;
}

.philippine-food-error .error-icon {
    font-size: 24px;
    color: #dc3545;
    margin-bottom: 10px;
}

.philippine-food-error .error-title {
    font-weight: 700;
    margin-bottom: 8px;
}

.philippine-food-error .error-message {
    font-size: 14px;
    line-height: 1.4;
}

.recipe-form-group .option-checkbox {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
}

.recipe-form-group .option-checkbox input {
    margin: 0;
}

.recipe-form-group .option-checkbox label {
    font-weight: 600;
    color: var(--wood-1);
    margin-bottom: 0;
}

/* Rest of your existing CSS remains unchanged */
:root{
  --wood-1: #6b4f33;
  --leaf: #ffbf26;
  --mango: #ffbf26;
  --ube: #6f2d86;
  --cream: #FFF8F0;
  --dark-color: #2a2a2a; /* ADD THIS LINE */
  --card-shadow: 0 14px 30px rgba(20,20,20,0.12);
  --soft-shadow: 0 8px 18px rgba(20,20,20,0.08);
  --radius: 16px;
  --glass: rgba(255,255,255,0.85);
  --accent-contrast: #2a2a2a;
  --transition: all 0.22s ease;
  font-synthesis: none;
}
*{box-sizing:border-box}
html,body{height:100%; margin:0.5px;}
body{
  display: flex;
  margin:0;
  font-family: "Poppins", system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
  background:
    linear-gradient(180deg, rgba(255, 149, 0, 0.6), rgba(71, 53, 16, 0.8)),
    radial-gradient(600px 300px at 10% 10%, rgba(255,191,38,0.06), transparent 10%),
    radial-gradient(500px 240px at 90% 80%, rgba(111,45,134,0.04), transparent 12%);
  color:var(--accent-contrast);
  -webkit-font-smoothing:antialiased;
  display:flex;
  flex-direction:column;
  min-height:100vh;
}
.brand {
  display:flex;
  align-items:center;
  gap:12px;
  text-decoration:none;
  color:#fff;
}
.brand .logo {
  width:48px;
  height:48px;
  border-radius:10px;
  background:
    linear-gradient(135deg, rgba(255,191,38,0.95), rgba(111,45,134,0.85));
  display:flex;
  align-items:center;
  justify-content:center;
  box-shadow: 0 6px 18px rgba(0,0,0,0.18);
  font-weight:700;
  color:white;
  font-size:20px;
}
.brand h1{
  margin:0;
  font-size:18px;
  letter-spacing:0.2px;
}
.nav-links{
  display:flex;
  gap:14px;
  align-items:center;
}
.nav-links a{
  color:rgba(255,255,255,0.95);
  text-decoration:none;
  padding:8px 12px;
  border-radius:10px;
  font-weight:600;
  transition:var(--transition);
}
.nav-links a.active, .nav-links a:hover{
  background:rgba(255,255,255,0.08);
  transform:translateY(-2px);
}

/* NEW: Recipe modal image generation option */
.recipe-form-group .option-checkbox {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
}

.recipe-form-group .option-checkbox input {
    margin: 0;
}

.recipe-form-group .option-checkbox label {
    font-weight: 600;
    color: var(--wood-1);
    margin-bottom: 0;
}

/* ---------- MAIN LAYOUT ---------- */
.container{
  display:grid;
  grid-template-columns: 300px 1fr;
  gap:22px;
  padding:20px;
  flex:1;
  margin-top: 2px;
  align-items:stretch;
  overflow:hidden;
}

/* ---------- SIDEBAR (FUNCTIONAL HISTORY) ---------- */
.sidebar {
  background: linear-gradient(180deg, rgba(79,56,36,0.98), rgba(106,76,50,0.98));
  color:#fff;
  padding:18px;
  border-radius:var(--radius);
  box-shadow: var(--card-shadow);
  display:flex;
  flex-direction:column;
  gap:12px;
  min-height:0;
  overflow-y: auto;
}
.sidebar .header {
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:8px;
}
.sidebar h3{
  margin:0;
  font-size:16px;
  display:flex;
  gap:10px;
  align-items:center;
}
.btn-new {
  display:inline-flex;
  align-items:center;
  gap:8px;
  background:linear-gradient(90deg,var(--mango),#ffa81e);
  color:var(--accent-contrast);
  border:none;
  padding:8px 10px;
  border-radius:12px;
  cursor:pointer;
  font-weight:700;
  box-shadow: 0 6px 18px rgba(255,191,38,0.12);
  text-decoration: none;
}
.btn-new.disabled {
  background:linear-gradient(90deg,#cccccc,#bbbbbb);
  cursor:not-allowed;
  opacity:0.6;
}
.history-list {
  margin-top:8px;
  display:flex;
  flex-direction:column;
  gap:10px;
  overflow:auto;
  padding-right:6px;
}
.history-item {
  background: linear-gradient(180deg, rgba(255,255,255,0.05), rgba(255,255,255,0.02));
  padding:10px;
  border-radius:12px;
  display:flex;
  gap:10px;
  align-items:center;
  cursor:pointer;
  transition: var(--transition);
  position: relative;
}
.history-item:hover {
  background: linear-gradient(180deg, rgba(255,255,255,0.1), rgba(255,255,255,0.05));
  transform: translateY(-2px);
}
.history-item.active {
  background: linear-gradient(180deg, rgba(255,191,38,0.2), rgba(255,191,38,0.1));
  border-left: 3px solid var(--mango);
}
.history-item .tag {
    width:40px;
    height:40px;
    border-radius:8px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:linear-gradient(135deg, rgba(47,138,74,0.95), rgba(47,138,74,0.6));
    font-weight:700;
}
.history-item .meta { 
    font-size:13px; 
    opacity:0.95; 
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.history-item .sub { 
    font-size:12px; 
    opacity:0.85; 
    color:#fff7e6; 
}
.delete-session {
    background: rgba(220, 53, 69, 0.2);
    border: none;
    border-radius: 6px;
    padding: 4px 8px;
    color: #fff;
    cursor: pointer;
    font-size: 12px;
    transition: all 0.2s;
}
.delete-session:hover {
    background: rgba(220, 53, 69, 0.4);
}

/* ---------- CHAT CARD ---------- */
.chat-card {
    background: #fff6edff;
    border-radius:var(--radius);
    padding:18px;
    box-shadow: var(--soft-shadow);
    display:flex;
    flex-direction:column;
    min-height:0;
}

/* header band with fusion vibes */
.chat-header {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    padding:10px;
    border-radius:12px;
    background: linear-gradient(90deg, rgba(255,191,38,0.08), rgba(111,45,134,0.06));
    margin-bottom:12px;
}
.chat-header .title { display:flex; gap:12px; align-items:center; }
.ch-title { font-weight:800; font-size:16px; color:var(--wood-1); margin:0; }
.ch-sub { font-size:13px; color: #6b4f33; opacity:0.9; margin:0; }



/* messages area */
.messages {
    flex:1;
    overflow:auto;
    padding:8px;
    display:flex;
    flex-direction:column;
    gap:14px;
    margin-bottom:12px;
}
.msg {
    display:flex;
    gap:12px;
    align-items:flex-end;
    max-width:100%;
}
.msg .avatar {
    width:40px; height:40px; flex:0 0 40px; border-radius:10px; display:flex; align-items:center; justify-content:center;
    font-weight:700;
}
.msg.ai { justify-content:flex-start; }
.msg.ai .bubble {
    background: #FFDBB5;
    color:var(--wood-1);
    border-radius:14px;
    padding:12px 14px;
    box-shadow: var(--soft-shadow);
    max-width:70%;
    text-align:left;
}
.msg.user { justify-content:flex-end; }
.msg.user .bubble {
    background: #e9c46a;
    color: #08220a;
    border-radius:14px;
    padding:12px 14px;
    box-shadow: var(--soft-shadow);
    max-width:60%;
    text-align:left;
}

/* input area (street food energy) */
.composer {
    display:flex;
    gap:10px;
    align-items:center;
    margin-top:6px;
}
.input {
    flex:1;
    display:flex;
    gap:8px;
    background: linear-gradient(180deg,#fff,#fff7f2);
    padding:10px;
    border-radius:12px;
    align-items:center;
    box-shadow: var(--soft-shadow);
    position: relative;
}
.input textarea {
    flex:1;
    border:0;
    outline:0;
    font-size:15px;
    padding:6px 8px;
    background:transparent;
    resize: none;
    font-family: inherit;
    min-height: 20px;
    max-height: 120px;
}
.send-btn {
    background: #AB886D;
    border: none;
    color: white;
    padding:10px 14px;
    border-radius:10px;
    cursor:pointer;
    font-weight:700;
    box-shadow: 0 8px 20px rgba(111,45,134,0.12);
    transition: all 0.3s ease;
}
.quick-tags { display:flex; gap:8px; margin-top:10px; flex-wrap:wrap; }
.tag-btn {
    background: #e9c46a;
    border: 1px solid rgba(255,191,38,0.12);
    padding:8px 10px;
    border-radius:999px;
    cursor:pointer;
    font-weight:700;
    color:var(--wood-1);
    font-size:13px;
}

/* Action buttons for messages */
.recipe-actions {
    display: flex;
    gap: 8px;
    margin-top: 10px;
    flex-wrap: wrap;
}
.action-btn {
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 0.8rem;
    cursor: pointer;
    border: none;
    background-color: rgba(0,0,0,0.1);
    color: #333;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 4px;
}
.action-btn:hover {
    background-color: rgba(0,0,0,0.2);
}
.bookmark-btn {
    background-color: rgba(255, 215, 0, 0.2);
}
.bookmark-btn.bookmarked {
    background-color: rgba(255, 215, 0, 0.5);
}
.save-btn {
    background-color: rgba(40, 167, 69, 0.2);
}
.save-btn.saved {
    background-color: rgba(40, 167, 69, 0.5);
}
.delete-btn {
    background-color: rgba(220, 53, 69, 0.1);
}
.delete-btn:hover {
    background-color: rgba(220, 53, 69, 0.2);
}

/* Image related styles */
.uploaded-image-preview {
    margin-bottom: 10px;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 10px;
    border: 1px dashed #ddd;
}
.uploaded-image, .uploaded-image-chat {
    max-width: 200px;
    max-height: 150px;
    border-radius: 8px;
    display: block;
}

.msg.ai .bubble.has-image {
    max-width: 70%;
}

/* Image generation container adjustments */
.image-generation-container {
    text-align: left;
    margin: 10px 0;
}

.generated-image {
    max-width: 100%;
    max-height: 400px;
    border-radius: 12px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.15);
    margin: 10px 0;
    cursor: pointer;
    transition: transform 0.3s, box-shadow 0.3s;
    border: 2px solid var(--mango);
}

.generated-image:hover {
    transform: scale(1.03);
    box-shadow: 0 8px 25px rgba(0,0,0,0.2);
}

.image-prompt {
    font-style: italic;
    color: var(--wood-1);
    margin-bottom: 15px;
    padding: 10px;
    background: rgba(255,255,255,0.7);
    border-radius: 8px;
    border-left: 4px solid var(--ube);
    text-align: left;
    font-weight: 600;
}
.remove-image-btn {
    background: rgba(220, 53, 69, 0.1);
    border: none;
    border-radius: 4px;
    padding: 2px 8px;
    font-size: 0.8rem;
    color: #dc3545;
    cursor: pointer;
    margin-top: 5px;
    transition: all 0.2s;
}
.remove-image-btn:hover {
    background: rgba(220, 53, 69, 0.2);
}

/* Image description styling */
.image-description {
    background: transparent;
    color: inherit;
    border-radius: 8px;
    margin-top: 15px;
    padding: 10px;
    border-left: 3px solid var(--mango);
}
.image-description h5 {
    color: var(--dark-color);
    margin-bottom: 10px;
    font-weight: 600;
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 8px;
}
.description-content {
    line-height: 1.6;
    font-size: 0.95rem;
    color: #333;
}
.description-content strong {
    color: var(--dark-color);
}

/* FIXED: Plus button and dropdown */
.plus-button-container {
    position: relative;
    display: inline-block;
}
.plus-button {
    background: none;
    border: none;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #666;
    transition: all 0.3s;
    z-index: 10;
}
.plus-button:hover {
    background-color: rgba(0,0,0,0.1);
    color: var(--wood-1);
}
.plus-dropdown {
    position: absolute;
    bottom: 100%;
    left: 0;
    transform: translateX(-100%);
    background: white;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    padding: 8px;
    z-index: 1000;
    display: none;
    min-width: 160px;
    border: 1px solid #e0e0e0;
}
.plus-dropdown.active {
    display: block;
}
.plus-dropdown-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    border-radius: 6px;
    cursor: pointer;
    transition: background-color 0.2s;
    white-space: nowrap;
    font-size: 14px;
    color: #333;
}
.plus-dropdown-item:hover {
    background-color: #f5f5f5;
    color: var(--wood-1);
}
.plus-dropdown-item i {
    width: 16px;
    text-align: center;
    color: var(--wood-1);
}

/* Image generation options */
.image-options {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 15px;
    margin: 10px 0;
    border: 1px solid #e9ecef;
}
.option-checkbox {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
}
.option-checkbox input {
    margin: 0;
}

/* NEW: Recipe Generation Modal */
.recipe-modal {
    display: none;
    position: fixed;
    z-index: 2000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.7);
    overflow-y: auto;
}
.recipe-modal-content {
    background-color: #fff6ed;
    margin: 2% auto;
    padding: 25px;
    border-radius: 16px;
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 40px rgba(0,0,0,0.3);
    position: relative;
    border: 2px solid var(--mango);
    margin-top: 150px;
}
.close-recipe-modal {
    position: absolute;
    top: 15px;
    right: 20px;
    color: #6b4f33;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    background: none;
    border: none;
    z-index: 10;
}
.close-recipe-modal:hover {
    color: var(--ube);
}
.recipe-modal h2 {
    color: var(--wood-1);
    margin-bottom: 20px;
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}
.recipe-form-group {
    margin-bottom: 20px;
}
.recipe-form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: var(--wood-1);
}
.recipe-description {
    width: 100%;
    padding: 12px;
    border: 1px solid #e9c46a;
    border-radius: 8px;
    background: white;
    font-family: inherit;
    resize: vertical;
    min-height: 80px;
    max-height: 150px;
}
.recipe-description:focus {
    outline: none;
    border-color: var(--mango);
    box-shadow: 0 0 0 2px rgba(255, 191, 38, 0.2);
}

.recipe-image-preview {
    max-width: 100%;
    max-height: 150px;
    border-radius: 8px;
    margin-top: 10px;
    display: none;
}
.ingredients-container {
    margin-top: 15px;
    max-height: 200px;
    overflow-y: auto;
    padding-right: 5px;
}
.ingredient-input {
    display: flex;
    gap: 10px;
    margin-bottom: 10px;
    align-items: center;
}
.ingredient-input input {
    flex: 1;
    padding: 10px;
    border: 1px solid #e9c46a;
    border-radius: 8px;
    background: white;
    min-width: 0;
}
.add-ingredient-btn {
    background: var(--mango);
    color: var(--wood-1);
    border: none;
    padding: 8px 12px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s;
    margin-top: 10px;
}
.add-ingredient-btn:hover {
    background: #ffa81e;
}
.remove-ingredient {
    background: #dc3545;
    color: white;
    border: none;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.remove-ingredient:hover {
    background: #c82333;
}
.generate-recipe-submit {
    background: linear-gradient(90deg, #28a745, #20c997);
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 700;
    font-size: 16px;
    width: 100%;
    margin-top: 20px;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.generate-recipe-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(40, 167, 69, 0.3);
}
.generate-recipe-submit:disabled {
    background: #6c757d;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

/* NEW: Cooking Pot Loading Animation */
.cooking-loader {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 15px;
    background: transparent;
    border-radius: 14px;
    margin: 10px 0;
}

.pot-loader {
    position: relative;
    width: 60px;
    height: 45px;
    margin: 0 auto 10px;
}

.pot {
    width: 60px;
    height: 30px;
    background: #d69e6f;
    border-radius: 0 0 15px 15px;
    position: absolute;
    bottom: 0;
    box-shadow: 0 3px 6px rgba(139, 94, 52, 0.2);
}

.pot:before {
    content: '';
    position: absolute;
    top: -4px;
    left: 4px;
    right: 4px;
    height: 6px;
    background: #d69e6f;
    border-radius: 50%;
}

.lid {
    width: 68px;
    height: 12px;
    background: #8b5e34;
    border-radius: 15px 15px 0 0;
    position: absolute;
    top: -8px;
    left: -4px;
    animation: lidMove 1.5s infinite ease-in-out;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.handle {
    position: absolute;
    top: 4px;
    right: -12px;
    width: 12px;
    height: 6px;
    background: #6b4c2e;
    border-radius: 3px;
}

.bubbles span {
    position: absolute;
    bottom: 30px;
    left: 26px;
    width: 6px;
    height: 6px;
    background: #f9e4c9;
    border-radius: 50%;
    animation: bubbleUp 1.5s infinite ease-in-out;
}

.bubbles span:nth-child(2) {
    left: 34px;
    animation-delay: 0.3s;
}
.bubbles span:nth-child(3) {
    left: 18px;
    animation-delay: 0.6s;
}

.steam {
    position: absolute;
    top: -30px;
    left: 30px;
    width: 0;
    height: 0;
    border-left: 6px solid transparent;
    border-right: 6px solid transparent;
    border-bottom: 12px solid rgba(139, 94, 52, 0.3);
    animation: steamPuff 2s infinite ease-in-out;
}

.steam:nth-child(2) {
    left: 22px;
    animation-delay: 0.5s;
}

.steam:nth-child(3) {
    left: 38px;
    animation-delay: 1s;
}

.cooking-dots {
    display: flex;
    justify-content: center;
    gap: 5px;
    margin-top: 8px;
}

.cooking-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    animation: dotPulse 1.5s infinite ease-in-out;
}

.cooking-dot:nth-child(1) {
    background: #C1856D;
    animation-delay: 0s;
}

.cooking-dot:nth-child(2) {
    background: #FFDBB5;
    animation-delay: 0.3s;
}

.cooking-dot:nth-child(3) {
    background: #C1856D;
    animation-delay: 0.6s;
}

.loading-text {
    margin-top: 8px;
    text-align: center;
    color: #8b5e34;
    font-weight: 500;
    font-size: 12px;
    min-height: 16px;
}

@keyframes lidMove {
    0%, 100% { transform: rotate(0deg); }
    50% { transform: rotate(-8deg); }
}

@keyframes bubbleUp {
    0% { opacity: 0; transform: translateY(0) scale(0.5); }
    50% { opacity: 1; transform: translateY(-15px) scale(1); }
    100% { opacity: 0; transform: translateY(-30px) scale(0.5); }
}

@keyframes steamPuff {
    0%, 100% { opacity: 0; transform: translateY(0) scale(0.8); }
    50% { opacity: 0.6; transform: translateY(-8px) scale(1.2); }
}

@keyframes dotPulse {
    0%, 100% { transform: scale(1); opacity: 0.7; }
    50% { transform: scale(1.5); opacity: 1; }
}

/* Modal for image viewing */
.image-modal {
    display: none;
    position: fixed;
    z-index: 2000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.9);
}
.modal-content {
    margin: auto;
    display: block;
    max-width: 90%;
    max-height: 90%;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
}
.close-modal {
    position: absolute;
    top: 15px;
    right: 35px;
    color: #f1f1f1;
    font-size: 40px;
    font-weight: bold;
    cursor: pointer;
}
.close-modal:hover {
    color: #bbb;
}

/* NEW: Verification Error Modal */
#verificationErrorModal {
    display: none;
    position: fixed;
    z-index: 3000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.7);
}

#verificationErrorModal .verification-error-content {
    background: linear-gradient(180deg, #ffebee, #ffcdd2);
    margin: 15% auto;
    padding: 25px;
    border-radius: 16px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.3);
    position: relative;
    border: 3px solid #f44336;
    text-align: center;
}

/* NEW: Intro text styling */
.intro-container {
    text-align: center;
    margin-top: 300px;
    padding: 10px;
}

.intro-logo {
    width: 120px;
    height: 120px;
    margin: 0 auto 15px;
    border-radius: 20px;
    background: linear-gradient(135deg, rgba(255,191,38,0.95), rgba(111,45,134,0.85));
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    overflow: hidden;
}

.intro-logo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.intro-text {
    font-size: 24px;
    font-weight: 700;
    color: var(--wood-1);
    margin-bottom: 10px;
    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.intro-subtext {
    font-size: 16px;
    color: #6b4f33;
    opacity: 0.9;
    margin-bottom: 20px;
}

/* small responsive */
@media (max-width: 900px){
    .container { grid-template-columns: 1fr; padding:12px; }
    .sidebar { order:2; }
    .chat-card { order:1; }
    .generated-image { max-width: 100%; }
    .msg .bubble { max-width: 90%; }
    .plus-dropdown {
        left: auto;
        right: 0;
    }
    .intro-text {
        font-size: 20px;
    }
    .intro-logo {
        width: 100px;
        height: 100px;
    }
    .recipe-modal-content {
        width: 95%;
        margin: 2% auto;
        padding: 15px;
    }
}

/* Scrollbar styling */
::-webkit-scrollbar {
    width: 8px;
}
::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}
::-webkit-scrollbar-thumb {
    background: #e9c46a;
    border-radius: 10px;
}
::-webkit-scrollbar-thumb:hover {
    background: #d69e6f;
}

</style>
</head>

<body>
<?php include 'side-nav.php' ?>

<!-- NEW: Verification Error Modal -->
<div id="verificationErrorModal" class="verification-error-modal">
    <div class="verification-error-content">
        <div class="verification-error-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="verification-error-title">Filipino Cuisine Only</div>
        <div id="verificationErrorMessage" class="verification-error-message">
            Your input is not related to Filipino cuisine.
        </div>
        <div id="verificationSuggestions" class="verification-suggestions" style="display: none;">
            <h4>Try these Filipino dishes instead:</h4>
            <ul id="suggestionsList"></ul>
        </div>
        <button class="verification-error-close" id="verificationErrorClose">Okay, I understand!</button>
    </div>
</div>

<!-- Image Modal -->
<div id="imageModal" class="image-modal">
    <span class="close-modal">&times;</span>
    <img class="modal-content" id="modalImage">
</div>

<!-- NEW: Recipe Generation Modal -->
<div id="recipeModal" class="recipe-modal">
    <div class="recipe-modal-content">
        <button class="close-recipe-modal">&times;</button>
        <h2><i class="fas fa-utensils"></i> Generate Recipe</h2>
        
        <form id="recipeForm" enctype="multipart/form-data">
            <input type="file" id="recipeImage" name="recipe_image" accept="image/*" style="display: none;">
            
            <div class="recipe-form-group">
                <div class="recipe-image-upload" id="" style="">
      
                </div>
                <img id="recipeImagePreview" class="recipe-image-preview" src="" alt="Preview">
            </div>
            
            <div class="recipe-form-group">
                <label>Ingredients:</label>
                <div class="ingredients-container" id="ingredientsContainer">
                    <div class="ingredient-input">
                        <input type="text" name="ingredients[]" placeholder="Enter ingredient (e.g., chicken, garlic, onions)" required>
                        <button type="button" class="remove-ingredient" style="display: none;">×</button>
                    </div>
                </div>
                <button type="button" class="add-ingredient-btn" id="addIngredient">
                    <i class="fas fa-plus"></i> Add Ingredient
                </button>
            </div>
            
            <div class="recipe-form-group">
                <label for="recipeDescription">Description (Optional):</label>
                <textarea 
                    id="recipeDescription" 
                    name="description" 
                    class="recipe-description" 
                    placeholder="Describe what you want (e.g., 'I want savory flavor food', 'spicy and hearty meal', 'light and healthy dish', etc.)"
                ></textarea>
                <small style="color: #666; font-size: 12px;">Tell us about the flavor, style, or type of dish you're looking for</small>
            </div>
            
            <div class="recipe-form-group">
                <div class="option-checkbox">
                    <input type="checkbox" id="generateImageCheckbox" name="generate_image" value="true" checked>
                    <label for="generateImageCheckbox">Generate AI image of suggested dish</label>
                </div>
                <small style="color: #666; font-size: 12px;">AI will generate an image of the recipe suggested based on your ingredients</small>
            </div>
            
            <button type="submit" class="generate-recipe-submit" id="generateRecipeSubmit">
                <i class="fas fa-magic"></i> Generate Recipe
            </button>
        </form>
    </div>
</div>

<!-- MAIN -->
<div class="container">
    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="header">
            <h3><i class="ri-history-line"></i>&nbsp;Chat History</h3>
            <?php 
            $hasCurrentSessionConversations = hasCurrentSessionConversations($_SESSION['user_id'], $_SESSION['chat_session_id']);
            $newChatClass = $hasCurrentSessionConversations ? '' : 'disabled';
            $newChatTitle = $hasCurrentSessionConversations ? 'Start new chat' : 'Current chat is empty - continue chatting';
            ?>
            <a href="<?php echo $hasCurrentSessionConversations ? '?new_chat=1' : '#'; ?>" class="btn-new <?php echo $newChatClass; ?>" id="btnNew" title="<?php echo $newChatTitle; ?>">
                <i class="ri-add-line"></i> New
            </a>
        </div>

        <div class="history-list">
        <?php if (!empty($userSessions)): ?>
            <?php foreach ($userSessions as $session): ?>
                <div class="history-item <?php echo $session['session_id'] === $_SESSION['chat_session_id'] ? 'active' : ''; ?>" 
                     onclick="window.location.href='?session_id=<?php echo $session['session_id']; ?>'">
                    <div class="tag">
                        <img src="final-logo.png" alt="Chat" style="width:100%;height:100%;border-radius:8px;object-fit:cover;">
                    </div>
                    <div style="flex: 1;">
                        <div class="meta"><?php echo !empty($session['first_message']) ? htmlspecialchars(substr($session['first_message'], 0, 10) . (strlen($session['first_message']) > 40 ? '...' : '')) : 'New Chat'; ?></div>
                        <div class="sub"><?php echo $session['message_count'] > 0 ? $session['message_count'] . ' messages' : 'No messages'; ?></div>
                    </div>
                    <?php if ($session['session_id'] !== $_SESSION['chat_session_id']): ?>
                        <form method="post" style="display: inline;" onclick="event.stopPropagation();">
                            <input type="hidden" name="delete_session" value="<?php echo $session['session_id']; ?>">
                            <button type="submit" class="delete-session" title="Delete session">
                                <i class="ri-delete-bin-line"></i>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="history-item active">
                <div class="tag">
                    <img src="final-logo.png" alt="Chat" style="width:100%;height:100%;border-radius:8px;object-fit:cover;">
                </div>
                <div>
                    <div class="meta">Current Chat</div>
                    <div class="sub">Start a conversation</div>
                </div>
            </div>
        <?php endif; ?>
        </div>
    </aside>

    <!-- CHAT -->
    <main class="chat-card">
        <div class="chat-header">
            <div class="title">
            <?php if ($has_profile_image): ?>
                <img src="<?php echo (strpos($profile_image, 'uploads/') === 0) ? $profile_image : 'uploads/' . $profile_image; ?>" 
                     style="width:42px;height:42px;border-radius:10px;object-fit:cover;border:2px solid white;box-shadow:0 2px 8px rgba(0,0,0,0.2)" 
                     alt="Profile" 
                     onerror="handleChatImageError(this)">
            <?php else: ?>
                <div style="width:42px;height:42px;border-radius:10px;background:linear-gradient(135deg,#ffbf26,#6f2d86);display:flex;align-items:center;justify-content:center;color:white;font-weight:800;font-size:14px;border:2px solid white;box-shadow:0 2px8 px rgba(0,0,0,0.2)">
                    <?php echo $initials; ?>
                </div>
            <?php endif; ?>
            <div>
                <p class="ch-title"><?php echo htmlspecialchars($username); ?></p>
                <p class="ch-sub">Ask Chef-AI about Filipino cuisine</p>
            </div>
            </div>
        </div>

        <div class="messages" id="messages">
            <?php if (!empty($userHistory)): ?>
                <?php foreach ($userHistory as $item): ?>
                    <!-- User Message -->
                    <div class="msg user">
                        <div class="bubble">
                            <?php if (!empty($item['image_path']) && (strpos($item['message'], 'Analyze image:') === 0 || strpos($item['message'], 'Analyze uploaded image') === 0)): ?>
                                <div class="uploaded-image-preview">
                                    <img src="uploads/<?php echo htmlspecialchars($item['image_path']); ?>" class="uploaded-image-chat" alt="Uploaded image" onclick="openImageModal(this.src)">
                                </div>
                            <?php endif; ?>
                            <?php echo nl2br(htmlspecialchars($item['message'])); ?>
                        </div>
                    </div>
                    
                    <!-- AI Response -->
                    <?php if (!empty($item['response'])): ?>
                    <div class="msg ai">
                        <div class="avatar">
                            <img src="final-logo.png" alt="ChefAI" style="width:100%;height:100%;border-radius:10px;object-fit:cover;">
                        </div>
                        <div class="bubble has-image">
                            <div class="ai-content"><?php echo formatStoredResponse($item['response']); ?></div>
                            
                            <?php if (!empty($item['image_path'])): ?>
                                <?php if (strpos($item['message'], 'Generate image:') === 0 || strpos($item['message'], 'Generated recipe from ingredients:') === 0): ?>
                                    <div class="image-generation-container">
                                        <?php if (strpos($item['message'], 'Generated recipe from ingredients:') === 0): ?>
                                            <div class="image-prompt"><strong>Recipe Image:</strong> Based on your ingredients and description</div>
                                        <?php else: ?>
                                            <div class="image-prompt"><strong>Generated image for:</strong> <?php echo htmlspecialchars(str_replace('Generate image: ', '', $item['message'])); ?></div>
                                        <?php endif; ?>
                                        <img src="uploads/<?php echo htmlspecialchars($item['image_path']); ?>" class="generated-image" alt="Generated image" onclick="openImageModal(this.src)">
                                        <?php if (!empty($item['description'])): ?>
                                            <?php
                                                $clean_description = preg_replace('/^(Image Description:|Description:)\s*/i', '', $item['description']);
                                                $clean_description = trim($clean_description);
                                            ?>
                                            <div class="image-description">
                                                <h5><i class="fas fa-align-left"></i> Image Description</h5>
                                                <div class="description-content"><?php echo formatStoredResponse($clean_description); ?></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <div class="recipe-actions">
                                <?php if (!empty($item['image_path']) && (strpos($item['message'], 'Generate image:') === 0 || strpos($item['message'], 'Generated recipe from ingredients:') === 0)): ?>
                                    <form method="post" class="save-image-form" style="display:inline;">
                                        <input type="hidden" name="save_image" value="<?php echo $item['id']; ?>">
                                        <button type="submit" class="action-btn save-btn <?php echo $item['is_saved'] ? 'saved' : ''; ?>" title="<?php echo $item['is_saved'] ? 'Unsave' : 'Save'; ?>">
                                            <i class="fas fa-save"></i> <?php echo $item['is_saved'] ? 'Saved' : 'Save'; ?>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" class="bookmark-form" style="display:inline;">
                                        <input type="hidden" name="bookmark_history" value="<?php echo $item['id']; ?>">
                                        <button type="submit" class="action-btn bookmark-btn <?php echo $item['is_bookmarked'] ? 'bookmarked' : ''; ?>" title="<?php echo $item['is_bookmarked'] ? 'Unbookmark' : 'Bookmark'; ?>">
                                            <i class="fas fa-star"></i> <?php echo $item['is_bookmarked'] ? 'Bookmarked' : 'Bookmark'; ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="delete_history" value="<?php echo $item['id']; ?>">
                                    <button type="submit" class="action-btn delete-btn" title="Delete">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- NEW: Modified intro section -->
                <div class="intro-container">
                    <div class="intro-logo">
                        <img src="final-logo-txt.png" alt="ChefAI Logo">
                    </div>
                    <div class="intro-text">Hello <?php echo htmlspecialchars($username); ?>!</div>
                    <div class="intro-subtext">What would you like to cook today?</div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- UPLOADED IMAGE PREVIEW -->
        <div id="uploaded-image-preview" class="uploaded-image-preview" style="display: none;">
            <img id="uploaded-image" class="uploaded-image" src="" alt="Uploaded image">
            <button type="button" id="remove-image-btn" class="remove-image-btn">
                <i class="fas fa-times"></i> Remove Image
            </button>
        </div>
        
        <!-- Image generation options -->
        <div id="image-options" class="image-options" style="display: none;">
            <div class="option-checkbox">
                <input type="checkbox" id="generate-description" name="generate_description" value="true">
                <label for="generate-description">Generate description for the image</label>
            </div>
        </div>

        <div class="composer">
            <div class="input">
                <textarea id="inputBox" placeholder="Ask ChefAI (ex: 'quick halo-halo recipe')" rows="1"></textarea>
                
                <input type="file" id="image-upload" accept="image/*" style="display: none;">
                
                <div class="plus-button-container">
                    <button type="button" id="plus-button" class="plus-button" title="More options">
                        <i class="fas fa-plus"></i>
                    </button>
                    <div class="plus-dropdown" id="plus-dropdown">
                        <div class="plus-dropdown-item" id="upload-image-option">
                            <i class="fas fa-paperclip"></i>
                            <span>Upload Image</span>
                        </div>
                        <div class="plus-dropdown-item" id="generate-image-option">
                            <i class="fas fa-image"></i>
                            <span>Generate Image</span>
                        </div>
                        <div class="plus-dropdown-item" id="generate-recipe-option">
                            <i class="fas fa-utensils"></i>
                            <span>Generate Recipe</span>
                        </div>
                    </div>
                </div>
            
                <button class="send-btn" id="sendBtn" title="Send"><i class="ri-send-plane-2-line" style="font-size:18px"></i></button>
            </div>
        </div>
    </main>
</div>

<script>
// Global function for image modal
function openImageModal(src) {
    const modalImage = document.getElementById('modalImage');
    const imageModal = document.getElementById('imageModal');
    if (modalImage && imageModal) {
        modalImage.src = src;
        imageModal.style.display = 'block';
    }
}

// Global function for chat image error handling
function handleChatImageError(img) {
    console.log('Chat image error occurred');
    const parent = img.parentElement;
    const initials = '<?php echo $initials; ?>';
    
    const initialsDiv = document.createElement('div');
    initialsDiv.style.cssText = 'width:42px;height:42px;border-radius:10px;background:linear-gradient(135deg,#ffbf26,#6f2d86);display:flex;align-items:center;justify-content:center;color:white;font-weight:800;font-size:14px;border:2px solid white;box-shadow:0 2px 8px rgba(0,0,0,0.2)';
    initialsDiv.textContent = initials;
    
    parent.replaceChild(initialsDiv, img);
    
    fetch('update_profile_image.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'user_id=<?php echo $user_id; ?>&profile_image='
    }).catch(error => {
        console.error('Error updating profile image:', error);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    console.log('ChefAI JavaScript loaded');
    
    // Get DOM elements
    const sendBtn = document.getElementById('sendBtn');
    const inputBox = document.getElementById('inputBox');
    const messages = document.getElementById('messages');
    const introContainer = document.querySelector('.intro-container');
    const btnNew = document.getElementById('btnNew');
    
    // Image functionality variables
    let currentMode = 'text';
    let hasUploadedImage = false;
    let currentUploadedImage = null;
    const imageUpload = document.getElementById('image-upload');
    const uploadedImagePreview = document.getElementById('uploaded-image-preview');
    const uploadedImage = document.getElementById('uploaded-image');
    const removeImageBtn = document.getElementById('remove-image-btn');
    const imageOptions = document.getElementById('image-options');
    const generateDescriptionCheckbox = document.getElementById('generate-description');
    const plusButton = document.getElementById('plus-button');
    const plusDropdown = document.getElementById('plus-dropdown');
    const uploadImageOption = document.getElementById('upload-image-option');
    const generateImageOption = document.getElementById('generate-image-option');
    const generateRecipeOption = document.getElementById('generate-recipe-option');

    // Recipe Generation variables
    const generateRecipeBtn = document.getElementById('generateRecipeBtn');
    const recipeModal = document.getElementById('recipeModal');
    const closeRecipeModal = document.querySelector('.close-recipe-modal');
    const recipeForm = document.getElementById('recipeForm');
    const recipeImageUpload = document.getElementById('recipeImageUpload');
    const recipeImage = document.getElementById('recipeImage');
    const recipeImagePreview = document.getElementById('recipeImagePreview');
    const ingredientsContainer = document.getElementById('ingredientsContainer');
    const addIngredientBtn = document.getElementById('addIngredient');
    const generateRecipeSubmit = document.getElementById('generateRecipeSubmit');
    const recipeDescription = document.getElementById('recipeDescription');
    const generateImageCheckbox = document.getElementById('generateImageCheckbox');

    // Verification error modal elements
    const verificationErrorModal = document.getElementById('verificationErrorModal');
    const verificationErrorMessage = document.getElementById('verificationErrorMessage');
    const verificationSuggestions = document.getElementById('verificationSuggestions');
    const suggestionsList = document.getElementById('suggestionsList');
    const verificationErrorClose = document.getElementById('verificationErrorClose');

    // Image modal elements
    const imageModal = document.getElementById('imageModal');
    const modalImage = document.getElementById('modalImage');
    const closeModal = document.querySelector('.close-modal');

    // NEW: Show verification error modal
    function showVerificationError(message, suggestions = []) {
        if (verificationErrorMessage) {
            verificationErrorMessage.textContent = message;
        }
        
        if (suggestions && suggestions.length > 0) {
            if (verificationSuggestions) verificationSuggestions.style.display = 'block';
            if (suggestionsList) {
                suggestionsList.innerHTML = '';
                suggestions.forEach(suggestion => {
                    const li = document.createElement('li');
                    li.textContent = suggestion;
                    suggestionsList.appendChild(li);
                });
            }
        } else {
            if (verificationSuggestions) verificationSuggestions.style.display = 'none';
        }
        
        if (verificationErrorModal) {
            verificationErrorModal.style.display = 'block';
        }
    }

    if (verificationErrorClose) {
        verificationErrorClose.addEventListener('click', function() {
            if (verificationErrorModal) {
                verificationErrorModal.style.display = 'none';
            }
        });
    }

    window.addEventListener('click', function(event) {
        if (event.target === verificationErrorModal && verificationErrorModal) {
            verificationErrorModal.style.display = 'none';
        }
    });

    // Image modal functionality
    if (closeModal) {
        closeModal.addEventListener('click', function() {
            if (imageModal) {
                imageModal.style.display = 'none';
            }
        });
    }

    window.addEventListener('click', function(event) {
        if (event.target === imageModal && imageModal) {
            imageModal.style.display = 'none';
        }
    });

    // Recipe Generation Modal Functions
    if (generateRecipeBtn) {
        generateRecipeBtn.addEventListener('click', function() {
            if (recipeModal) {
                recipeModal.style.display = 'block';
            }
        });
    }

    if (closeRecipeModal) {
        closeRecipeModal.addEventListener('click', function() {
            if (recipeModal) {
                recipeModal.style.display = 'none';
            }
            resetRecipeForm();
        });
    }

    window.addEventListener('click', function(event) {
        if (event.target === recipeModal && recipeModal) {
            recipeModal.style.display = 'none';
            resetRecipeForm();
        }
    });

    // Generate Recipe Option in Plus Dropdown
    if (generateRecipeOption) {
        generateRecipeOption.addEventListener('click', function() {
            if (recipeModal) {
                recipeModal.style.display = 'block';
            }
            if (plusDropdown) plusDropdown.classList.remove('active');
        });
    }

    // Recipe image upload
    if (recipeImageUpload) {
        recipeImageUpload.addEventListener('click', function() {
            if (recipeImage) {
                recipeImage.click();
            }
        });
    }

    if (recipeImage) {
        recipeImage.addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    if (recipeImagePreview) {
                        recipeImagePreview.src = e.target.result;
                        recipeImagePreview.style.display = 'block';
                    }
                };
                
                reader.readAsDataURL(file);
                
                const ingredientInputs = recipeForm ? recipeForm.querySelectorAll('input[name="ingredients[]"]') : [];
                ingredientInputs.forEach(input => {
                    input.required = false;
                    input.placeholder = "Enter ingredient (optional - image uploaded)";
                });
            } else {
                const ingredientInputs = recipeForm ? recipeForm.querySelectorAll('input[name="ingredients[]"]') : [];
                ingredientInputs.forEach(input => {
                    input.required = true;
                    input.placeholder = "Enter ingredient (e.g., chicken, garlic, onions)";
                });
            }
        });
    }

    // Add ingredient field
    if (addIngredientBtn) {
        addIngredientBtn.addEventListener('click', function() {
            addIngredientField();
        });
    }

    function addIngredientField(ingredient = '') {
        if (!ingredientsContainer) return;
        
        const hasImage = recipeImage && recipeImage.files && recipeImage.files[0];
        const isRequired = !hasImage;
        const placeholder = hasImage ? 
            "Enter ingredient (optional - image uploaded)" : 
            "Enter ingredient (e.g., chicken, garlic, onions)";
        
        const ingredientInput = document.createElement('div');
        ingredientInput.className = 'ingredient-input';
        ingredientInput.innerHTML = `
            <input type="text" name="ingredients[]" placeholder="${placeholder}" ${isRequired ? 'required' : ''} value="${ingredient}">
            <button type="button" class="remove-ingredient">×</button>
        `;
        
        ingredientsContainer.appendChild(ingredientInput);
        
        const removeBtn = ingredientInput.querySelector('.remove-ingredient');
        if (removeBtn) {
            removeBtn.addEventListener('click', function() {
                ingredientInput.remove();
                updateRemoveButtons();
            });
        }
        
        updateRemoveButtons();
    }

    function updateRemoveButtons() {
        if (!ingredientsContainer) return;
        
        const ingredientInputs = ingredientsContainer.querySelectorAll('.ingredient-input');
        const removeButtons = ingredientsContainer.querySelectorAll('.remove-ingredient');
        
        if (ingredientInputs.length > 1) {
            removeButtons.forEach(btn => {
                if (btn) btn.style.display = 'flex';
            });
        } else {
            removeButtons.forEach(btn => {
                if (btn) btn.style.display = 'none';
            });
        }
    }

    function resetRecipeForm() {
        if (recipeForm) recipeForm.reset();
        if (recipeImagePreview) {
            recipeImagePreview.style.display = 'none';
        }
        if (recipeDescription) {
            recipeDescription.value = '';
        }
        if (generateImageCheckbox) {
            generateImageCheckbox.checked = true;
        }
        
        if (ingredientsContainer) {
            ingredientsContainer.innerHTML = '';
            addIngredientField();
            const ingredientInputs = ingredientsContainer.querySelectorAll('input[name="ingredients[]"]');
            ingredientInputs.forEach(input => {
                input.required = true;
                input.placeholder = "Enter ingredient (e.g., chicken, garlic, onions)";
            });
        }
    }

    // Handle recipe form submission
    if (recipeForm) {
        recipeForm.addEventListener('submit', function(e) {
            e.preventDefault();
            generateRecipe();
        });
    }

    async function generateRecipe() {
        const formData = new FormData(recipeForm);
        formData.append('generate_recipe', 'true');
        
        const generateImage = generateImageCheckbox ? generateImageCheckbox.checked : false;
        formData.append('generate_image', generateImage);
        
        const ingredients = formData.getAll('ingredients[]').filter(ing => ing.trim() !== '');
        const recipeImageFile = recipeImage ? recipeImage.files[0] : null;
        
        if (ingredients.length === 0 && !recipeImageFile) {
            alert('Please add at least one ingredient OR upload an image.');
            return;
        }
        
        const ingredientInputs = recipeForm ? recipeForm.querySelectorAll('input[name="ingredients[]"]') : [];
        if (recipeImageFile) {
            ingredientInputs.forEach(input => {
                input.required = false;
            });
        } else {
            const hasValidIngredient = ingredients.length > 0;
            if (!hasValidIngredient) {
                alert('Please add at least one ingredient.');
                return;
            }
        }
        
        if (generateRecipeSubmit) {
            generateRecipeSubmit.disabled = true;
            generateRecipeSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating Recipe...';
        }
        
        if (introContainer) {
            introContainer.style.display = 'none';
        }
        
        try {
            const response = await fetch('', {
                method: 'POST',
                body: formData
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.error) {
                // NEW: Handle verification errors
                if (data.verification_error) {
                    showVerificationError(data.verification_message, data.suggestions || []);
                } else if (data.error.includes('Hindi malinaw ang ingredients') || data.error.includes('Walang ingredients na nakita')) {
                    alert('Image Analysis: ' + data.error + (data.image_analysis ? '\n\nAnalysis: ' + data.image_analysis : ''));
                } else {
                    alert('Error: ' + data.error);
                }
                if (generateRecipeSubmit) {
                    generateRecipeSubmit.disabled = false;
                    generateRecipeSubmit.innerHTML = '<i class="fas fa-magic"></i> Generate Recipe';
                }
                return;
            } else if (data.success) {
                if (recipeModal) {
                    recipeModal.style.display = 'none';
                }
                resetRecipeForm();
                
                let userMessage = `Generated recipe`;
                if (data.ingredients) {
                    userMessage += ` from ingredients: ${data.ingredients}`;
                }
                if (data.description) {
                    userMessage += ` (Description: ${data.description})`;
                }
                if (recipeImageFile) {
                    userMessage += ` (with uploaded image)`;
                }
                createMessage('user', userMessage);
                
                await typeEffect(data.recipe, data.history_id);
                
                if (data.image_generated && data.image_path) {
                    const imageMessage = `I generated an image of the suggested dish "${data.recipe_name}" based on your ingredients and preferences.`;
                    createMessage('ai', imageMessage, true, data.image_path, `AI-generated image of ${data.recipe_name}`, null, data.history_id);
                }
            } else {
                alert('Unknown error occurred during recipe generation.');
            }
        } catch (error) {
            console.error('Error generating recipe:', error);
            alert('Sorry, there was an error generating the recipe. Please try again. Error: ' + error.message);
        } finally {
            if (generateRecipeSubmit) {
                generateRecipeSubmit.disabled = false;
                generateRecipeSubmit.innerHTML = '<i class="fas fa-magic"></i> Generate Recipe';
            }
            ingredientInputs.forEach(input => {
                input.required = true;
            });
        }
    }

    // Initialize recipe form with one ingredient field
    addIngredientField();

    function clearUploadedImage() {
        if (uploadedImagePreview) uploadedImagePreview.style.display = 'none';
        if (uploadedImage) uploadedImage.src = '';
        if (imageUpload) imageUpload.value = '';
        hasUploadedImage = false;
        currentUploadedImage = null;
        if (inputBox) inputBox.placeholder = "Ask ChefAI (ex: 'quick halo-halo recipe')";
    }

    if (imageUpload) {
        imageUpload.addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Please select a valid image file (JPEG, PNG, GIF, WebP).');
                    this.value = '';
                    return;
                }
                
                if (file.size > 5 * 1024 * 1024) {
                    alert('Image file size should be less than 5MB.');
                    this.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (uploadedImage) uploadedImage.src = e.target.result;
                    if (uploadedImagePreview) uploadedImagePreview.style.display = 'block';
                    hasUploadedImage = true;
                    currentUploadedImage = file;
                    if (inputBox) inputBox.placeholder = "Ask about the image or leave blank for general analysis...";
                };
                reader.readAsDataURL(file);
            }
        });
    }

    if (removeImageBtn) {
        removeImageBtn.addEventListener('click', function() {
            clearUploadedImage();
        });
    }

    if (plusButton) {
        plusButton.addEventListener('click', function(e) {
            e.stopPropagation();
            if (plusDropdown) {
                plusDropdown.classList.toggle('active');
            }
        });
    }

    document.addEventListener('click', function() {
        if (plusDropdown) {
            plusDropdown.classList.remove('active');
        }
    });

    if (uploadImageOption) {
        uploadImageOption.addEventListener('click', function() {
            if (imageUpload) imageUpload.click();
            if (plusDropdown) plusDropdown.classList.remove('active');
        });
    }

    if (generateImageOption) {
        generateImageOption.addEventListener('click', function() {
            currentMode = 'generate_image';
            if (inputBox) inputBox.placeholder = "Describe the image you want to generate...";
            if (imageOptions) imageOptions.style.display = 'block';
            if (inputBox) inputBox.focus();
            if (plusDropdown) plusDropdown.classList.remove('active');
        });
    }

    function updateNewChatButtonState() {
        const hasConversations = messages ? messages.querySelectorAll('.msg').length > 0 : false;
        
        if (btnNew) {
            if (hasConversations) {
                btnNew.classList.remove('disabled');
                btnNew.href = '?new_chat=1';
                btnNew.title = 'Start new chat';
            } else {
                btnNew.classList.add('disabled');
                btnNew.href = '#';
                btnNew.title = 'Current chat is empty - continue chatting';
            }
        }
    }

    function createMessage(role, text, isImage = false, imageUrl = null, imagePrompt = null, description = null, historyId = null) {
        const wrap = document.createElement('div');
        wrap.className = 'msg ' + role;
        
        if (role === 'ai') {
            let descriptionHtml = '';
            if (description) {
                descriptionHtml = `<div class="image-description">
                    <h5><i class="fas fa-align-left"></i> Image Description</h5>
                    <div class="description-content">${formatResponse(description)}</div>
                </div>`;
            }
            
            let imageHtml = '';
            let bubbleClass = '';
            if (isImage && imageUrl) {
                bubbleClass = 'has-image';
                let promptText = imagePrompt;
                if (text.includes('Generated recipe from ingredients:')) {
                    promptText = 'Based on your ingredients and description';
                }
                imageHtml = `<div class="image-generation-container">
                    <div class="image-prompt"><strong>Recipe Image:</strong> ${escapeHtml(promptText)}</div>
                    <img src="uploads/${imageUrl}" class="generated-image" alt="Generated image" onclick="openImageModal(this.src)">
                    ${descriptionHtml}
                </div>`;
            }
            
            const historyIdValue = historyId ? historyId : '0';
            
            wrap.innerHTML = `<div class="avatar">
                    <img src="final-logo.png" alt="ChefAI" style="width:100%;height:100%;border-radius:10px;object-fit:cover;">
                </div>
                <div class="bubble ${bubbleClass}">
                    <div class="ai-content">${formatResponse(text)}</div>
                    ${imageHtml}
                    <div class="recipe-actions">
                    ${isImage ? 
                        `<form method="post" class="save-image-form" style="display:inline;">
                        <input type="hidden" name="save_image" value="${historyIdValue}">
                        <button type="submit" class="action-btn save-btn" title="Save">
                            <i class="fas fa-save"></i> Save
                        </button>
                        </form>` : 
                        `<form method="post" class="bookmark-form" style="display:inline;">
                        <input type="hidden" name="bookmark_history" value="${historyIdValue}">
                        <button type="submit" class="action-btn bookmark-btn" title="Bookmark">
                            <i class="fas fa-star"></i> Bookmark
                        </button>
                        </form>`
                    }
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="delete_history" value="${historyIdValue}">
                        <button type="submit" class="action-btn delete-btn" title="Delete">
                          <i class="fas fa-trash"></i> Delete
                        </button>
                    </form>
                    </div>
                </div>`;
        } else {
            let imageHtml = '';
            if (isImage && imageUrl) {
                imageHtml = `<div class="uploaded-image-preview">
                    <img src="${imageUrl}" class="uploaded-image-chat" alt="Uploaded image" onclick="openImageModal(this.src)">
                </div>`;
            }
            
            wrap.innerHTML = `<div class="bubble">
                    ${imageHtml}
                    <div>${escapeHtml(text)}</div>
                </div>`;
        }
        
        if (messages) {
            messages.appendChild(wrap);
            scrollToBottom();
        }
        
        updateNewChatButtonState();
        
        return wrap;
    }

    function escapeHtml(unsafe) {
        if (!unsafe) return '';
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;")
            .replace(/\n/g, '<br>');
    }

    function formatResponse(text) {
        if (!text) return '';
        
        let formatted = text;
        formatted = formatted.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        formatted = formatted.replace(/\*(.*?)\*/g, '<em>$1</em>');
        formatted = formatted.replace(/^\s*[-*]\s+(.*)$/gim, '• $1');
        formatted = formatted.replace(/^\s*(\d+)\.\s+(.*)$/gim, '$1. $2');
        formatted = formatted.replace(/\n/g, '<br>');
        formatted = formatted.replace(/(<br\s*\/?>\s*){2,}/g, '<br><br>');
        
        return formatted;
    }

    function showLoading() {
        const cookingLoader = document.createElement('div');
        cookingLoader.className = 'msg ai';
        cookingLoader.id = 'typing-indicator';
        cookingLoader.innerHTML = `
            <div class="avatar">
                <img src="final-logo.png" alt="ChefAI" style="width:100%;height:100%;border-radius:10px;object-fit:cover;">
            </div>
            <div class="bubble">
                <div class="cooking-loader">
                    <div class="pot-loader">
                        <div class="lid">
                            <div class="handle"></div>
                        </div>
                        <div class="pot"></div>
                        <div class="bubbles">
                            <span></span><span></span><span></span>
                        </div>
                        <div class="steam"></div>
                        <div class="steam"></div>
                        <div class="steam"></div>
                    </div>
                    
                    <div class="cooking-dots">
                        <div class="cooking-dot"></div>
                        <div class="cooking-dot"></div>
                        <div class="cooking-dot"></div>
                    </div>
                    
                    <div class="loading-text">Cooking something delicious...</div>
                </div>
            </div>
        `;
        if (messages) {
            messages.appendChild(cookingLoader);
            scrollToBottom();
        }
    }

    function showImageAnalysisLoading() {
        const cookingLoader = document.createElement('div');
        cookingLoader.className = 'msg ai';
        cookingLoader.id = 'image-analysis-loading';
        cookingLoader.innerHTML = `
            <div class="avatar">
                <img src="final-logo.png" alt="ChefAI" style="width:100%;height:100%;border-radius:10px;object-fit:cover;">
            </div>
            <div class="bubble">
                <div class="cooking-loader">
                    <div class="pot-loader">
                        <div class="lid">
                            <div class="handle"></div>
                        </div>
                        <div class="pot"></div>
                        <div class="bubbles">
                            <span></span><span></span><span></span>
                        </div>
                        <div class="steam"></div>
                        <div class="steam"></div>
                        <div class="steam"></div>
                    </div>
                    
                    <div class="cooking-dots">
                        <div class="cooking-dot"></div>
                        <div class="cooking-dot"></div>
                        <div class="cooking-dot"></div>
                    </div>
                    
                    <div class="loading-text">Analyzing your image...</div>
                </div>
            </div>
        `;
        if (messages) {
            messages.appendChild(cookingLoader);
            scrollToBottom();
        }
    }

    function showImageGenerationLoading() {
        const cookingLoader = document.createElement('div');
        cookingLoader.className = 'msg ai';
        cookingLoader.id = 'image-generation-loading';
        cookingLoader.innerHTML = `
            <div class="avatar">
                <img src="final-logo.png" alt="ChefAI" style="width:100%;height:100%;border-radius:10px;object-fit:cover;">
            </div>
            <div class="bubble">
                <div class="cooking-loader">
                    <div class="pot-loader">
                        <div class="lid">
                            <div class="handle"></div>
                        </div>
                        <div class="pot"></div>
                        <div class="bubbles">
                            <span></span><span></span><span></span>
                        </div>
                        <div class="steam"></div>
                        <div class="steam"></div>
                        <div class="steam"></div>
                    </div>
                    
                    <div class="cooking-dots">
                        <div class="cooking-dot"></div>
                        <div class="cooking-dot"></div>
                        <div class="cooking-dot"></div>
                    </div>
                    
                    <div class="loading-text">Generating image...</div>
                </div>
            </div>
        `;
        if (messages) {
            messages.appendChild(cookingLoader);
            scrollToBottom();
        }
    }

    async function handleSend() {
        const message = inputBox ? inputBox.value.trim() : '';
        const hasImage = hasUploadedImage;
        
        if (introContainer) {
            introContainer.style.display = 'none';
        }
        
        if (hasImage && !message && currentMode === 'text') {
            sendImageAnalysis();
            return;
        }
        
        if (hasImage && message && currentMode === 'text') {
            sendImageAnalysis();
            return;
        }
        
        if (message === '' && !hasImage && currentMode !== 'generate_image') {
            alert('Please enter a message.');
            return;
        }
        
        if (currentMode === 'generate_image') {
            const generateDescription = generateDescriptionCheckbox ? generateDescriptionCheckbox.checked : false;
            sendImageGeneration(message, generateDescription);
        } else if (hasImage) {
            sendImageAnalysis();
        } else {
            sendTextMessage(message);
        }
    }

    async function sendTextMessage(message) {
        createMessage('user', message);
        
        if (inputBox) {
            inputBox.value = '';
            inputBox.style.height = 'auto';
            inputBox.placeholder = "Ask ChefAI (ex: 'quick halo-halo recipe')";
        }
        
        currentMode = 'text';
        if (imageOptions) imageOptions.style.display = 'none';
        
        scrollToBottom();
        
        setInputsDisabled(true);
        showLoading();
        
        try {
            const res = await fetch('', {
                method: 'POST',
             headers: { 'Content-Type': 'application/json; charset=utf-8' },
                body: JSON.stringify({ prompt: message })
            });
            
            if (!res.ok) throw new Error('Network response was not ok');
            
            const data = await res.json();
            
            const typingIndicator = document.getElementById('typing-indicator');
            if (typingIndicator) {
                typingIndicator.remove();
            }
            
            // NO verification error handling for general chat
            if (data && data.reply) {
                await typeEffect(data.reply, data.history_id);
            } else {
                createMessage('ai', 'Sorry — no response received from ChefAI.');
            }
        } catch (err) {
            console.error('Error in sendTextMessage:', err);
            const typingIndicator = document.getElementById('typing-indicator');
            if (typingIndicator) {
                typingIndicator.remove();
            }
            createMessage('ai', '⚠️ Error connecting to ChefAI. Please try again later.');
        } finally {
            setInputsDisabled(false);
        }
    }
    
    async function sendImageAnalysis() {
        const userQuestion = inputBox ? inputBox.value.trim() : '';

        if (!currentUploadedImage) {
            alert('Please select a valid image file to analyze.');
            return;
        }

        const imageFile = currentUploadedImage;
        const imageDataUrl = uploadedImage ? uploadedImage.src : '';

        clearUploadedImage();

        createMessage('user', userQuestion || '', true, imageDataUrl);

        if (inputBox) {
            inputBox.value = '';
            inputBox.style.height = 'auto';
            inputBox.placeholder = "Ask ChefAI (ex: 'quick halo-halo recipe')";
        }
        
        currentMode = 'text';
        if (imageOptions) imageOptions.style.display = 'none';
        scrollToBottom();

        setInputsDisabled(true);
        showImageAnalysisLoading();

        const formData = new FormData();
        formData.append('analyze_image', 'true');
        formData.append('image_file', imageFile);
        if (userQuestion !== '') {
            formData.append('user_question', userQuestion);
        }

        try {
            const response = await fetch('', {
                method: 'POST',
                body: formData
            });
            
            if (!response.ok) throw new Error('Network response was not ok');
            
            const data = await response.json();
            
            const loadingIndicator = document.getElementById('image-analysis-loading');
            if (loadingIndicator) {
                loadingIndicator.remove();
            }

            if (data.error) {
                createMessage('ai', data.error);
            } else if (data.success) {
                await typeEffect(data.analysis, data.history_id);
            }
        } catch (error) {
            console.error('Error in sendImageAnalysis:', error);
            const loadingIndicator = document.getElementById('image-analysis-loading');
            if (loadingIndicator) {
                loadingIndicator.remove();
            }
            createMessage('ai', 'Sorry, there was an error analyzing the image. Please try again.');
        } finally {
            setInputsDisabled(false);
        }
    }

    async function sendImageGeneration(prompt, generateDescription = false) {
        if (!prompt) {
            alert('Please enter a description for the image you want to generate.');
            return;
        }

        createMessage('user', 'Generate image: ' + prompt);

        if (inputBox) {
            inputBox.value = '';
            inputBox.style.height = 'auto';
            inputBox.placeholder = "Ask ChefAI (ex: 'quick halo-halo recipe')";
        }
        
        currentMode = 'text';
        if (imageOptions) imageOptions.style.display = 'none';
        if (generateDescriptionCheckbox) generateDescriptionCheckbox.checked = false;
        
        scrollToBottom();
        
        setInputsDisabled(true);
        showImageGenerationLoading();
        
        const imageFormData = new FormData();
        imageFormData.append('generate_image', 'true');
        imageFormData.append('prompt', prompt);
        if (generateDescription) {
            imageFormData.append('generate_description', 'true');
        }
        
        try {
            const response = await fetch('', {
                method: 'POST',
                body: imageFormData
            });
            
            if (!response.ok) throw new Error('Network response was not ok');
            
            const data = await response.json();
            
            const loadingIndicator = document.getElementById('image-generation-loading');
            if (loadingIndicator) {
                loadingIndicator.remove();
            }
            
            // Handle verification errors in image generation
            if (data.error) {
                if (data.verification_error) {
                    showVerificationError(data.verification_message, data.suggestions || []);
                } else {
                    createMessage('ai', data.error);
                }
            } else if (data.success) {
                createMessage('ai', data.response, true, data.image_url, data.prompt, data.description, data.history_id);
            }
        } catch (error) {
            console.error('Error in sendImageGeneration:', error);
            const loadingIndicator = document.getElementById('image-generation-loading');
            if (loadingIndicator) {
                loadingIndicator.remove();
            }
            createMessage('ai', 'Sorry, there was an error generating the image. Please try again.');
        } finally {
            setInputsDisabled(false);
        }
    }

    function setInputsDisabled(disabled) {
        if (inputBox) inputBox.disabled = disabled;
        if (plusButton) plusButton.disabled = disabled;
        if (sendBtn) sendBtn.disabled = disabled;
        if (generateRecipeSubmit) generateRecipeSubmit.disabled = disabled;
    }

    function typeEffect(text, historyId = null) {
        return new Promise(resolve => {
            const bubbleWrap = document.createElement('div');
            bubbleWrap.className = 'msg ai';
            
            const historyIdValue = historyId ? historyId : '0';
            
            bubbleWrap.innerHTML = `<div class="avatar">
                    <img src="final-logo.png" alt="ChefAI" style="width:100%;height:100%;border-radius:10px;object-fit:cover;">
                </div>
                <div class="bubble">
                    <div class="ai-content"></div>
                    <div class="recipe-actions">
                        <form method="post" class="bookmark-form" style="display:inline;">
                            <input type="hidden" name="bookmark_history" value="${historyIdValue}">
                            <button type="submit" class="action-btn bookmark-btn" title="Bookmark">
                                <i class="fas fa-star"></i> Bookmark
                            </button>
                        </form>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="delete_history" value="${historyIdValue}">
                            <button type="submit" class="action-btn delete-btn" title="Delete">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </form>
                    </div>
                </div>`;
            
            if (messages) {
                messages.appendChild(bubbleWrap);
                const bubble = bubbleWrap.querySelector('.ai-content');
                
                let position = 0;
                
                function step() {
                    if (position >= text.length) {
                        resolve();
                        return;
                    }
                    
                    position++;
                    const partialText = text.slice(0, position);
                    if (bubble) bubble.innerHTML = formatResponse(partialText);
                    scrollToBottom();
                    
                    if (position < text.length) {
                        const delay = text.length > 400 ? 6 : 18;
                        setTimeout(step, delay);
                    } else {
                        resolve();
                    }
                }
                
                step();
            } else {
                resolve();
            }
        });
    }

    // Enhanced bookmark and save functionality
    document.addEventListener('click', function(e) {
        if (e.target.closest('.bookmark-form')) {
            e.preventDefault();
            const form = e.target.closest('.bookmark-form');
            const formData = new FormData(form);
            const button = form.querySelector('.bookmark-btn');
            const historyId = form.querySelector('input[name="bookmark_history"]').value;
            
            if (historyId === '0') {
                alert('Please wait a moment for the message to save before bookmarking.');
                return;
            }
            
            if (button) button.disabled = true;
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.bookmarked) {
                        button.classList.add('bookmarked');
                        button.innerHTML = '<i class="fas fa-star"></i> Bookmarked';
                        button.title = 'Unbookmark';
                    } else {
                        button.classList.remove('bookmarked');
                        button.innerHTML = '<i class="fas fa-star"></i> Bookmark';
                        button.title = 'Bookmark';
                    }
                } else {
                    alert('Error: ' + (data.error || 'Could not bookmark'));
                }
            })
            .catch(error => {
                console.error('Bookmark error:', error);
                alert('Error bookmarking message');
            })
            .finally(() => {
                if (button) button.disabled = false;
            });
        }
    });

    document.addEventListener('click', function(e) {
        if (e.target.closest('.save-image-form')) {
            e.preventDefault();
            const form = e.target.closest('.save-image-form');
            const formData = new FormData(form);
            const button = form.querySelector('.save-btn');
            
            if (button) button.disabled = true;
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.saved) {
                        button.classList.add('saved');
                        button.innerHTML = '<i class="fas fa-save"></i> Saved';
                        button.title = 'Unsave';
                    } else {
                        button.classList.remove('saved');
                        button.innerHTML = '<i class="fas fa-save"></i> Save';
                        button.title = 'Save';
                    }
                } else {
                    alert('Error: ' + (data.error || 'Could not save image'));
                }
            })
            .catch(error => {
                console.error('Save image error:', error);
                alert('Error saving image');
            })
            .finally(() => {
                if (button) button.disabled = false;
            });
        }
    });

    // Initialize new chat button state
    updateNewChatButtonState();

    // Add input event listener to update button state
    if (inputBox) {
        inputBox.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });

        inputBox.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                handleSend();
            }
        });
    }

    // Main send button event listener
    if (sendBtn) {
        sendBtn.addEventListener('click', function(e) {
            e.preventDefault();
            handleSend();
        });
    }

    function scrollToBottom() {
        if (messages) {
            messages.scrollTop = messages.scrollHeight;
        }
    }

    // Final test
    console.log('JavaScript initialization complete');
    setTimeout(scrollToBottom, 150);
});
</script>
</body>
</html>