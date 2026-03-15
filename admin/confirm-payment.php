$stmt = $pdo->prepare("
UPDATE clients
SET
    status='active',
    expired_at = DATE_ADD(CURDATE(), INTERVAL ? DAY)
WHERE id=?
");

$stmt->execute([
    $product['duration'],
    $client_id
]);