<?php
declare(strict_types=1);

if (!function_exists('vms_source_finder_table_exists')) {
    function vms_source_finder_table_exists(PDO $pdo, string $table): bool
    {
        static $cache = [];
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }

        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        $cache[$table] = (bool)$stmt->fetchColumn();
        return $cache[$table];
    }
}

if (!function_exists('vms_source_finder_normalize_url')) {
    function vms_source_finder_normalize_url(?string $url): string
    {
        $url = trim((string)$url);
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            return strtolower(rtrim($url, '/'));
        }

        $host = strtolower(preg_replace('/^www\./', '', (string)$parts['host']));
        $path = isset($parts['path']) ? rtrim((string)$parts['path'], '/') : '';
        $query = isset($parts['query']) ? ('?' . (string)$parts['query']) : '';

        return $host . $path . $query;
    }
}

if (!function_exists('vms_source_finder_domain_from_url')) {
    function vms_source_finder_domain_from_url(?string $url): string
    {
        $host = (string)parse_url((string)$url, PHP_URL_HOST);
        return strtolower(preg_replace('/^www\./', '', $host));
    }
}

if (!function_exists('vms_source_finder_get_config')) {
    function vms_source_finder_get_config(): array
    {
        static $config = null;
        if ($config !== null) {
            return $config;
        }

        $config = [
            'provider' => trim((string)(getenv('VMS_SOURCE_FINDER_PROVIDER') ?: '')),
            'serpapi_key' => trim((string)(getenv('VMS_SERPAPI_KEY') ?: '')),
            'serpapi_engine' => trim((string)(getenv('VMS_SERPAPI_ENGINE') ?: 'google')),
            'timeout_seconds' => (int)(getenv('VMS_SOURCE_FINDER_TIMEOUT') ?: 12),
        ];

        $privatePath = __DIR__ . '/../private/config_maintenance_source_finder.php';
        $localPath = __DIR__ . '/../config_maintenance_source_finder.php';

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

if (!function_exists('vms_source_finder_user_can_manage_library')) {
    function vms_source_finder_user_can_manage_library(): bool
    {
        $roleId = (int)($_SESSION['role_id'] ?? 0);
        return in_array($roleId, [1, 2], true);
    }
}

if (!function_exists('vms_source_finder_is_configured')) {
    function vms_source_finder_is_configured(): bool
    {
        $config = vms_source_finder_get_config();
        $provider = strtolower(trim((string)($config['provider'] ?? '')));

        if ($provider === 'serpapi') {
            return !empty($config['serpapi_key']);
        }

        return false;
    }
}

if (!function_exists('vms_source_finder_config_message')) {
    function vms_source_finder_config_message(): string
    {
        return 'Source search is not configured yet. Add a provider and API key in private/config_maintenance_source_finder.php or set VMS_SOURCE_FINDER_PROVIDER and VMS_SERPAPI_KEY in the environment.';
    }
}

if (!function_exists('vms_source_finder_clean_term')) {
    function vms_source_finder_clean_term(?string $value): string
    {
        return trim((string)$value);
    }
}

if (!function_exists('vms_source_finder_extract_year')) {
    function vms_source_finder_extract_year(?string $value): string
    {
        $value = trim((string)$value);
        if ($value !== '' && preg_match('/\b(19|20)\d{2}\b/', $value, $m)) {
            return $m[0];
        }
        return '';
    }
}

if (!function_exists('vms_source_finder_known_manufacturer_domains')) {
    function vms_source_finder_known_manufacturer_domains(): array
    {
        return [
            'caterpillar' => ['cat.com'],
            'cummins' => ['cummins.com'],
            'yanmar' => ['yanmar.com', 'yanmarmarine.com'],
            'john deere' => ['deere.com'],
            'volvo penta' => ['volvopenta.com'],
            'northern lights' => ['northern-lights.com'],
            'onan' => ['cummins.com'],
            'kohler' => ['kohlerpower.com', 'kohler.com'],
            'man' => ['man-es.com'],
            'mtu' => ['mtu-solutions.com'],
            'scania' => ['scania.com'],
            'detroit diesel' => ['demanddetroit.com', 'detroitdiesel.com'],
            'perkins' => ['perkins.com'],
            'wabtec' => ['wabtec.com'],
            'isuzu' => ['isuzu.co.jp', 'isuzu.com'],
            'honda' => ['marine.honda.com', 'powerequipment.honda.com'],
            'yamaha' => ['yamaha-motor.com', 'yamahaoutboards.com'],
            'suzuki' => ['suzukimarine.com', 'global.suzuki'],
            'mercury' => ['mercurymarine.com'],
        ];
    }
}

if (!function_exists('vms_source_finder_guess_manufacturer_domains')) {
    function vms_source_finder_guess_manufacturer_domains(string $manufacturer): array
    {
        $normalized = strtolower(trim($manufacturer));
        if ($normalized === '') {
            return [];
        }

        foreach (vms_source_finder_known_manufacturer_domains() as $name => $domains) {
            if (str_contains($normalized, $name)) {
                return $domains;
            }
        }

        return [];
    }
}

if (!function_exists('vms_source_finder_build_queries')) {
    function vms_source_finder_build_queries(array $search): array
    {
        $type = vms_source_finder_clean_term($search['equipment_type'] ?? '');
        $manufacturer = vms_source_finder_clean_term($search['manufacturer'] ?? '');
        $model = vms_source_finder_clean_term($search['model'] ?? '');
        $serialOrYear = vms_source_finder_clean_term($search['serial_year'] ?? '');
        $year = vms_source_finder_extract_year($serialOrYear);

        $baseParts = array_values(array_filter([$manufacturer, $model, $type, $year]));
        $base = trim(implode(' ', $baseParts));
        if ($base === '') {
            return [];
        }

        $terms = [
            'maintenance schedule pdf',
            'operation manual pdf',
            'owner manual pdf',
            'service manual pdf',
        ];

        $queries = [];
        foreach ($terms as $term) {
            $queries[] = trim($base . ' ' . $term);
        }

        $domains = vms_source_finder_guess_manufacturer_domains($manufacturer);
        foreach ($domains as $domain) {
            $queries[] = trim($base . ' maintenance schedule site:' . $domain);
            $queries[] = trim($base . ' service manual site:' . $domain);
        }

        return array_values(array_unique($queries));
    }
}

if (!function_exists('vms_source_finder_detect_result_type')) {
    function vms_source_finder_detect_result_type(array $result): string
    {
        $url = strtolower((string)($result['url'] ?? ''));
        $title = strtolower((string)($result['title'] ?? ''));

        if (str_contains($url, '.pdf') || str_contains($title, 'pdf')) {
            return 'PDF';
        }
        if (str_contains($title, 'manual')) {
            return 'Manual';
        }
        return 'Webpage';
    }
}

if (!function_exists('vms_source_finder_result_confidence')) {
    function vms_source_finder_result_confidence(array $result, array $manufacturerDomains): array
    {
        $domain = strtolower((string)($result['domain'] ?? ''));
        $confidence = 'third_party';
        $label = 'Third-party / Unknown';

        foreach ($manufacturerDomains as $candidate) {
            $candidate = strtolower($candidate);
            if ($candidate !== '' && ($domain === $candidate || str_ends_with($domain, '.' . $candidate))) {
                return ['key' => 'likely_manufacturer', 'label' => 'Likely manufacturer source', 'class' => 'success'];
            }
        }

        if (!empty($manufacturerDomains)) {
            foreach ($manufacturerDomains as $candidate) {
                $candidateRoot = strtolower(preg_replace('/^www\./', '', (string)$candidate));
                if ($candidateRoot !== '' && str_contains($domain, strtok($candidateRoot, '.'))) {
                    return ['key' => 'possible_manufacturer', 'label' => 'Possible manufacturer source', 'class' => 'primary'];
                }
            }
        }

        return ['key' => $confidence, 'label' => $label, 'class' => 'secondary'];
    }
}

if (!function_exists('vms_source_finder_search')) {
    function vms_source_finder_search(array $search): array
    {
        $config = vms_source_finder_get_config();
        $provider = strtolower(trim((string)($config['provider'] ?? '')));

        if (!vms_source_finder_is_configured()) {
            return [
                'ok' => false,
                'message' => vms_source_finder_config_message(),
                'queries' => vms_source_finder_build_queries($search),
                'results' => [],
            ];
        }

        if ($provider !== 'serpapi') {
            return [
                'ok' => false,
                'message' => 'Configured source finder provider is not supported by this Phase 0 prototype.',
                'queries' => vms_source_finder_build_queries($search),
                'results' => [],
            ];
        }

        $queries = vms_source_finder_build_queries($search);
        if (!$queries) {
            return [
                'ok' => false,
                'message' => 'Enter at least manufacturer or model information to search for maintenance sources.',
                'queries' => [],
                'results' => [],
            ];
        }

        $manufacturerDomains = vms_source_finder_guess_manufacturer_domains((string)($search['manufacturer'] ?? ''));
        $results = [];
        $seen = [];

        foreach (array_slice($queries, 0, 6) as $query) {
            $apiUrl = 'https://serpapi.com/search.json?' . http_build_query([
                'engine' => $config['serpapi_engine'] ?? 'google',
                'q' => $query,
                'api_key' => $config['serpapi_key'] ?? '',
                'num' => 10,
            ]);

            $ch = curl_init($apiUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => max(5, (int)($config['timeout_seconds'] ?? 12)),
                CURLOPT_USERAGENT => 'VMS Maintenance Source Finder/1.0',
            ]);

            $response = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($response === false || $response === '' || $httpCode >= 400) {
                return [
                    'ok' => false,
                    'message' => 'The source search provider request failed. Check the provider configuration and API key.',
                    'queries' => $queries,
                    'results' => [],
                    'error_detail' => $curlError !== '' ? $curlError : ('HTTP ' . $httpCode),
                ];
            }

            $decoded = json_decode($response, true);
            if (!is_array($decoded)) {
                return [
                    'ok' => false,
                    'message' => 'The source search provider returned an unreadable response.',
                    'queries' => $queries,
                    'results' => [],
                ];
            }

            foreach (($decoded['organic_results'] ?? []) as $row) {
                $url = trim((string)($row['link'] ?? ''));
                if ($url === '' || isset($seen[$url])) {
                    continue;
                }

                $seen[$url] = true;
                $domain = (string)parse_url($url, PHP_URL_HOST);
                $result = [
                    'title' => trim((string)($row['title'] ?? $url)),
                    'url' => $url,
                    'domain' => strtolower(preg_replace('/^www\./', '', $domain)),
                    'snippet' => trim((string)($row['snippet'] ?? '')),
                    'query' => $query,
                ];
                $result['result_type'] = vms_source_finder_detect_result_type($result);
                $result['confidence'] = vms_source_finder_result_confidence($result, $manufacturerDomains);
                $results[] = $result;
            }
        }

        return [
            'ok' => true,
            'message' => count($results) > 0 ? '' : 'No source results were found for this search.',
            'queries' => $queries,
            'results' => $results,
        ];
    }
}

if (!function_exists('vms_source_finder_get_saved_sources')) {
    function vms_source_finder_get_saved_sources(PDO $pdo, array $search, ?int $equipmentId = null, int $limit = 25): array
    {
        if (!vms_source_finder_table_exists($pdo, 'equipment_manual_sources')) {
            return [];
        }

        $manufacturer = vms_source_finder_clean_term($search['manufacturer'] ?? '');
        $model = vms_source_finder_clean_term($search['model'] ?? '');
        $equipmentType = vms_source_finder_clean_term($search['equipment_type'] ?? '');

        $sql = "
            SELECT
                s.*,
                u.fName AS approved_fName,
                u.lName AS approved_lName,
                e.equipmentName AS linked_equipment_name
            FROM equipment_manual_sources s
            LEFT JOIN users u ON u.id = s.approved_by
            LEFT JOIN equipment e ON e.eid = s.equipment_id
            WHERE COALESCE(s.is_active, 1) = 1
        ";
        $params = [];

        $matchParts = [];
        if ($equipmentId !== null && $equipmentId > 0) {
            $matchParts[] = "s.equipment_id = ?";
            $params[] = $equipmentId;
        }
        if ($manufacturer !== '' && $model !== '') {
            $matchParts[] = "(LOWER(TRIM(s.manufacturer)) = LOWER(TRIM(?)) AND LOWER(TRIM(s.model)) = LOWER(TRIM(?)))";
            $params[] = $manufacturer;
            $params[] = $model;
        }
        if ($manufacturer !== '' && $equipmentType !== '') {
            $matchParts[] = "(LOWER(TRIM(s.manufacturer)) = LOWER(TRIM(?)) AND LOWER(TRIM(s.equipment_type)) = LOWER(TRIM(?)))";
            $params[] = $manufacturer;
            $params[] = $equipmentType;
        }

        if (!$matchParts) {
            return [];
        }

        $sql .= " AND (" . implode(' OR ', $matchParts) . ")";
        $sql .= " ORDER BY
            CASE
                WHEN s.equipment_id IS NOT NULL AND s.equipment_id = ? THEN 0
                WHEN LOWER(TRIM(COALESCE(s.manufacturer, ''))) = LOWER(TRIM(?))
                 AND LOWER(TRIM(COALESCE(s.model, ''))) = LOWER(TRIM(?)) THEN 1
                WHEN LOWER(TRIM(COALESCE(s.manufacturer, ''))) = LOWER(TRIM(?))
                 AND LOWER(TRIM(COALESCE(s.equipment_type, ''))) = LOWER(TRIM(?)) THEN 2
                ELSE 3
            END,
            s.approved_at DESC,
            s.created_at DESC
            LIMIT " . max(1, (int)$limit);
        $params[] = $equipmentId ?? 0;
        $params[] = $manufacturer;
        $params[] = $model;
        $params[] = $manufacturer;
        $params[] = $equipmentType;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('vms_source_finder_find_existing_source_by_url')) {
    function vms_source_finder_find_existing_source_by_url(PDO $pdo, string $sourceUrl): ?array
    {
        if (!vms_source_finder_table_exists($pdo, 'equipment_manual_sources')) {
            return null;
        }

        $normalized = vms_source_finder_normalize_url($sourceUrl);
        if ($normalized === '') {
            return null;
        }

        $stmt = $pdo->prepare("
            SELECT source_id, title, source_url, approved_at
            FROM equipment_manual_sources
            WHERE source_url_normalized = ?
            LIMIT 1
        ");
        $stmt->execute([$normalized]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('vms_source_finder_prepare_source_record')) {
    function vms_source_finder_prepare_source_record(array $data): array
    {
        $sourceUrl = trim((string)($data['source_url'] ?? ''));
        $normalized = vms_source_finder_normalize_url($sourceUrl);
        $domain = trim((string)($data['source_domain'] ?? ''));
        if ($domain === '') {
            $domain = vms_source_finder_domain_from_url($sourceUrl);
        }

        return [
            'equipment_id' => !empty($data['equipment_id']) ? (int)$data['equipment_id'] : null,
            'equipment_type' => vms_source_finder_clean_term($data['equipment_type'] ?? ''),
            'manufacturer' => vms_source_finder_clean_term($data['manufacturer'] ?? ''),
            'model' => vms_source_finder_clean_term($data['model'] ?? ''),
            'serial_or_year' => vms_source_finder_clean_term($data['serial_or_year'] ?? ''),
            'title' => trim((string)($data['title'] ?? '')),
            'source_url' => $sourceUrl,
            'source_url_normalized' => $normalized,
            'source_url_hash' => sha1($normalized),
            'source_domain' => strtolower($domain),
            'source_type' => vms_source_finder_clean_term($data['source_type'] ?? ''),
            'confidence_label' => vms_source_finder_clean_term($data['confidence_label'] ?? ''),
            'notes' => trim((string)($data['notes'] ?? '')),
        ];
    }
}

if (!function_exists('vms_source_finder_save_source')) {
    function vms_source_finder_save_source(PDO $pdo, array $data, int $approvedBy): array
    {
        if (!vms_source_finder_table_exists($pdo, 'equipment_manual_sources')) {
            throw new RuntimeException('Source library table is not available yet. Apply the migration first.');
        }

        $record = vms_source_finder_prepare_source_record($data);
        if ($record['source_url'] === '' || $record['source_url_normalized'] === '') {
            throw new RuntimeException('A valid source URL is required.');
        }
        if ($record['title'] === '') {
            throw new RuntimeException('A source title is required.');
        }

        $existing = vms_source_finder_find_existing_source_by_url($pdo, $record['source_url']);
        if ($existing) {
            return [
                'created' => false,
                'existing' => $existing,
            ];
        }

        $stmt = $pdo->prepare("
            INSERT INTO equipment_manual_sources (
                equipment_id,
                equipment_type,
                manufacturer,
                model,
                serial_or_year,
                title,
                source_url,
                source_url_normalized,
                source_url_hash,
                source_domain,
                source_type,
                confidence_label,
                notes,
                approved_by,
                approved_at,
                created_at,
                updated_at,
                is_active
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW(), 1)
        ");
        $stmt->execute([
            $record['equipment_id'],
            $record['equipment_type'] !== '' ? $record['equipment_type'] : null,
            $record['manufacturer'] !== '' ? $record['manufacturer'] : null,
            $record['model'] !== '' ? $record['model'] : null,
            $record['serial_or_year'] !== '' ? $record['serial_or_year'] : null,
            $record['title'],
            $record['source_url'],
            $record['source_url_normalized'],
            $record['source_url_hash'],
            $record['source_domain'] !== '' ? $record['source_domain'] : null,
            $record['source_type'] !== '' ? $record['source_type'] : null,
            $record['confidence_label'] !== '' ? $record['confidence_label'] : null,
            $record['notes'] !== '' ? $record['notes'] : null,
            $approvedBy,
        ]);

        return [
            'created' => true,
            'source_id' => (int)$pdo->lastInsertId(),
        ];
    }
}
