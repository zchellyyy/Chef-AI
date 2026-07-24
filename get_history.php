<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo '<p>Please log in to view history</p>';
    exit();
}

// Function to get user history with prepared statements
function getUserHistory($user_id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT id, message, response, is_bookmarked, created_at, image_path FROM chat_history 
                          WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }
    
    $stmt->close();
    return $history;
}

$allHistory = getUserHistory($_SESSION['user_id']);
if (!empty($allHistory)): 
    foreach ($allHistory as $item): ?>
        <div class="history-item" data-id="<?php echo $item['id']; ?>">
            <div class="history-item-content">
                <?php echo htmlspecialchars(substr($item['message'], 0, 50)); ?>
                <?php if (strlen($item['message']) > 50) echo '...'; ?>
            </div>
            <div class="history-item-time">
                <?php echo date('M j, g:i A', strtotime($item['created_at'])); ?>
            </div>
        </div>
    <?php endforeach;
else: ?>
    <p>No history available</p>
<?php endif; ?>