<?php
session_start();
header('Content-Type: application/json');
require_once 'db_connect.php';

if (!isset($_GET['recipe_id'])) {
  echo json_encode(['success' => false, 'message' => 'Missing recipe_id']); exit;
}

$recipe_id = (int)$_GET['recipe_id'];

$stmt = $conn->prepare("
  SELECT id, recipe_id, user_id, username, comment, created_at
  FROM recipe_comments
  WHERE recipe_id = ?
  ORDER BY created_at DESC
");
$stmt->bind_param("i", $recipe_id);
$stmt->execute();
$res = $stmt->get_result();

$rows = [];
while ($r = $res->fetch_assoc()) {
  // Basic escaping right before send
  $rows[] = [
    'id'        => (int)$r['id'],
    'recipe_id' => (int)$r['recipe_id'],
    'user_id'   => (int)$r['user_id'],
    'username'  => htmlspecialchars($r['username'], ENT_QUOTES, 'UTF-8'),
    'comment'   => htmlspecialchars($r['comment'], ENT_QUOTES, 'UTF-8'),
    'created_at'=> $r['created_at']
  ];
}

echo json_encode(['success' => true, 'comments' => $rows]);
