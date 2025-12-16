<?php
// upload.php

// Настройки для вашего окружения
// Убедитесь, что PHP может выполнять команды shell_exec и имеет доступ к этим путям.
$pythonPath = 'python3'; 
$scriptPath = __DIR__ . '/transcribe_local.py';
$tempDir = '/tmp'; // Временная директория, доступная для записи

// --- Функции-помощники ---

/**
 * Конвертирует аудиофайл (webm из браузера) в формат WAV (LINEAR16, 16000 Гц) 
 * с помощью FFmpeg.
 * @param string $sourcePath Путь к исходному файлу (webm).
 * @return string|false Путь к конвертированному файлу (wav) или false в случае ошибки.
 */
function convertToWav(string $sourcePath, string $tempDir): string|false
{
    $outputFile = $tempDir . '/' . uniqid('audio_') . '.wav';
    
    // Команда FFmpeg: моно (-ac 1), 16000 Гц (-ar 16000), PCM (s16le)
    $command = "ffmpeg -i " . escapeshellarg($sourcePath) . " -y -ac 1 -ar 16000 -acodec pcm_s16le " . escapeshellarg($outputFile) . " 2>&1";
    
    $output = shell_exec($command);
    
    if (file_exists($outputFile) && filesize($outputFile) > 0) {
        return $outputFile;
    } else {
        error_log("FFmpeg Error: " . $output);
        return false;
    }
}

/**
 * Выполняет локальную транскрибацию с помощью Python-скрипта Whisper.
 */
function transcribeAudioLocal(string $wavPath, string $pythonPath, string $scriptPath): string|false
{
    $command = $pythonPath . ' ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg($wavPath) . ' 2>&1';
    
    // Выполняем команду и получаем весь вывод (stdout + stderr)
    $output = shell_exec($command);

    // Если в выводе есть "Error" или "Exception" (от Python-скрипта), считаем это ошибкой
    if (str_contains($output, 'Error') || str_contains($output, 'Exception')) {
        error_log('Whisper Error Output: ' . $output);
        return false;
    }
    
    // Возвращаем очищенный вывод (текст транскрипции)
    return trim($output); 
}


// --- Основная логика обработки запроса ---

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['audio_file'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Некорректный запрос или файл не найден.']);
    exit;
}

$uploadedFile = $_FILES['audio_file'];
$tempPath = $uploadedFile['tmp_name'];

if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Ошибка загрузки файла на сервер.']);
    exit;
}

// 1. Конвертация файла
$wavPath = convertToWav($tempPath, $tempDir);

if (!$wavPath) {
    echo json_encode(['success' => false, 'error' => 'Ошибка конвертации аудиофайла. Проверьте установку FFmpeg.']);
    // Важно: удаляем временный файл, если он был создан
    if (file_exists($tempPath)) {
        unlink($tempPath);
    }
    exit;
}

// 2. Транскрибация
$transcription = transcribeAudioLocal($wavPath, $pythonPath, $scriptPath);

// 3. Очистка временных файлов
if (file_exists($tempPath)) unlink($tempPath);
if (file_exists($wavPath)) unlink($wavPath);

// 4. Отправка результата клиенту
if ($transcription !== false && !empty($transcription)) {
    echo json_encode(['success' => true, 'transcription' => $transcription]);
} else {
    echo json_encode(['success' => false, 'error' => 'Не удалось получить транскрипцию. Проверьте логи сервера и запуск Python.']);
}
?>