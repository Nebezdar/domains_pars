<?php
require 'db.php';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$offset = ($page - 1) * $limit;

$query = "SELECT id, domain_name, updated_at FROM control_domains LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($query);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$domains = $stmt->fetchAll(PDO::FETCH_ASSOC);

$countQuery = "SELECT COUNT(*) FROM control_domains";
$totalCount = $pdo->query($countQuery)->fetchColumn();
$totalPages = ceil($totalCount / $limit);

echo json_encode([
    'domains' => $domains,
    'currentPage' => $page,
    'totalPages' => $totalPages,
]);
