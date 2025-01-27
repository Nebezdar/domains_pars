<?php
require 'db.php';

// Параметры запроса
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$offset = ($page - 1) * $limit;

// Получение данных из базы
$query = "SELECT id, domain_name, updated_at FROM domains LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($query);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$domains = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Подсчет общего количества записей
$countQuery = "SELECT COUNT(*) FROM domains";
$totalCount = $pdo->query($countQuery)->fetchColumn();
$totalPages = ceil($totalCount / $limit);

// Возвращаем данные в формате JSON
echo json_encode([
    'domains' => $domains,
    'currentPage' => $page,
    'totalPages' => $totalPages,
]);
