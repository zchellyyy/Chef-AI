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
if ($comment_id <= 0) {
  echo json_encode(['success' => false, 'message' => 'Missing comment_id']); exit;
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

$del = $conn->prepare("DELETE FROM recipe_comments WHERE id = ?");
$del->bind_param("i", $comment_id);
$ok = $del->execute();

echo json_encode(['success' => (bool)$ok]);
