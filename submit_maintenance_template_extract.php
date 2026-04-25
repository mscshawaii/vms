<?php
require __DIR__ . '/session_check.php';
require __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/maintenance_template_extraction_functions.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!vms_template_user_can_manage()) {
    http_response_code(403);
    exit('Not authorized.');
}

$action = trim((string)($_POST['action'] ?? ''));
$sourceId = (int)($_POST['source_id'] ?? 0);
$equipmentId = (int)($_POST['equipment_id'] ?? 0);
$createdBy = vms_template_current_user_id();
$pastedContent = trim((string)($_POST['pasted_content'] ?? ''));

if ($sourceId <= 0 || $createdBy <= 0) {
    http_response_code(422);
    exit('Missing source or user context.');
}

$source = vms_template_get_source($pdo, $sourceId);
if (!$source) {
    http_response_code(404);
    exit('Saved source not found.');
}

$redirectBase = 'maintenance_template_extract.php?source_id=' . $sourceId;
if ($equipmentId > 0) {
    $redirectBase .= '&equipment_id=' . $equipmentId;
}

$setFlash = static function (string $type, string $message, array $debug = []): void {
    $_SESSION['maintenance_template_extract_flash'] = [
        'type' => $type,
        'message' => $message,
        'debug' => $debug,
    ];
};

try {
    $pdo->beginTransaction();

    if ($action === 'heuristic_extract') {
        $inputSourceUsed = $pastedContent !== '' ? 'pasted_content' : 'saved_source';
        $providerConfigured = vms_template_extraction_is_configured();
        $providerAttempted = false;
        $providerRowCount = 0;
        $providerError = '';
        $heuristicAttempted = false;
        $groupingApplied = false;
        $groupedRowCount = 0;
        $rawCandidateCount = 0;

        $providerResult = vms_template_extract_candidates_via_provider_detailed($source, $pastedContent);
        $providerAttempted = (bool)($providerResult['attempted'] ?? false);
        $providerError = trim((string)($providerResult['error'] ?? ''));
        $candidates = $providerResult['items'] ?? [];
        $providerRowCount = is_array($candidates) ? count($candidates) : 0;

        if ($providerRowCount === 0) {
            $heuristicAttempted = true;
            $candidates = vms_template_extract_candidates_from_source($source, $pastedContent);
        }

        $rawCandidateCount = is_array($candidates) ? count($candidates) : 0;
        $groupedResult = vms_template_group_candidates_by_interval(is_array($candidates) ? $candidates : []);
        $candidates = $groupedResult['items'] ?? [];
        $groupedRowCount = (int)($groupedResult['grouped_count'] ?? 0);
        $groupingApplied = (bool)($groupedResult['grouped'] ?? false);

        $inserted = 0;
        foreach ($candidates as $candidate) {
            $inserted += vms_template_insert_row($pdo, $source, $candidate, $createdBy) > 0 ? 1 : 0;
        }

        $debug = [
            'input_source_used' => $inputSourceUsed,
            'pasted_content_length' => strlen($pastedContent),
            'provider_configured' => $providerConfigured ? 'Yes' : 'No',
            'provider_attempted' => $providerAttempted ? 'Yes' : 'No',
            'provider_returned_row_count' => $providerRowCount,
            'heuristic_fallback_attempted' => $heuristicAttempted ? 'Yes' : 'No',
            'raw_candidate_count' => $rawCandidateCount,
            'grouped_row_count' => $groupedRowCount,
            'grouping_applied' => $groupingApplied ? 'Yes' : 'No',
        ];

        $pdo->commit();
        if ($inserted > 0) {
            $successMessage = $groupingApplied
                ? 'Draft extraction completed. Extracted maintenance actions were grouped into interval-based service packages.'
                : 'Draft extraction completed.';
            $setFlash('success', $successMessage, $debug);
            header('Location: ' . $redirectBase . '&status=extract_complete');
            exit;
        }

        if ($providerAttempted && $providerError !== '') {
            $setFlash('warning', 'Advanced extraction failed: ' . $providerError . '. Manual draft entry remains available.', $debug);
            header('Location: ' . $redirectBase . '&status=extract_failed');
            exit;
        }

        $noRowsMessage = $inputSourceUsed === 'pasted_content'
            ? 'No candidate maintenance rows were detected from the pasted content.'
            : 'No candidate maintenance rows were detected from the saved source text.';
        $setFlash('warning', $noRowsMessage, $debug);
        header('Location: ' . $redirectBase . '&status=extract_none');
        exit;
    }

    if ($action === 'pdf_extract') {
        $config = vms_template_extraction_get_config();
        $maxPages = max(1, (int)($config['max_pdf_pages'] ?? 5));
        $pageRange = trim((string)($_POST['page_range'] ?? ''));
        $pdfSourceMode = trim((string)($_POST['pdf_source_mode'] ?? 'saved_source'));
        $pages = vms_template_parse_page_range($pageRange, $maxPages);
        $pdfSupport = vms_template_detect_pdf_conversion_support();

        $sourceUsed = $pdfSourceMode === 'upload' ? 'uploaded_pdf' : 'saved_source_pdf';
        $providerAttempted = false;
        $providerError = '';
        $rawCandidateCount = 0;
        $groupedRowCount = 0;
        $groupingApplied = false;
        $conversionMethodUsed = $pdfSupport['available'] ? (string)($pdfSupport['method'] ?? '') : '';
        $pdfCommand = '';
        $pdfOutputDir = '';
        $pdfGeneratedImageCount = 0;
        $pdfGeneratedFiles = [];
        $pdfCommandExitCode = '';
        $pdfCommandStderr = '';
        $pdfImagePreviews = [];
        $extractionRunId = '';

        if (!$pdfSupport['available']) {
            $pdo->commit();
            $setFlash('warning', (string)($pdfSupport['dependency_message'] ?? 'PDF conversion support is not available.'), [
                'input_source_used' => $sourceUsed,
                'pdf_page_range' => $pageRange,
                'pdf_pages_processed' => 0,
                'pdf_conversion_method' => 'Unavailable',
                'pdf_command' => '',
                'pdf_output_dir' => '',
                'pdf_generated_image_count' => 0,
                'pdf_generated_files' => '',
                'pdf_command_exit_code' => '',
                'pdf_command_stderr' => '',
                'provider_configured' => vms_template_extraction_is_configured() ? 'Yes' : 'No',
                'provider_attempted' => 'No',
                'provider_returned_row_count' => 0,
                'heuristic_fallback_attempted' => 'No',
                'raw_candidate_count' => 0,
                'grouped_row_count' => 0,
                'grouping_applied' => 'No',
            ]);
            header('Location: ' . $redirectBase . '&status=extract_failed');
            exit;
        }

        $workDir = vms_template_make_temp_dir();
        try {
            $pdfPath = $workDir . DIRECTORY_SEPARATOR . 'source.pdf';
            if ($pdfSourceMode === 'upload') {
                $upload = $_FILES['uploaded_pdf'] ?? null;
                if (!is_array($upload) || (($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE)) {
                    throw new RuntimeException('Please upload a PDF to use the manual PDF extraction path.');
                }
                vms_template_store_uploaded_pdf($upload, $pdfPath);
            } else {
                vms_template_fetch_source_pdf($source, $pdfPath);
            }

            $renderResult = vms_template_render_pdf_pages_to_images($pdfPath, $pages, $workDir);
            $conversionMethodUsed = (string)($renderResult['method'] ?? $conversionMethodUsed);
            $renderDiagnostics = is_array($renderResult['diagnostics'] ?? null) ? $renderResult['diagnostics'] : [];
            $pdfCommand = trim((string)($renderDiagnostics['command'] ?? ''));
            $pdfOutputDir = trim((string)($renderDiagnostics['output_dir'] ?? $workDir));
            $pdfGeneratedImageCount = (int)($renderDiagnostics['generated_count'] ?? 0);
            $pdfGeneratedFiles = is_array($renderDiagnostics['generated_files'] ?? null) ? $renderDiagnostics['generated_files'] : [];
            $pdfCommandExitCode = (string)($renderDiagnostics['exit_code'] ?? '');
            $pdfCommandStderr = trim((string)($renderDiagnostics['stderr'] ?? ''));
            $imagePaths = $renderResult['images'] ?? [];
            if (!is_array($imagePaths) || !$imagePaths) {
                throw new RuntimeException('No page images were produced from the selected PDF range.');
            }
            $pdfImagePreviews = vms_template_collect_image_previews($imagePaths);

            $providerResult = vms_template_extract_candidates_from_pdf_images_detailed($source, $imagePaths, $pages, $pageRange, $sourceUsed);
            $providerAttempted = (bool)($providerResult['attempted'] ?? false);
            $providerError = trim((string)($providerResult['error'] ?? ''));
            $providerDiagnostics = is_array($providerResult['diagnostics'] ?? null) ? $providerResult['diagnostics'] : [];
            $rawCandidates = $providerResult['items'] ?? [];
            $rawCandidateCount = is_array($rawCandidates) ? count($rawCandidates) : 0;
            $groupedResult = vms_template_group_candidates_by_interval(is_array($rawCandidates) ? $rawCandidates : []);
            $groupedRowCount = (int)($groupedResult['grouped_count'] ?? 0);
            $groupingApplied = (bool)($groupedResult['grouped'] ?? false);

            $debug = [
                'input_source_used' => $sourceUsed,
                'pdf_page_range' => $pageRange,
                'pdf_pages_processed' => count($pages),
                'pdf_conversion_method' => $conversionMethodUsed !== '' ? $conversionMethodUsed : 'Unknown',
                'pdf_command' => $pdfCommand,
                'pdf_output_dir' => $pdfOutputDir,
                'pdf_generated_image_count' => $pdfGeneratedImageCount,
                'pdf_generated_files' => implode(', ', array_map('strval', $pdfGeneratedFiles)),
                'pdf_command_exit_code' => $pdfCommandExitCode,
                'pdf_command_stderr' => $pdfCommandStderr,
                'pdf_image_previews' => $pdfImagePreviews,
                'provider_endpoint' => trim((string)($providerDiagnostics['endpoint'] ?? '')),
                'provider_model_attempted' => trim((string)($providerDiagnostics['model_attempted'] ?? '')),
                'provider_models_attempted' => is_array($providerDiagnostics['models_attempted'] ?? null) ? implode(', ', array_map('strval', $providerDiagnostics['models_attempted'])) : '',
                'provider_image_files_included_count' => (int)($providerDiagnostics['image_files_included_count'] ?? 0),
                'provider_approx_payload_size_bytes' => (int)($providerDiagnostics['approx_payload_size_bytes'] ?? 0),
                'provider_openai_error_message' => trim((string)($providerDiagnostics['openai_error_message'] ?? '')),
                'provider_openai_error_type' => trim((string)($providerDiagnostics['openai_error_type'] ?? '')),
                'provider_openai_error_code' => trim((string)($providerDiagnostics['openai_error_code'] ?? '')),
                'provider_prompt_mode_used' => trim((string)($providerDiagnostics['prompt_mode_used'] ?? '')),
                'provider_response_text_length' => (int)($providerDiagnostics['response_text_length'] ?? 0),
                'provider_json_parse_failed' => trim((string)($providerDiagnostics['json_parse_failed'] ?? 'No')),
                'provider_zero_rows_returned' => trim((string)($providerDiagnostics['zero_rows_returned'] ?? 'No')),
                'provider_configured' => vms_template_extraction_is_configured() ? 'Yes' : 'No',
                'provider_attempted' => $providerAttempted ? 'Yes' : 'No',
                'provider_returned_row_count' => $rawCandidateCount,
                'heuristic_fallback_attempted' => 'No',
                'raw_candidate_count' => $rawCandidateCount,
                'grouped_row_count' => $groupedRowCount,
                'grouping_applied' => $groupingApplied ? 'Yes' : 'No',
            ];

            if ($providerAttempted && $providerError !== '') {
                $pdo->commit();
                $setFlash('warning', 'Advanced extraction failed: ' . $providerError . '. Manual draft entry remains available.', $debug);
                header('Location: ' . $redirectBase . '&status=extract_failed');
                exit;
            }

            if ($rawCandidateCount <= 0) {
                $pdo->commit();
                $setFlash('warning', 'No extracted table rows were detected from the selected PDF pages.', $debug);
                header('Location: ' . $redirectBase . '&status=extract_none');
                exit;
            }

            $extractionRunId = vms_template_create_extraction_run($pdo, [
                'source_id' => $sourceId,
                'input_type' => 'pdf',
                'page_range' => $pageRange,
                'provider' => trim((string)($config['provider'] ?? 'openai')),
                'model_used' => trim((string)($providerDiagnostics['model_attempted'] ?? '')),
                'raw_candidate_count' => $rawCandidateCount,
                'created_grouped_template_count' => 0,
                'status' => 'pending_review',
                'created_by' => $createdBy,
            ]);

            $insertedRows = 0;
            $lowConfidenceCount = 0;
            foreach ($rawCandidates as $candidate) {
                $insertedRows += vms_template_insert_extraction_row($pdo, $source, $extractionRunId, $candidate, $createdBy) > 0 ? 1 : 0;
                if (vms_template_guess_confidence_tier((string)($candidate['confidence_label'] ?? '')) === 'low') {
                    $lowConfidenceCount++;
                }
            }

            vms_template_update_extraction_run($pdo, $extractionRunId, [
                'raw_candidate_count' => $insertedRows,
                'status' => 'pending_review',
            ]);

            $debug['raw_candidate_count'] = $insertedRows;
            $debug['low_confidence_row_count'] = $lowConfidenceCount;
            $debug['accepted_row_count'] = 0;
            $debug['rejected_row_count'] = 0;
            $debug['grouped_draft_count'] = 0;
            $debug['extraction_run_id'] = $extractionRunId;

            $_SESSION['maintenance_template_extract_flash'] = [
                'type' => 'success',
                'message' => 'PDF extraction completed. Review extracted table rows carefully before creating draft templates.',
                'debug' => $debug,
            ];

            $pdo->commit();
            $reviewRedirect = 'maintenance_extraction_review.php?run_id=' . urlencode($extractionRunId) . '&source_id=' . $sourceId;
            if ($equipmentId > 0) {
                $reviewRedirect .= '&equipment_id=' . $equipmentId;
            }
            header('Location: ' . $reviewRedirect);
            exit;
        } catch (Throwable $pdfError) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
                $pdo->beginTransaction();
            }
            $pdo->commit();
            $setFlash('warning', 'PDF extraction failed: ' . trim((string)$pdfError->getMessage()) . '. Manual draft entry remains available.', [
                'input_source_used' => $sourceUsed,
                'pdf_page_range' => $pageRange,
                'pdf_pages_processed' => 0,
                'pdf_conversion_method' => $conversionMethodUsed !== '' ? $conversionMethodUsed : 'Unknown',
                'pdf_command' => $pdfCommand,
                'pdf_output_dir' => $pdfOutputDir !== '' ? $pdfOutputDir : $workDir,
                'pdf_generated_image_count' => $pdfGeneratedImageCount,
                'pdf_generated_files' => implode(', ', array_map('strval', $pdfGeneratedFiles)),
                'pdf_command_exit_code' => $pdfCommandExitCode,
                'pdf_command_stderr' => $pdfCommandStderr,
                'pdf_image_previews' => $pdfImagePreviews,
                'provider_endpoint' => '',
                'provider_model_attempted' => '',
                'provider_models_attempted' => '',
                'provider_image_files_included_count' => 0,
                'provider_approx_payload_size_bytes' => 0,
                'provider_openai_error_message' => '',
                'provider_openai_error_type' => '',
                'provider_openai_error_code' => '',
                'provider_prompt_mode_used' => '',
                'provider_response_text_length' => 0,
                'provider_json_parse_failed' => 'No',
                'provider_zero_rows_returned' => 'No',
                'provider_configured' => vms_template_extraction_is_configured() ? 'Yes' : 'No',
                'provider_attempted' => $providerAttempted ? 'Yes' : 'No',
                'provider_returned_row_count' => 0,
                'heuristic_fallback_attempted' => 'No',
                'raw_candidate_count' => $rawCandidateCount,
                'grouped_row_count' => $groupedRowCount,
                'grouping_applied' => $groupingApplied ? 'Yes' : 'No',
            ]);
            header('Location: ' . $redirectBase . '&status=extract_failed');
            exit;
        } finally {
            vms_template_cleanup_path($workDir);
        }
    }

    if ($action === 'manual_add') {
        $row = [
            'service_name' => trim((string)($_POST['service_name'] ?? '')),
            'interval_hours' => trim((string)($_POST['interval_hours'] ?? '')),
            'interval_months' => trim((string)($_POST['interval_months'] ?? '')),
            'interval_basis' => trim((string)($_POST['interval_basis'] ?? '')),
            'steps' => trim((string)($_POST['steps'] ?? '')),
            'source_excerpt' => trim((string)($_POST['source_excerpt'] ?? '')),
            'confidence_label' => trim((string)($_POST['confidence_label'] ?? 'Manual draft entry')),
            'template_origin' => 'manual',
        ];
        $insertedId = vms_template_insert_row($pdo, $source, $row, $createdBy);
        $pdo->commit();
        if ($insertedId > 0) {
            $setFlash('success', 'Draft maintenance row added.', [
                'input_source_used' => 'manual_entry',
                'pasted_content_length' => 0,
                'provider_configured' => vms_template_extraction_is_configured() ? 'Yes' : 'No',
                'provider_attempted' => 'No',
                'provider_returned_row_count' => 0,
                'heuristic_fallback_attempted' => 'No',
                'raw_candidate_count' => 0,
                'grouped_row_count' => 0,
                'grouping_applied' => 'No',
            ]);
            header('Location: ' . $redirectBase . '&status=draft_added');
            exit;
        }

        $setFlash('warning', 'A matching draft row already exists for this source.', [
            'input_source_used' => 'manual_entry',
            'pasted_content_length' => 0,
            'provider_configured' => vms_template_extraction_is_configured() ? 'Yes' : 'No',
            'provider_attempted' => 'No',
            'provider_returned_row_count' => 0,
            'heuristic_fallback_attempted' => 'No',
            'raw_candidate_count' => 0,
            'grouped_row_count' => 0,
            'grouping_applied' => 'No',
        ]);
        header('Location: ' . $redirectBase . '&status=extract_none');
        exit;
    }

    throw new RuntimeException('Unsupported extraction action.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    exit('Failed to extract maintenance draft: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
