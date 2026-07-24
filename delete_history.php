// delete_history.php
<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: login.php");
    exit();
}

$stmt = $conn->prepare("DELETE FROM chat_history WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $_GET['id'], $_SESSION['user_id']);
$stmt->execute();

header("Location: history.php");
exit();
?>