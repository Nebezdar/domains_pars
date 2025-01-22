<?php
namespace Facebook\WebDriver;

use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\WebDriverBy;

require_once('vendor/autoload.php');

// Добавляем подключение к БД
$pdo = new \PDO('mysql:host=192.168.0.184;dbname=DB_Domain_Parse', 'root', '123123');
$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

// Получаем домены для парсинга
$stmt = $pdo->query("SELECT id, domain_name FROM control_domains WHERE updated_at IS NULL");
$domains = $stmt->fetchAll(\PDO::FETCH_ASSOC);

// Настройка WebDriver
$host = 'http://localhost:4444/';
$capabilities = DesiredCapabilities::chrome();
$chromeOptions = new ChromeOptions();
$chromeOptions->addArguments(['--headless']);
$capabilities->setCapability(ChromeOptions::CAPABILITY_W3C, $chromeOptions);
$driver = RemoteWebDriver::create($host, $capabilities);
$driver->manage()->window()->maximize();

foreach ($domains as $domain) {
    try {
        echo "Парсинг домена: " . $domain['domain_name'] . "\n";
        
        if (!@file_get_contents('https://' . $domain['domain_name'])) {
            echo "Сайт {$domain['domain_name']} недоступен\n";
            continue;
        }
        
        // открываем страницу контактов
        $driver->get('https://' . $domain['domain_name']);
        sleep(2);
        
        // инициализируем массив для хранения контактной информации
        $contacts = [
            'phones' => [],
            'emails' => []
        ];

        echo "Поиск телефонов...\n";
        // Пробуем более общие селекторы
        $phone_elements = $driver->findElements(WebDriverBy::cssSelector('a[href^="tel:"]'));
        echo "Найдено телефонов: " . count($phone_elements) . "\n";
        
        foreach ($phone_elements as $phone_element) {
            $phone = $phone_element->getText();
            $contacts['phones'][] = $phone;
            echo "Найден телефон: " . $phone . "\n";
        }

        echo "Поиск email адресов...\n";
        // Пробуем более общие селекторы для email
        $email_elements = $driver->findElements(WebDriverBy::cssSelector('a[href^="mailto:"]'));
        echo "Найдено email адресов: " . count($email_elements) . "\n";
        
        foreach ($email_elements as $email_element) {
            $email = $email_element->getText();
            $contacts['emails'][] = $email;
            echo "Найден email: " . $email . "\n";
        }

        // Если не нашли контакты, попробуем поискать по тексту
        if (empty($contacts['phones']) && empty($contacts['emails'])) {
            echo "Поиск контактов по тексту...\n";
            $content = $driver->findElement(WebDriverBy::tagName('body'))->getText();
            
            // Поиск телефонов по шаблону
            if (preg_match_all('/\+7[\s\-\(]?\d{3}[\s\-\)]?\d{3}[\s\-]?\d{2}[\s\-]?\d{2}/', $content, $matches)) {
                $contacts['phones'] = $matches[0];
            }
            
            // Поиск email по шаблону
            if (preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $content, $matches)) {
                $contacts['emails'] = $matches[0];
            }
        }

        
        $now = date('Y-m-d H:i:s');
        
        // Подготавливаем запрос для вставки данных
        $insertStmt = $pdo->prepare("
            INSERT INTO control_data (domain_id, `key`, value, created_at) 
            VALUES (:domain_id, :key, :value, :created_at)
        ");

        // Сохраняем телефоны
        foreach ($contacts['phones'] as $phone) {
            $insertStmt->execute([
                'domain_id' => $domain['id'],
                'key' => 'phone',
                'value' => $phone,
                'created_at' => $now
            ]);
        }

        // Сохраняем email адреса
        foreach ($contacts['emails'] as $email) {
            $insertStmt->execute([
                'domain_id' => $domain['id'],
                'key' => 'email',
                'value' => $email,
                'created_at' => $now
            ]);
        }

        // Обновляем дату парсинга в domains
        $updateStmt = $pdo->prepare("
            UPDATE control_domains
            SET updated_at = :updated_at 
            WHERE id = :id
        ");
        $updateStmt->execute([
            'updated_at' => $now,
            'id' => $domain['id']
        ]);

    } catch (\Exception $e) {
        echo "Ошибка при парсинге домена {$domain['domain_name']}: " . $e->getMessage() . "\n";
    }
}

$driver->close();
