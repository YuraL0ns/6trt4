#!/usr/bin/env php
<?php

/**
 * Скрипт для анализа и форматирования логов Laravel за предыдущий день
 * Группирует ошибки по типам (404, 403, error, warning) для удобного просмотра
 */

$yesterday = date('Y-m-d', strtotime('-1 day'));
$logFile = storage_path("logs/laravel-{$yesterday}.log");

if (!file_exists($logFile)) {
    echo "Лог-файл за {$yesterday} не найден: {$logFile}\n";
    exit(1);
}

echo "Анализ логов за {$yesterday}\n";
echo str_repeat('=', 80) . "\n\n";

$logContent = file_get_contents($logFile);
$lines = explode("\n", $logContent);

// Группируем ошибки по типам
$errors = [
    '404' => [],
    '403' => [],
    'error' => [],
    'warning' => [],
    'other' => []
];

$currentEntry = '';
$currentType = null;

foreach ($lines as $line) {
    // Определяем тип ошибки
    if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\].*production\.(ERROR|WARNING|INFO|DEBUG)/', $line, $matches)) {
        // Сохраняем предыдущую запись
        if ($currentEntry && $currentType) {
            $errors[$currentType][] = $currentEntry;
        }
        
        // Начинаем новую запись
        $currentEntry = $line;
        $currentType = null;
        
        // Определяем тип
        if (stripos($line, '404') !== false || stripos($line, 'NotFoundHttpException') !== false) {
            $currentType = '404';
        } elseif (stripos($line, '403') !== false || stripos($line, 'Forbidden') !== false || stripos($line, 'AccessDenied') !== false) {
            $currentType = '403';
        } elseif (stripos($line, 'ERROR') !== false || stripos($line, 'Exception') !== false) {
            $currentType = 'error';
        } elseif (stripos($line, 'WARNING') !== false) {
            $currentType = 'warning';
        } else {
            $currentType = 'other';
        }
    } elseif ($currentEntry) {
        // Продолжаем текущую запись
        $currentEntry .= "\n" . $line;
    }
}

// Сохраняем последнюю запись
if ($currentEntry && $currentType) {
    $errors[$currentType][] = $currentEntry;
}

// Выводим результаты
$totalErrors = 0;
foreach ($errors as $type => $items) {
    if ($type === 'other') continue;
    $count = count($items);
    $totalErrors += $count;
    
    if ($count > 0) {
        echo "\n" . strtoupper($type) . " ОШИБКИ ({$count}):\n";
        echo str_repeat('-', 80) . "\n";
        
        // Группируем похожие ошибки
        $grouped = [];
        foreach ($items as $item) {
            // Извлекаем основное сообщение об ошибке
            $key = preg_replace('/\[.*?\]/', '', $item);
            $key = preg_replace('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', '', $key);
            $key = preg_replace('/production\.(ERROR|WARNING)/', '', $key);
            $key = trim($key);
            $key = substr($key, 0, 200); // Ограничиваем длину ключа
            
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'count' => 0,
                    'examples' => []
                ];
            }
            
            $grouped[$key]['count']++;
            if (count($grouped[$key]['examples']) < 3) {
                $grouped[$key]['examples'][] = $item;
            }
        }
        
        // Сортируем по количеству вхождений
        uasort($grouped, function($a, $b) {
            return $b['count'] - $a['count'];
        });
        
        // Выводим сгруппированные ошибки
        foreach ($grouped as $key => $group) {
            echo "\n[Встречается {$group['count']} раз]\n";
            echo "Пример:\n";
            echo substr($group['examples'][0], 0, 500) . "\n";
            if (strlen($group['examples'][0]) > 500) {
                echo "... (обрезано)\n";
            }
        }
        
        echo "\n";
    }
}

echo "\n" . str_repeat('=', 80) . "\n";
echo "Всего ошибок найдено: {$totalErrors}\n";
echo str_repeat('=', 80) . "\n";

// Сохраняем отчет в файл
$reportFile = storage_path("logs/analysis-{$yesterday}.txt");
$reportContent = "Анализ логов за {$yesterday}\n";
$reportContent .= str_repeat('=', 80) . "\n\n";

foreach ($errors as $type => $items) {
    if ($type === 'other') continue;
    $count = count($items);
    
    if ($count > 0) {
        $reportContent .= "\n" . strtoupper($type) . " ОШИБКИ ({$count}):\n";
        $reportContent .= str_repeat('-', 80) . "\n";
        
        // Группируем похожие ошибки
        $grouped = [];
        foreach ($items as $item) {
            $key = preg_replace('/\[.*?\]/', '', $item);
            $key = preg_replace('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', '', $key);
            $key = preg_replace('/production\.(ERROR|WARNING)/', '', $key);
            $key = trim($key);
            $key = substr($key, 0, 200);
            
            if (!isset($grouped[$key])) {
                $grouped[$key] = ['count' => 0, 'examples' => []];
            }
            
            $grouped[$key]['count']++;
            if (count($grouped[$key]['examples']) < 3) {
                $grouped[$key]['examples'][] = $item;
            }
        }
        
        uasort($grouped, function($a, $b) {
            return $b['count'] - $a['count'];
        });
        
        foreach ($grouped as $key => $group) {
            $reportContent .= "\n[Встречается {$group['count']} раз]\n";
            $reportContent .= "Пример:\n";
            $reportContent .= substr($group['examples'][0], 0, 500) . "\n";
            if (strlen($group['examples'][0]) > 500) {
                $reportContent .= "... (обрезано)\n";
            }
        }
        
        $reportContent .= "\n";
    }
}

$reportContent .= "\n" . str_repeat('=', 80) . "\n";
$reportContent .= "Всего ошибок найдено: {$totalErrors}\n";
$reportContent .= str_repeat('=', 80) . "\n";

file_put_contents($reportFile, $reportContent);
echo "\nОтчет сохранен в: {$reportFile}\n";
