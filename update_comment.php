<?php
session_start();
header('Content-Type: application/json');
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
  echo json_encode(['success' => false, 'message' => 'Not authenticated']); exit;
}
$user_id = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success' => false, 'message' => 'Invalid method']); exit;
}

$comment_id = isset($_POST['comment_id']) ? (int)$_POST['comment_id'] : 0;
$new_comment = trim($_POST['comment'] ?? '');
if ($comment_id <= 0 || $new_comment === '') {
  echo json_encode(['success' => false, 'message' => 'Missing fields']); exit;
}

// verify ownership
$stmt = $conn->prepare("SELECT user_id FROM recipe_comments WHERE id = ?");
$stmt->bind_param("i", $comment_id);
$stmt->execute();
$res = $stmt->get_result();
if (!$row = $res->fetch_assoc()) {
  echo json_encode(['success' => false, 'message' => 'Comment not found']); exit;
}
if ((int)$row['user_id'] !== $user_id) {
  echo json_encode(['success' => false, 'message' => 'Not allowed']); exit;
}

$upd = $conn->prepare("UPDATE recipe_comments SET comment = ? WHERE id = ?");
$upd->bind_param("si", $new_comment, $comment_id);
$ok = $upd->execute();

echo json_encode([
  'success' => (bool)$ok,
  'comment' => htmlspecialchars($new_comment, ENT_QUOTES, 'UTF-8')
]);
