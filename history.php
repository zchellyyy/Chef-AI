// history.php
<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get full history
$stmt = $conn->prepare("SELECT * FROM chat_history WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$history = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Chat History</title>
</head>
<body>
    <h1>My Recipe Search History</h1>
    <table>
        <tr>
            <th>Date</th>
            <th>Question</th>
            <th>Response Preview</th>
            <th>Actions</th>
        </tr>
        <?php while ($item = $history->fetch_assoc()): ?>
        <tr>
            <td><?= date('M j, Y g:i a', strtotime($item['created_at'])) ?></td>
            <td><?= htmlspecialchars($item['message']) ?></td>
            <td><?= htmlspecialchars(substr($item['response'], 0, 50)) ?>...</td>
            <td>
                <a href="view_history.php?id=<?= $item['id'] ?>">View</a> |
                <a href="delete_history.php?id=<?= $item['id'] ?>" onclick="return confirm('Delete this item?')">Delete</a>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>