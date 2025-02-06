<?php
namespace Facebook\WebDriver;

use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\WebDriverBy;

require_once('vendor/autoload.php');


$pdo = new \PDO('mysql:host=192.168.0.184;dbname=DB_Domain_Parse', 'root', '123123');
$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);


$stmt = $pdo->query("SELECT id, domain_name FROM control_domains WHERE updated_at IS NULL");
$domains = $stmt->fetchAll(\PDO::FETCH_ASSOC);

// WebDriver
$host = 'http://localhost:4444/';
$capabilities = DesiredCapabilities::chrome();
$chromeOptions = new ChromeOptions();
$chromeOptions->addArguments(['--headless']);
$capabilities->setCapability(ChromeOptions::CAPABILITY_W3C, $chromeOptions);
$driver = RemoteWebDriver::create($host, $capabilities);
$driver->manage()->window()->maximize();

// Устанавливаем таймауты
$driver->manage()->timeouts()->pageLoadTimeout(30); // 30 секунд на загрузку страницы
$driver->manage()->timeouts()->implicitlyWait(10);  // 10 секунд на поиск элементов

// Определяем константу на уровне файла, до цикла
const MAX_PAGES_PER_SITE = 100;

// В начале скрипта, после подключения автозагрузки
$logFile = __DIR__ . '/parsing_log.txt';
$startTime = date('Y-m-d H:i:s');
file_put_contents($logFile, "=== Начало парсинга: {$startTime} ===\n", FILE_APPEND);

// Добавим массив запрещенных расширений
$excluded_extensions = [
    // Изображения
    'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', 'ico',
    // Документы
    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf', 'csv',
    // Архивы
    'zip', 'rar', '7z', 'tar', 'gz',
    // Аудио
    'mp3', 'wav', 'ogg', 'wma', 'm4a',
    // Видео
    'mp4', 'avi', 'mov', 'wmv', 'flv', 'webm',
    // Другие
    'exe', 'dll', 'iso', 'apk', 'dmg'
];

foreach ($domains as $domain) {
    try {
        echo "Парсинг домена: " . $domain['domain_name'] . "\n";
        
        // Проверяем доступность сайта через cURL
        $ch = curl_init('https://' . $domain['domain_name']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            echo "Сайт {$domain['domain_name']} недоступен (HTTP код: $httpCode)\n";
            continue;
        }
        
        // Массив для хранения уже посещенных URL
        $visited_urls = [];
        // Очередь URL для обработки
        $urls_to_process = ['https://' . $domain['domain_name']];
        
        // инициализируем массив для хранения контактной информации
        $contacts = [
            'phones' => [],
            'emails' => []
        ];

        // Регулярные выражения для поиска контактов
        $phone_patterns = [
            '/(?:\+7|8)[\s\-\(]?\d{3}[\s\-\)]?\d{3}[\s\-]?\d{2}[\s\-]?\d{2}/' // только номера, начинающиеся с +7 или 8
        ];
        
        $email_pattern = '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/';

        while (!empty($urls_to_process)) {
            // Проверяем количество обработанных страниц
            if (count($visited_urls) >= MAX_PAGES_PER_SITE) {
                echo "\n=== Достигнут лимит в " . MAX_PAGES_PER_SITE . " страниц для домена " . $domain['domain_name'] . " ===\n\n";
                break;
            }
            
            $current_url = array_shift($urls_to_process);
            
            // Пропускаем, если URL уже был обработан
            if (in_array($current_url, $visited_urls)) {
                continue;
            }
            
            echo "\n=== Обработка страницы (" . (count($visited_urls) + 1) . " из " . MAX_PAGES_PER_SITE . "): " . $current_url . " ===\n";
            
            try {
                $driver->get($current_url);
                sleep(2); // Ждем загрузку страницы
                
                // Добавляем текущий URL в список посещенных
                $visited_urls[] = $current_url;
                
                // Получаем весь HTML страницы
                $page_source = $driver->getPageSource();
                
                // Поиск телефонов по регулярным выражениям
                foreach ($phone_patterns as $pattern) {
                    if (preg_match_all($pattern, $page_source, $matches)) {
                        foreach ($matches[0] as $phone) {
                            // Очищаем телефон от всех символов кроме цифр и +
                            $phone = preg_replace('/[^\d+]/', '', $phone);
                            // Проверяем длину номера (должно быть 11 цифр)
                            if (strlen(preg_replace('/[^\d]/', '', $phone)) === 11 && !in_array($phone, $contacts['phones'])) {
                                $contacts['phones'][] = $phone;
                                echo "Найден телефон: " . $phone . "\n";
                            }
                        }
                    }
                }
                
                // Поиск email по регулярному выражению
                if (preg_match_all($email_pattern, $page_source, $matches)) {
                    foreach ($matches[0] as $email) {
                        $email = trim(strtolower($email)); // приводим к нижнему регистру
                        if (!empty($email) && !in_array($email, $contacts['emails'])) {
                            $contacts['emails'][] = $email;
                            echo "Найден email: " . $email . "\n";
                        }
                    }
                }
                
                // Дополнительный поиск через теги
                $phone_elements = $driver->findElements(WebDriverBy::cssSelector('a[href^="tel:"]'));
                foreach ($phone_elements as $phone_element) {
                    $phone = trim(preg_replace('/[\s\-\(\)]/', '', $phone_element->getText()));
                    if (!empty($phone) && !in_array($phone, $contacts['phones'])) {
                        $contacts['phones'][] = $phone;
                        echo "Найден телефон через тег: " . $phone . "\n";
                    }
                }

                $email_elements = $driver->findElements(WebDriverBy::cssSelector('a[href^="mailto:"]'));
                foreach ($email_elements as $email_element) {
                    $email = trim(strtolower($email_element->getText()));
                    if (!empty($email) && !in_array($email, $contacts['emails'])) {
                        $contacts['emails'][] = $email;
                        echo "Найден email через тег: " . $email . "\n";
                    }
                }

                // Обновляем вывод информации о найденных ссылках
                echo "\nНайденные ссылки на странице:\n";
                $links = $driver->findElements(WebDriverBy::tagName('a'));
                $newLinksCount = 0;
                foreach ($links as $link) {
                    try {
                        $href = $link->getAttribute('href');
                        
                        // Пропускаем пустые ссылки и якоря
                        if (empty($href) || $href === '#' || strpos($href, 'javascript:') === 0) {
                            continue;
                        }
                        
                        // Проверяем расширение файла
                        $path_info = pathinfo(strtolower($href));
                        if (isset($path_info['extension']) && in_array($path_info['extension'], $excluded_extensions)) {
                            continue;
                        }
                        
                        // Обработка относительных ссылок
                        if (strpos($href, '/') === 0) {
                            $href = 'https://' . $domain['domain_name'] . $href;
                        }
                        // Обработка ссылок без протокола
                        elseif (strpos($href, '//') === 0) {
                            $href = 'https:' . $href;
                        }
                        // Обработка относительных ссылок без начального слеша
                        elseif (strpos($href, 'http') !== 0) {
                            $href = 'https://' . $domain['domain_name'] . '/' . $href;
                        }
                        
                        // Проверяем расширение файла после формирования полного URL
                        $path_info = pathinfo(strtolower($href));
                        if (isset($path_info['extension']) && in_array($path_info['extension'], $excluded_extensions)) {
                            continue;
                        }
                        
                        // Проверяем, что ссылка относится к текущему домену
                        if (strpos($href, 'https://' . $domain['domain_name']) === 0 && 
                            strpos($href, '#') === false &&
                            !in_array($href, $visited_urls) && 
                            !in_array($href, $urls_to_process)) {
                            
                            // Удаляем trailing slash для единообразия
                            $href = rtrim($href, '/');
                            
                            $urls_to_process[] = $href;
                            $newLinksCount++;
                            echo "  + " . $href . "\n";
                        }
                    } catch (\Exception $e) {
                        echo "Ошибка при обработке ссылки: " . $e->getMessage() . "\n";
                        continue;
                    }
                }
                echo "Всего новых ссылок добавлено: " . $newLinksCount . "\n";
                echo "Осталось обработать страниц: " . count($urls_to_process) . "\n";
                
            } catch (\Exception $e) {
                echo "Ошибка при обработке страницы {$current_url}: " . $e->getMessage() . "\n";
                continue;
            }
        }

        // Проверяем существующие записи перед вставкой
        $now = date('Y-m-d H:i:s');
        
        foreach ($contacts['phones'] as $phone) {
            // Проверяем существование записи
            $checkStmt = $pdo->prepare("
                SELECT COUNT(*) FROM control_data 
                WHERE domain_id = :domain_id AND `key` = 'phone' AND value = :value
            ");
            $checkStmt->execute([
                'domain_id' => $domain['id'],
                'value' => $phone
            ]);
            
            if ($checkStmt->fetchColumn() == 0) {
                $insertStmt = $pdo->prepare("
                    INSERT INTO control_data (domain_id, `key`, value, created_at) 
                    VALUES (:domain_id, :key, :value, :created_at)
                ");
                $insertStmt->execute([
                    'domain_id' => $domain['id'],
                    'key' => 'phone',
                    'value' => $phone,
                    'created_at' => $now
                ]);
            }
        }
        
        foreach ($contacts['emails'] as $email) {
            // Проверяем существование записи
            $checkStmt = $pdo->prepare("
                SELECT COUNT(*) FROM control_data 
                WHERE domain_id = :domain_id AND `key` = 'email' AND value = :value
            ");
            $checkStmt->execute([
                'domain_id' => $domain['id'],
                'value' => $email
            ]);
            
            if ($checkStmt->fetchColumn() == 0) {
                $insertStmt = $pdo->prepare("
                    INSERT INTO control_data (domain_id, `key`, value, created_at) 
                    VALUES (:domain_id, :key, :value, :created_at)
                ");
                $insertStmt->execute([
                    'domain_id' => $domain['id'],
                    'key' => 'email',
                    'value' => $email,
                    'created_at' => $now
                ]);
            }
        }

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

$endTime = date('Y-m-d H:i:s');
file_put_contents($logFile, "=== Окончание парсинга: {$endTime} ===\n\n", FILE_APPEND);

$driver->close();
