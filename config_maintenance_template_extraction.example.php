<?php
declare(strict_types=1);

/*
|---------------------------------------------------------------------------
| Phase 2 Maintenance Template Extraction
|---------------------------------------------------------------------------
|
| Copy to:
|   - private/config_maintenance_template_extraction.php
|   - config_maintenance_template_extraction.php
|
| Environment variables are also supported:
|   VMS_TEMPLATE_EXTRACTION_PROVIDER=openai
|   VMS_TEMPLATE_EXTRACTION_API_KEY=your_api_key_here
|
| This phase does not create live schedules. Extraction output stays draft or
| approved template data only until a later apply phase is built.
|
*/

return [
    'provider' => '',
    'api_key' => '',
    'openai_model' => '',
    'fallback_openai_model' => '',
    'max_input_chars' => 8000,
    'max_output_tokens' => 1200,
    'max_pdf_pages' => 5,
    'max_pdf_file_size_mb' => 15,
    'max_pdf_payload_mb' => 18,
    'pdf_render_dpi' => 150,
    'pdf_conversion_method' => '',
    'temp_upload_dir' => '',
    'timeout_seconds' => 20,
];
