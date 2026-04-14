<?php

declare(strict_types=1);

require __DIR__ . '/lighthouse-common.php';

lighthouse_assert_method(['POST']);

// Validate file upload
if (empty($_FILES['file'])) {
    lighthouse_send_json(400, ['error' => 'No file uploaded. Send a multipart/form-data POST with field name "file"']);
}

$file = $_FILES['file'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION  => 'Upload stopped by extension',
    ];
    $errorMessage = $uploadErrors[$file['error']] ?? 'Unknown upload error';
    lighthouse_send_json(400, ['error' => $errorMessage]);
}

// Validate filename — only allow known safe report filename patterns
$originalName = basename($file['name']);

$allowedPatterns = [
    '/^performance_report_\d{8}_\d{6}\.html$/',
    '/^money_sites_risk_monitoring_report_\d{8}_\d{6}\.html$/',
];

$nameAllowed = false;
foreach ($allowedPatterns as $pattern) {
    if (preg_match($pattern, $originalName) === 1) {
        $nameAllowed = true;
        break;
    }
}

if (!$nameAllowed) {
    lighthouse_send_json(400, [
        'error' => 'Filename does not match an allowed report pattern',
        'allowedPatterns' => [
            'performance_report_YYYYMMDD_HHMMSS.html',
            'money_sites_risk_monitoring_report_YYYYMMDD_HHMMSS.html',
        ]
    ]);
}

// Validate MIME type — must be HTML
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);
if (!in_array($mimeType, ['text/html', 'text/plain'], true)) {
    lighthouse_send_json(400, ['error' => 'Only HTML files are accepted']);
}

// Move file into external/
$externalDir = realpath(__DIR__ . '/../external');
if ($externalDir === false || !is_dir($externalDir)) {
    lighthouse_send_json(500, ['error' => 'External directory does not exist']);
}

$destination = $externalDir . DIRECTORY_SEPARATOR . $originalName;

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    lighthouse_send_json(500, ['error' => 'Failed to move uploaded file to external directory']);
}

lighthouse_send_json(200, [
    'success' => true,
    'message' => 'File uploaded successfully',
    'filename' => $originalName
]);
