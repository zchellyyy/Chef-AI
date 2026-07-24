// view_history.php
<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: login.php");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM chat_history WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $_GET['id'], $_SESSION['user_id']);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();

if (!$item) {
    die("History item not found or doesn't belong to you");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>History: <?= htmlspecialchars(substr($item['message'], 0, 30)) ?></title>
</head>
<body>
    <h1>Question from <?= date('M j, Y', strtotime($item['created_at'])) ?></h1>
    <h2><?= htmlspecialchars($item['message']) ?></h2>
    <div class="response">
        <?= nl2br(htmlspecialchars($item['response'])) ?>
    </div>
    <a href="history.php">Back to History</a>
</body>
</html>