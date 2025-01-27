<?php
require 'vendor/autoload.php';
require 'db.php';
require 'patterns.php';

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;

$query = "SELECT id, domain_name FROM domains WHERE updated_at IS NULL";
$stmt = $pdo->query($query);
$domains = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($domains)) {
    die("Нет доменов для парсинга.");
}

$serverUrl = 'http://localhost:4444/wd/hub'; // URL Selenium-сервера
$driver = RemoteWebDriver::create($serverUrl, DesiredCapabilities::chrome());

foreach ($domains as $domain) {
    $domainId = $domain['id'];
    $domainName = $domain['domain_name'];

    try {
        $driver->get("http://$domainName");
        $pageSource = $driver->getPageSource();

        $parsedData = [];
        foreach ($patterns as $key => $pattern) {
            if (preg_match_all($pattern, $pageSource, $matches)) {
                $parsedData[$key] = $matches[1];
            }
        }

        $insertQuery = "INSERT INTO parsed_data (domain_id, `key`, value) VALUES (:domain_id, :key, :value)";
        $insertStmt = $pdo->prepare($insertQuery);

        foreach ($parsedData as $key => $values) {
            foreach ($values as $value) {
                $insertStmt->execute([
                    ':domain_id' => $domainId,
                    ':key' => $key,
                    ':value' => $value
                ]);
            }
        }

        $updateQuery = "UPDATE domains SET updated_at = NOW() WHERE id = :id";
        $updateStmt = $pdo->prepare($updateQuery);
        $updateStmt->execute([':id' => $domainId]);

        echo "Домен $domainName успешно обработан.\n";

    } catch (Exception $e) {
        echo "Ошибка при обработке домена $domainName: " . $e->getMessage() . "\n";
    }
}

$driver->quit();
