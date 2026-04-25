<?php
declare(strict_types=1);

require_once __DIR__ . '/maintenance_source_finder_functions.php';

if (!function_exists('vms_template_user_can_manage')) {
    function vms_template_user_can_manage(): bool
    {
        $roleId = (int)($_SESSION['role_id'] ?? 0);
        return in_array($roleId, [1, 2], true);
    }
}

if (!function_exists('vms_template_current_user_id')) {
    function vms_template_current_user_id(): int
    {
        return (int)($_SESSION['user_id'] ?? 0);
    }
}

if (!function_exists('vms_template_table_exists')) {
    function vms_template_table_exists(PDO $pdo): bool
    {
        return vms_source_finder_table_exists($pdo, 'equipment_maintenance_templates');
    }
}

if (!function_exists('vms_template_extraction_runs_table_exists')) {
    function vms_template_extraction_runs_table_exists(PDO $pdo): bool
    {
        return vms_source_finder_table_exists($pdo, 'equipment_maintenance_extraction_runs');
    }
}

if (!function_exists('vms_template_extraction_rows_table_exists')) {
    function vms_template_extraction_rows_table_exists(PDO $pdo): bool
    {
        return vms_source_finder_table_exists($pdo, 'equipment_maintenance_extraction_rows');
    }
}

if (!function_exists('vms_template_extraction_get_config')) {
    function vms_template_extraction_get_config(): array
    {
        static $config = null;
        if ($config !== null) {
            return $config;
        }

        $config = [
            'provider' => trim((string)(getenv('VMS_TEMPLATE_EXTRACTION_PROVIDER') ?: '')),
            'api_key' => trim((string)(getenv('VMS_TEMPLATE_EXTRACTION_API_KEY') ?: getenv('OPENAI_API_KEY') ?: '')),
            'openai_model' => trim((string)(getenv('VMS_TEMPLATE_EXTRACTION_MODEL') ?: '')),
            'fallback_openai_model' => trim((string)(getenv('VMS_TEMPLATE_EXTRACTION_FALLBACK_MODEL') ?: '')),
            'max_input_chars' => (int)(getenv('VMS_TEMPLATE_EXTRACTION_MAX_INPUT_CHARS') ?: 8000),
            'max_output_tokens' => (int)(getenv('VMS_TEMPLATE_EXTRACTION_MAX_OUTPUT_TOKENS') ?: 1200),
            'max_pdf_pages' => (int)(getenv('VMS_TEMPLATE_EXTRACTION_MAX_PDF_PAGES') ?: 5),
            'max_pdf_file_size_mb' => (int)(getenv('VMS_TEMPLATE_EXTRACTION_MAX_PDF_MB') ?: 15),
            'max_pdf_payload_mb' => (int)(getenv('VMS_TEMPLATE_EXTRACTION_MAX_PDF_PAYLOAD_MB') ?: 18),
            'pdf_render_dpi' => (int)(getenv('VMS_TEMPLATE_EXTRACTION_PDF_DPI') ?: 150),
            'pdf_conversion_method' => trim((string)(getenv('VMS_TEMPLATE_EXTRACTION_PDF_METHOD') ?: '')),
            'temp_upload_dir' => trim((string)(getenv('VMS_TEMPLATE_EXTRACTION_TEMP_DIR') ?: '')),
            'timeout_seconds' => (int)(getenv('VMS_TEMPLATE_EXTRACTION_TIMEOUT') ?: 20),
        ];

        $privatePath = __DIR__ . '/../private/config_maintenance_template_extraction.php';
        $localPath = __DIR__ . '/../config_maintenance_template_extraction.php';
        foreach ([$privatePath, $localPath] as $path) {
            if (is_file($path)) {
                $loaded = require $path;
                if (is_array($loaded)) {
                    $config = array_merge($config, $loaded);
                }
                break;
            }
        }

        return $config;
    }
}

if (!function_exists('vms_template_extraction_get_diagnostics')) {
    function vms_template_extraction_get_diagnostics(): array
    {
        $privatePath = __DIR__ . '/../private/config_maintenance_template_extraction.php';
        $localPath = __DIR__ . '/../config_maintenance_template_extraction.php';
        $config = vms_template_extraction_get_config();
        $provider = trim((string)($config['provider'] ?? ''));
        $apiKey = trim((string)($config['api_key'] ?? ''));
        $model = trim((string)($config['openai_model'] ?? $config['model'] ?? ''));
        $fallbackModel = trim((string)($config['fallback_openai_model'] ?? ''));

        return [
            'private_config_exists' => is_file($privatePath),
            'local_config_exists' => is_file($localPath),
            'provider_loaded' => $provider !== '',
            'provider_name' => $provider,
            'api_key_present' => $apiKey !== '',
            'model_loaded' => $model !== '',
            'model_name' => $model,
            'fallback_model_loaded' => $fallbackModel !== '',
            'fallback_model_name' => $fallbackModel,
            'pdf_support' => vms_template_detect_pdf_conversion_support(),
            'configured' => vms_template_extraction_is_configured(),
        ];
    }
}

if (!function_exists('vms_template_extraction_is_configured')) {
    function vms_template_extraction_is_configured(): bool
    {
        $config = vms_template_extraction_get_config();
        return trim((string)($config['provider'] ?? '')) !== '' && trim((string)($config['api_key'] ?? '')) !== '';
    }
}

if (!function_exists('vms_template_extraction_config_message')) {
    function vms_template_extraction_config_message(): string
    {
        return 'Advanced extraction provider is not configured. You can still use built-in note heuristics and manual draft entry on this page.';
    }
}

if (!function_exists('vms_template_build_extraction_input')) {
    function vms_template_build_extraction_input(array $source, ?string $manualContent = null): string
    {
        $manualContent = trim((string)$manualContent);
        $config = vms_template_extraction_get_config();
        $maxChars = max(500, (int)($config['max_input_chars'] ?? 8000));

        $input = $manualContent !== '' ? $manualContent : trim(implode("\n\n", array_filter([
            trim((string)($source['title'] ?? '')),
            trim((string)($source['notes'] ?? '')),
        ], static fn($value) => $value !== '')));

        if (function_exists('mb_substr')) {
            return trim(mb_substr($input, 0, $maxChars));
        }

        return trim(substr($input, 0, $maxChars));
    }
}

if (!function_exists('vms_template_parse_page_range')) {
    function vms_template_parse_page_range(string $pageRange, int $maxPages): array
    {
        $pageRange = trim($pageRange);
        if ($pageRange === '') {
            throw new RuntimeException('Page range is required.');
        }

        if (!preg_match('/^\d+\s*(?:-\s*\d+)?$/', $pageRange)) {
            throw new RuntimeException('Page range must look like 88-90 or 12.');
        }

        [$start, $end] = array_pad(array_map('trim', explode('-', $pageRange, 2)), 2, null);
        $startPage = (int)$start;
        $endPage = $end !== null && $end !== '' ? (int)$end : $startPage;

        if ($startPage <= 0 || $endPage <= 0 || $endPage < $startPage) {
            throw new RuntimeException('Page range is invalid.');
        }

        $pages = range($startPage, $endPage);
        if (count($pages) > $maxPages) {
            throw new RuntimeException('Page range exceeds the maximum of ' . $maxPages . ' pages for PDF extraction.');
        }

        return $pages;
    }
}

if (!function_exists('vms_template_exec_command_available')) {
    function vms_template_exec_command_available(): bool
    {
        $disabled = strtolower((string)ini_get('disable_functions'));
        foreach (['exec', 'shell_exec', 'proc_open', 'popen'] as $fn) {
            if (function_exists($fn) && !str_contains($disabled, $fn)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('vms_template_find_command')) {
    function vms_template_find_command(array $candidates): ?string
    {
        if (!vms_template_exec_command_available()) {
            return null;
        }

        foreach ($candidates as $candidate) {
            $candidate = trim((string)$candidate);
            if ($candidate === '') {
                continue;
            }

            $checkCommand = stripos(PHP_OS_FAMILY, 'Windows') === 0
                ? 'where ' . escapeshellarg($candidate) . ' 2>NUL'
                : 'command -v ' . escapeshellarg($candidate) . ' 2>/dev/null';

            $output = [];
            $code = 1;
            @exec($checkCommand, $output, $code);
            if ($code === 0 && !empty($output[0])) {
                return trim((string)$output[0]);
            }
        }

        return null;
    }
}

if (!function_exists('vms_template_detect_pdf_conversion_support')) {
    function vms_template_detect_pdf_conversion_support(): array
    {
        static $support = null;
        if ($support !== null) {
            return $support;
        }

        $config = vms_template_extraction_get_config();
        $preferred = strtolower(trim((string)($config['pdf_conversion_method'] ?? '')));

        $support = [
            'available' => false,
            'method' => '',
            'dependency_message' => 'PDF conversion support is not available. Install ImageMagick/Ghostscript or Poppler pdftoppm on the server to enable page-image extraction.',
        ];

        if ($preferred === 'imagick' && extension_loaded('imagick')) {
            $support = [
                'available' => true,
                'method' => 'imagick',
                'dependency_message' => '',
            ];
            return $support;
        }

        if ($preferred === 'pdftoppm') {
            $cmd = vms_template_find_command(['pdftoppm']);
            if ($cmd !== null) {
                $support = [
                    'available' => true,
                    'method' => 'pdftoppm',
                    'dependency_message' => '',
                    'command' => $cmd,
                ];
                return $support;
            }
        }

        if ($preferred === 'magick') {
            $cmd = vms_template_find_command(['magick']);
            if ($cmd !== null) {
                $support = [
                    'available' => true,
                    'method' => 'magick',
                    'dependency_message' => '',
                    'command' => $cmd,
                ];
                return $support;
            }
        }

        if (extension_loaded('imagick')) {
            $support = [
                'available' => true,
                'method' => 'imagick',
                'dependency_message' => '',
            ];
            return $support;
        }

        $pdftoppm = vms_template_find_command(['pdftoppm']);
        if ($pdftoppm !== null) {
            $support = [
                'available' => true,
                'method' => 'pdftoppm',
                'dependency_message' => '',
                'command' => $pdftoppm,
            ];
            return $support;
        }

        $magick = vms_template_find_command(['magick']);
        if ($magick !== null) {
            $support = [
                'available' => true,
                'method' => 'magick',
                'dependency_message' => '',
                'command' => $magick,
            ];
            return $support;
        }

        return $support;
    }
}

if (!function_exists('vms_template_make_temp_dir')) {
    function vms_template_make_temp_dir(): string
    {
        $config = vms_template_extraction_get_config();
        $baseDir = trim((string)($config['temp_upload_dir'] ?? ''));
        if ($baseDir === '') {
            $baseDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'vms_template_extract';
        }

        if (!is_dir($baseDir) && !@mkdir($baseDir, 0775, true) && !is_dir($baseDir)) {
            throw new RuntimeException('Unable to create a temporary PDF extraction directory.');
        }

        $unique = $baseDir . DIRECTORY_SEPARATOR . 'job_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
        if (!@mkdir($unique, 0775, true) && !is_dir($unique)) {
            throw new RuntimeException('Unable to create a temporary extraction workspace.');
        }

        return $unique;
    }
}

if (!function_exists('vms_template_cleanup_path')) {
    function vms_template_cleanup_path(string $path): void
    {
        if ($path === '' || !file_exists($path)) {
            return;
        }

        if (is_file($path) || is_link($path)) {
            @unlink($path);
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            vms_template_cleanup_path($path . DIRECTORY_SEPARATOR . $item);
        }
        @rmdir($path);
    }
}

if (!function_exists('vms_template_fetch_source_pdf')) {
    function vms_template_fetch_source_pdf(array $source, string $targetPath): void
    {
        $url = trim((string)($source['source_url'] ?? ''));
        if ($url === '' || !preg_match('/\.pdf(?:\?|#|$)/i', $url)) {
            throw new RuntimeException('The saved source URL is not a PDF.');
        }

        $config = vms_template_extraction_get_config();
        $maxBytes = max(1, (int)($config['max_pdf_file_size_mb'] ?? 15)) * 1024 * 1024;
        $timeout = max(10, (int)($config['timeout_seconds'] ?? 20));

        $fp = fopen($targetPath, 'wb');
        if ($fp === false) {
            throw new RuntimeException('Unable to create a temporary PDF file.');
        }

        $downloaded = 0;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
            CURLOPT_USERAGENT => 'VMS Maintenance Template Extraction',
            CURLOPT_FAILONERROR => false,
            CURLOPT_NOPROGRESS => false,
            CURLOPT_PROGRESSFUNCTION => static function ($resource, float $downloadSize, float $downloadedSoFar, float $uploadSize = 0.0, float $uploadedSoFar = 0.0) use ($maxBytes, &$downloaded) {
                $downloaded = (int)$downloadedSoFar;
                if ($downloadedSoFar > $maxBytes) {
                    return 1;
                }
                return 0;
            },
        ]);
        $result = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $error = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if ($result === false || $httpCode >= 400) {
            @unlink($targetPath);
            throw new RuntimeException('Unable to fetch the saved source PDF' . ($httpCode > 0 ? ' (HTTP ' . $httpCode . ')' : '') . ($error !== '' ? ': ' . $error : '.'));
        }

        if ($downloaded > $maxBytes || filesize($targetPath) > $maxBytes) {
            @unlink($targetPath);
            throw new RuntimeException('The source PDF exceeds the configured file size limit.');
        }

        if ($contentType !== '' && stripos($contentType, 'pdf') === false && !preg_match('/\.pdf(?:\?|#|$)/i', $url)) {
            @unlink($targetPath);
            throw new RuntimeException('The saved source did not return a PDF document.');
        }
    }
}

if (!function_exists('vms_template_store_uploaded_pdf')) {
    function vms_template_store_uploaded_pdf(array $file, string $targetPath): void
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('PDF upload failed.');
        }

        $maxBytes = max(1, (int)(vms_template_extraction_get_config()['max_pdf_file_size_mb'] ?? 15)) * 1024 * 1024;
        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > $maxBytes) {
            throw new RuntimeException('Uploaded PDF exceeds the configured file size limit.');
        }

        $tmpName = (string)($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('Uploaded PDF could not be verified.');
        }

        $type = strtolower(trim((string)($file['type'] ?? '')));
        $originalName = strtolower(trim((string)($file['name'] ?? '')));
        if ($type !== 'application/pdf' && !str_ends_with($originalName, '.pdf')) {
            throw new RuntimeException('Only PDF uploads are allowed.');
        }

        $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
        $mime = $finfo ? (string)finfo_file($finfo, $tmpName) : '';
        if ($finfo) {
            finfo_close($finfo);
        }
        if ($mime !== '' && $mime !== 'application/pdf' && $mime !== 'application/x-pdf') {
            throw new RuntimeException('Uploaded file is not a valid PDF.');
        }

        if (!move_uploaded_file($tmpName, $targetPath)) {
            throw new RuntimeException('Unable to store the uploaded PDF for extraction.');
        }
    }
}

if (!function_exists('vms_template_render_pdf_pages_to_images')) {
    function vms_template_render_pdf_pages_to_images(string $pdfPath, array $pages, string $workDir): array
    {
        $support = vms_template_detect_pdf_conversion_support();
        if (empty($support['available'])) {
            throw new RuntimeException((string)($support['dependency_message'] ?? 'PDF conversion support is not available.'));
        }

        $method = (string)($support['method'] ?? '');
        $config = vms_template_extraction_get_config();
        $dpi = max(96, (int)($config['pdf_render_dpi'] ?? 144));
        if ($method === 'imagick') {
            $images = [];
            foreach ($pages as $page) {
                $imagePath = $workDir . DIRECTORY_SEPARATOR . 'page_' . (int)$page . '.png';
                $imagick = new Imagick();
                $imagick->setResolution($dpi, $dpi);
                $imagick->readImage($pdfPath . '[' . ((int)$page - 1) . ']');
                $imagick->setImageFormat('png');
                $imagick->writeImage($imagePath);
                $imagick->clear();
                $imagick->destroy();
                if (is_file($imagePath)) {
                    $images[] = $imagePath;
                }
            }
            return [
                'method' => 'imagick',
                'images' => $images,
                'diagnostics' => [
                    'command' => 'imagick extension',
                    'output_dir' => $workDir,
                    'generated_count' => count($images),
                    'generated_files' => array_map('basename', $images),
                    'exit_code' => 0,
                    'stderr' => '',
                ],
            ];
        }

        if ($method === 'pdftoppm') {
            $command = (string)($support['command'] ?? 'pdftoppm');
            $prefix = $workDir . DIRECTORY_SEPARATOR . 'page';
            $cmd = escapeshellarg($command) . ' -f ' . (int)min($pages) . ' -l ' . (int)max($pages) . ' -png ' . escapeshellarg($pdfPath) . ' ' . escapeshellarg($prefix) . ' 2>&1';
            $output = [];
            $code = 1;
            @exec($cmd, $output, $code);

            $patterns = [
                $prefix . '-*.png',
                $workDir . DIRECTORY_SEPARATOR . 'page-*.png',
                $workDir . DIRECTORY_SEPARATOR . 'output-*.png',
                $workDir . DIRECTORY_SEPARATOR . '*.png',
            ];
            $found = [];
            foreach ($patterns as $pattern) {
                foreach (glob($pattern) ?: [] as $match) {
                    if (is_file($match)) {
                        $found[$match] = $match;
                    }
                }
            }

            $images = array_values($found);
            usort($images, static function (string $a, string $b): int {
                return strnatcmp(basename($a), basename($b));
            });

            if (!$images || $code !== 0) {
                return [
                    'method' => 'pdftoppm',
                    'images' => [],
                    'diagnostics' => [
                        'command' => preg_replace('/\s+2>&1$/', '', $cmd) ?: $cmd,
                        'output_dir' => $workDir,
                        'generated_count' => count($images),
                        'generated_files' => array_map('basename', $images),
                        'exit_code' => $code,
                        'stderr' => trim(implode("\n", $output)),
                    ],
                ];
            }

            return [
                'method' => 'pdftoppm',
                'images' => $images,
                'diagnostics' => [
                    'command' => preg_replace('/\s+2>&1$/', '', $cmd) ?: $cmd,
                    'output_dir' => $workDir,
                    'generated_count' => count($images),
                    'generated_files' => array_map('basename', $images),
                    'exit_code' => $code,
                    'stderr' => trim(implode("\n", $output)),
                ],
            ];
        }

        if ($method === 'magick') {
            $command = (string)($support['command'] ?? 'magick');
            $images = [];
            $commands = [];
            foreach ($pages as $page) {
                $imagePath = $workDir . DIRECTORY_SEPARATOR . 'page_' . (int)$page . '.png';
                $cmd = escapeshellarg($command) . ' -density ' . $dpi . ' ' . escapeshellarg($pdfPath . '[' . ((int)$page - 1) . ']') . ' -quality 90 ' . escapeshellarg($imagePath);
                $commands[] = $cmd;
                $output = [];
                $code = 1;
                @exec($cmd, $output, $code);
                if ($code === 0 && is_file($imagePath)) {
                    $images[] = $imagePath;
                }
            }
            return [
                'method' => 'magick',
                'images' => $images,
                'diagnostics' => [
                    'command' => implode(' ; ', $commands),
                    'output_dir' => $workDir,
                    'generated_count' => count($images),
                    'generated_files' => array_map('basename', $images),
                    'exit_code' => isset($code) ? (int)$code : 0,
                    'stderr' => '',
                ],
            ];
        }

        throw new RuntimeException('Supported PDF conversion method was not available.');
    }
}

if (!function_exists('vms_template_encode_image_data_url')) {
    function vms_template_encode_image_data_url(string $imagePath): string
    {
        if (!is_file($imagePath) || !is_readable($imagePath)) {
            throw new RuntimeException('Rendered PDF page image is not readable.');
        }

        $mime = function_exists('mime_content_type') ? (string)mime_content_type($imagePath) : 'image/png';
        if ($mime === '') {
            $mime = 'image/png';
        }
        if (!in_array($mime, ['image/png', 'image/x-png'], true)) {
            throw new RuntimeException('Rendered PDF page image was not a PNG file.');
        }

        $contents = file_get_contents($imagePath);
        if ($contents === false) {
            throw new RuntimeException('Unable to read a rendered PDF page image.');
        }

        return 'data:image/png;base64,' . base64_encode($contents);
    }
}

if (!function_exists('vms_template_parse_openai_error_details')) {
    function vms_template_parse_openai_error_details(string $response, int $httpCode): array
    {
        $safeMessage = 'HTTP ' . $httpCode . ' returned from OpenAI request.';
        $safeType = '';
        $safeCode = '';

        $decoded = json_decode($response, true);
        if (is_array($decoded) && isset($decoded['error']) && is_array($decoded['error'])) {
            $safeMessage = trim((string)($decoded['error']['message'] ?? $safeMessage));
            $safeType = trim((string)($decoded['error']['type'] ?? ''));
            $safeCode = trim((string)($decoded['error']['code'] ?? ''));
        }

        return [
            'message' => $safeMessage,
            'type' => $safeType,
            'code' => $safeCode,
            'http_status' => $httpCode,
        ];
    }
}

if (!function_exists('vms_template_openai_error_is_invalid_model')) {
    function vms_template_openai_error_is_invalid_model(array $errorDetails): bool
    {
        $message = strtolower(trim((string)($errorDetails['message'] ?? '')));
        $type = strtolower(trim((string)($errorDetails['type'] ?? '')));
        $code = strtolower(trim((string)($errorDetails['code'] ?? '')));

        return str_contains($message, 'model')
            && (str_contains($message, 'not found') || str_contains($message, 'invalid') || str_contains($message, 'unsupported'))
            || in_array($type, ['invalid_request_error', 'model_not_found'], true)
            || in_array($code, ['model_not_found', 'invalid_model'], true);
    }
}

if (!function_exists('vms_template_collect_image_previews')) {
    function vms_template_collect_image_previews(array $imagePaths, int $maxImages = 3, int $maxTotalBytes = 1800000): array
    {
        $previews = [];
        $usedBytes = 0;

        foreach ($imagePaths as $imagePath) {
            if (count($previews) >= $maxImages) {
                break;
            }

            try {
                $dataUrl = vms_template_encode_image_data_url((string)$imagePath);
            } catch (Throwable $e) {
                continue;
            }

            $size = strlen($dataUrl);
            if ($usedBytes + $size > $maxTotalBytes) {
                break;
            }

            $previews[] = [
                'name' => basename((string)$imagePath),
                'data_url' => $dataUrl,
            ];
            $usedBytes += $size;
        }

        return $previews;
    }
}

if (!function_exists('vms_template_clean_maintenance_text')) {
    function vms_template_clean_maintenance_text(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $text = preg_replace('/\b[o0]\b/u', ' ', $text ?? '');
        $text = preg_replace('/\s*\(\s*\d+\s*\)\s*$/u', '', $text ?? '');
        $text = preg_replace('/^\s*\(?\d+\)?\s*$/u', '', $text ?? '');
        $text = preg_replace('/^\s*page\s*\d+\s*$/iu', '', $text ?? '');
        $text = preg_replace('/^\s*\d+\s*$/u', '', $text ?? '');
        $text = preg_replace('/\s+/', ' ', $text ?? '');
        $text = trim((string)$text, " \t\n\r\0\x0B-:;,.()");

        if ($text === '' || preg_match('/^(unknown|n\/a|na|none)$/i', $text)) {
            return '';
        }

        return $text;
    }
}

if (!function_exists('vms_template_generate_extraction_run_id')) {
    function vms_template_generate_extraction_run_id(): string
    {
        return 'mte_' . date('YmdHis') . '_' . bin2hex(random_bytes(4));
    }
}

if (!function_exists('vms_template_guess_confidence_tier')) {
    function vms_template_guess_confidence_tier(string $confidenceLabel): string
    {
        $normalized = strtolower(trim($confidenceLabel));
        if ($normalized === '') {
            return 'medium';
        }
        if (str_contains($normalized, 'low')) {
            return 'low';
        }
        if (str_contains($normalized, 'high')) {
            return 'high';
        }
        return 'medium';
    }
}

if (!function_exists('vms_template_row_warning_flags')) {
    function vms_template_row_warning_flags(array $row): array
    {
        $warnings = [];

        $combinedStep = vms_template_clean_maintenance_text((string)($row['combined_step'] ?? $row['steps'] ?? ''));
        $itemName = vms_template_clean_maintenance_text((string)($row['item_name'] ?? $row['component'] ?? ''));
        $actionName = vms_template_clean_maintenance_text((string)($row['action_name'] ?? ''));
        $intervalLabel = vms_template_clean_maintenance_text((string)($row['interval_label'] ?? ''));
        $sourceExcerpt = vms_template_clean_maintenance_text((string)($row['source_excerpt'] ?? ''));
        $hours = isset($row['interval_hours']) && $row['interval_hours'] !== '' ? (int)$row['interval_hours'] : null;
        $months = isset($row['interval_months']) && $row['interval_months'] !== '' ? (int)$row['interval_months'] : null;

        if ($combinedStep === '' || vms_template_is_generic_action($combinedStep)) {
            $warnings[] = 'Generic step';
        }
        if ($intervalLabel !== '' && $hours === null && $months === null) {
            $warnings[] = 'Interval missing value';
        }
        if ($sourceExcerpt === '') {
            $warnings[] = 'Source excerpt missing';
        }
        if ($itemName === '' || $actionName === '') {
            $warnings[] = 'Item/action incomplete';
        }
        if (preg_match('/^\(?\d+\)?$/', $itemName) || preg_match('/^\(?\d+\)?$/', $combinedStep)) {
            $warnings[] = 'Artifact-like row';
        }
        if ($intervalLabel !== '') {
            $label = strtolower($intervalLabel);
            if ($hours !== null && str_contains($label, 'month') && !str_contains($label, 'hour')) {
                $warnings[] = 'Interval label mismatch';
            }
            if ($months !== null && str_contains($label, 'hour') && !str_contains($label, 'month')) {
                $warnings[] = 'Interval label mismatch';
            }
        }

        return array_values(array_unique($warnings));
    }
}

if (!function_exists('vms_template_normalize_extraction_row')) {
    function vms_template_normalize_extraction_row(array $source, array $row): array
    {
        $itemName = vms_template_clean_maintenance_text((string)($row['item_name'] ?? $row['component'] ?? ''));
        $actionName = vms_template_normalize_action_label((string)($row['action_name'] ?? ''));
        $combinedStep = vms_template_clean_maintenance_text((string)($row['combined_step'] ?? ''));
        $serviceName = vms_template_clean_maintenance_text((string)($row['service_name'] ?? ''));
        $steps = vms_template_clean_maintenance_text((string)($row['steps'] ?? ''));

        if ($itemName === '' || $actionName === '') {
            [$serviceComponent, $serviceAction] = vms_template_split_service_name_parts($serviceName);
            [$stepsComponent, $stepsAction] = vms_template_split_service_name_parts($steps);
            if ($itemName === '') {
                $itemName = $serviceComponent !== '' ? $serviceComponent : $stepsComponent;
            }
            if ($actionName === '') {
                $actionName = $stepsAction !== '' ? $stepsAction : $serviceAction;
            }
        }

        if ($combinedStep === '') {
            $combinedStep = vms_template_build_descriptive_step_text([
                'service_name' => $serviceName,
                'component' => $itemName,
                'steps' => $steps,
            ]);
        }

        $intervalLabel = vms_template_clean_maintenance_text((string)($row['interval_label'] ?? ''));
        $markedCellValue = vms_template_clean_maintenance_text((string)($row['marked_cell_value'] ?? ''));
        $sourceExcerpt = vms_template_clean_maintenance_text((string)($row['source_excerpt'] ?? ''));
        $footnoteRefs = vms_template_clean_maintenance_text((string)($row['footnote_refs'] ?? ''));
        $confidenceLabel = trim((string)($row['confidence_label'] ?? 'Medium confidence'));

        return [
            'equipment_type' => trim((string)($row['equipment_type'] ?? ($source['equipment_type'] ?? ''))) ?: null,
            'manufacturer' => trim((string)($row['manufacturer'] ?? ($source['manufacturer'] ?? ''))) ?: null,
            'model' => trim((string)($row['model'] ?? ($source['model'] ?? ''))) ?: null,
            'item_name' => $itemName !== '' ? $itemName : null,
            'action_name' => $actionName !== '' ? $actionName : null,
            'combined_step' => $combinedStep !== '' ? $combinedStep : null,
            'interval_label' => $intervalLabel !== '' ? $intervalLabel : null,
            'interval_hours' => isset($row['interval_hours']) && $row['interval_hours'] !== '' ? (int)$row['interval_hours'] : null,
            'interval_months' => isset($row['interval_months']) && $row['interval_months'] !== '' ? (int)$row['interval_months'] : null,
            'interval_basis' => trim((string)($row['interval_basis'] ?? '')) ?: null,
            'marked_cell_value' => $markedCellValue !== '' ? $markedCellValue : null,
            'source_excerpt' => $sourceExcerpt !== '' ? $sourceExcerpt : null,
            'footnote_refs' => $footnoteRefs !== '' ? $footnoteRefs : null,
            'confidence_label' => $confidenceLabel !== '' ? $confidenceLabel : 'Medium confidence',
        ];
    }
}

if (!function_exists('vms_template_is_generic_action')) {
    function vms_template_is_generic_action(string $text): bool
    {
        $normalized = strtolower(trim((string)$text));
        $normalized = str_replace([' - ', '-', '_'], ' ', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized ?? '');

        return in_array($normalized, [
            'check',
            'inspect',
            'replace',
            'change',
            'service',
            'clean',
            'lubricate',
            'check adjust',
            'check/adjust',
            'adjust',
            'tighten',
        ], true);
    }
}

if (!function_exists('vms_template_normalize_action_label')) {
    function vms_template_normalize_action_label(string $text): string
    {
        $text = vms_template_clean_maintenance_text($text);
        if ($text === '') {
            return '';
        }

        $text = str_ireplace(['check-adjust', 'check adjust'], 'Check/adjust', $text);
        $text = preg_replace('/\bcheck\s*\/\s*adjust\b/i', 'Check/adjust', $text ?? '');
        $text = preg_replace('/\bchange\b/i', 'Change', $text ?? '');
        $text = preg_replace('/\breplace\b/i', 'Replace', $text ?? '');
        $text = preg_replace('/\binspect\b/i', 'Inspect', $text ?? '');
        $text = preg_replace('/\bcheck\b/i', 'Check', $text ?? '');
        $text = preg_replace('/\bservice\b/i', 'Service', $text ?? '');
        $text = preg_replace('/\bclean\b/i', 'Clean', $text ?? '');
        $text = preg_replace('/\blubricate\b/i', 'Lubricate', $text ?? '');
        $text = preg_replace('/\s+/', ' ', $text ?? '');

        return trim((string)$text);
    }
}

if (!function_exists('vms_template_split_service_name_parts')) {
    function vms_template_split_service_name_parts(string $serviceName): array
    {
        $serviceName = vms_template_clean_maintenance_text($serviceName);
        if ($serviceName === '') {
            return ['', ''];
        }

        if (preg_match('/^(.+?)\s*-\s*(check\/adjust|check-adjust|check adjust|check|inspect|replace|change|service|clean|lubricate)\s*$/i', $serviceName, $match)) {
            return [
                vms_template_clean_maintenance_text((string)$match[1]),
                vms_template_normalize_action_label((string)$match[2]),
            ];
        }

        if (preg_match('/^(check\/adjust|check-adjust|check adjust|check|inspect|replace|change|service|clean|lubricate)\s+(.+)$/i', $serviceName, $match)) {
            return [
                vms_template_clean_maintenance_text((string)$match[2]),
                vms_template_normalize_action_label((string)$match[1]),
            ];
        }

        return [$serviceName, ''];
    }
}

if (!function_exists('vms_template_build_descriptive_step_text')) {
    function vms_template_build_descriptive_step_text(array $candidate): string
    {
        $serviceName = vms_template_clean_maintenance_text((string)($candidate['service_name'] ?? ''));
        $component = vms_template_clean_maintenance_text((string)($candidate['component'] ?? ''));
        $steps = vms_template_clean_maintenance_text((string)($candidate['steps'] ?? ''));

        [$serviceComponent, $serviceAction] = vms_template_split_service_name_parts($serviceName);
        [$stepsComponent, $stepsAction] = vms_template_split_service_name_parts($steps);

        $action = $stepsAction !== '' ? $stepsAction : $serviceAction;
        $item = $component !== '' ? $component : ($stepsComponent !== '' && !vms_template_is_generic_action($stepsComponent) ? $stepsComponent : $serviceComponent);

        if ($steps !== '' && !vms_template_is_generic_action($steps) && !preg_match('/^[A-Z][a-z]+$/', $steps)) {
            return $steps;
        }

        if ($action !== '' && $item !== '') {
            return trim($action . ' ' . $item);
        }

        if ($serviceName !== '' && !vms_template_is_generic_action($serviceName)) {
            return $serviceName;
        }

        if ($steps !== '' && !vms_template_is_generic_action($steps)) {
            return $steps;
        }

        if ($action !== '') {
            return $action;
        }

        return $item;
    }
}

if (!function_exists('vms_template_normalize_interval_basis')) {
    function vms_template_normalize_interval_basis($basis, ?int $hours, ?int $months, string $serviceName = '', string $steps = '', string $excerpt = ''): string
    {
        $haystack = strtolower(trim(implode(' ', [
            (string)$basis,
            $serviceName,
            $steps,
            $excerpt,
        ])));

        if (str_contains($haystack, 'every use') || str_contains($haystack, 'each use')) {
            return 'every_use';
        }
        if (str_contains($haystack, 'after use')) {
            return 'after_use';
        }
        if (str_contains($haystack, 'initial') || str_contains($haystack, 'first ') || str_contains($haystack, 'break-in')) {
            return 'initial_service';
        }

        $basis = strtolower(trim((string)$basis));
        if ($hours !== null && $months !== null) {
            return 'hours_or_months';
        }
        if ($hours !== null) {
            return 'hours';
        }
        if ($months !== null) {
            return 'months';
        }
        if (in_array($basis, ['every_use', 'after_use', 'initial_service', 'hours_or_months', 'hours', 'months'], true)) {
            return $basis;
        }

        return 'unknown';
    }
}

if (!function_exists('vms_template_format_month_service_label')) {
    function vms_template_format_month_service_label(int $months): string
    {
        if ($months === 12) {
            return 'Annual';
        }
        if ($months === 24) {
            return '2-Year';
        }
        if ($months % 12 === 0 && $months > 24) {
            return (int)($months / 12) . '-Year';
        }

        return $months . '-Month';
    }
}

if (!function_exists('vms_template_build_group_service_name')) {
    function vms_template_build_group_service_name(array $group): string
    {
        $basis = (string)($group['interval_basis'] ?? 'unknown');
        $hours = isset($group['interval_hours']) && $group['interval_hours'] !== '' ? (int)$group['interval_hours'] : null;
        $months = isset($group['interval_months']) && $group['interval_months'] !== '' ? (int)$group['interval_months'] : null;

        if ($basis === 'every_use') {
            return 'Every Use Inspection';
        }
        if ($basis === 'after_use') {
            return 'After Use Service';
        }
        if ($basis === 'initial_service') {
            if ($hours !== null && $months !== null) {
                return 'Initial ' . $hours . '-Hour / ' . vms_template_format_month_service_label($months) . ' Service';
            }
            if ($hours !== null) {
                return 'Initial ' . $hours . '-Hour Service';
            }
            if ($months !== null) {
                return 'Initial ' . vms_template_format_month_service_label($months) . ' Service';
            }
            return 'Initial Service';
        }

        $parts = [];
        if ($hours !== null) {
            $parts[] = $hours . '-Hour';
        }
        if ($months !== null) {
            $parts[] = vms_template_format_month_service_label($months);
        }

        if (!$parts) {
            return 'Maintenance Service Package';
        }

        return implode(' / ', $parts) . ' Service';
    }
}

if (!function_exists('vms_template_build_group_step_line')) {
    function vms_template_build_group_step_line(array $candidate): string
    {
        $line = vms_template_build_descriptive_step_text($candidate);
        $line = preg_replace('/^\s*[-*]\s*/', '', $line ?? '');
        $line = vms_template_clean_maintenance_text((string)$line);
        return $line;
    }
}

if (!function_exists('vms_template_trim_combined_excerpt')) {
    function vms_template_trim_combined_excerpt(array $excerpts, int $maxChars = 1500): string
    {
        $clean = [];
        foreach ($excerpts as $excerpt) {
            $excerpt = trim((string)$excerpt);
            if ($excerpt !== '' && !in_array($excerpt, $clean, true)) {
                $clean[] = $excerpt;
            }
        }

        $combined = implode("\n\n", $clean);
        if ($combined === '') {
            return '';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($combined) <= $maxChars) {
                return $combined;
            }
            return rtrim(mb_substr($combined, 0, $maxChars - 3)) . '...';
        }

        if (strlen($combined) <= $maxChars) {
            return $combined;
        }

        return rtrim(substr($combined, 0, $maxChars - 3)) . '...';
    }
}

if (!function_exists('vms_template_group_candidates_by_interval')) {
    function vms_template_group_candidates_by_interval(array $candidates): array
    {
        if (!$candidates) {
            return [
                'items' => [],
                'original_count' => 0,
                'grouped_count' => 0,
                'grouped' => false,
            ];
        }

        $groups = [];
        foreach ($candidates as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }

            $hours = isset($candidate['interval_hours']) && $candidate['interval_hours'] !== '' ? (int)$candidate['interval_hours'] : null;
            $months = isset($candidate['interval_months']) && $candidate['interval_months'] !== '' ? (int)$candidate['interval_months'] : null;
            $basis = vms_template_normalize_interval_basis(
                $candidate['interval_basis'] ?? '',
                $hours,
                $months,
                (string)($candidate['service_name'] ?? ''),
                (string)($candidate['steps'] ?? ''),
                (string)($candidate['source_excerpt'] ?? '')
            );

            $key = implode('|', [
                $basis,
                $hours !== null ? (string)$hours : '',
                $months !== null ? (string)$months : '',
            ]);

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'equipment_type' => trim((string)($candidate['equipment_type'] ?? '')),
                    'manufacturer' => trim((string)($candidate['manufacturer'] ?? '')),
                    'model' => trim((string)($candidate['model'] ?? '')),
                    'interval_hours' => $hours,
                    'interval_months' => $months,
                    'interval_basis' => $basis,
                    'steps_list' => [],
                    'source_excerpts' => [],
                    'confidence_labels' => [],
                    'template_origins' => [],
                ];
            }

            $stepLine = vms_template_build_group_step_line($candidate);
            if ($stepLine !== '' && !in_array($stepLine, $groups[$key]['steps_list'], true)) {
                $groups[$key]['steps_list'][] = $stepLine;
            }

            $excerpt = trim((string)($candidate['source_excerpt'] ?? ''));
            if ($excerpt !== '' && !in_array($excerpt, $groups[$key]['source_excerpts'], true)) {
                $groups[$key]['source_excerpts'][] = $excerpt;
            }

            $confidence = trim((string)($candidate['confidence_label'] ?? ''));
            if ($confidence !== '' && !in_array($confidence, $groups[$key]['confidence_labels'], true)) {
                $groups[$key]['confidence_labels'][] = $confidence;
            }

            $origin = trim((string)($candidate['template_origin'] ?? ''));
            if ($origin !== '' && !in_array($origin, $groups[$key]['template_origins'], true)) {
                $groups[$key]['template_origins'][] = $origin;
            }
        }

        $grouped = [];
        foreach ($groups as $group) {
            $serviceName = vms_template_build_group_service_name($group);
            $steps = '';
            if (!empty($group['steps_list'])) {
                $steps = "- " . implode("\n- ", $group['steps_list']);
            }

            $confidenceLabel = 'Grouped extraction draft';
            if (!empty($group['confidence_labels'])) {
                $confidenceLabel = implode(' + ', $group['confidence_labels']);
                if (!str_contains(strtolower($confidenceLabel), 'group')) {
                    $confidenceLabel .= ' + Grouped service package';
                }
            }

            $grouped[] = [
                'equipment_type' => $group['equipment_type'] !== '' ? $group['equipment_type'] : null,
                'manufacturer' => $group['manufacturer'] !== '' ? $group['manufacturer'] : null,
                'model' => $group['model'] !== '' ? $group['model'] : null,
                'service_name' => $serviceName,
                'interval_hours' => $group['interval_hours'],
                'interval_months' => $group['interval_months'],
                'interval_basis' => $group['interval_basis'],
                'steps' => $steps !== '' ? $steps : null,
                'source_excerpt' => vms_template_trim_combined_excerpt($group['source_excerpts']),
                'confidence_label' => $confidenceLabel,
                'template_origin' => !empty($group['template_origins']) ? implode('+', $group['template_origins']) . '_grouped' : 'grouped',
            ];
        }

        usort($grouped, static function (array $a, array $b): int {
            $aHours = $a['interval_hours'] ?? PHP_INT_MAX;
            $bHours = $b['interval_hours'] ?? PHP_INT_MAX;
            if ($aHours !== $bHours) {
                return $aHours <=> $bHours;
            }

            $aMonths = $a['interval_months'] ?? PHP_INT_MAX;
            $bMonths = $b['interval_months'] ?? PHP_INT_MAX;
            if ($aMonths !== $bMonths) {
                return $aMonths <=> $bMonths;
            }

            return strcmp((string)($a['service_name'] ?? ''), (string)($b['service_name'] ?? ''));
        });

        return [
            'items' => $grouped,
            'original_count' => count($candidates),
            'grouped_count' => count($grouped),
            'grouped' => count($candidates) > 1 && count($grouped) > 0,
        ];
    }
}

if (!function_exists('vms_template_create_extraction_run')) {
    function vms_template_create_extraction_run(PDO $pdo, array $data): string
    {
        if (!vms_template_extraction_runs_table_exists($pdo)) {
            throw new RuntimeException('Extraction run table is not available yet. Apply equipment_maintenance_extraction_phase3.sql first.');
        }

        $runId = trim((string)($data['extraction_run_id'] ?? ''));
        if ($runId === '') {
            $runId = vms_template_generate_extraction_run_id();
        }

        $stmt = $pdo->prepare("
            INSERT INTO equipment_maintenance_extraction_runs (
                extraction_run_id, source_id, input_type, page_range, provider, model_used,
                raw_candidate_count, created_grouped_template_count, status, created_by, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $runId,
            (int)($data['source_id'] ?? 0),
            trim((string)($data['input_type'] ?? 'pdf')) ?: 'pdf',
            trim((string)($data['page_range'] ?? '')) ?: null,
            trim((string)($data['provider'] ?? '')) ?: null,
            trim((string)($data['model_used'] ?? '')) ?: null,
            (int)($data['raw_candidate_count'] ?? 0),
            (int)($data['created_grouped_template_count'] ?? 0),
            trim((string)($data['status'] ?? 'pending_review')) ?: 'pending_review',
            (int)($data['created_by'] ?? 0),
        ]);

        return $runId;
    }
}

if (!function_exists('vms_template_update_extraction_run')) {
    function vms_template_update_extraction_run(PDO $pdo, string $runId, array $data): void
    {
        if (!vms_template_extraction_runs_table_exists($pdo) || $runId === '') {
            return;
        }

        $fields = [];
        $params = [];
        foreach ([
            'raw_candidate_count',
            'created_grouped_template_count',
            'status',
            'model_used',
            'page_range',
            'provider',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = $field . ' = ?';
                $params[] = $data[$field];
            }
        }

        if (!$fields) {
            return;
        }

        $sql = 'UPDATE equipment_maintenance_extraction_runs SET ' . implode(', ', $fields) . ', updated_at = NOW() WHERE extraction_run_id = ?';
        $params[] = $runId;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }
}

if (!function_exists('vms_template_insert_extraction_row')) {
    function vms_template_insert_extraction_row(PDO $pdo, array $source, string $runId, array $row, int $createdBy): int
    {
        if (!vms_template_extraction_rows_table_exists($pdo)) {
            throw new RuntimeException('Extraction row table is not available yet. Apply equipment_maintenance_extraction_phase3.sql first.');
        }

        $normalized = vms_template_normalize_extraction_row($source, $row);
        if (
            empty($normalized['combined_step'])
            && empty($normalized['item_name'])
            && empty($normalized['action_name'])
        ) {
            return 0;
        }

        $stmt = $pdo->prepare("
            INSERT INTO equipment_maintenance_extraction_rows (
                source_id, equipment_type, manufacturer, model, extraction_run_id,
                item_name, action_name, combined_step, interval_label, interval_hours,
                interval_months, interval_basis, marked_cell_value, source_excerpt, footnote_refs,
                confidence_label, review_status, reviewed_by, reviewed_at, created_by, created_at, updated_at, is_active
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NULL, NULL, ?, NOW(), NOW(), 1)
        ");
        $stmt->execute([
            (int)($source['source_id'] ?? 0),
            $normalized['equipment_type'],
            $normalized['manufacturer'],
            $normalized['model'],
            $runId !== '' ? $runId : null,
            $normalized['item_name'],
            $normalized['action_name'],
            $normalized['combined_step'],
            $normalized['interval_label'],
            $normalized['interval_hours'],
            $normalized['interval_months'],
            $normalized['interval_basis'],
            $normalized['marked_cell_value'],
            $normalized['source_excerpt'],
            $normalized['footnote_refs'],
            $normalized['confidence_label'],
            $createdBy,
        ]);

        return (int)$pdo->lastInsertId();
    }
}

if (!function_exists('vms_template_get_extraction_run')) {
    function vms_template_get_extraction_run(PDO $pdo, string $runId): ?array
    {
        if (!vms_template_extraction_runs_table_exists($pdo) || $runId === '') {
            return null;
        }

        $stmt = $pdo->prepare("
            SELECT r.*, u.fName AS created_fName, u.lName AS created_lName
            FROM equipment_maintenance_extraction_runs r
            LEFT JOIN users u ON u.id = r.created_by
            WHERE r.extraction_run_id = ?
            LIMIT 1
        ");
        $stmt->execute([$runId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('vms_template_get_extraction_rows')) {
    function vms_template_get_extraction_rows(PDO $pdo, string $runId): array
    {
        if (!vms_template_extraction_rows_table_exists($pdo) || $runId === '') {
            return [];
        }

        $stmt = $pdo->prepare("
            SELECT er.*, ru.fName AS reviewed_fName, ru.lName AS reviewed_lName, cu.fName AS created_fName, cu.lName AS created_lName
            FROM equipment_maintenance_extraction_rows er
            LEFT JOIN users ru ON ru.id = er.reviewed_by
            LEFT JOIN users cu ON cu.id = er.created_by
            WHERE er.extraction_run_id = ?
              AND COALESCE(er.is_active, 1) = 1
            ORDER BY er.extraction_row_id ASC
        ");
        $stmt->execute([$runId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('vms_template_update_extraction_row')) {
    function vms_template_update_extraction_row(PDO $pdo, int $rowId, array $source, array $row, int $reviewedBy): void
    {
        if (!vms_template_extraction_rows_table_exists($pdo) || $rowId <= 0) {
            return;
        }

        $normalized = vms_template_normalize_extraction_row($source, $row);
        $reviewStatus = trim((string)($row['review_status'] ?? 'pending'));
        if (!in_array($reviewStatus, ['pending', 'accepted', 'rejected'], true)) {
            $reviewStatus = 'pending';
        }

        $stmt = $pdo->prepare("
            UPDATE equipment_maintenance_extraction_rows
            SET item_name = ?, action_name = ?, combined_step = ?, interval_label = ?,
                interval_hours = ?, interval_months = ?, interval_basis = ?, marked_cell_value = ?,
                source_excerpt = ?, footnote_refs = ?, confidence_label = ?, review_status = ?,
                reviewed_by = ?, reviewed_at = NOW(), updated_at = NOW()
            WHERE extraction_row_id = ?
        ");
        $stmt->execute([
            $normalized['item_name'],
            $normalized['action_name'],
            $normalized['combined_step'],
            $normalized['interval_label'],
            $normalized['interval_hours'],
            $normalized['interval_months'],
            $normalized['interval_basis'],
            $normalized['marked_cell_value'],
            $normalized['source_excerpt'],
            $normalized['footnote_refs'],
            $normalized['confidence_label'],
            $reviewStatus,
            $reviewedBy,
            $rowId,
        ]);
    }
}

if (!function_exists('vms_template_build_candidate_from_extraction_row')) {
    function vms_template_build_candidate_from_extraction_row(array $row): array
    {
        $combinedStep = vms_template_clean_maintenance_text((string)($row['combined_step'] ?? ''));
        $itemName = vms_template_clean_maintenance_text((string)($row['item_name'] ?? ''));
        $actionName = vms_template_normalize_action_label((string)($row['action_name'] ?? ''));

        $serviceName = $combinedStep;
        if ($serviceName === '') {
            $serviceName = $actionName !== '' && $itemName !== '' ? trim($actionName . ' ' . $itemName) : ($itemName !== '' ? $itemName : $actionName);
        }

        return [
            'equipment_type' => trim((string)($row['equipment_type'] ?? '')) ?: null,
            'manufacturer' => trim((string)($row['manufacturer'] ?? '')) ?: null,
            'model' => trim((string)($row['model'] ?? '')) ?: null,
            'service_name' => $serviceName,
            'component' => $itemName,
            'interval_hours' => isset($row['interval_hours']) && $row['interval_hours'] !== '' ? (int)$row['interval_hours'] : null,
            'interval_months' => isset($row['interval_months']) && $row['interval_months'] !== '' ? (int)$row['interval_months'] : null,
            'interval_basis' => trim((string)($row['interval_basis'] ?? '')) ?: null,
            'steps' => $combinedStep,
            'source_excerpt' => trim((string)($row['source_excerpt'] ?? '')) ?: null,
            'confidence_label' => trim((string)($row['confidence_label'] ?? 'Reviewed extraction row')) ?: 'Reviewed extraction row',
            'template_origin' => 'extraction_review',
        ];
    }
}

if (!function_exists('vms_template_create_templates_from_accepted_rows')) {
    function vms_template_create_templates_from_accepted_rows(PDO $pdo, array $source, string $runId, int $createdBy): array
    {
        $rows = vms_template_get_extraction_rows($pdo, $runId);
        $accepted = array_values(array_filter($rows, static function (array $row): bool {
            return (string)($row['review_status'] ?? 'pending') === 'accepted';
        }));

        $candidates = [];
        foreach ($accepted as $row) {
            $candidate = vms_template_build_candidate_from_extraction_row($row);
            if (trim((string)($candidate['service_name'] ?? '')) === '') {
                continue;
            }
            $candidates[] = $candidate;
        }

        $grouped = vms_template_group_candidates_by_interval($candidates);
        $inserted = 0;
        foreach (($grouped['items'] ?? []) as $candidate) {
            $inserted += vms_template_insert_row($pdo, $source, $candidate, $createdBy) > 0 ? 1 : 0;
        }

        vms_template_update_extraction_run($pdo, $runId, [
            'raw_candidate_count' => count($accepted),
            'created_grouped_template_count' => $inserted,
            'status' => 'templates_created',
        ]);

        return [
            'accepted_count' => count($accepted),
            'grouped_count' => (int)($grouped['grouped_count'] ?? 0),
            'inserted_template_count' => $inserted,
        ];
    }
}

if (!function_exists('vms_template_get_extraction_review_summary')) {
    function vms_template_get_extraction_review_summary(array $rows): array
    {
        $summary = [
            'pending' => 0,
            'accepted' => 0,
            'rejected' => 0,
            'low_confidence' => 0,
        ];

        foreach ($rows as $row) {
            $status = (string)($row['review_status'] ?? 'pending');
            if (isset($summary[$status])) {
                $summary[$status]++;
            }
            if (vms_template_guess_confidence_tier((string)($row['confidence_label'] ?? '')) === 'low') {
                $summary['low_confidence']++;
            }
        }

        return $summary;
    }
}

if (!function_exists('vms_template_get_source')) {
    function vms_template_get_source(PDO $pdo, int $sourceId): ?array
    {
        if (!vms_source_finder_table_exists($pdo, 'equipment_manual_sources')) {
            return null;
        }

        $stmt = $pdo->prepare("
            SELECT
                s.*,
                u.fName AS approved_fName,
                u.lName AS approved_lName,
                e.equipmentName,
                e.equipmentLocation,
                e.eid AS linked_equipment_id,
                v.vessel_id,
                v.vesselName,
                o.company_name
            FROM equipment_manual_sources s
            LEFT JOIN users u ON u.id = s.approved_by
            LEFT JOIN equipment e ON e.eid = s.equipment_id
            LEFT JOIN vessels v ON v.vessel_id = e.vessel_id
            LEFT JOIN owners o ON o.owner_id = v.company_id
            WHERE s.source_id = ?
            LIMIT 1
        ");
        $stmt->execute([$sourceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('vms_template_extract_text_candidates')) {
    function vms_template_extract_text_candidates(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $segments = preg_split('/[\r\n]+|(?<=\.)\s+/', $text) ?: [];
        $candidates = [];

        foreach ($segments as $segment) {
            $segment = trim((string)$segment);
            $segment = trim($segment, " \t\n\r\0\x0B.;");
            if ($segment === '') {
                continue;
            }

            $baseService = $segment;
            $instruction = $segment;
            if (str_contains($segment, ':')) {
                [$baseService, $instruction] = array_map('trim', explode(':', $segment, 2));
            }

            $serviceBase = trim(preg_replace('/\s+/', ' ', $baseService));
            $instruction = trim(preg_replace('/\s+/', ' ', $instruction));
            $lineMatches = [];

            if (preg_match_all('/\b(inspect|replace|change|clean|check|service|lubricate)\b[^0-9]{0,60}?\b(?:every|each)\s+(\d{1,5})\s*hours?\b/i', $instruction, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $action = ucfirst(strtolower(trim((string)$match[1])));
                    $hours = (int)$match[2];
                    $key = strtolower($serviceBase . '|' . $action);
                    $lineMatches[$key] = $lineMatches[$key] ?? [
                        'service_name' => trim($serviceBase !== '' ? ($serviceBase . ' - ' . $action) : $action),
                        'interval_hours' => null,
                        'interval_months' => null,
                        'interval_basis' => 'hours',
                        'steps' => '',
                        'source_excerpt' => $segment,
                        'confidence_label' => 'Draft heuristic match',
                        'template_origin' => 'heuristic',
                    ];
                    $lineMatches[$key]['interval_hours'] = $hours;
                }
            }

            if (preg_match_all('/\b(inspect|replace|change|clean|check|service|lubricate)\b[^0-9]{0,60}?\b(?:every|each)\s+(\d{1,3})\s*months?\b/i', $instruction, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $action = ucfirst(strtolower(trim((string)$match[1])));
                    $months = (int)$match[2];
                    $key = strtolower($serviceBase . '|' . $action);
                    $lineMatches[$key] = $lineMatches[$key] ?? [
                        'service_name' => trim($serviceBase !== '' ? ($serviceBase . ' - ' . $action) : $action),
                        'interval_hours' => null,
                        'interval_months' => null,
                        'interval_basis' => 'months',
                        'steps' => '',
                        'source_excerpt' => $segment,
                        'confidence_label' => 'Draft heuristic match',
                        'template_origin' => 'heuristic',
                    ];
                    $lineMatches[$key]['interval_months'] = $months;
                    if ($lineMatches[$key]['interval_basis'] === '') {
                        $lineMatches[$key]['interval_basis'] = 'months';
                    }
                }
            }

            if (preg_match_all('/\b(inspect|replace|change|clean|check|service|lubricate)\b[^.]*?\bannually\b/i', $instruction, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $action = ucfirst(strtolower(trim((string)$match[1])));
                    $key = strtolower($serviceBase . '|' . $action);
                    $lineMatches[$key] = $lineMatches[$key] ?? [
                        'service_name' => trim($serviceBase !== '' ? ($serviceBase . ' - ' . $action) : $action),
                        'interval_hours' => null,
                        'interval_months' => null,
                        'interval_basis' => 'months',
                        'steps' => '',
                        'source_excerpt' => $segment,
                        'confidence_label' => 'Draft heuristic match',
                        'template_origin' => 'heuristic',
                    ];
                    $lineMatches[$key]['interval_months'] = 12;
                }
            }

            if (!$lineMatches && preg_match('/^([A-Za-z][A-Za-z0-9 \/\-,&()]{2,120}?)\s+(?:every|each)\s+(\d{1,5})\s*hours?\b/i', $instruction, $match)) {
                $service = trim((string)$match[1]);
                $hours = (int)$match[2];
                $key = strtolower($service . '|hours');
                $lineMatches[$key] = [
                    'service_name' => ucwords($service),
                    'interval_hours' => $hours,
                    'interval_months' => null,
                    'interval_basis' => 'hours',
                    'steps' => '',
                    'source_excerpt' => $segment,
                    'confidence_label' => 'Draft heuristic match',
                    'template_origin' => 'heuristic',
                ];
            }

            foreach ($lineMatches as $key => $candidate) {
                $candidates[$key . '|' . (string)($candidate['interval_hours'] ?? '') . '|' . (string)($candidate['interval_months'] ?? '')] = $candidate;
            }
        }

        return array_values($candidates);
    }
}

if (!function_exists('vms_template_extract_candidates_from_source')) {
    function vms_template_extract_candidates_from_source(array $source, ?string $manualContent = null): array
    {
        $combinedText = vms_template_build_extraction_input($source, $manualContent);
        return vms_template_extract_text_candidates($combinedText);
    }
}

if (!function_exists('vms_template_extract_candidates_via_provider')) {
    function vms_template_extract_candidates_via_provider(array $source, ?string $manualContent = null): array
    {
        return vms_template_extract_candidates_via_provider_detailed($source, $manualContent)['items'];
    }
}

if (!function_exists('vms_template_extract_candidates_via_provider_detailed')) {
    function vms_template_extract_candidates_via_provider_detailed(array $source, ?string $manualContent = null): array
    {
        if (!vms_template_extraction_is_configured()) {
            return [
                'attempted' => false,
                'items' => [],
                'error' => 'Provider not configured.',
                'http_status' => null,
            ];
        }

        $config = vms_template_extraction_get_config();
        $provider = strtolower(trim((string)($config['provider'] ?? '')));
        if ($provider !== 'openai') {
            return [
                'attempted' => false,
                'items' => [],
                'error' => 'Configured provider is not supported.',
                'http_status' => null,
            ];
        }

        $inputText = vms_template_build_extraction_input($source, $manualContent);
        if ($inputText === '') {
            return [
                'attempted' => false,
                'items' => [],
                'error' => 'No extraction input text was available.',
                'http_status' => null,
            ];
        }

        $modelsToTry = [];
        $primaryModel = trim((string)($config['openai_model'] ?? $config['model'] ?? ''));
        if ($primaryModel === '') {
            $primaryModel = 'gpt-4o-mini';
        }
        $modelsToTry[] = $primaryModel;
        $fallbackModel = trim((string)($config['fallback_openai_model'] ?? ''));
        if ($fallbackModel !== '' && $fallbackModel !== $primaryModel) {
            $modelsToTry[] = $fallbackModel;
        }

        $maxOutputTokens = max(200, (int)($config['max_output_tokens'] ?? 1200));
        $lastError = '';
        $lastHttpCode = null;

        foreach ($modelsToTry as $model) {
            $payload = [
                'model' => $model,
                'max_tokens' => $maxOutputTokens,
            'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Extract maintenance schedule items from the provided equipment maintenance content. Return JSON with key "items" as an array. Each item should include service_name, interval_hours, interval_months, interval_basis, steps, source_excerpt, confidence_label. Use null when a field is unknown. Keep steps concise.',
                    ],
                    [
                        'role' => 'user',
                        'content' => json_encode([
                            'source_title' => (string)($source['title'] ?? ''),
                            'equipment_type' => (string)($source['equipment_type'] ?? ''),
                            'manufacturer' => (string)($source['manufacturer'] ?? ''),
                            'model' => (string)($source['model'] ?? ''),
                            'source_notes' => (string)($source['notes'] ?? ''),
                            'manual_content' => $inputText,
                        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    ],
                ],
            ];

            $ch = curl_init('https://api.openai.com/v1/chat/completions');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_TIMEOUT => max(10, (int)($config['timeout_seconds'] ?? 20)),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . (string)$config['api_key'],
                ],
                CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);

            $response = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            $lastHttpCode = $httpCode;

            if ($response === false || $response === '' || $httpCode >= 400) {
                if ($httpCode === 429) {
                    return [
                        'attempted' => true,
                        'items' => [],
                        'error' => 'OpenAI rate limit/quota reached. Try again later or check API billing/limits.',
                        'http_status' => $httpCode,
                    ];
                }
                $lastError = $curlError !== '' ? ('HTTP ' . $httpCode . ': ' . $curlError) : ('HTTP ' . $httpCode . ' returned from OpenAI extraction request.');
                continue;
            }

            $decoded = json_decode($response, true);
            if (!is_array($decoded)) {
                $lastError = 'JSON parse failure from provider response.';
                continue;
            }

            $content = trim((string)($decoded['choices'][0]['message']['content'] ?? ''));
            if ($content === '') {
                $lastError = 'Empty model response.';
                continue;
            }

            if (preg_match('/```(?:json)?\s*(.*?)```/is', $content, $match)) {
                $content = trim((string)$match[1]);
            }

            $json = json_decode($content, true);
            if (!is_array($json) || !isset($json['items']) || !is_array($json['items'])) {
                $lastError = 'Invalid JSON schema returned by provider.';
                continue;
            }

            $items = [];
            foreach ($json['items'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $serviceName = trim((string)($item['service_name'] ?? ''));
                if ($serviceName === '') {
                    continue;
                }
                $items[] = [
                    'service_name' => $serviceName,
                    'interval_hours' => isset($item['interval_hours']) && $item['interval_hours'] !== '' ? (int)$item['interval_hours'] : null,
                    'interval_months' => isset($item['interval_months']) && $item['interval_months'] !== '' ? (int)$item['interval_months'] : null,
                    'interval_basis' => trim((string)($item['interval_basis'] ?? '')),
                    'steps' => trim((string)($item['steps'] ?? '')),
                    'source_excerpt' => trim((string)($item['source_excerpt'] ?? '')),
                    'confidence_label' => trim((string)($item['confidence_label'] ?? 'AI extraction draft')),
                    'template_origin' => 'provider',
                ];
            }

            return [
                'attempted' => true,
                'items' => $items,
                'error' => '',
                'http_status' => $httpCode,
            ];
        }

        return [
            'attempted' => true,
            'items' => [],
            'error' => $lastError !== '' ? $lastError : 'Advanced extraction returned no usable results.',
            'http_status' => $lastHttpCode,
        ];
    }
}

if (!function_exists('vms_template_extract_candidates_from_pdf_images_detailed')) {
    function vms_template_extract_candidates_from_pdf_images_detailed(array $source, array $imagePaths, array $pages, string $pageRange, string $sourceUsed = 'saved_source_pdf'): array
    {
        if (!vms_template_extraction_is_configured()) {
            return [
                'attempted' => false,
                'items' => [],
                'error' => 'Provider not configured.',
                'http_status' => null,
            ];
        }

        if (!$imagePaths) {
            return [
                'attempted' => false,
                'items' => [],
                'error' => 'No rendered PDF page images were available for extraction.',
                'http_status' => null,
            ];
        }

        $config = vms_template_extraction_get_config();
        $provider = strtolower(trim((string)($config['provider'] ?? '')));
        if ($provider !== 'openai') {
            return [
                'attempted' => false,
                'items' => [],
                'error' => 'Configured provider is not supported for PDF extraction.',
                'http_status' => null,
            ];
        }

        $modelsToTry = [];
        $primaryModel = trim((string)($config['openai_model'] ?? ''));
        if ($primaryModel === '') {
            $primaryModel = 'gpt-4o-mini';
        }
        $modelsToTry[] = $primaryModel;
        $fallbackModel = trim((string)($config['fallback_openai_model'] ?? ''));
        if ($fallbackModel !== '' && $fallbackModel !== $primaryModel) {
            $modelsToTry[] = $fallbackModel;
        }

        $baseContext = json_encode([
            'source_title' => (string)($source['title'] ?? ''),
            'equipment_type' => (string)($source['equipment_type'] ?? ''),
            'manufacturer' => (string)($source['manufacturer'] ?? ''),
            'model' => (string)($source['model'] ?? ''),
            'page_range' => $pageRange,
            'pages' => array_values($pages),
            'source_used' => $sourceUsed,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $imageCount = 0;
        $approxPayloadBytes = 0;
        $validatedImageUrls = [];
        foreach ($imagePaths as $imagePath) {
            $dataUrl = vms_template_encode_image_data_url($imagePath);
            $imageCount++;
            $approxPayloadBytes += strlen($dataUrl);
            $validatedImageUrls[] = $dataUrl;
        }

        $maxPayloadBytes = max(1, (int)($config['max_pdf_payload_mb'] ?? 18)) * 1024 * 1024;
        if ($approxPayloadBytes > $maxPayloadBytes) {
            return [
                'attempted' => false,
                'items' => [],
                'error' => 'Rendered PDF page images are too large for the configured OpenAI request payload. Reduce page count or render DPI.',
                'http_status' => null,
                'diagnostics' => [
                    'endpoint' => 'https://api.openai.com/v1/chat/completions',
                    'models_attempted' => [],
                    'image_files_included_count' => $imageCount,
                    'approx_payload_size_bytes' => $approxPayloadBytes,
                    'openai_error_message' => 'Payload too large before request.',
                    'openai_error_type' => '',
                    'openai_error_code' => '',
                    'model_attempted' => '',
                ],
            ];
        }

        $lastError = '';
        $lastHttpCode = null;
        $maxOutputTokens = max(400, (int)($config['max_output_tokens'] ?? 1200));
        $endpoint = 'https://api.openai.com/v1/chat/completions';
        $modelsAttempted = [];
        $lastErrorDetails = [
            'message' => '',
            'type' => '',
            'code' => '',
            'http_status' => null,
        ];
        $lastPromptMode = '';
        $lastResponseLength = 0;
        $lastJsonParseFailed = false;
        $lastZeroRowsReturned = false;
        $promptModes = [
            'strict' => 'The attached images are manufacturer manual maintenance schedule tables. Your job is table transcription, not final template creation. Interpret the table layout visually. Column headers are maintenance intervals. Rows are components, inspection items, or service actions. Cells marked with o, dots, circles, checkmarks, X, or other visible marks mean the service is required at that interval. Convert each marked cell into a transcription row. Each row must preserve item/component name, action name, combined step text such as "Change engine oil", the interval label from the column header, the marked cell value, any visible footnote references, inferred interval_hours and interval_months if the column header clearly shows them, interval_basis, source_excerpt, and confidence_label. Respect language such as "perform at every indicated month or operating hour interval, whichever comes first." Do not invent intervals. Ignore page headers, page numbers, standalone o markers, footnote-only rows like "o (2)", warranty text, and unrelated legends unless needed to interpret the marks. Return JSON with key "items". Do not return an empty items array unless no maintenance schedule table is visible in these images.',
            'relaxed' => 'These images show manufacturer maintenance schedule tables. Transcribe every visible maintenance row or item/action that appears marked under any interval column. Treat column headers as intervals and marked cells as required service. For each marked row, return item/component name, action name if visible, combined step text like "Check propeller and cotter pin" or "Replace water pump impeller", the interval_label from the marked column, marked_cell_value, interval_hours or interval_months when the column clearly shows them, interval_basis, footnote_refs, source_excerpt, and confidence_label. Ignore standalone o marks, page numbers, and obvious artifact-only rows. Do not return empty unless no maintenance table is visible.',
        ];

        foreach ($promptModes as $promptMode => $promptText) {
            foreach ($modelsToTry as $model) {
                $modelsAttempted[] = $model;
                $lastPromptMode = $promptMode;
                $content = [
                    [
                        'type' => 'text',
                        'text' => $promptText,
                    ],
                    [
                        'type' => 'text',
                        'text' => $baseContext,
                    ],
                ];
                foreach ($validatedImageUrls as $dataUrl) {
                    $content[] = [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => $dataUrl,
                            'detail' => 'high',
                        ],
                    ];
                }

                $payload = [
                    'model' => $model,
                    'max_tokens' => $maxOutputTokens,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $content,
                        ],
                    ],
                ];

                $approxRequestBytes = strlen((string)json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

                $ch = curl_init($endpoint);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_TIMEOUT => max(10, (int)($config['timeout_seconds'] ?? 20)),
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json',
                        'Authorization: Bearer ' . (string)$config['api_key'],
                    ],
                    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ]);

                $response = curl_exec($ch);
                $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);

                $lastHttpCode = $httpCode;

                if ($response === false || $response === '' || $httpCode >= 400) {
                    if ($httpCode === 429) {
                        return [
                            'attempted' => true,
                            'items' => [],
                            'error' => 'OpenAI rate limit/quota reached. Try again later or check API billing/limits.',
                            'http_status' => $httpCode,
                            'diagnostics' => [
                                'endpoint' => $endpoint,
                                'models_attempted' => $modelsAttempted,
                                'image_files_included_count' => $imageCount,
                                'approx_payload_size_bytes' => $approxRequestBytes,
                                'openai_error_message' => 'OpenAI rate limit/quota reached. Try again later or check API billing/limits.',
                                'openai_error_type' => '',
                                'openai_error_code' => '',
                                'model_attempted' => $model,
                                'prompt_mode_used' => $promptMode,
                                'response_text_length' => 0,
                                'json_parse_failed' => 'No',
                                'zero_rows_returned' => 'No',
                            ],
                        ];
                    }
                    $lastErrorDetails = $curlError !== '' && $response === ''
                        ? [
                            'message' => 'HTTP ' . $httpCode . ': ' . $curlError,
                            'type' => '',
                            'code' => '',
                            'http_status' => $httpCode,
                        ]
                        : vms_template_parse_openai_error_details((string)$response, $httpCode);
                    $lastError = 'HTTP ' . $httpCode . ': ' . (string)($lastErrorDetails['message'] ?? 'OpenAI PDF extraction request failed.');
                    if (count($modelsToTry) > (count($modelsAttempted) % count($modelsToTry) ?: count($modelsToTry)) && vms_template_openai_error_is_invalid_model($lastErrorDetails)) {
                        continue;
                    }
                    continue;
                }

                $decoded = json_decode((string)$response, true);
                if (!is_array($decoded)) {
                    $lastError = 'JSON parse failure from provider response.';
                    $lastJsonParseFailed = true;
                    continue;
                }

                $messageContent = $decoded['choices'][0]['message']['content'] ?? '';
                $contentText = '';
                if (is_array($messageContent)) {
                    foreach ($messageContent as $part) {
                        if (is_array($part) && ($part['type'] ?? '') === 'text') {
                            $contentText .= (string)($part['text'] ?? '');
                        }
                    }
                } else {
                    $contentText = trim((string)$messageContent);
                }
                $lastResponseLength = strlen($contentText);

                if ($contentText === '') {
                    $lastError = 'Empty model response.';
                    continue;
                }

                if (preg_match('/```(?:json)?\s*(.*?)```/is', $contentText, $match)) {
                    $contentText = trim((string)$match[1]);
                }

                $json = json_decode($contentText, true);
                if (!is_array($json) || !isset($json['items']) || !is_array($json['items'])) {
                    $lastError = 'Invalid JSON schema returned by provider.';
                    $lastJsonParseFailed = true;
                    continue;
                }

                $items = [];
                foreach ($json['items'] as $item) {
                    if (!is_array($item)) {
                        continue;
                    }

                    $component = vms_template_clean_maintenance_text((string)($item['component'] ?? ''));
                    $serviceName = vms_template_clean_maintenance_text((string)($item['service_name'] ?? ''));
                    if ($serviceName === '' && $component !== '') {
                        $serviceName = $component;
                    }
                    if ($serviceName === '') {
                        continue;
                    }

                    $steps = vms_template_clean_maintenance_text((string)($item['steps'] ?? ''));
                    if ($steps === '' && $component !== '' && strcasecmp($component, $serviceName) !== 0) {
                        $steps = $component;
                    }

                    $candidate = [
                        'service_name' => $serviceName,
                        'component' => $component,
                        'item_name' => $component,
                        'action_name' => $actionName,
                        'combined_step' => $steps,
                        'interval_label' => vms_template_clean_maintenance_text((string)($item['interval_label'] ?? '')),
                        'interval_hours' => isset($item['interval_hours']) && $item['interval_hours'] !== '' ? (int)$item['interval_hours'] : null,
                        'interval_months' => isset($item['interval_months']) && $item['interval_months'] !== '' ? (int)$item['interval_months'] : null,
                        'interval_basis' => trim((string)($item['interval_basis'] ?? '')),
                        'marked_cell_value' => vms_template_clean_maintenance_text((string)($item['marked_cell_value'] ?? '')),
                        'steps' => $steps,
                        'source_excerpt' => vms_template_clean_maintenance_text((string)($item['source_excerpt'] ?? ('PDF pages ' . $pageRange))),
                        'footnote_refs' => vms_template_clean_maintenance_text((string)($item['footnote_refs'] ?? '')),
                        'confidence_label' => trim((string)($item['confidence_label'] ?? 'AI PDF extraction draft')),
                        'template_origin' => 'provider_pdf',
                    ];

                    $descriptiveStep = vms_template_build_descriptive_step_text($candidate);
                    if ($descriptiveStep === '') {
                        continue;
                    }

                    $items[] = [
                        'service_name' => !vms_template_is_generic_action($serviceName) ? $serviceName : $descriptiveStep,
                        'component' => $component,
                        'item_name' => $component,
                        'action_name' => $actionName,
                        'combined_step' => $descriptiveStep,
                        'interval_label' => $candidate['interval_label'],
                        'interval_hours' => $candidate['interval_hours'],
                        'interval_months' => $candidate['interval_months'],
                        'interval_basis' => $candidate['interval_basis'],
                        'marked_cell_value' => $candidate['marked_cell_value'],
                        'steps' => $descriptiveStep,
                        'source_excerpt' => $candidate['source_excerpt'],
                        'footnote_refs' => $candidate['footnote_refs'],
                        'confidence_label' => $candidate['confidence_label'],
                        'template_origin' => $candidate['template_origin'],
                    ];
                }

                if (!$items) {
                    $lastZeroRowsReturned = true;
                    if ($promptMode === 'strict') {
                        continue;
                    }
                }

                return [
                    'attempted' => true,
                    'items' => $items,
                    'error' => '',
                    'http_status' => $httpCode,
                    'diagnostics' => [
                        'endpoint' => $endpoint,
                        'models_attempted' => $modelsAttempted,
                        'image_files_included_count' => $imageCount,
                        'approx_payload_size_bytes' => $approxRequestBytes,
                        'openai_error_message' => '',
                        'openai_error_type' => '',
                        'openai_error_code' => '',
                        'model_attempted' => $model,
                        'prompt_mode_used' => $promptMode,
                        'response_text_length' => $lastResponseLength,
                        'json_parse_failed' => $lastJsonParseFailed ? 'Yes' : 'No',
                        'zero_rows_returned' => $lastZeroRowsReturned ? 'Yes' : 'No',
                    ],
                ];
            }
        }

        return [
            'attempted' => true,
            'items' => [],
            'error' => $lastError !== '' ? $lastError : 'Advanced PDF extraction returned no usable results.',
            'http_status' => $lastHttpCode,
            'diagnostics' => [
                'endpoint' => $endpoint,
                'models_attempted' => $modelsAttempted,
                'image_files_included_count' => $imageCount,
                'approx_payload_size_bytes' => $approxPayloadBytes,
                'openai_error_message' => (string)($lastErrorDetails['message'] ?? ''),
                'openai_error_type' => (string)($lastErrorDetails['type'] ?? ''),
                'openai_error_code' => (string)($lastErrorDetails['code'] ?? ''),
                'model_attempted' => !empty($modelsAttempted) ? (string)end($modelsAttempted) : '',
                'prompt_mode_used' => $lastPromptMode,
                'response_text_length' => $lastResponseLength,
                'json_parse_failed' => $lastJsonParseFailed ? 'Yes' : 'No',
                'zero_rows_returned' => $lastZeroRowsReturned ? 'Yes' : 'No',
            ],
        ];
    }
}

if (!function_exists('vms_template_get_templates_for_source')) {
    function vms_template_get_templates_for_source(PDO $pdo, int $sourceId): array
    {
        if (!vms_template_table_exists($pdo)) {
            return [];
        }

        $stmt = $pdo->prepare("
            SELECT
                t.*,
                u.fName AS reviewed_fName,
                u.lName AS reviewed_lName,
                cu.fName AS created_fName,
                cu.lName AS created_lName
            FROM equipment_maintenance_templates t
            LEFT JOIN users u ON u.id = t.reviewed_by
            LEFT JOIN users cu ON cu.id = t.created_by
            WHERE t.source_id = ?
            ORDER BY
                CASE t.review_status
                    WHEN 'draft' THEN 0
                    WHEN 'approved' THEN 1
                    ELSE 2
                END,
                t.created_at DESC,
                t.template_id DESC
        ");
        $stmt->execute([$sourceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('vms_template_get_matching_approved_templates')) {
    function vms_template_get_matching_approved_templates(PDO $pdo, array $search, int $limit = 10): array
    {
        if (!vms_template_table_exists($pdo)) {
            return [];
        }

        $manufacturer = trim((string)($search['manufacturer'] ?? ''));
        $model = trim((string)($search['model'] ?? ''));
        $equipmentType = trim((string)($search['equipment_type'] ?? ''));

        $where = ["t.review_status = 'approved'", "COALESCE(t.is_active, 1) = 1"];
        $params = [];
        $matchParts = [];

        if ($manufacturer !== '' && $model !== '') {
            $matchParts[] = "(LOWER(TRIM(COALESCE(t.manufacturer, ''))) = LOWER(TRIM(?)) AND LOWER(TRIM(COALESCE(t.model, ''))) = LOWER(TRIM(?)))";
            $params[] = $manufacturer;
            $params[] = $model;
        }
        if ($manufacturer !== '' && $equipmentType !== '') {
            $matchParts[] = "(LOWER(TRIM(COALESCE(t.manufacturer, ''))) = LOWER(TRIM(?)) AND LOWER(TRIM(COALESCE(t.equipment_type, ''))) = LOWER(TRIM(?)))";
            $params[] = $manufacturer;
            $params[] = $equipmentType;
        }
        if (!$matchParts) {
            return [];
        }

        $where[] = '(' . implode(' OR ', $matchParts) . ')';

        $sql = "
            SELECT t.*
            FROM equipment_maintenance_templates t
            WHERE " . implode(' AND ', $where) . "
            ORDER BY
                CASE
                    WHEN LOWER(TRIM(COALESCE(t.manufacturer, ''))) = LOWER(TRIM(?))
                     AND LOWER(TRIM(COALESCE(t.model, ''))) = LOWER(TRIM(?)) THEN 0
                    WHEN LOWER(TRIM(COALESCE(t.manufacturer, ''))) = LOWER(TRIM(?))
                     AND LOWER(TRIM(COALESCE(t.equipment_type, ''))) = LOWER(TRIM(?)) THEN 1
                    ELSE 2
                END,
                t.reviewed_at DESC,
                t.template_id DESC
            LIMIT " . max(1, $limit);
        $params[] = $manufacturer;
        $params[] = $model;
        $params[] = $manufacturer;
        $params[] = $equipmentType;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('vms_template_row_exists')) {
    function vms_template_row_exists(PDO $pdo, int $sourceId, string $serviceName, ?int $hours, ?int $months): bool
    {
        if (!vms_template_table_exists($pdo)) {
            return false;
        }

        $stmt = $pdo->prepare("
            SELECT template_id
            FROM equipment_maintenance_templates
            WHERE source_id = ?
              AND LOWER(TRIM(service_name)) = LOWER(TRIM(?))
              AND ((interval_hours IS NULL AND ? IS NULL) OR interval_hours = ?)
              AND ((interval_months IS NULL AND ? IS NULL) OR interval_months = ?)
              AND review_status IN ('draft', 'approved')
              AND COALESCE(is_active, 1) = 1
            LIMIT 1
        ");
        $stmt->execute([$sourceId, $serviceName, $hours, $hours, $months, $months]);
        return (bool)$stmt->fetchColumn();
    }
}

if (!function_exists('vms_template_insert_row')) {
    function vms_template_insert_row(PDO $pdo, array $source, array $row, int $createdBy): int
    {
        if (!vms_template_table_exists($pdo)) {
            throw new RuntimeException('Maintenance template table is not available yet. Apply the migration first.');
        }

        $serviceName = trim((string)($row['service_name'] ?? ''));
        if ($serviceName === '') {
            throw new RuntimeException('Service name is required.');
        }

        $hours = isset($row['interval_hours']) && $row['interval_hours'] !== '' ? (int)$row['interval_hours'] : null;
        $months = isset($row['interval_months']) && $row['interval_months'] !== '' ? (int)$row['interval_months'] : null;
        if (vms_template_row_exists($pdo, (int)$source['source_id'], $serviceName, $hours, $months)) {
            return 0;
        }

        $stmt = $pdo->prepare("
            INSERT INTO equipment_maintenance_templates (
                source_id,
                source_title,
                source_url,
                source_domain,
                equipment_type,
                manufacturer,
                model,
                service_name,
                interval_hours,
                interval_months,
                interval_basis,
                steps,
                source_excerpt,
                confidence_label,
                review_status,
                review_note,
                template_origin,
                created_by,
                created_at,
                updated_at,
                is_active
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', NULL, ?, ?, NOW(), NOW(), 1)
        ");
        $stmt->execute([
            (int)$source['source_id'],
            trim((string)($source['title'] ?? '')),
            trim((string)($source['source_url'] ?? '')),
            trim((string)($source['source_domain'] ?? '')),
            trim((string)($row['equipment_type'] ?? ($source['equipment_type'] ?? ''))) ?: null,
            trim((string)($row['manufacturer'] ?? ($source['manufacturer'] ?? ''))) ?: null,
            trim((string)($row['model'] ?? ($source['model'] ?? ''))) ?: null,
            $serviceName,
            $hours,
            $months,
            trim((string)($row['interval_basis'] ?? '')) ?: null,
            trim((string)($row['steps'] ?? '')) ?: null,
            trim((string)($row['source_excerpt'] ?? '')) ?: null,
            trim((string)($row['confidence_label'] ?? 'Draft manual entry')) ?: null,
            trim((string)($row['template_origin'] ?? 'manual')) ?: 'manual',
            $createdBy,
        ]);

        return (int)$pdo->lastInsertId();
    }
}
