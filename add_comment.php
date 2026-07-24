<?php
session_start();
header('Content-Type: application/json');
require_once 'db_connect.php';

// AI Comment Filter Function
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
        
        preg_match('/\{[^}]+\}/', $aiResponse, $matches);
        if (isset($matches[0])) {
            return json_decode($matches[0], true);
        }
        
        return ['is_appropriate' => true, 'inappropriate_words' => [], 'reason' => 'Parse error', 'suggested_replacement' => null];
        
    } catch (Exception $e) {
        return ['is_appropriate' => true, 'inappropriate_words' => [], 'reason' => 'Exception: ' . $e->getMessage(), 'suggested_replacement' => null];
    }
}

if (!isset($_SESSION['user_id'])) {
  echo json_encode(['success' => false, 'message' => 'Not authenticated']); exit;
}

$user_id  = (int)$_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success' => false, 'message' => 'Invalid method']); exit;
}

$recipe_id = isset($_POST['recipe_id']) ? (int)$_POST['recipe_id'] : 0;
$comment   = trim($_POST['comment'] ?? '');

if ($recipe_id <= 0 || $comment === '') {
  echo json_encode(['success' => false, 'message' => 'Missing fields']); exit;
}

// Check if user is currently banned
$ban_check = $conn->prepare("SELECT ban_until, reason FROM user_bans WHERE user_id = ? AND ban_until > NOW() ORDER BY ban_until DESC LIMIT 1");
$ban_check->bind_param("i", $user_id);
$ban_check->execute();
$ban_result = $ban_check->get_result();

if ($ban_result->num_rows > 0) {
    $ban_data = $ban_result->fetch_assoc();
    $ban_until = new DateTime($ban_data['ban_until']);
    $now = new DateTime();
    $interval = $now->diff($ban_until);
    
    $hours_remaining = $interval->h + ($interval->days * 24);
    $minutes_remaining = $interval->i;
    
    echo json_encode([
        'success' => false, 
        'message' => 'banned',
        'ban_data' => [
            'hours' => $hours_remaining,
            'minutes' => $minutes_remaining,
            'reason' => $ban_data['reason'],
            'ban_until' => $ban_data['ban_until']
        ]
    ]);
    exit;
}

// Optional: tiny flood control (one comment every 2 seconds)
if (!isset($_SESSION['last_comment_at'])) $_SESSION['last_comment_at'] = 0;
if (time() - $_SESSION['last_comment_at'] < 2) {
  echo json_encode(['success' => false, 'message' => 'Too fast.']); exit;
}

// AI Content Filter Check
$filterResult = filterCommentWithAI($comment);

if (!$filterResult['is_appropriate']) {
    // Record strike for inappropriate content
    $strike_stmt = $conn->prepare("INSERT INTO user_strikes (user_id, comment_text, inappropriate_words, reason) VALUES (?, ?, ?, ?)");
    $inappropriate_words_json = json_encode($filterResult['inappropriate_words']);
    $strike_stmt->bind_param("isss", $user_id, $comment, $inappropriate_words_json, $filterResult['reason']);
    $strike_stmt->execute();
    
    // Check for consecutive strikes in last 24 hours
    $recent_strikes_stmt = $conn->prepare("
        SELECT COUNT(*) as strike_count 
        FROM user_strikes 
        WHERE user_id = ? 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY created_at DESC
    ");
    $recent_strikes_stmt->bind_param("i", $user_id);
    $recent_strikes_stmt->execute();
    $strikes_result = $recent_strikes_stmt->get_result();
    $strikes_data = $strikes_result->fetch_assoc();
    $strike_count = $strikes_data['strike_count'];
    
    // Apply ban if 3 or more strikes
    if ($strike_count >= 3) {
        // Check if user has previous bans (for escalating ban duration)
        $previous_ban_stmt = $conn->prepare("
            SELECT COUNT(*) as previous_bans 
            FROM user_bans 
            WHERE user_id = ? 
            AND ban_until < NOW()
        ");
        $previous_ban_stmt->bind_param("i", $user_id);
        $previous_ban_stmt->execute();
        $previous_ban_result = $previous_ban_stmt->get_result();
        $previous_ban_data = $previous_ban_result->fetch_assoc();
        $previous_bans = $previous_ban_data['previous_bans'];
        
        // Escalating ban: 2 hours for first offense, 24 hours for repeat offenses
        $ban_hours = ($previous_bans > 0) ? 24 : 2;
        
        $ban_until = date('Y-m-d H:i:s', strtotime("+{$ban_hours} hours"));
        $ban_reason = "{$strike_count} consecutive inappropriate comments please wait a few hours";
        
        $ban_stmt = $conn->prepare("INSERT INTO user_bans (user_id, ban_until, reason) VALUES (?, ?, ?)");
        $ban_stmt->bind_param("iss", $user_id, $ban_until, $ban_reason);
        $ban_stmt->execute();
        
        echo json_encode([
            'success' => false,
            'message' => 'banned',
            'filter_data' => $filterResult,
            'ban_data' => [
                'hours' => $ban_hours,
                'minutes' => 0,
                'reason' => $ban_reason,
                'ban_until' => $ban_until,
                'strike_count' => $strike_count
            ]
        ]);
    } else {
        // Return strike information but no ban yet
        echo json_encode([
            'success' => false,
            'message' => 'inappropriate_content',
            'filter_data' => $filterResult,
            'strike_count' => $strike_count,
            'strikes_remaining' => 3 - $strike_count
        ]);
    }
    exit;
}

// If content is appropriate, save to database and reset strikes
$stmt = $conn->prepare("
  INSERT INTO recipe_comments (recipe_id, user_id, username, comment)
  VALUES (?, ?, ?, ?)
");
$stmt->bind_param("iiss", $recipe_id, $user_id, $username, $comment);
$ok = $stmt->execute();

if ($ok) {
    // Reset strikes for good behavior (clear strikes older than 1 hour)
    $reset_strikes_stmt = $conn->prepare("DELETE FROM user_strikes WHERE user_id = ? AND created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $reset_strikes_stmt->bind_param("i", $user_id);
    $reset_strikes_stmt->execute();
    
    $_SESSION['last_comment_at'] = time();
    echo json_encode([
        'success' => true,
        'comment' => [
            'id'        => $conn->insert_id,
            'recipe_id' => $recipe_id,
            'user_id'   => $user_id,
            'username'  => htmlspecialchars($username, ENT_QUOTES, 'UTF-8'),
            'comment'   => htmlspecialchars($comment, ENT_QUOTES, 'UTF-8'),
            'created_at'=> date('Y-m-d H:i:s')
        ],
        'strike_count' => 0 // Reset strikes on successful comment
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'DB error']);
}
?>