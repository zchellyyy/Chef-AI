<?php
session_start();

// Prevent browser caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: LoginMobile.php");
    exit();
}

// Include database connection
require_once 'db_connect.php';

// Get user info
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? '';

// AI Comment Filter Function - ADDED
function filterCommentWithAI($comment) {
    $API_KEY = 'AIzaSyBLgKBfTIRzVFn-aj0riJjIMHubHKepYRs';
    
    $prompt = "Analyze this recipe comment for inappropriate content. Return JSON only with this format:
    {
        \"is_appropriate\": true/false,
        \"inappropriate_words\": [\"list\", \"of\", \"words\"],
        \"reason\": \"brief explanation\",
        \"suggested_replacement\": \"suggested cleaned version or null\"
    }
    
    Comment: \"{$comment}\"
    
    Rules:
    - Flag: profanity, hate speech, harassment, explicit content, personal attacks
    - Allow: constructive criticism, recipe feedback, polite suggestions
    - Be strict but fair
    - Return only valid JSON, no other text";

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . $API_KEY;
    
    $requestBody = [
        'contents' => [[
            'parts' => [[
                'text' => $prompt
            ]]
        ]]
    ];

    try {
        $response = file_get_contents($url, false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => json_encode($requestBody)
            ]
        ]));

        if ($response === FALSE) {
            return ['is_appropriate' => true, 'inappropriate_words' => [], 'reason' => 'API error', 'suggested_replacement' => null];
        }

        $data = json_decode($response, true);
        $aiResponse = $data['candidates'][0]['content']['parts'][0]['text'];
        
        // Extract JSON from response
        preg_match('/\{[^}]+\}/', $aiResponse, $matches);
        if (isset($matches[0])) {
            return json_decode($matches[0], true);
        }
        
        return ['is_appropriate' => true, 'inappropriate_words' => [], 'reason' => 'Parse error', 'suggested_replacement' => null];
        
    } catch (Exception $e) {
        return ['is_appropriate' => true, 'inappropriate_words' => [], 'reason' => 'Exception: ' . $e->getMessage(), 'suggested_replacement' => null];
    }
}

// Database functions
function getUserPreferences($conn, $user_id) {
    $mix_n_match_cuisines = ['Filipino'];
    $ai_creativity = 'balanced';
    
    $stmt = $conn->prepare("SELECT mix_n_match_cuisines, ai_creativity FROM user_preferences WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $prefs = $result->fetch_assoc();
        if (!empty($prefs['mix_n_match_cuisines'])) {
            $mix_n_match_cuisines = json_decode($prefs['mix_n_match_cuisines'], true);
            if (empty($mix_n_match_cuisines)) {
                $mix_n_match_cuisines = ['Filipino'];
            }
        }
        if (!empty($prefs['ai_creativity'])) {
            $ai_creativity = $prefs['ai_creativity'];
        }
    }
    
    return [
        'mix_n_match_cuisines' => $mix_n_match_cuisines,
        'ai_creativity' => $ai_creativity
    ];
}

function getRecipesByCategory($conn) {
    $sql = "SELECT ar.*, 
            CASE 
                WHEN ar.user_id IS NULL THEN 'ChefAI'
                ELSE u.username
            END as creator_name
            FROM accepted_recipe ar
            LEFT JOIN users u ON ar.user_id = u.id
            ORDER BY ar.created_at DESC";
    
    $result = $conn->query($sql);
    $recipesByCategory = [
        'Main Dish' => [],
        'Side Dish' => [],
        'Dessert' => [],
        'Beverages / Drinks' => []
    ];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Normalize category names for consistent grouping
            $category = trim($row['category']);
            
            // Handle different possible category names for beverages
            if (strpos(strtolower($category), 'beverage') !== false || 
                strpos(strtolower($category), 'drink') !== false) {
                $category = 'Beverages / Drinks';
            }
            
            // Map to the correct category key
            if (array_key_exists($category, $recipesByCategory)) {
                $recipesByCategory[$category][] = $row;
            } else {
                // If it's a different category, try to find the closest match
                $found = false;
                foreach ($recipesByCategory as $key => $value) {
                    if (strpos(strtolower($key), strtolower($category)) !== false || 
                        strpos(strtolower($category), strtolower($key)) !== false) {
                        $recipesByCategory[$key][] = $row;
                        $found = true;
                        break;
                    }
                }
                
                // If no match found, just add it to the main category array
                if (!$found) {
                    $recipesByCategory['Main Dish'][] = $row;
                }
            }
        }
    }
    
    return $recipesByCategory;
}

// Get all recipes for comparison
function getAllRecipes($conn) {
    $sql = "SELECT ar.*, 
            CASE 
                WHEN ar.user_id IS NULL THEN 'ChefAI'
                ELSE u.username
            END as creator_name
            FROM accepted_recipe ar
            LEFT JOIN users u ON ar.user_id = u.id
            ORDER BY ar.recipe_name ASC";
    
    $result = $conn->query($sql);
    $recipes = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recipes[] = $row;
        }
    }
    
    return $recipes;
}

// Get data using the functions
$preferences = getUserPreferences($conn, $user_id);
$mix_n_match_cuisines = $preferences['mix_n_match_cuisines'];
$ai_creativity = $preferences['ai_creativity'];

$recipesByCategory = getRecipesByCategory($conn);
$allRecipes = getAllRecipes($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>ChefAI | Discover Filipino Recipes</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ==========================
           THEME VARIABLES
        ========================== */
        :root {
            --primary-color: #C1856D;
            --secondary-color: #4ECDC4;
            --accent-color: #FFE66D;
            --dark-color: #292F36;
            --light-color: #F7FFF7;
            --neutral-color: #6C4E31;
            --card-shadow: 0 10px 20px rgba(0,0,0,0.08);
            --card-hover-shadow: 0 15px 30px rgba(0,0,0,0.12);
            --transition: all 0.3s ease;
        }

        /* ==========================
           BASE STYLES
        ========================== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #fff6edff !important;
            font-family: "Poppins", sans-serif;
            line-height: 1.6;
            padding-bottom: 80px; /* Space for bottom navigation */
        }

        /* ==========================
           HEADER / HERO SECTION
        ========================== */
        .header {
            background: linear-gradient(120deg, var(--primary-color), var(--neutral-color));
            color: var(--light-color);
            text-align: center;
            padding: 40px 20px 70px;
            box-shadow: var(--card-shadow);
            position: relative;
            overflow: hidden;
        }

        .header::after {
            content: "";
            position: absolute;
            inset: 0;
            background: url('https://images.unsplash.com/photo-1504674900247-0877df9cc836?auto=format&fit=crop&w=1500&q=80') center/cover;
            opacity: 0.12;
        }

        .header h1 {
            font-size: 24px;
            margin: 0;
            font-weight: 700;
            position: relative;
        }

        .header p {
            font-size: 14px;
            opacity: 0.9;
            margin-top: 8px;
            position: relative;
        }

        /* ==========================
           SEARCH FUNCTIONALITY
        ========================== */
        .search-container {
            position: relative;
            width: 95%;
            max-width: 500px;
            margin: -35px auto 30px;
        }

        #search-input {
            width: 100%;
            padding: 14px 20px;
            border-radius: 40px;
            border: none;
            outline: none;
            font-size: 16px;
            background: #fff;
            color: var(--dark-color);
            box-shadow: var(--card-shadow);
            transition: var(--transition);
        }

        #search-input:focus {
            box-shadow: var(--card-hover-shadow);
            border: 2px solid var(--secondary-color);
        }

        .search-results {
            position: absolute;
            top: 60px;
            left: 0;
            right: 0;
            background: #fff;
            border-radius: 15px;
            box-shadow: var(--card-hover-shadow);
            max-height: 350px;
            overflow-y: auto;
            display: none;
            z-index: 1000;
        }

        .view-btn{
            background: #ab886d;
            border: none;
            color: var(--dark-color);
            padding: 8px 14px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
        }

        .recipe-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 15px;
            gap: 12px;
            border-bottom: 1px solid #f3f3f3;
        }

        .recipe-item:hover { 
            background: var(--light-color); 
        }

        .recipe-left {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
        }

        .recipe-thumb {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            object-fit: cover;
            box-shadow: var(--card-shadow);
        }

        .recipe-info h4 {
            margin: 0;
            font-size: 15px;
            font-weight: 600;
            color: var(--primary-color);
        }

        .recipe-info p { 
            font-size: 12px; 
            color: #777; 
            margin: 2px 0 0; 
        }

        /* ==========================
           COMPARE BUTTON
        ========================== */
        .compare-btn-container {
            display: flex;
            justify-content: center;
            margin: 20px 0 15px;
        }

        .compare-btn-below-search {
            background: #C1856D;
            color: var(--dark-color);
            border: none;
            padding: 14px 25px;
            border-radius: 40px;
            font-size: 15px;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: var(--card-shadow);
            font-weight: 600;
            min-width: 180px;
            justify-content: center;
        }

        .compare-btn-below-search:hover {
            background: var(--accent-color);
            color: var(--neutral-color);
            transform: translateY(-2px);
            box-shadow: var(--card-hover-shadow);
        }

        /* ==========================
           CATEGORIES FILTER
        ========================== */
        .categories-filter {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 5px;
            margin: 0 auto 30px;
            padding: 0 15px;
            max-width: 3000px;
        }

        .category-btn {
            background: white;
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 13px;
            cursor: pointer;
            transition: var(--transition);
            font-weight: 500;
            box-shadow: var(--card-shadow);
        }

        .category-btn:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
        }

        .category-btn.active {
            background: var(--primary-color);
            color: white;
        }

        /* ==========================
           RECIPE SECTIONS
        ========================== */
        .section {
            width: 95%;
            max-width: 1200px;
            margin: 0 auto 50px;
            display: none; /* Hidden by default, shown when category is active */
        }

        .section.active {
            display: block;
        }

        .section h2 {
            font-size: 20px;
            color: var(--neutral-color);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 0 10px;
        }

        .section h2::before {
            content: "🍽️";
            font-size: 20px;
        }

        /* ==========================
           HORIZONTAL SCROLLABLE CARDS
        ========================== */
        .cards-scroll-container {
            display: flex;
            overflow-x: auto;
            gap: 15px;
            padding: 10px 5px 15px;
            scrollbar-width: none; /* For Firefox */
            -ms-overflow-style: none; /* For Internet Explorer and Edge */
        }

        .cards-scroll-container::-webkit-scrollbar {
            display: none; /* For Chrome, Safari, and Opera */
        }

        .card {
            background: #fff;
            border-radius: 18px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            min-width: 260px;
            flex-shrink: 0;
        }

        .card:hover {
            transform: translateY(-8px);
            box-shadow: var(--card-hover-shadow);
        }

        .card img {
            width: 100%;
            height: 160px;
            object-fit: cover;
        }

        .card-content {
            padding: 14px;
            flex: 1;
        }

        .card-content h3 {
            margin: 0;
            color: var(--primary-color);
            font-size: 17px;
        }

        .card-content p {
            font-size: 12px;
            color: #555;
            margin: 6px 0;
        }

        .creator-text {
            color: var(--neutral-color);
            font-size: 11px;
            font-style: italic;
            margin-bottom: 8px;
        }

        .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: #777;
        }

        .card button {
            background: #ab886d;
            border: none;
            color: var(--dark-color);
            padding: 7px 12px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
            font-size: 12px;
        }

        .card button:hover {
            background: var(--accent-color);
            color: var(--neutral-color);
        }

        /* ==========================
           BOTTOM NAVIGATION
        ========================== */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            display: flex;
            justify-content: space-around;
            align-items: center;
            padding: 10px 0;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            z-index: 1000;
            border-top-left-radius: 20px;
            border-top-right-radius: 20px;
        }

        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: #777;
            transition: var(--transition);
            flex: 1;
            padding: 6px 0;
        }

        .nav-item.active {
            color: var(--primary-color);
        }

        .nav-icon {
            font-size: 18px;
            margin-bottom: 3px;
        }

        .nav-label {
            font-size: 11px;
            font-weight: 500;
        }

        /* ==========================
           FOOTER
        ========================== */
        footer {
            text-align: center;
            background: var(--neutral-color);
            color: var(--light-color);
            padding: 15px;
            font-size: 13px;
            border-top-left-radius: 25px;
            border-top-right-radius: 25px;
            letter-spacing: 0.3px;
            margin-top: 30px;
        }

        /* ==========================
           POPUP STYLES - MOBILE OPTIMIZED
        ========================== */
        .popup-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.7);
            z-index: 1100;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(5px);
            padding: 15px;
        }

        .popup-container {
            background-color: white;
            border-radius: 16px;
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            position: relative;
        }

        .popup-content {
            padding: 15px;
        }

        .popup-header {
            padding: 20px 15px;
            margin-bottom: 10px;
            position: relative;
            background: linear-gradient(to right, var(--primary-color), var(--neutral-color));
            color: white;
            border-radius: 16px 16px 0 0;
        }

        .popup-header h2 {
            margin: 0;
            font-weight: 700;
            text-align: center;
            color: white;
            font-size: 18px;
            padding-right: 40px;
        }

        .popup-body {
            padding: 10px 0;
        }

        .popup-image {
            margin-bottom: 15px;
            border-radius: 12px;
            overflow: hidden;
        }

        .popup-image img {
            width: 100%;
            max-height: 200px;
            object-fit: cover;
            display: block;
        }

        .popup-details {
            padding: 0 15px;
        }

        .popup-details h3 {
            color: var(--neutral-color);
            margin-bottom: 12px;
            font-weight: 600;
            padding-bottom: 6px;
            border-bottom: 2px solid rgba(108, 78, 49, 0.1);
            font-size: 16px;
        }

        .popup-details p {
            color: #555;
            line-height: 1.6;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .ingredients-list {
            margin-top: 20px;
        }

        .ingredients-list h4 {
            color: var(--neutral-color);
            margin-bottom: 12px;
            font-weight: 600;
            padding-bottom: 6px;
            border-bottom: 2px solid rgba(108, 78, 49, 0.1);
            font-size: 16px;
        }

        .ingredients-list ul {
            padding-left: 15px;
            list-style-type: none;
        }

        .ingredients-list li {
            margin-bottom: 6px;
            position: relative;
            padding-left: 20px;
            color: #555;
            font-size: 14px;
        }

        .ingredients-list li:before {
            content: "•";
            color: var(--primary-color);
            font-size: 1.3rem;
            position: absolute;
            left: 0;
            top: -5px;
        }

        .popup-footer {
            padding: 15px 0 10px;
            text-align: center;
            border-top: 1px solid #eee;
            margin-top: 15px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .action-btn {
            background: var(--secondary-color);
            color: var(--dark-color);
            border: none;
            padding: 12px 20px;
            border-radius: 30px;
            cursor: pointer;
            transition: var(--transition);
            font-weight: 500;
            font-size: 14px;
            width: 100%;
        }

        .action-btn:hover {
            background: var(--accent-color);
            color: var(--neutral-color);
            transform: translateY(-2px);
        }

        .close-btn {
            position: absolute;
            right: 15px;
            top: 15px;
            font-size: 1.5rem;
            cursor: pointer;
            color: white;
            background: rgba(0,0,0,0.2);
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            z-index: 10;
        }

        .close-btn:hover {
            background: rgba(0,0,0,0.3);
        }

        .exit-btn {
            background: #f8f9fa;
            color: #6c757d;
            border: 1px solid #dee2e6;
            padding: 12px 20px;
            border-radius: 30px;
            cursor: pointer;
            transition: var(--transition);
            font-weight: 500;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            font-size: 14px;
            width: 100%;
        }

        .exit-btn:hover {
            background: #e9ecef;
            color: #495057;
            transform: translateY(-2px);
        }

        /* ==========================
           INSTRUCTIONS SECTION
        ========================== */
        .instructions-section {
            margin-top: 20px;
        }

        .instructions-toggle {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 12px;
            cursor: pointer;
            transition: var(--transition);
            border: 1px solid #e9ecef;
        }

        .instructions-toggle:hover {
            background: #e9ecef;
        }

        .instructions-toggle h4 {
            margin: 0;
            color: var(--dark-color);
            font-size: 15px;
            font-weight: 600;
        }

        .instructions-toggle i {
            color: #666;
            transition: var(--transition);
        }

        .instructions-content {
            display: none;
            padding: 15px;
            background: #f9f5f0;
            border-radius: 8px;
            border-left: 4px solid var(--primary-color);
            margin-bottom: 15px;
        }

        .instructions-text {
            white-space: pre-line;
            line-height: 1.7;
            color: #555;
            font-size: 14px;
        }

        /* ==========================
           COMMENTS SECTION - UPDATED WITH EDIT/DELETE
        ========================== */
        .comments-section {
            padding: 0 15px 10px;
        }

        .comments-section h3 {
            color: var(--neutral-color);
            margin: 10px 0 12px;
            border-bottom: 2px solid rgba(108,78,49,.1);
            padding-bottom: 6px;
            font-weight: 600;
            font-size: 16px;
        }

        #comments-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 10px;
        }

        .comment-item {
            background: #fff;
            border: 1px solid #eee;
            border-radius: 10px;
            padding: 10px 12px;
            box-shadow: 0 2px 6px rgba(0,0,0,.04);
            position: relative;
            transition: var(--transition);
        }

        .comment-item:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,.08);
        }

        .comment-meta {
            font-size: 13px;
            color: var(--neutral-color);
            font-weight: 600;
            display: flex;
            gap: 6px;
            align-items: center;
            justify-content: space-between;
        }

        .comment-user-info {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .comment-date {
            color: #999;
            font-weight: 400;
            font-size: 11px;
        }

        .comment-text {
            margin-top: 5px;
            white-space: pre-wrap;
            word-break: break-word;
            font-size: 14px;
            padding-right: 40px;
        }

        .comment-actions {
            position: absolute;
            right: 8px;
            top: 8px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 6px 18px rgba(0,0,0,.12);
            display: none;
            z-index: 5;
            overflow: hidden;
            min-width: 80px;
        }

        .comment-actions button {
            display: block;
            width: 100%;
            padding: 8px 12px;
            background: #fff;
            border: 0;
            text-align: left;
            cursor: pointer;
            font-size: 12px;
            transition: var(--transition);
        }

        .comment-actions button:hover {
            background: #f7f7f7;
        }

        .comment-actions .edit-btn:hover {
            background: #e3f2fd;
            color: #1976d2;
        }

        .comment-actions .del-btn:hover {
            background: #ffebee;
            color: #d32f2f;
        }

        .comment-actions-btn {
            background: transparent;
            border: none;
            color: #666;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 4px;
            transition: var(--transition);
            opacity: 0;
        }

        .comment-item:hover .comment-actions-btn {
            opacity: 1;
        }

        .comment-actions-btn:hover {
            background: #f0f0f0;
            color: #333;
        }

        .comment-edit-form {
            display: none;
            margin-top: 8px;
        }

        .comment-edit-input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-family: "Poppins", sans-serif;
            font-size: 14px;
            resize: vertical;
            min-height: 60px;
        }

        .comment-edit-input:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .comment-edit-actions {
            display: flex;
            gap: 8px;
            margin-top: 8px;
        }

        .comment-edit-actions button {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            transition: var(--transition);
        }

        .comment-save-btn {
            background: var(--secondary-color);
            color: var(--dark-color);
        }

        .comment-save-btn:hover {
            background: var(--accent-color);
        }

        .comment-cancel-btn {
            background: #f8f9fa;
            color: #6c757d;
            border: 1px solid #dee2e6;
        }

        .comment-cancel-btn:hover {
            background: #e9ecef;
        }

        #comment-form {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        #comment-input {
            flex: 1;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: "Poppins", sans-serif;
            font-size: 14px;
            resize: vertical;
            min-height: 60px;
        }

        #comment-input:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .btn-primary {
            background-color: var(--secondary-color);
            border: none;
            color: var(--dark-color);
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
            white-space: nowrap;
            font-size: 14px;
            width: 100%;
        }

        .btn-primary:hover {
            background-color: var(--accent-color);
            color: var(--neutral-color);
        }

        /* ==========================
           COMMENT FILTER POPUP STYLES - ADDED FOR MOBILE
        ========================== */
        .filter-popup-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.7);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(5px);
            padding: 15px;
        }

        .filter-popup-container {
            background-color: white;
            border-radius: 16px;
            width: 95%;
            max-width: 400px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            border-left: 6px solid #ff6b6b;
        }

        .filter-popup-header {
            background: linear-gradient(135deg, #ff6b6b, #ff8e8e);
            color: white;
            padding: 18px;
            border-radius: 16px 16px 0 0;
            text-align: center;
        }

        .filter-popup-header h3 {
            margin: 0;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .filter-popup-body {
            padding: 20px;
        }

        .filter-warning-text {
            color: #d63031;
            font-weight: 600;
            margin-bottom: 12px;
            text-align: center;
            font-size: 15px;
        }

        .highlighted-words {
            background: #ffeaa7;
            padding: 12px;
            border-radius: 10px;
            margin: 12px 0;
            border: 2px dashed #fdcb6e;
        }

        .highlighted-words h4 {
            margin: 0 0 8px 0;
            color: #e17055;
            font-size: 14px;
        }

        .bad-words-list {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 8px;
        }

        .bad-word {
            background: #ff7675;
            color: white;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
        }

        .filter-reason {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
            margin: 12px 0;
            font-size: 13px;
            color: #555;
            border-left: 4px solid #74b9ff;
        }

        .suggestion-box {
            margin: 15px 0;
        }

        .suggestion-box h4 {
            margin: 0 0 8px 0;
            color: #00b894;
            font-size: 14px;
        }

        #suggested-comment {
            width: 100%;
            padding: 10px;
            border: 2px solid #00b894;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            resize: vertical;
            min-height: 70px;
            background: #f1f8f5;
        }

        .filter-popup-footer {
            display: flex;
            flex-direction: column;
            gap: 10px;
            justify-content: center;
            padding: 15px;
            border-top: 1px solid #eee;
        }

        .filter-edit-btn {
            background: #74b9ff;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
            font-size: 14px;
        }

        .filter-edit-btn:hover {
            background: #0984e3;
            transform: translateY(-2px);
        }

        .filter-cancel-btn {
            background: #dfe6e9;
            color: #636e72;
            border: none;
            padding: 12px 20px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
            font-size: 14px;
        }

        .filter-cancel-btn:hover {
            background: #b2bec3;
            transform: translateY(-2px);
        }

        .use-suggestion-btn {
            background: #00b894;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 12px;
            margin-top: 8px;
            transition: var(--transition);
            width: 100%;
        }

        .use-suggestion-btn:hover {
            background: #00a085;
        }

        /* ==========================
           STRIKE & BAN SYSTEM STYLES - ADDED FOR MOBILE
        ========================== */
        .strike-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 12px;
            font-size: 13px;
            display: none;
            border-left: 4px solid #ffc107;
        }

        .strike-warning.show {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .strike-warning i {
            color: #856404;
            font-size: 14px;
        }

        .strike-counter {
            background: #ffeaa7;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 11px;
            color: #e17055;
            font-weight: 600;
            margin-left: 8px;
            border: 1px solid #fdcb6e;
        }

        .success-message {
            animation: slideDown 0.3s ease-out;
            background: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #c3e6cb;
            margin: 8px 0;
            font-size: 13px;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Enhanced filter popup */
        .filter-popup-container {
            animation: popIn 0.3s ease-out;
            max-width: 400px;
        }

        @keyframes popIn {
            0% { transform: scale(0.9); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }

        /* ==========================
           AI SUGGESTIONS
        ========================== */
        #ai-popup .popup-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 500px;
            overflow: hidden;
        }

        #ai-popup .popup-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--neutral-color) 100%);
            color: white;
            padding: 20px;
            border-radius: 16px 16px 0 0;
            text-align: center;
            position: relative;
        }

        #ai-popup .popup-header h2 {
            margin: 0;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            padding-right: 40px;
        }

        #ai-popup .popup-header h2 i {
            margin-right: 10px;
            font-size: 18px;
        }

        #ai-popup .popup-content {
            padding: 0;
        }

        #ai-popup .popup-body {
            padding: 20px;
            max-height: 60vh;
            overflow-y: auto;
        }

        #ai-suggestions-loading {
            text-align: center;
            padding: 30px;
            background-color: rgba(255,255,255,0.8);
            border-radius: 12px;
        }

        #ai-suggestions-loading p {
            font-size: 16px;
            color: var(--neutral-color);
            margin-bottom: 20px;
            font-weight: 500;
        }

        #ai-suggestions-results {
            padding: 0;
        }

        #ai-suggestions-results h4 {
            color: var(--neutral-color);
            font-size: 17px;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px dashed var(--primary-color);
            text-align: center;
            font-weight: 600;
        }

        .ai-suggestion-item {
            padding: 15px;
            margin-bottom: 12px;
            background-color: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            border-left: 4px solid var(--primary-color);
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }

        .ai-suggestion-item:hover {
            transform: translateY(-5px);
            box-shadow: var(--card-hover-shadow);
            border-left: 4px solid var(--accent-color);
        }

        .ai-suggestion-item .dish-name {
            font-weight: 600;
            color: var(--neutral-color);
            font-size: 15px;
        }

        /* ==========================
           AI MIX N MATCH INSTRUCTIONS POPUP - ADDED
        ========================== */
        #ai-mix-popup .popup-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 500px;
            overflow: hidden;
        }

        #ai-mix-popup .popup-header {
            background: linear-gradient(135deg, #4ECDC4 0%, #44A08D 100%);
            color: white;
            padding: 20px;
            border-radius: 16px 16px 0 0;
            text-align: center;
            position: relative;
        }

        #ai-mix-popup .popup-header h2 {
            margin: 0;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            padding-right: 40px;
        }

        #ai-mix-popup .popup-header h2 i {
            margin-right: 10px;
            font-size: 18px;
        }

        #ai-mix-popup .popup-body {
            padding: 20px;
            max-height: 60vh;
            overflow-y: auto;
        }

        .ai-mix-content {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid #4ECDC4;
        }

        .ai-mix-title {
            font-size: 18px;
            color: #C1856D;
            margin-bottom: 15px;
            font-weight: 600;
            text-align: center;
        }

        /* Mix n Match Instructions in Comparison Format */
        .ai-mix-instructions {
            line-height: 1.6;
            color: #333;
            font-size: 14px;
            background: white;
            padding: 20px;
        }

        .ai-mix-instructions h3 {
            color: var(--primary-color);
            font-size: 17px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 700;
            border-bottom: 2px solid var(--secondary-color);
            padding-bottom: 12px;
        }

        .ai-mix-instructions h4 {
            color: var(--neutral-color);
            font-size: 15px;
            margin: 20px 0 12px 0;
            font-weight: 600;
            padding-bottom: 6px;
            border-bottom: 2px solid rgba(108, 78, 49, 0.1);
        }

        .ai-mix-instructions ul {
            margin: 12px 0 20px 0;
            padding-left: 15px;
            list-style: none;
        }

        .ai-mix-instructions li {
            margin: 8px 0;
            padding-left: 0;
            color: #555;
            position: relative;
        }

        .ai-mix-instructions li:before {
            content: "•";
            color: var(--primary-color);
            font-weight: bold;
            margin-right: 6px;
        }

        .ai-mix-footer {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 15px;
        }

        /* ==========================
           COMPARISON STYLES
        ========================== */
        .compare-popup-container {
            max-width: 500px;
            max-height: 80vh;
        }

        .selected-recipes-section {
            margin-bottom: 1.5rem;
        }

        .selected-recipes-section h4 {
            color: var(--neutral-color);
            margin-bottom: 0.8rem;
            font-weight: 600;
            font-size: 16px;
        }

        .selected-recipes-container {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 1.2rem;
            min-height: 100px;
            border: 2px dashed #dee2e6;
        }

        .no-selection-message {
            text-align: center;
            color: #6c757d;
            padding: 1.5rem;
        }

        .no-selection-message i {
            font-size: 2rem;
            margin-bottom: 0.8rem;
            color: #adb5bd;
        }

        .selected-recipe-item {
            display: flex;
            align-items: center;
            background: white;
            padding: 0.8rem;
            border-radius: 8px;
            margin-bottom: 0.6rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            position: relative;
        }

        .selected-recipe-image {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            object-fit: cover;
            margin-right: 0.8rem;
        }

        .selected-recipe-info {
            flex: 1;
        }

        .selected-recipe-info h5 {
            margin: 0 0 0.4rem 0;
            color: var(--dark-color);
            font-size: 14px;
        }

        .selected-recipe-info p {
            margin: 0;
            color: #666;
            font-size: 12px;
        }

        .remove-compare-btn {
            background: #dc3545;
            color: white;
            border: none;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            transition: var(--transition);
        }

        .remove-compare-btn:hover {
            background: #c82333;
            transform: scale(1.1);
        }

        .available-recipes-section h4 {
            color: var(--neutral-color);
            margin-bottom: 0.8rem;
            font-weight: 600;
            font-size: 16px;
        }

        .available-recipes-list {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #e9ecef;
            border-radius: 12px;
        }

        .recipe-compare-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.8rem;
            border-bottom: 1px solid #e9ecef;
            transition: var(--transition);
        }

        .recipe-compare-item:last-child {
            border-bottom: none;
        }

        .recipe-compare-item:hover {
            background: #f8f9fa;
        }

        .recipe-compare-info {
            display: flex;
            align-items: center;
            flex: 1;
        }

        .recipe-compare-image {
            width: 45px;
            height: 45px;
            border-radius: 8px;
            object-fit: cover;
            margin-right: 0.8rem;
        }

        .recipe-compare-details h5 {
            margin: 0 0 0.4rem 0;
            color: var(--dark-color);
            font-size: 14px;
        }

        .recipe-compare-details p {
            margin: 0;
            color: #666;
            font-size: 12px;
        }

        .recipe-meta {
            color: #888 !important;
            font-size: 11px !important;
        }

        .add-compare-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 12px;
            cursor: pointer;
            transition: var(--transition);
        }

        .add-compare-btn:hover {
            background: var(--dark-color);
            transform: scale(1.05);
        }

        .add-compare-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
        }

        .view-compare-btn {
            background: transparent;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 12px;
            cursor: pointer;
            transition: var(--transition);
            margin-right: 0.4rem;
        }

        .view-compare-btn:hover {
            background: var(--primary-color);
            color: white;
        }

        /* ==========================
           COMPARE SEARCH & FILTER
        ========================== */
        .compare-search-container {
            margin-bottom: 12px;
        }

        .compare-search-box {
            background: #f5f7fa;
            border-radius: 25px;
            padding: 10px 15px;
            display: flex;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .compare-search-box i {
            color: #666;
            margin-right: 8px;
        }

        #compare-search-input {
            border: none;
            background: transparent;
            outline: none;
            flex: 1;
            font-size: 14px;
            color: var(--dark-color);
            font-family: 'Poppins', sans-serif;
        }

        #compare-search-input::placeholder {
            color: rgba(102, 102, 102, 0.7);
        }

        #clear-compare-search {
            background: transparent;
            border: none;
            color: #666;
            cursor: pointer;
            padding: 0.4rem;
            border-radius: 50%;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #clear-compare-search:hover {
            background: rgba(0,0,0,0.1);
        }

        .compare-category-filter {
            margin-bottom: 12px;
        }

        .compare-category-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
        }

        .compare-category-btn {
            background: white;
            border: 1px solid #ddd;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 12px;
            cursor: pointer;
            white-space: nowrap;
            transition: var(--transition);
        }

        .compare-category-btn.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        /* ==========================
           COMPARISON RESULTS
        ========================== */
        .comparison-results-container {
            max-width: 500px;
            max-height: 85vh;
            width: 100%;
        }

        .comparison-layout {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
            padding: 1rem;
        }

        .comparison-recipe-card {
            background: white;
            border-radius: 15px;
            padding: 1.2rem;
            box-shadow: var(--card-shadow);
            border: 1px solid #e9ecef;
            height: fit-content;
        }

        .comparison-recipe-header {
            display: flex;
            align-items: center;
            margin-bottom: 1.2rem;
            gap: 0.8rem;
        }

        .comparison-recipe-image {
            width: 70px;
            height: 70px;
            border-radius: 8px;
            object-fit: cover;
        }

        .comparison-recipe-title h3 {
            margin: 0 0 0.4rem 0;
            color: var(--dark-color);
            font-size: 16px;
        }

        .comparison-recipe-title p {
            margin: 0;
            color: #666;
            font-size: 13px;
        }

        .comparison-basic-info {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.8rem;
            margin-bottom: 1.2rem;
            padding: 0.8rem;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .info-item {
            text-align: center;
        }

        .info-label {
            display: block;
            font-size: 12px;
            color: #666;
            margin-bottom: 0.4rem;
            font-weight: 500;
        }

        .info-value {
            display: block;
            font-size: 14px;
            color: var(--dark-color);
            font-weight: 600;
        }

        .comparison-details-section {
            border-top: 1px solid #e9ecef;
            padding-top: 0.8rem;
        }

        .details-toggle {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.8rem;
            background: #f8f9fa;
            border-radius: 6px;
            margin-bottom: 0.6rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .details-toggle:hover {
            background: #e9ecef;
        }

        .details-toggle h4 {
            margin: 0;
            color: var(--dark-color);
            font-size: 14px;
            font-weight: 600;
        }

        .details-toggle i {
            color: #666;
            transition: var(--transition);
        }

        .details-content {
            display: none;
            padding: 0.8rem;
            background: white;
            border-radius: 6px;
            border: 1px solid #e9ecef;
            margin-bottom: 0.8rem;
        }

        .loading-details {
            text-align: center;
            color: #666;
            font-style: italic;
            font-size: 13px;
        }

        .ingredients-list-compare {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .ingredients-list-compare li {
            padding: 0.4rem 0;
            color: #555;
            font-size: 13px;
            border-bottom: 1px solid #f0f0f0;
        }

        .ingredients-list-compare li:last-child {
            border-bottom: none;
        }

        .ingredients-list-compare li:before {
            content: "•";
            color: var(--primary-color);
            margin-right: 0.4rem;
        }

        .instructions-text-compare {
            color: #555;
            font-size: 13px;
            line-height: 1.6;
        }

        .error-text {
            color: #dc3545;
            font-size: 13px;
            text-align: center;
            font-style: italic;
        }

        /* ==========================
           AI COMPARISON STYLES
        ========================== */
        .ai-purpose-container {
            max-width: 500px;
        }

        .purpose-selection h4 {
            color: var(--neutral-color);
            margin-bottom: 0.8rem;
            text-align: center;
            font-weight: 600;
            font-size: 16px;
        }

        .purpose-options {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            justify-items: center;
            max-width: 500px;
            margin: 0 auto;
        }

        .purpose-option {
            cursor: pointer;
            width: 100%;
            max-width: 180px;
        }

        .purpose-card {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 1.2rem 0.8rem;
            text-align: center;
            transition: var(--transition);
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .purpose-option input:checked + .purpose-card {
            border-color: var(--primary-color);
            background: linear-gradient(135deg, #f9f5f0 0%, #f0e6d9 100%);
            transform: translateY(-2px);
            box-shadow: var(--card-hover-shadow);
        }

        .purpose-card i {
            font-size: 1.8rem;
            color: var(--primary-color);
            margin-bottom: 0.8rem;
        }

        .purpose-card h5 {
            margin: 0 0 0.8rem 0;
            color: var(--dark-color);
            font-size: 14px;
            font-weight: 600;
        }

        .purpose-card p {
            margin: 0;
            color: #666;
            font-size: 12px;
            line-height: 1.4;
        }

        .ai-comparison-container {
            max-width: 500px;
            max-height: 80vh;
        }

        .ai-comparison-content {
            background: white;
            padding: 1.2rem;
            border-radius: 8px;
            border-left: 4px solid var(--primary-color);
        }

        .ai-comparison-text {
            line-height: 1.6;
            color: #333;
            font-size: 14px;
        }

        .ai-comparison-text strong {
            color: var(--primary-color);
            font-weight: 600;
        }

        .ai-comparison-text em {
            color: #666;
            font-style: italic;
        }

        .ai-comparison-text h4 {
            color: var(--primary-color);
            margin: 1.2rem 0 0.6rem 0;
            font-weight: 600;
            font-size: 15px;
        }

        .ai-comparison-text li {
            margin: 0.4rem 0;
        }

        /* ==========================
           WINNER BADGE STYLES - ADDED FROM DESKTOP VERSION
        ========================== */
        .winner-badge {
            background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%);
            color: #8B4513;
            padding: 6px 12px;
            border-radius: 15px;
            font-weight: 700;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin: 8px 0;
            box-shadow: 0 4px 12px rgba(255, 215, 0, 0.3);
            border: 2px solid #FFD700;
        }

        .winner-badge i {
            font-size: 12px;
        }

        .comparison-winner-section {
            background: linear-gradient(135deg, #f9f5f0 0%, #f0e6d9 100%);
            border: 2px solid var(--accent-color);
            border-radius: 10px;
            padding: 1.2rem;
            margin: 1.2rem 0;
            text-align: center;
        }

        .comparison-winner-section h3 {
            color: var(--neutral-color);
            margin-bottom: 0.8rem;
            font-weight: 700;
            font-size: 16px;
        }

        .winner-recipe {
            background: white;
            border-radius: 8px;
            padding: 1.2rem;
            margin: 0.8rem 0;
            box-shadow: var(--card-shadow);
            border-left: 4px solid #FFD700;
        }

        .winner-recipe h4 {
            color: var(--primary-color);
            margin-bottom: 0.4rem;
            font-weight: 700;
            font-size: 15px;
        }

        .winner-reason {
            color: #555;
            font-size: 13px;
            line-height: 1.5;
            margin-top: 0.4rem;
        }

        /* ==========================
           LOADING & ANIMATIONS
        ========================== */
        .loading-animation {
            animation: pulse 1.5s infinite;
            color: var(--primary-color);
            font-size: 2.5rem;
            margin-bottom: 15px;
        }

        .error-message {
            color: var(--primary-color);
            font-weight: 600;
            background: rgba(255, 107, 107, 0.1);
            padding: 12px;
            border-radius: 8px;
            border-left: 4px solid var(--primary-color);
            font-size: 14px;
        }

        .text-muted {
            color: #6c757d !important;
        }

        .text-danger {
            color: #dc3545 !important;
        }

        .small {
            font-size: 12px;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        /* ==========================
           MIX N MATCH SECTION - ADJUSTED WIDTH
        ========================== */
        #section-chefai .card {
            min-width: 280px; /* Wider for better visibility */
            max-width: 320px; /* Limit maximum width */
        }

        /* ==========================
           RESPONSIVE STYLES
        ========================== */
        @media (max-width: 480px) {
            .header {
                padding: 30px 15px 60px;
            }

            .header h1 {
                font-size: 22px;
            }

            .header p {
                font-size: 13px;
            }

            .search-container {
                width: 95%;
            }

            .card {
                min-width: 240px;
            }

            .section h2 {
                font-size: 18px;
            }

            .purpose-options {
                grid-template-columns: 1fr;
            }

            .purpose-option {
                max-width: 100%;
            }

            .compare-btn-below-search {
                padding: 12px 20px;
                font-size: 14px;
                min-width: 160px;
            }

            .category-btn {
                padding: 7px 12px;
                font-size: 12px;
            }

            /* Mix n Match section adjustments */
            #section-chefai .card {
                min-width: 260px;
                max-width: 300px;
            }

            /* Comment filter popup adjustments */
            .filter-popup-container {
                max-width: 350px;
            }

            .filter-popup-footer {
                flex-direction: column;
            }
        }

        @media (max-width: 360px) {
            .header h1 {
                font-size: 20px;
            }

            .card {
                min-width: 220px;
            }

            .category-btn {
                padding: 6px 10px;
                font-size: 11px;
            }

            /* Mix n Match section adjustments */
            #section-chefai .card {
                min-width: 240px;
                max-width: 280px;
            }

            /* Comment filter popup adjustments */
            .filter-popup-container {
                max-width: 320px;
            }
        }
    </style>
</head>
<body>
    <?php include 'side-nav-mobile.php'; ?>
    <!-- HERO HEADER -->
    <div class="header">
         <h1>Welcome to ChefAI <?php echo htmlspecialchars($username); ?>🍳</h1>
        <p>Cook, share, and discover your favorite Filipino dishes</p>
    </div>

    <!-- SEARCH FUNCTIONALITY -->
    <div class="search-container">
        <input type="text" id="search-input" placeholder="Search for recipes...">
        <div id="search-results" class="search-results"></div>
    </div>

    <!-- COMPARE BUTTON -->
    <div class="compare-btn-container">
        <button class="compare-btn-below-search" onclick="openComparePopup()">
            <i class="fas fa-balance-scale"></i>
            Compare Recipes
        </button>
    </div>

    <!-- CATEGORIES FILTER -->
    <div class="categories-filter">
        <button class="category-btn active" data-category="all">All Recipes</button>
        <button class="category-btn" data-category="main">Main Dish</button>
        <button class="category-btn" data-category="side">Side Dish</button>
        <button class="category-btn" data-category="dessert">Dessert</button>
        <button class="category-btn" data-category="beverage">Beverages</button>
    </div>

    <!-- RECIPE CATEGORIES -->
    <?php
    $categories = ["Main Dish", "Side Dish", "Dessert", "Beverages / Drinks"];
    foreach ($categories as $category):
        if (!empty($recipesByCategory[$category])):
    ?>
    <section class="section" id="section-<?php echo strtolower(str_replace(' ', '-', $category)); ?>">
        <h2><?php echo htmlspecialchars($category); ?></h2>
        <div class="cards-scroll-container">
            <?php foreach ($recipesByCategory[$category] as $recipe): ?>
                <div class="card">
                    <img src="uploads/<?php echo htmlspecialchars($recipe['image_name']); ?>" alt="<?php echo htmlspecialchars($recipe['recipe_name']); ?>">
                    <div class="card-content">
                        <h3><?php echo htmlspecialchars($recipe['recipe_name']); ?></h3>
                        <p>Prep: <?php echo htmlspecialchars($recipe['prep_time']); ?> mins | Cook: <?php echo htmlspecialchars($recipe['cook_time']); ?> mins</p>
                        <p class="creator-text">Created by: <?php echo htmlspecialchars($recipe['creator_name']); ?></p>
                        <div class="card-footer">
                            <span>Serves <?php echo htmlspecialchars($recipe['servings']); ?></span>
                            <button onclick="viewRecipeDetails(<?php echo $recipe['id']; ?>)">View</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; endforeach; ?>

    <!-- CHEF-AI SECTION -->
    <section class="section" id="section-chefai">
        <h2>Ask Chef-AI For Mix n Match</h2>
        <div class="cards-scroll-container">
            <div class="card" onclick="getDishSuggestions()">
                <img src="final-logo-mix n match.png" alt="Chef-AI">
                <div class="card-content">
                    <h3>Hi This is Chef-AI</h3>
                    <p>Click me to get Mix n Match food suggestions based on your preferences!</p>
                    <div class="card-footer">
                        <span>AI Assistant</span>
                        <button>Get Suggestions</button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- RECIPE DETAILS POPUP -->
    <div class="popup-overlay" id="popup">
        <div class="popup-container">
            <div class="popup-content">
                <div class="popup-header">
                    <h2 id="popup-title">Recipe Title</h2>
                    <span class="close-btn" onclick="closePopup('popup')">&times;</span>
                </div>
                
                <div class="popup-image">
                    <img id="popup-img" src="" alt="">
                </div>
                
                <div class="popup-body">
                    <div class="popup-details">
                        <p id="popup-description">This is a sample description of the recipe.</p>
                        
                        <div class="ingredients-list">
                            <h4>Ingredients</h4>
                            <ul id="popup-ingredients">
                                <li>Loading ingredients...</li>
                            </ul>
                        </div>
                        
                        <div class="instructions-section">
                            <div class="instructions-toggle" onclick="toggleInstructions(this)">
                                <h4>Instructions</h4>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <div class="instructions-content" id="popup-instructions">
                                <div class="instructions-text">Loading instructions...</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- COMMENTS SECTION - UPDATED WITH STRIKE WARNING -->
                <div class="comments-section">
                    <h3>Comments & Feedback
                        <small id="comments-count" style="color:#888;font-weight:400;margin-left:6px;"></small>
                        <span id="strike-counter" class="strike-counter" style="display: none;"></span>
                    </h3>
                    
                    <!-- Strike Warning - ADDED -->
                    <div id="strike-warning" class="strike-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        You have <span id="current-strikes">0</span> strike(s). Be careful with your comments!
                    </div>
                    
                    <div id="comments-list">
                        <!-- comments injected here -->
                    </div>
                    
                    <form id="comment-form" onsubmit="return submitComment(event)">
                        <textarea id="comment-input" rows="2" placeholder="Write a comment..."></textarea>
                        <button type="submit" class="btn-primary">Post</button>
                    </form>
                </div>
                
                <div class="popup-footer">
                    <button class="action-btn" id="save-btn">Save Recipe</button>
                    <button class="exit-btn" onclick="closePopup('popup')">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- AI COMMENT FILTER POPUP - ADDED -->
    <div class="filter-popup-overlay" id="comment-filter-popup">
        <div class="filter-popup-container">
            <div class="filter-popup-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Content Warning</h3>
            </div>
            <div class="filter-popup-body">
                <div class="filter-warning-text">
                    Your comment contains inappropriate content
                </div>
                
                <div class="highlighted-words">
                    <h4>Problematic Words Detected:</h4>
                    <div class="bad-words-list" id="bad-words-list">
                        <!-- Bad words will be inserted here -->
                    </div>
                </div>
                
                <div class="filter-reason">
                    <strong>Reason:</strong> <span id="filter-reason-text">Content violates community guidelines</span>
                </div>
                
                <div class="suggestion-box">
                    <h4>Suggested Alternative:</h4>
                    <textarea id="suggested-comment" readonly></textarea>
                    <button class="use-suggestion-btn" onclick="useSuggestion()">
                        <i class="fas fa-magic"></i> Use This Suggestion
                    </button>
                </div>
            </div>
            <div class="filter-popup-footer">
                <button class="filter-edit-btn" onclick="editComment()">
                    <i class="fas fa-edit"></i> Edit Comment
                </button>
                <button class="filter-cancel-btn" onclick="cancelComment()">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
    </div>

    <!-- AI MIX N MATCH INSTRUCTIONS POPUP - ADDED -->
    <div class="popup-overlay" id="ai-mix-popup">
        <div class="popup-container">
            <div class="popup-content">
                <div class="popup-header">
                    <h2><i class="fas fa-robot"></i> Chef-AI Mix n Match</h2>
                    <span class="close-btn" onclick="closePopup('ai-mix-popup')">&times;</span>
                </div>
                
                <div class="popup-body">
                    <div class="ai-mix-content">
                        <h3 class="ai-mix-title" id="ai-mix-title">Mix n Match Combination</h3>
                        <div class="ai-mix-instructions" id="ai-mix-instructions">
                            Loading instructions...
                        </div>
                    </div>
                </div>
                
                <div class="popup-footer ai-mix-footer">
                    <button class="action-btn" id="save-ai-mix-btn" onclick="saveAIMixMatch()">
                        <i class="fas fa-bookmark"></i> Save to Bookmarks
                    </button>
                    <button class="exit-btn" onclick="closePopup('ai-mix-popup')">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- AI SUGGESTIONS POPUP -->
    <div class="popup-overlay" id="ai-popup">
        <div class="popup-container">
            <div class="popup-content">
                <div class="popup-header">
                    <h2><i class="fas fa-robot"></i> Chef-AI Mix n Match Suggestions</h2>
                    <span class="close-btn" onclick="closePopup('ai-popup')">&times;</span>
                </div>
                <div class="popup-body">
                    <div id="ai-suggestions-loading">
                        <p>Chef-AI is crafting delicious Mix n Match dishes for you...</p>
                        <div class="loading-animation">
                            <i class="fas fa-utensils"></i>
                        </div>
                        <div style="color: rgba(0,0,0,0.8);">
                            <small>Analyzing thousands of recipe combinations...</small>
                        </div>
                    </div>
                    <div id="ai-suggestions-results" style="display: none;">
                        <h4>Your Customized Meal Mix n Match Combination</h4>
                        <div id="ai-suggestions-list"></div>
                    </div>
                </div>
                <div class="popup-footer">
                    <button class="action-btn" onclick="closePopup('ai-popup')">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- COMPARE RECIPES POPUP -->
    <div class="popup-overlay" id="compare-popup">
        <div class="popup-container compare-popup-container">
            <div class="popup-content">
                <div class="popup-header">
                    <h2><i class="fas fa-balance-scale"></i> Compare Recipes</h2>
                    <span class="close-btn" onclick="closePopup('compare-popup')">&times;</span>
                </div>
                
                <div class="popup-body">
                    <div class="selected-recipes-section">
                        <h4>Selected for Comparison (Max 4)</h4>
                        <div class="selected-recipes-container" id="selected-recipes-container">
                            <div class="no-selection-message">
                                <i class="fas fa-plus-circle"></i>
                                <p>Add recipes to compare by clicking "Add" button</p>
                            </div>
                        </div>
                    </div>

                    <div class="available-recipes-section">
                        <h4>Available Recipes</h4>
                        
                        <!-- SEARCH AND FILTER SECTION -->
                        <div class="compare-search-container">
                            <div class="compare-search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" id="compare-search-input" placeholder="Search recipes to compare...">
                                <button id="clear-compare-search" style="display: none;">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="compare-category-filter">
                            <div class="compare-category-buttons">
                                <button class="compare-category-btn active" data-category="all">All Categories</button>
                                <button class="compare-category-btn" data-category="main">Main Dish</button>
                                <button class="compare-category-btn" data-category="side">Side Dish</button>
                                <button class="compare-category-btn" data-category="beverage">Beverages</button>
                                <button class="compare-category-btn" data-category="dessert">Dessert</button>
                            </div>
                        </div>

                        <div class="available-recipes-list" id="available-recipes-list">
                            <?php foreach ($allRecipes as $recipe): ?>
                                <div class="recipe-compare-item" 
                                     data-recipe-id="<?php echo $recipe['id']; ?>"
                                     data-name="<?php echo htmlspecialchars(strtolower($recipe['recipe_name'])); ?>"
                                     data-category="<?php 
                                         $cat = strtolower($recipe['category']);
                                         if (strpos($cat, 'main') !== false) echo 'main';
                                         elseif (strpos($cat, 'side') !== false) echo 'side';
                                         elseif (strpos($cat, 'beverage') !== false || strpos($cat, 'drink') !== false) echo 'beverage';
                                         elseif (strpos($cat, 'dessert') !== false) echo 'dessert';
                                         else echo 'main';
                                     ?>">
                                    <div class="recipe-compare-info">
                                        <img src="uploads/<?php echo htmlspecialchars($recipe['image_name']); ?>" 
                                             alt="<?php echo htmlspecialchars($recipe['recipe_name']); ?>" 
                                             class="recipe-compare-image">
                                        <div class="recipe-compare-details">
                                            <h5><?php echo htmlspecialchars($recipe['recipe_name']); ?></h5>
                                            <p><?php echo htmlspecialchars($recipe['category']); ?></p>
                                            <p class="recipe-meta">
                                                <?php echo ($recipe['prep_time'] + $recipe['cook_time']); ?> min • 
                                                <?php echo $recipe['servings']; ?> servings
                                            </p>
                                        </div>
                                    </div>
                                    <div class="recipe-compare-actions">
                                        <button class="view-compare-btn" onclick="viewCompareRecipeDetails(<?php echo $recipe['id']; ?>)">
                                            View
                                        </button>
                                        <button class="add-compare-btn" onclick="addToCompare(<?php echo $recipe['id']; ?>, '<?php echo htmlspecialchars($recipe['recipe_name']); ?>', '<?php echo htmlspecialchars($recipe['image_name']); ?>', '<?php echo htmlspecialchars($recipe['category']); ?>', <?php echo ($recipe['prep_time'] + $recipe['cook_time']); ?>, <?php echo $recipe['servings']; ?>)">
                                            Add
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="popup-footer">
                    <button class="action-btn" onclick="startComparison()" id="start-comparison-btn" disabled>
                        <i class="fas fa-chart-bar"></i> Compare Selected
                    </button>
                    <button class="action-btn" style="background: #C1856D;" onclick="getAIComparison()" id="ai-comparison-btn" disabled>
                        <i class="fas fa-robot"></i> AI Analysis
                    </button>
                    <button class="exit-btn" onclick="closePopup('compare-popup')">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- COMPARISON RESULTS POPUP -->
    <div class="popup-overlay" id="comparison-results-popup">
        <div class="popup-container comparison-results-container">
            <div class="popup-content">
                <div class="popup-header" style="position: relative;">
                    <h2><i class="fas fa-chart-bar"></i> Recipe Comparison</h2>
                    <span class="close-btn" onclick="closePopup('comparison-results-popup')">&times;</span>
                </div>
                <div class="popup-body">
                    <div class="comparison-layout" id="comparison-layout">
                        <!-- Comparison cards will be inserted here -->
                    </div>
                </div>
                <div class="popup-footer">
                    <button class="action-btn" onclick="backToCompare()">
                        <i class="fas fa-arrow-left"></i> Back to Compare
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- AI PURPOSE SELECTION POPUP -->
    <div class="popup-overlay" id="ai-purpose-popup">
        <div class="popup-container ai-purpose-container">
            <div class="popup-content">
                <div class="popup-header">
                    <h2><i class="fas fa-robot"></i> AI Comparison Purpose</h2>
                    <span class="close-btn" onclick="closePopup('ai-purpose-popup')">&times;</span>
                </div>
                
                <div class="popup-body">
                    <div class="purpose-selection">
                        <h4>What would you like me to analyze?</h4>
                        <div class="purpose-options">
                            <label class="purpose-option">
                                <input type="radio" name="purpose" value="nutrition" checked>
                                <div class="purpose-card">
                                    <i class="fas fa-apple-alt"></i>
                                    <h5>Nutritional Analysis</h5>
                                    <p>Compare calories, protein, carbs, and nutritional value</p>
                                </div>
                            </label>
                            
                            <label class="purpose-option">
                                <input type="radio" name="purpose" value="difficulty">
                                <div class="purpose-card">
                                    <i class="fas fa-clock"></i>
                                    <h5>Cooking Difficulty</h5>
                                    <p>Analyze preparation time, skill level, and complexity</p>
                                </div>
                            </label>
                            
                            <label class="purpose-option">
                                <input type="radio" name="purpose" value="ingredients">
                                <div class="purpose-card">
                                    <i class="fas fa-shopping-basket"></i>
                                    <h5>Ingredients Analysis</h5>
                                    <p>Compare ingredient availability, cost, and freshness</p>
                                </div>
                            </label>
                            
                            <label class="purpose-option">
                                <input type="radio" name="purpose" value="occasions">
                                <div class="purpose-card">
                                    <i class="fas fa-calendar-alt"></i>
                                    <h5>Best Occasions</h5>
                                    <p>Suggest when each recipe works best for different events</p>
                                </div>
                            </label>
                            
                            <!-- NEW: DELICIOUSNESS COMPARISON OPTION -->
                            <label class="purpose-option">
                                <input type="radio" name="purpose" value="deliciousness">
                                <div class="purpose-card">
                                    <i class="fas fa-trophy"></i>
                                    <h5>Taste & Deliciousness</h5>
                                    <p>Compare flavor profiles and declare the most delicious winner</p>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="popup-footer">
                    <button class="action-btn" onclick="proceedWithAIComparison()">
                        <i class="fas fa-robot"></i> Generate AI Analysis
                    </button>
                    <button class="exit-btn" onclick="closePopup('ai-purpose-popup')">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- AI COMPARISON RESULTS POPUP -->
    <div class="popup-overlay" id="ai-comparison-results-popup">
        <div class="popup-container ai-comparison-container">
            <div class="popup-content">
                <div class="popup-header">
                    <h2><i class="fas fa-robot"></i> AI Comparison Results</h2>
                    <span class="close-btn" onclick="closePopup('ai-comparison-results-popup')">&times;</span>
                </div>
                
                <div class="popup-body">
                    <div class="ai-comparison-content">
                        <div id="ai-comparison-text" class="ai-comparison-text">
                            <!-- AI analysis will be displayed here -->
                        </div>
                    </div>
                </div>
                
                <div class="popup-footer">
                    <button class="action-btn" onclick="closePopup('ai-comparison-results-popup')">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- QUICK VIEW POPUP FOR COMPARISON -->
    <div class="popup-overlay" id="quick-view-popup">
        <div class="popup-container">
            <div class="popup-content">
                <div class="popup-header">
                    <h2 id="quick-view-title">Recipe Details</h2>
                    <span class="close-btn" onclick="closePopup('quick-view-popup')">&times;</span>
                </div>
                
                <div class="popup-image">
                    <img id="quick-view-img" src="" alt="" style="width: 100%; height: 200px; object-fit: cover;">
                </div>
                
                <div class="popup-body">
                    <div class="popup-details">
                        <p id="quick-view-description">Loading recipe details...</p>
                        
                        <div class="ingredients-list">
                            <h4>Ingredients</h4>
                            <ul id="quick-view-ingredients">
                                <li>Loading ingredients...</li>
                            </ul>
                        </div>
                        
                        <!-- Instructions Section for Quick View -->
                        <div class="instructions-section">
                            <div class="instructions-toggle" onclick="toggleInstructions(this)">
                                <h4>Instructions</h4>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <div class="instructions-content" id="quick-view-instructions">
                                <div class="instructions-text">Loading instructions...</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="popup-footer">
                    <button class="action-btn" onclick="closePopup('quick-view-popup')">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let currentRecipe = null;
        let currentAISuggestion = null;
        let selectedRecipes = [];
        const MAX_COMPARE_ITEMS = 4;
        let openActionsEl = null;
        let currentRecipeId = null;
        let currentAIMixData = null;
        let originalCommentText = ''; // Store original comment for filter popup
        let currentStrikeCount = 0;

        // Comments functionality
        const CURRENT_USER = {
            id: <?php echo (int)$user_id; ?>,
            username: <?php echo json_encode($username); ?>
        };

        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            setupEventListeners();
            setupSearchFunctionality();
            setupCategoryFilter();
            updateStrikeWarning();
            
            // Show all sections by default
            document.querySelectorAll('.section').forEach(section => {
                section.classList.add('active');
            });
        });

        // Setup event listeners
        function setupEventListeners() {
            const saveBtn = document.getElementById('save-btn');
            if (saveBtn) {
                saveBtn.addEventListener('click', saveRecipe);
            }
        }

        // Category filter functionality
        function setupCategoryFilter() {
            const categoryButtons = document.querySelectorAll('.category-btn');
            
            categoryButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Remove active class from all buttons
                    categoryButtons.forEach(btn => btn.classList.remove('active'));
                    // Add active class to clicked button
                    this.classList.add('active');
                    
                    const category = this.getAttribute('data-category');
                    filterSections(category);
                });
            });
        }

        // Filter sections based on category
        function filterSections(category) {
            const sections = document.querySelectorAll('.section');
            
            sections.forEach(section => {
                if (category === 'all') {
                    section.classList.add('active');
                } else {
                    const sectionId = section.getAttribute('id');
                    
                    if (sectionId.includes(category)) {
                        section.classList.add('active');
                    } else {
                        section.classList.remove('active');
                    }
                }
            });
        }

        // Search functionality
        function setupSearchFunctionality() {
            const searchInput = document.getElementById("search-input");
            const resultsDiv = document.getElementById("search-results");
            let searchTimeout;

            searchInput.addEventListener("input", () => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    const q = searchInput.value.trim();
                    resultsDiv.innerHTML = "";
                    if (!q) {
                        resultsDiv.style.display = "none";
                        return;
                    }

                    // Show loading
                    resultsDiv.innerHTML = "<div style='text-align:center;padding:20px;color:#777;'>Searching...</div>";
                    resultsDiv.style.display = "block";

                    fetch("search_recipes.php?q=" + encodeURIComponent(q))
                        .then(res => {
                            if (!res.ok) throw new Error('Search failed');
                            return res.json();
                        })
                        .then(data => {
                            if (!data.length) {
                                resultsDiv.innerHTML = "<div style='text-align:center;color:#777;padding:20px;'>No recipes found.</div>";
                                resultsDiv.style.display = "block";
                                return;
                            }
                            
                            resultsDiv.innerHTML = "";
                            data.forEach(r => {
                                const item = document.createElement("div");
                                item.classList.add("recipe-item");
                                item.innerHTML = `
                                    <div class="recipe-left">
                                        <img src="uploads/${r.image_name}" class="recipe-thumb" alt="${r.recipe_name}">
                                        <div class="recipe-info">
                                            <h4>${r.recipe_name}</h4>
                                            <p>${r.category} • ${r.prep_time + r.cook_time} min • ${r.servings} servings</p>
                                        </div>
                                    </div>
                                    <button class="view-btn" onclick="viewRecipeDetails(${r.id}); document.getElementById('search-results').style.display='none';">View</button>
                                `;
                                resultsDiv.appendChild(item);
                            });
                            resultsDiv.style.display = "block";
                        })
                        .catch(error => {
                            console.error('Search error:', error);
                            resultsDiv.innerHTML = "<div style='color: red; text-align: center; padding: 20px;'>Search temporarily unavailable</div>";
                            resultsDiv.style.display = "block";
                        });
                }, 300);
            });

            // Close search results when clicking outside
            document.addEventListener("click", e => {
                if (!e.target.closest(".search-container")) {
                    resultsDiv.style.display = "none";
                }
            });
        }

        // Recipe details functionality
        function viewRecipeDetails(recipeId) {
            currentRecipeId = recipeId;
            document.getElementById('popup').style.display = 'flex';
            document.getElementById('popup-title').textContent = 'Loading...';
            document.getElementById('popup-description').textContent = 'Fetching recipe details...';
            document.getElementById('popup-ingredients').innerHTML = '<li>Loading ingredients...</li>';
            document.getElementById('popup-instructions').querySelector('.instructions-text').textContent = 'Loading instructions...';
            
            fetch('get_recipe_details.php?id=' + recipeId)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        currentRecipe = data;
                        
                        document.getElementById('popup-title').textContent = data.recipe_name;
                        document.getElementById('popup-img').src = 'uploads/' + data.image_name;
                        document.getElementById('popup-img').alt = data.recipe_name;
                        document.getElementById('popup-description').textContent = 
                            `${data.category} | Prep: ${data.prep_time} mins | Cook: ${data.cook_time} mins | Servings: ${data.servings}`;
                        
                        const ingredientsList = document.getElementById('popup-ingredients');
                        ingredientsList.innerHTML = '';
                        
                        let ingredients = data.ingredients.split('\n');
                        if (ingredients.length === 1) {
                            ingredients = data.ingredients.split(',');
                        }
                        
                        ingredients.forEach(ingredient => {
                            if (ingredient.trim() !== '') {
                                const li = document.createElement('li');
                                li.textContent = ingredient.trim();
                                ingredientsList.appendChild(li);
                            }
                        });
                        
                        const instructionsText = document.getElementById('popup-instructions').querySelector('.instructions-text');
                        if (data.instructions && data.instructions.trim() !== '') {
                            instructionsText.textContent = data.instructions;
                        } else {
                            instructionsText.textContent = 'No instructions available for this recipe.';
                        }
                        
                        loadComments(recipeId);
                        
                    } else {
                        throw new Error(data.message || 'Failed to load recipe details');
                    }
                })
                .catch(error => {
                    console.error('Error fetching recipe details:', error);
                    document.getElementById('popup-title').textContent = 'Error';
                    document.getElementById('popup-description').textContent = error.message || 'An error occurred while fetching recipe details.';
                });
        }

        // Save recipe function - UPDATED to save to save_recipe table
        function saveRecipe() {
            if (!currentRecipe) return;
            
            const saveBtn = document.getElementById('save-btn');
            const originalText = saveBtn.textContent;
            saveBtn.textContent = 'Saving...';
            saveBtn.disabled = true;
            
            // Prepare data for saving to save_recipe table - INCLUDING recipe_id
            const formData = new FormData();
            formData.append('user_id', CURRENT_USER.id);
            formData.append('recipe_id', currentRecipe.id); // ADD THIS LINE
            formData.append('recipe_name', currentRecipe.recipe_name);
            formData.append('category', currentRecipe.category);
            formData.append('prep_time', currentRecipe.prep_time);
            formData.append('cook_time', currentRecipe.cook_time);
            formData.append('servings', currentRecipe.servings);
            formData.append('ingredients', currentRecipe.ingredients);
            formData.append('instructions', currentRecipe.instructions);
            formData.append('image_name', currentRecipe.image_name);
            
            fetch('save_recipe.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Recipe saved successfully!');
                    saveBtn.textContent = 'Saved!';
                } else {
                    throw new Error(data.message || 'Failed to save recipe');
                }
            })
            .catch(error => {
                console.error('Error saving recipe:', error);
                alert(error.message || 'Failed to save recipe');
                saveBtn.textContent = originalText;
            })
            .finally(() => {
                saveBtn.disabled = false;
                setTimeout(() => {
                    if (saveBtn.textContent === 'Saved!') {
                        saveBtn.textContent = originalText;
                    }
                }, 2000);
            });
        }

        // Toggle instructions
        function toggleInstructions(toggleElement) {
            const content = toggleElement.nextElementSibling;
            const icon = toggleElement.querySelector('i');
            
            if (content.style.display === 'block') {
                content.style.display = 'none';
                icon.className = 'fas fa-chevron-down';
            } else {
                content.style.display = 'block';
                icon.className = 'fas fa-chevron-up';
            }
        }

        // AI functionality
        async function callGeminiAPI(prompt) {
            const API_KEY = 'AIzaSyBLgKBfTIRzVFn-aj0riJjIMHubHKepYRs'; // Replace with your actual API key
            const url = `https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=${API_KEY}`;
            
            const requestBody = {
                contents: [{
                    parts: [{
                        text: prompt
                    }]
                }]
            };

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(requestBody)
                });

                if (!response.ok) {
                    throw new Error(`API request failed with status ${response.status}`);
                }

                const data = await response.json();
                return data.candidates[0].content.parts[0].text;
            } catch (error) {
                console.error('Error calling Gemini API:', error);
                throw error;
            }
        }

        async function getDishSuggestions() {
            document.getElementById('ai-popup').style.display = 'flex';
            document.getElementById('ai-suggestions-loading').style.display = 'block';
            document.getElementById('ai-suggestions-results').style.display = 'none';
            
            try {
                const mixCuisines = <?php echo json_encode($mix_n_match_cuisines); ?>;
                const creativity = '<?php echo $ai_creativity; ?>';
                
                let prompt = `Provide 5 customized meal combinations in this exact format based on ${mixCuisines.join(', ')} cuisines:\n\n`;
                prompt += "1. Mix n Match [Main Dish] with [Side Dish 1] and [Side Dish 2]\n";
                prompt += "2. Mix n Match [Main Dish] with [Side Dish] and [Drink]\n";
                prompt += "3. Mix n Match [Main Dish] with [Side Dish 1], [Side Dish 2] and [Drink]\n";
                prompt += "4. Mix n Match [Main Dish] with [Side Dish] and [Dessert]\n";
                prompt += "5. Mix n Match [Main Dish] with [Side Dish 1], [Side Dish 2] and [Dessert]\n\n";
                prompt += "Format strictly as numbered list with 'Mix n Match' prefix.";
                
                const aiResponse = await callGeminiAPI(prompt);
                
                document.getElementById('ai-suggestions-loading').style.display = 'none';
                document.getElementById('ai-suggestions-results').style.display = 'block';
                
                displayAISuggestions(aiResponse);
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('ai-suggestions-loading').innerHTML = 
                    `<p class="error-message">Error: ${error.message}</p>`;
            }
        }

        function displayAISuggestions(aiResponse) {
            const suggestionsList = document.getElementById('ai-suggestions-list');
            suggestionsList.innerHTML = '';
            
            let items = aiResponse.split('\n').filter(item => item.trim() !== '');
            items = items.filter(item => /^\d+\.\s*Mix n Match/.test(item));
            
            items.forEach(item => {
                item = item.replace(/^\d+\.\s*/, '').trim();
                
                if (item) {
                    const div = document.createElement('div');
                    div.className = 'ai-suggestion-item';
                    div.innerHTML = `<div class="dish-name">${item}</div>`;
                    div.addEventListener('click', function() {
                        getRecipeInstructions(item);
                    });
                    suggestionsList.appendChild(div);
                }
            });
        }

        async function getRecipeInstructions(suggestion) {
            currentAISuggestion = suggestion;
            
            document.getElementById('ai-popup').style.display = 'none';
            document.getElementById('ai-mix-popup').style.display = 'flex';
            document.getElementById('ai-mix-title').textContent = suggestion;
            
            const instructionsElement = document.getElementById('ai-mix-instructions');
            instructionsElement.textContent = "Loading detailed instructions...";
            
            try {
                // Extract the main components from the suggestion
                const components = extractComponents(suggestion);
                
                let prompt = `Create a detailed comparison and guide for this Mix n Match combination: ${suggestion}

        Format the response EXACTLY like this structure with bullet points:

        ${components.mainDish} vs ${components.sideDish1}${components.sideDish2 ? ' vs ' + components.sideDish2 : ''}: A Complete Meal Analysis

        ${components.mainDish}

        * Cooking Difficulty: [Easy/Medium/Hard]
        * Preparation Time: [X-Y minutes prep, X-Y minutes cooking]
        * Required Skills: [list key cooking skills needed]
        * Equipment Needed: [list essential kitchen equipment]
        * Key Flavors: [describe main flavor profile]
        * Serving Style: [how it's typically served]
        * Overall Complexity: [Low/Medium/High - brief explanation]

        ${components.sideDish1}

        * Cooking Difficulty: [Easy/Medium/Hard]
        * Preparation Time: [X-Y minutes prep, X-Y minutes cooking]
        * Required Skills: [list key cooking skills needed]
        * Equipment Needed: [list essential kitchen equipment]
        * Key Flavors: [describe main flavor profile]
        * Serving Style: [how it's typically served]
        * Overall Complexity: [Low/Medium/High - brief explanation]

        ${components.sideDish2 ? components.sideDish2 + '\n\n* Cooking Difficulty: [Easy/Medium/Hard]\n* Preparation Time: [X-Y minutes prep, X-Y minutes cooking]\n* Required Skills: [list key cooking skills needed]\n* Equipment Needed: [list essential kitchen equipment]\n* Key Flavors: [describe main flavor profile]\n* Serving Style: [how it\'s typically served]\n* Overall Complexity: [Low/Medium/High - brief explanation]\n\n' : ''}

        Meal Combination Strategy:

        * Flavor Harmony: [how these dishes complement each other]
        * Preparation Timeline: [recommended cooking order and timing]
        * Skill Requirements: [overall skill level needed for the complete meal]
        * Equipment Checklist: [all equipment needed for the full meal]
        * Time Management: [total time and coordination tips]

        Recommendation for Home Cooks:

        * [Clear recommendation on who this combination is best for]
        * [Tips for success and common pitfalls to avoid]
        * [Customization options and variations]

        Please use this exact bullet point format with asterisks and maintain the same structure as shown above.`;

                const aiResponse = await callGeminiAPI(prompt);
                
                // Format the response to match the comparison style
                const formattedResponse = formatMixMatchAsComparison(aiResponse);
                instructionsElement.innerHTML = formattedResponse;
                
                // Store the AI mix data for saving
                currentAIMixData = {
                    suggestion: suggestion,
                    instructions: aiResponse,
                    formattedInstructions: formattedResponse
                };
                
            } catch (error) {
                console.error('Error:', error);
                instructionsElement.innerHTML = `<div class="error-text">Error: Could not load instructions. ${error.message}</div>`;
            }
        }

        // Helper function to extract components from Mix n Match suggestion
        function extractComponents(suggestion) {
            const cleanSuggestion = suggestion.replace(/^Mix n Match\s*/i, '');
            const parts = cleanSuggestion.split(/\s+with\s+|\s+and\s+/i);
            
            return {
                mainDish: parts[0]?.trim() || '',
                sideDish1: parts[1]?.trim() || '',
                sideDish2: parts[2]?.trim() || ''
            };
        }

        // Format the Mix n Match instructions to look like the comparison analysis
        function formatMixMatchAsComparison(response) {
            // First, clean up the response and ensure proper line breaks
            let cleanedResponse = response
                .replace(/\*\*/g, '') // Remove bold markers
                .replace(/\*/g, '•') // Convert asterisks to bullet points
                .trim();

            // Split into lines and process each section
            const lines = cleanedResponse.split('\n');
            let html = '';
            let currentSection = '';
            let inList = false;

            lines.forEach(line => {
                line = line.trim();
                if (!line) return;

                // Check if this is a section header (ends with colon or is a dish name)
                if (line.includes(': A Complete Meal Analysis') || 
                    (line.includes(':') && !line.startsWith('•')) ||
                    (/^[A-Z][a-zA-Z\s]+$/.test(line) && line.length < 50)) {
                    
                    // Close previous list if open
                    if (inList) {
                        html += '</ul>';
                        inList = false;
                    }

                    // Add section header
                    if (line.includes(': A Complete Meal Analysis')) {
                        html += `<h3 style="color: #4ECDC4; margin-bottom: 20px; text-align: center; font-weight: 700;">${line}</h3>`;
                    } else {
                        html += `<h4 style="color: #4ECDC4; margin: 20px 0 12px 0; font-weight: 600; padding-bottom: 6px; border-bottom: 2px solid rgba(108, 78, 49, 0.1);">${line}</h4>`;
                    }
                    currentSection = line;
                }
                // Check if this is a bullet point
                else if (line.startsWith('•')) {
                    if (!inList) {
                        html += '<ul style="margin: 12px 0 20px 0; padding-left: 15px; list-style: none;">';
                        inList = true;
                    }
                    
                    const listItem = line.substring(1).trim(); // Remove the bullet
                    html += `<li style="margin: 8px 0; padding-left: 0; color: #555; position: relative;">
                        <span style="color: var(--primary-color); font-weight: bold; margin-right: 6px;">•</span>
                        ${listItem}
                    </li>`;
                }
                // Regular text line
                else {
                    // Close list if open
                    if (inList) {
                        html += '</ul>';
                        inList = false;
                    }
                    
                    // Add as paragraph if it's substantial text
                    if (line.length > 10) {
                        html += `<p style="margin: 8px 0; color: #555; line-height: 1.6;">${line}</p>`;
                    }
                }
            });

            // Close any open list
            if (inList) {
                html += '</ul>';
            }

            return html;
        }

        // Save AI Mix Match to bookmarks
        function saveAIMixMatch() {
            if (!currentAIMixData) {
                alert('No AI mix match data to save.');
                return;
            }
            
            const saveBtn = document.getElementById('save-ai-mix-btn');
            const originalText = saveBtn.textContent;
            saveBtn.textContent = 'Saving...';
            saveBtn.disabled = true;
            
            // Prepare data for saving to chat_history table (bookmarks)
            const formData = new FormData();
            formData.append('user_id', CURRENT_USER.id);
            formData.append('message', currentAIMixData.suggestion);
            formData.append('response', currentAIMixData.instructions);
            formData.append('is_bookmarked', '1');
            
            console.log('Saving AI mix match:', {
                user_id: CURRENT_USER.id,
                message: currentAIMixData.suggestion,
                response_length: currentAIMixData.instructions.length
            });
            
            fetch('save_ai_mix_bookmark.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // First check if response is JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    // If not JSON, get the text to see what's wrong
                    return response.text().then(text => {
                        console.error('Non-JSON response:', text);
                        throw new Error('Server returned non-JSON response. Check if save_ai_mix_bookmark.php exists and has no PHP errors.');
                    });
                }
                return response.json();
            })
            .then(data => {
                console.log('Save response:', data);
                if (data.success) {
                    alert(data.message || 'AI Mix Match saved to bookmarks successfully!');
                    saveBtn.textContent = 'Saved!';
                } else {
                    throw new Error(data.message || 'Failed to save to bookmarks');
                }
            })
            .catch(error => {
                console.error('Error saving AI mix match:', error);
                alert('Error saving to bookmarks: ' + error.message);
                saveBtn.textContent = originalText;
            })
            .finally(() => {
                saveBtn.disabled = false;
                setTimeout(() => {
                    if (saveBtn.textContent === 'Saved!') {
                        saveBtn.textContent = originalText;
                    }
                }, 2000);
            });
        }

        // ==========================
        // COMPARISON FUNCTIONALITY - FIXED
        // ==========================

        function openComparePopup() {
            document.getElementById('compare-popup').style.display = 'flex';
            updateCompareButtons();
            setupCompareSearch(); // Initialize search when popup opens
        }

        function setupCompareSearch() {
            const compareSearchInput = document.getElementById('compare-search-input');
            const clearCompareSearchBtn = document.getElementById('clear-compare-search');
            const compareCategoryBtns = document.querySelectorAll('.compare-category-btn');
            const recipeCompareItems = document.querySelectorAll('.recipe-compare-item');

            // Search functionality
            compareSearchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();
                filterCompareRecipes(searchTerm);
            });

            // Clear search
            clearCompareSearchBtn.addEventListener('click', function() {
                compareSearchInput.value = '';
                clearCompareSearchBtn.style.display = 'none';
                filterCompareRecipes('');
            });

            // Category filtering
            compareCategoryBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    compareCategoryBtns.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    
                    const category = this.getAttribute('data-category');
                    filterCompareRecipes(compareSearchInput.value.toLowerCase().trim(), category);
                });
            });

            function filterCompareRecipes(searchTerm = '', category = 'all') {
                recipeCompareItems.forEach(item => {
                    const recipeName = item.getAttribute('data-name');
                    const recipeCategory = item.getAttribute('data-category');
                    const matchesSearch = !searchTerm || recipeName.includes(searchTerm);
                    const matchesCategory = category === 'all' || 
                                          (category === 'main' && recipeCategory.includes('main')) ||
                                          (category === 'side' && recipeCategory.includes('side')) ||
                                          (category === 'beverage' && (recipeCategory.includes('beverage') || recipeCategory.includes('drink'))) ||
                                          (category === 'dessert' && recipeCategory.includes('dessert'));
                    
                    if (matchesSearch && matchesCategory) {
                        item.style.display = 'flex';
                    } else {
                        item.style.display = 'none';
                    }
                });
                
                clearCompareSearchBtn.style.display = searchTerm ? 'flex' : 'none';
            }
        }

        function addToCompare(recipeId, recipeName, imageName, category, totalTime, servings) {
            if (selectedRecipes.length >= MAX_COMPARE_ITEMS) {
                alert(`Maximum ${MAX_COMPARE_ITEMS} recipes can be compared at once.`);
                return;
            }
            
            if (selectedRecipes.find(recipe => recipe.id === recipeId)) {
                alert('This recipe is already selected for comparison.');
                return;
            }
            
            selectedRecipes.push({
                id: recipeId,
                name: recipeName,
                image: imageName,
                category: category,
                totalTime: totalTime,
                servings: servings
            });
            
            updateSelectedRecipesDisplay();
            updateCompareButtons();
        }

        function removeFromCompare(recipeId) {
            selectedRecipes = selectedRecipes.filter(recipe => recipe.id !== recipeId);
            updateSelectedRecipesDisplay();
            updateCompareButtons();
        }

        function updateSelectedRecipesDisplay() {
            const container = document.getElementById('selected-recipes-container');
            
            if (selectedRecipes.length === 0) {
                container.innerHTML = `
                    <div class="no-selection-message">
                        <i class="fas fa-plus-circle"></i>
                        <p>Add recipes to compare by clicking "Add" button</p>
                    </div>
                `;
                return;
            }
            
            container.innerHTML = selectedRecipes.map(recipe => `
                <div class="selected-recipe-item">
                    <img src="uploads/${recipe.image}" alt="${recipe.name}" class="selected-recipe-image">
                    <div class="selected-recipe-info">
                        <h5>${recipe.name}</h5>
                        <p>${recipe.category} • ${recipe.totalTime} min • ${recipe.servings} servings</p>
                    </div>
                    <button class="remove-compare-btn" onclick="removeFromCompare(${recipe.id})">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `).join('');
        }

        function updateCompareButtons() {
            const startBtn = document.getElementById('start-comparison-btn');
            const aiBtn = document.getElementById('ai-comparison-btn');
            
            if (selectedRecipes.length >= 2) {
                startBtn.disabled = false;
                aiBtn.disabled = false;
            } else {
                startBtn.disabled = true;
                aiBtn.disabled = true;
            }
        }

        function startComparison() {
            if (selectedRecipes.length < 2) {
                alert('Please select at least 2 recipes to compare.');
                return;
            }
            
            displayComparisonResults();
            closePopup('compare-popup');
        }

        function displayComparisonResults() {
            const container = document.getElementById('comparison-layout');
            const popup = document.getElementById('comparison-results-popup');
            
            container.innerHTML = selectedRecipes.map(recipe => `
                <div class="comparison-recipe-card">
                    <div class="comparison-recipe-header">
                        <img src="uploads/${recipe.image}" alt="${recipe.name}" class="comparison-recipe-image">
                        <div class="comparison-recipe-title">
                            <h3>${recipe.name}</h3>
                            <p>${recipe.category}</p>
                        </div>
                    </div>
                    
                    <div class="comparison-basic-info">
                        <div class="info-item">
                            <span class="info-label">Total Time</span>
                            <span class="info-value">${recipe.totalTime} min</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Servings</span>
                            <span class="info-value">${recipe.servings}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Category</span>
                            <span class="info-value">${recipe.category}</span>
                        </div>
                    </div>
                    
                    <div class="comparison-details-section">
                        <div class="details-toggle" onclick="toggleDetails(this, ${recipe.id})">
                            <h4>Ingredients</h4>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="details-content" id="ingredients-${recipe.id}">
                            <div class="loading-details">Loading ingredients...</div>
                        </div>
                        
                        <div class="details-toggle" onclick="toggleDetails(this, ${recipe.id}, 'instructions')">
                            <h4>Instructions</h4>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="details-content" id="instructions-${recipe.id}">
                            <div class="loading-details">Loading instructions...</div>
                        </div>
                    </div>
                </div>
            `).join('');
            
            popup.style.display = 'flex';
            
            // Load detailed information for each recipe
            selectedRecipes.forEach(recipe => {
                loadRecipeDetailsForComparison(recipe.id);
            });
        }

        async function loadRecipeDetailsForComparison(recipeId) {
            try {
                const response = await fetch('get_recipe_details.php?id=' + recipeId);
                const data = await response.json();
                
                if (data.success) {
                    const ingredientsContainer = document.getElementById(`ingredients-${recipeId}`);
                    if (ingredientsContainer) {
                        let ingredients = data.ingredients.split('\n');
                        if (ingredients.length === 1) {
                            ingredients = data.ingredients.split(',');
                        }
                        
                        ingredientsContainer.innerHTML = `
                            <ul class="ingredients-list-compare">
                                ${ingredients.map(ingredient => 
                                    ingredient.trim() ? `<li>${ingredient.trim()}</li>` : ''
                                ).join('')}
                            </ul>
                        `;
                    }
                    
                    const instructionsContainer = document.getElementById(`instructions-${recipeId}`);
                    if (instructionsContainer && data.instructions) {
                        instructionsContainer.innerHTML = `
                            <div class="instructions-text-compare">${data.instructions}</div>
                        `;
                    } else if (instructionsContainer) {
                        instructionsContainer.innerHTML = '<div class="error-text">No instructions available</div>';
                    }
                }
            } catch (error) {
                console.error('Error loading recipe details:', error);
                const ingredientsContainer = document.getElementById(`ingredients-${recipeId}`);
                const instructionsContainer = document.getElementById(`instructions-${recipeId}`);
                
                if (ingredientsContainer) {
                    ingredientsContainer.innerHTML = '<div class="error-text">Failed to load ingredients</div>';
                }
                if (instructionsContainer) {
                    instructionsContainer.innerHTML = '<div class="error-text">Failed to load instructions</div>';
                }
            }
        }

        function toggleDetails(toggleElement, recipeId, type = 'ingredients') {
            const contentId = type === 'instructions' ? `instructions-${recipeId}` : `ingredients-${recipeId}`;
            const content = document.getElementById(contentId);
            const icon = toggleElement.querySelector('i');
            
            if (content.style.display === 'block') {
                content.style.display = 'none';
                icon.className = 'fas fa-chevron-down';
            } else {
                content.style.display = 'block';
                icon.className = 'fas fa-chevron-up';
            }
        }

        function getAIComparison() {
            if (selectedRecipes.length < 2) {
                alert('Please select at least 2 recipes to compare.');
                return;
            }
            
            document.getElementById('ai-purpose-popup').style.display = 'flex';
        }

        function proceedWithAIComparison() {
            const purpose = document.querySelector('input[name="purpose"]:checked').value;
            generateAIComparison(purpose);
            closePopup('ai-purpose-popup');
        }

        // ENHANCED AI COMPARISON FUNCTION - ADDED DELICIOUSNESS OPTION
        async function generateAIComparison(purpose) {
            const popup = document.getElementById('ai-comparison-results-popup');
            const content = document.getElementById('ai-comparison-text');
            
            popup.style.display = 'flex';
            content.innerHTML = '<div class="loading-details">AI is analyzing the recipes...</div>';
            
            try {
                const recipeNames = selectedRecipes.map(recipe => recipe.name).join(', ');
                const recipeIds = selectedRecipes.map(recipe => recipe.id);
                
                // Get detailed recipe information for better comparison
                const recipeDetails = await Promise.all(
                    recipeIds.map(id => fetch('get_recipe_details.php?id=' + id).then(r => r.json()))
                );
                
                let prompt = '';
                
                switch(purpose) {
                    case 'nutrition':
                        prompt = `Compare these recipes nutritionally: ${recipeNames}. `;
                        prompt += "Provide a nutritional comparison including estimated calories, protein, carbs, fat content, and overall health benefits. Highlight which is healthier and why. ";
                        prompt += "At the end, declare a clear WINNER as '🏆 NUTRITION WINNER: [Recipe Name]' with a brief explanation why it's the healthiest option.";
                        break;
                    case 'difficulty':
                        prompt = `Compare cooking difficulty for: ${recipeNames}. `;
                        prompt += "Analyze preparation time, required skills, equipment needed, and overall complexity. Recommend which is easier for beginners. ";
                        prompt += "At the end, declare a clear WINNER as '🏆 EASE-OF-COOKING WINNER: [Recipe Name]' with a brief explanation why it's the easiest to prepare.";
                        break;
                    case 'ingredients':
                        prompt = `Analyze ingredients for: ${recipeNames}. `;
                        prompt += "Compare ingredient availability, cost, freshness requirements, and substitution possibilities. Compare overall ingredient complexity and cost-effectiveness. ";
                        prompt += "At the end, declare a clear WINNER as '🏆 INGREDIENT WINNER: [Recipe Name]' with a brief explanation why it has the best ingredient profile.";
                        break;
                    case 'occasions':
                        prompt = `Compare best occasions for: ${recipeNames}. `;
                        prompt += "Suggest best occasions for each recipe (family dinner, parties, quick meals, special events). Compare suitability for different events and audiences. ";
                        prompt += "At the end, declare a clear WINNER as '🏆 VERSATILITY WINNER: [Recipe Name]' with a brief explanation why it's the most versatile for different occasions.";
                        break;
                    case 'deliciousness':
                        prompt = `Compare taste and deliciousness for these recipes: ${recipeNames}. `;
                        prompt += `Based on the following recipe details:\n\n`;
                        
                        // Add recipe details to the prompt for better analysis
                        recipeDetails.forEach((detail, index) => {
                            if (detail.success) {
                                prompt += `Recipe ${index + 1}: ${detail.recipe_name}\n`;
                                prompt += `Ingredients: ${detail.ingredients}\n`;
                                prompt += `Instructions: ${detail.instructions}\n\n`;
                            }
                        });
                        
                        prompt += `Please analyze and compare these recipes based on:
        1. Flavor profile complexity and balance
        2. Ingredient quality and freshness impact
        3. Overall taste appeal and deliciousness
        4. Cultural authenticity (Filipino cuisine)
        5. Crowd-pleasing potential
        6. Visual appeal and presentation

        At the end, declare a clear WINNER as "🏆 RECIPE WINNER: [Recipe Name]" with a brief explanation why it's more delicious. Also provide a runner-up analysis.`;
                        break;
                }
                
                prompt += " Format the response with clear headings and bullet points. Be concise but informative.";
                
                const aiResponse = await callGeminiAPI(prompt);
                content.innerHTML = `<div class="ai-comparison-text">${formatAIResponse(aiResponse, purpose)}</div>`;
                
            } catch (error) {
                content.innerHTML = `<div class="error-text">Error generating AI analysis: ${error.message}</div>`;
            }
        }

        // ENHANCED FORMAT AI RESPONSE WITH WINNER BADGES
        function formatAIResponse(text, purpose) {
            let formattedText = text
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/\*(.*?)\*/g, '<em>$1</em>')
                .replace(/\n\s*•\s*/g, '\n• ')
                .replace(/\n• (.*?)(?=\n|$)/g, '<li>$1</li>')
                .replace(/(<li>.*<\/li>)/s, '<ul>$1</ul>')
                .replace(/\n### (.*?)\n/g, '<h4>$1</h4>')
                .replace(/\n## (.*?)\n/g, '<h4>$1</h4>')
                .replace(/\n# (.*?)\n/g, '<h4>$1</h4>')
                .replace(/\n/g, '<br>');

            // Special formatting for ALL comparison types to show winners
            const winnerPatterns = {
                nutrition: /🏆 NUTRITION WINNER: (.*?)(?=<br>|$)/gi,
                difficulty: /🏆 EASE-OF-COOKING WINNER: (.*?)(?=<br>|$)/gi,
                ingredients: /🏆 INGREDIENT WINNER: (.*?)(?=<br>|$)/gi,
                occasions: /🏆 VERSATILITY WINNER: (.*?)(?=<br>|$)/gi,
                deliciousness: /🏆 RECIPE WINNER: (.*?)(?=<br>|$)/gi
            };

            const winnerTitles = {
                nutrition: 'Nutrition Winner',
                difficulty: 'Ease-of-Cooking Winner',
                ingredients: 'Ingredient Winner',
                occasions: 'Versatility Winner',
                deliciousness: 'Overall Winner'
            };

            // Add winner badge styling for all purposes
            let winnerFound = false;
            let winnerContent = '';

            Object.keys(winnerPatterns).forEach(purposeType => {
                const matches = formattedText.match(winnerPatterns[purposeType]);
                if (matches && matches.length > 0) {
                    winnerFound = true;
                    winnerContent = matches[0];
                    
                    // Replace with styled badge
                    formattedText = formattedText.replace(
                        winnerPatterns[purposeType], 
                        '<div class="winner-badge"><i class="fas fa-trophy"></i> $&</div>'
                    );
                }
            });

            // Add winner section for ALL purposes if winner is found
            if (winnerFound) {
                const winnerSection = `
                    <div class="comparison-winner-section">
                        <h3><i class="fas fa-crown"></i> ${winnerTitles[purpose]}</h3>
                        <div class="winner-recipe">
                            ${winnerContent.replace(/🏆\s*/, '').replace('WINNER:', '<strong>WINNER:</strong>')}
                        </div>
                    </div>
                `;
                formattedText = winnerSection + formattedText;
            }

            return formattedText;
        }

        function backToCompare() {
            closePopup('comparison-results-popup');
            openComparePopup();
        }

        // Quick view for comparison
        function viewCompareRecipeDetails(recipeId) {
            viewRecipeDetails(recipeId);
        }

        // ==========================
        // ENHANCED COMMENT SYSTEM WITH AI FILTERING - ADDED
        // ==========================

        function loadComments(recipeId) {
            currentRecipeId = recipeId;
            const list = document.getElementById('comments-list');
            const count = document.getElementById('comments-count');
            if (!list || !count) return;

            list.innerHTML = '<div class="text-muted">Loading comments…</div>';
            count.textContent = '';

            // FIXED: Using recipe_comments table name from your add_comment.php
            fetch(`get_comments.php?recipe_id=${recipeId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderComments(data.comments);
                    } else {
                        list.innerHTML = `<div class="error-message">${data.message}</div>`;
                    }
                })
                .catch(error => {
                    console.error('Error loading comments:', error);
                    list.innerHTML = '<div class="error-message">Error loading comments</div>';
                });
        }

        function renderComments(comments) {
            const list = document.getElementById('comments-list');
            const count = document.getElementById('comments-count');
            list.innerHTML = '';

            if (!comments || comments.length === 0) {
                list.innerHTML = '<div class="text-muted" style="text-align:center;padding:20px;">No comments yet. Be the first to share feedback!</div>';
                count.textContent = '(0)';
                return;
            }
            count.textContent = `(${comments.length})`;

            comments.forEach(c => {
                const div = document.createElement('div');
                div.className = 'comment-item';
                div.dataset.commentId = c.id;
                div.dataset.userId = c.user_id;

                const dt = new Date(c.created_at);
                const dateStr = dt.toLocaleDateString() + ', ' + dt.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

                const isOwnComment = c.user_id === CURRENT_USER.id;

                div.innerHTML = `
                    <div class="comment-meta">
                        <div class="comment-user-info">
                            <span>${c.username}</span>
                            <span class="comment-date">${dateStr}</span>
                        </div>
                        ${isOwnComment ? `
                            <button class="comment-actions-btn" onclick="toggleCommentActions(this)">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                        ` : ''}
                    </div>
                    <div class="comment-text">${c.comment}</div>
                    ${isOwnComment ? `
                        <div class="comment-actions">
                            <button type="button" class="edit-btn" onclick="startEditComment(${c.id}, this)">Edit</button>
                            <button type="button" class="del-btn" onclick="deleteComment(${c.id}, this)">Delete</button>
                        </div>
                        <div class="comment-edit-form">
                            <textarea class="comment-edit-input">${c.comment}</textarea>
                            <div class="comment-edit-actions">
                                <button type="button" class="comment-save-btn" onclick="saveCommentEdit(${c.id}, this)">Save</button>
                                <button type="button" class="comment-cancel-btn" onclick="cancelCommentEdit(${c.id}, this)">Cancel</button>
                            </div>
                        </div>
                    ` : ''}
                `;

                list.appendChild(div);
            });
        }

        function toggleCommentActions(button) {
            const commentItem = button.closest('.comment-item');
            const actions = commentItem.querySelector('.comment-actions');
            
            document.querySelectorAll('.comment-actions').forEach(action => {
                if (action !== actions) {
                    action.style.display = 'none';
                }
            });
            
            if (actions.style.display === 'block') {
                actions.style.display = 'none';
            } else {
                actions.style.display = 'block';
                
                // Position actions to avoid overflow on mobile
                const rect = actions.getBoundingClientRect();
                const viewportWidth = window.innerWidth;
                
                if (rect.right > viewportWidth - 10) {
                    actions.style.right = '10px';
                    actions.style.left = 'auto';
                }
            }
        }

        // Close actions when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.comment-actions') && !e.target.closest('.comment-actions-btn')) {
                document.querySelectorAll('.comment-actions').forEach(action => {
                    action.style.display = 'none';
                });
            }
        });

        function startEditComment(commentId, button) {
            const commentItem = button.closest('.comment-item');
            const commentText = commentItem.querySelector('.comment-text');
            const editForm = commentItem.querySelector('.comment-edit-form');
            const editInput = commentItem.querySelector('.comment-edit-input');
            
            commentItem.querySelector('.comment-actions').style.display = 'none';
            
            commentText.style.display = 'none';
            editForm.style.display = 'block';
            editInput.value = commentText.textContent;
            editInput.focus();
            
            // Store original text for cancel
            editInput.dataset.originalText = commentText.textContent;
        }

        function cancelCommentEdit(commentId, button) {
            const commentItem = button.closest('.comment-item');
            const commentText = commentItem.querySelector('.comment-text');
            const editForm = commentItem.querySelector('.comment-edit-form');
            const editInput = commentItem.querySelector('.comment-edit-input');
            
            // Restore original text
            commentText.textContent = editInput.dataset.originalText;
            commentText.style.display = 'block';
            editForm.style.display = 'none';
        }

        function saveCommentEdit(commentId, button) {
            const commentItem = button.closest('.comment-item');
            const commentText = commentItem.querySelector('.comment-text');
            const editForm = commentItem.querySelector('.comment-edit-form');
            const editInput = commentItem.querySelector('.comment-edit-input');
            
            const newText = editInput.value.trim();
            if (newText === '') {
                alert('Comment cannot be empty');
                return;
            }
            
            const saveBtn = button;
            const originalText = saveBtn.textContent;
            saveBtn.textContent = 'Saving...';
            saveBtn.disabled = true;
            
            const formData = new FormData();
            formData.append('comment_id', commentId);
            formData.append('comment', newText);
            
            fetch('update_comment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    commentText.textContent = newText;
                    commentText.style.display = 'block';
                    editForm.style.display = 'none';
                } else {
                    alert('Failed to update comment: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error updating comment:', error);
                alert('Error updating comment: ' + error.message);
            })
            .finally(() => {
                saveBtn.textContent = originalText;
                saveBtn.disabled = false;
            });
        }

        function deleteComment(commentId, button) {
            if (!confirm('Are you sure you want to delete this comment?')) {
                return;
            }
            
            const commentItem = button.closest('.comment-item');
            
            commentItem.querySelector('.comment-actions').style.display = 'none';
            
            const originalText = button.textContent;
            button.textContent = 'Deleting...';
            button.disabled = true;
            
            const formData = new FormData();
            formData.append('comment_id', commentId);
            
            fetch('delete_comment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    commentItem.remove();
                    updateCommentsCount();
                } else {
                    alert('Failed to delete comment: ' + (data.message || 'Unknown error'));
                    button.textContent = originalText;
                    button.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error deleting comment:', error);
                alert('Error deleting comment: ' + error.message);
                button.textContent = originalText;
                button.disabled = false;
            });
        }

        function updateCommentsCount() {
            const list = document.getElementById('comments-list');
            const count = document.getElementById('comments-count');
            const commentItems = list.querySelectorAll('.comment-item');
            const visibleComments = Array.from(commentItems).filter(item => item.style.display !== 'none');
            count.textContent = `(${visibleComments.length})`;
        }

        function submitComment(e) {
            e.preventDefault();
            if (!currentRecipeId) {
                alert('No recipe selected');
                return false;
            }

            const ta = document.getElementById('comment-input');
            const text = (ta.value || '').trim();
            if (text === '') {
                alert('Please enter a comment');
                return false;
            }

            const btn = e.target.querySelector('button[type="submit"]');
            btn.disabled = true; 
            btn.textContent = 'Checking...';

            const formData = new FormData();
            formData.append('recipe_id', currentRecipeId);
            formData.append('comment', text);

            fetch('add_comment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    addCommentToUI(data.comment);
                    ta.value = '';
                    // Reset strike count on successful comment
                    currentStrikeCount = 0;
                    updateStrikeWarning();
                    showSuccessMessage('Comment posted successfully!');
                } else if (data.message === 'inappropriate_content') {
                    currentStrikeCount = data.strike_count || 1;
                    showFilterPopup(data.filter_data, text, currentStrikeCount, data.strikes_remaining);
                } else if (data.message === 'banned') {
                    showBanPopup(data.ban_data);
                } else {
                    alert('Failed to post comment: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error posting comment:', error);
                alert('Error posting comment: ' + error.message);
            })
            .finally(() => {
                btn.disabled = false; 
                btn.textContent = 'Post';
            });

            return false;
        }

        function showFilterPopup(filterData, originalComment, strikeCount, strikesRemaining) {
            originalCommentText = originalComment;
            
            const badWordsList = document.getElementById('bad-words-list');
            badWordsList.innerHTML = '';
            
            if (filterData.inappropriate_words && filterData.inappropriate_words.length > 0) {
                filterData.inappropriate_words.forEach(word => {
                    const span = document.createElement('span');
                    span.className = 'bad-word';
                    span.textContent = word;
                    badWordsList.appendChild(span);
                });
            } else {
                badWordsList.innerHTML = '<span class="bad-word">Inappropriate content detected</span>';
            }
            
            document.getElementById('filter-reason-text').textContent = filterData.reason || 'Content violates community guidelines';
            
            const suggestedTextarea = document.getElementById('suggested-comment');
            if (filterData.suggested_replacement && filterData.suggested_replacement !== 'null') {
                suggestedTextarea.value = filterData.suggested_replacement;
                document.querySelector('.suggestion-box').style.display = 'block';
            } else {
                suggestedTextarea.value = 'No suggestion available. Please edit your comment manually.';
                document.querySelector('.suggestion-box').style.display = 'block';
            }
            
            // Update strike warning
            const warningText = document.querySelector('.filter-warning-text');
            if (strikeCount >= 2) {
                warningText.innerHTML = `<i class="fas fa-exclamation-triangle"></i> Warning: You have ${strikeCount} strikes. ${strikesRemaining} more will result in a temporary ban.`;
                warningText.style.color = '#e74c3c';
                warningText.style.fontWeight = 'bold';
            } else {
                warningText.innerHTML = `<i class="fas fa-exclamation-triangle"></i> Your comment contains inappropriate content`;
                warningText.style.color = '#d63031';
            }
            
            document.getElementById('comment-filter-popup').style.display = 'flex';
            updateStrikeWarning();
        }

        function showBanPopup(banData) {
            const banPopup = document.createElement('div');
            banPopup.className = 'filter-popup-overlay';
            banPopup.style.display = 'flex';
            banPopup.style.zIndex = '4000';
            
            const hours = banData.hours;
            const minutes = banData.minutes;
            let timeText = '';
            
            if (hours > 0 && minutes > 0) {
                timeText = `${hours} hours and ${minutes} minutes`;
            } else if (hours > 0) {
                timeText = `${hours} hours`;
            } else {
                timeText = `${minutes} minutes`;
            }
            
            banPopup.innerHTML = `
                <div class="filter-popup-container">
                    <div class="filter-popup-header" style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);">
                        <h3><i class="fas fa-ban"></i> Temporary Ban Issued</h3>
                    </div>
                    <div class="filter-popup-body">
                        <div class="filter-warning-text" style="text-align: center; font-size: 15px; color: white; background: #e74c3c; padding: 12px; border-radius: 10px;">
                            <i class="fas fa-gavel"></i> You have been temporarily banned from commenting
                        </div>
                        <div style="text-align: center; padding: 15px; color: #555;">
                            <div style="font-size: 14px; margin-bottom: 12px;">
                                <strong>Ban Duration:</strong> ${timeText}
                            </div>
                            <div style="background: #f8f9fa; padding: 12px; border-radius: 8px; border-left: 4px solid #e74c3c;">
                                <strong>Reason:</strong> ${banData.reason || 'Multiple inappropriate comments detected'}
                            </div>
                            <p style="margin-top: 12px; font-size: 12px; color: #777;">
                                <i class="fas fa-info-circle"></i> This ban will automatically expire after the specified time.
                            </p>
                        </div>
                    </div>
                    <div class="filter-popup-footer">
                        <button class="filter-cancel-btn" onclick="this.closest('.filter-popup-overlay').remove()">
                            <i class="fas fa-times"></i> Understand
                        </button>
                    </div>
                </div>
            `;
            document.body.appendChild(banPopup);
            
            // Clear the comment input
            document.getElementById('comment-input').value = '';
        }

        function updateStrikeWarning() {
            const strikeWarning = document.getElementById('strike-warning');
            const currentStrikes = document.getElementById('current-strikes');
            const strikeCounter = document.getElementById('strike-counter');
            
            if (currentStrikeCount > 0) {
                strikeWarning.classList.add('show');
                currentStrikes.textContent = currentStrikeCount;
                strikeCounter.textContent = `${currentStrikeCount}/3 strikes`;
                strikeCounter.style.display = 'inline-block';
                
                // Change color based on strike count
                if (currentStrikeCount >= 2) {
                    strikeWarning.style.background = '#f8d7da';
                    strikeWarning.style.borderColor = '#f5c6cb';
                    strikeWarning.style.color = '#721c24';
                } else {
                    strikeWarning.style.background = '#fff3cd';
                    strikeWarning.style.borderColor = '#ffeaa7';
                    strikeWarning.style.color = '#856404';
                }
            } else {
                strikeWarning.classList.remove('show');
                strikeCounter.style.display = 'none';
            }
        }

        function useSuggestion() {
            const suggestedText = document.getElementById('suggested-comment').value;
            document.getElementById('comment-input').value = suggestedText;
            closeFilterPopup();
            document.getElementById('comment-input').focus();
        }

        function editComment() {
            document.getElementById('comment-input').value = originalCommentText;
            closeFilterPopup();
            document.getElementById('comment-input').focus();
            
            // Scroll to comment input
            document.getElementById('comment-input').scrollIntoView({ 
                behavior: 'smooth', 
                block: 'center' 
            });
        }

        function cancelComment() {
            document.getElementById('comment-input').value = '';
            closeFilterPopup();
        }

        function closeFilterPopup() {
            document.getElementById('comment-filter-popup').style.display = 'none';
            originalCommentText = '';
        }

        function showSuccessMessage(message) {
            const successMsg = document.createElement('div');
            successMsg.className = 'success-message';
            successMsg.innerHTML = `
                <div style="display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-check-circle"></i> ${message}
                </div>
            `;
            
            const commentForm = document.getElementById('comment-form');
            commentForm.parentNode.insertBefore(successMsg, commentForm);
            
            setTimeout(() => {
                successMsg.remove();
            }, 3000);
        }

        function addCommentToUI(commentData) {
            const list = document.getElementById('comments-list');
            const count = document.getElementById('comments-count');

            const div = document.createElement('div');
            div.className = 'comment-item';
            div.dataset.commentId = commentData.id;
            div.dataset.userId = commentData.user_id;

            const dt = new Date(commentData.created_at);
            const dateStr = dt.toLocaleDateString() + ', ' + dt.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

            div.innerHTML = `
                <div class="comment-meta">
                    <div class="comment-user-info">
                        <span>${commentData.username}</span>
                        <span class="comment-date">${dateStr}</span>
                    </div>
                    <button class="comment-actions-btn" onclick="toggleCommentActions(this)">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                </div>
                <div class="comment-text">${commentData.comment}</div>
                <div class="comment-actions">
                    <button type="button" class="edit-btn" onclick="startEditComment(${commentData.id}, this)">Edit</button>
                    <button type="button" class="del-btn" onclick="deleteComment(${commentData.id}, this)">Delete</button>
                </div>
                <div class="comment-edit-form">
                    <textarea class="comment-edit-input">${commentData.comment}</textarea>
                    <div class="comment-edit-actions">
                        <button type="button" class="comment-save-btn" onclick="saveCommentEdit(${commentData.id}, this)">Save</button>
                        <button type="button" class="comment-cancel-btn" onclick="cancelCommentEdit(${commentData.id}, this)">Cancel</button>
                    </div>
                </div>
            `;

            if (list.firstChild && !list.firstChild.classList.contains('text-muted')) {
                list.insertBefore(div, list.firstChild);
            } else {
                list.innerHTML = ''; 
                list.appendChild(div);
            }

            const m = count.textContent.match(/\d+/);
            const n = m ? parseInt(m[0], 10) + 1 : 1;
            count.textContent = `(${n})`;
        }

        // Utility functions
        function closePopup(popupId) {
            document.getElementById(popupId).style.display = 'none';
            if (popupId === 'popup') {
                document.getElementById('comment-form').style.display = 'flex';
                document.getElementById('comment-input').value = '';
            }
        }

        window.addEventListener("pageshow", function (event) {
            if (event.persisted) {
                window.location.reload();
            }
        });
    </script>
</body>
</html>