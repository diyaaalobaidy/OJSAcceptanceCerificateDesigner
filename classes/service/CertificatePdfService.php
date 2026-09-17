<?php

namespace APP\plugins\generic\acceptanceLetter\classes\service;

use Dompdf\Dompdf;
use Dompdf\Options;
use APP\submission\Submission;
use PKP\context\Context;
use APP\plugins\generic\acceptanceLetter\classes\model\AcceptanceTemplate;

// Load bundled composer dependencies if needed
if (!class_exists(\Dompdf\Dompdf::class)) {
    $vendorAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
    if (file_exists($vendorAutoload)) {
        require_once $vendorAutoload;
    }
}

class CertificatePdfService
{
    protected Context $context;

    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    /**
     * Map of supported template variables and their descriptions
     */
    public static function getSupportedVariables(): array
    {
        return [
            '{$journalName}'        => 'Full name of the journal',
            '{$journalInitials}'    => 'Journal initials or acronym',
            '{$issn}'               => 'Online / Print ISSN of the journal',
            '{$submissionId}'       => 'Manuscript submission ID',
            '{$articleTitle}'       => 'Accepted article title',
            '{$authorsList}'        => 'All author names separated by commas',
            '{$primaryAuthor}'      => 'Primary / corresponding author name',
            '{$sectionTitle}'       => 'Journal section name (e.g. Articles, Reviews)',
            '{$dateAccepted}'       => 'Date the submission was accepted',
            '{$dateIssued}'         => 'Date this acceptance letter is generated',
            '{$certificateNumber}'  => 'Unique official certificate identification number',
            '{$editorName}'         => 'Name of issuing editor or Editor-in-Chief',
            '{$editorRole}'         => 'Title / Role of issuing editor',
            '{$doi}'                => 'Assigned DOI (if available)',
            '{$verificationUrl}'    => 'URL link to publicly verify authenticity',
            '{$qrCode}'             => 'QR code image linking to verification page',
            '{$headerLogo}'         => 'Official Header Logo image',
            '{$editorSignature}'    => 'Editor Signature image',
            '{$journalSeal}'        => 'Journal Official Seal / Stamp image',
        ];
    }

    /**
     * Replace dynamic tokens with actual submission and journal details
     */
    public function substituteVariables(string $htmlTemplate, Submission $submission, array $extra = []): string
    {
        $publication = $submission->getCurrentPublication();
        
        // Authors
        $authors = $publication->getData('authors') ?? [];
        $authorNames = [];
        $primaryAuthor = '';
        foreach ($authors as $index => $author) {
            $name = $author->getFullName();
            $authorNames[] = $name;
            if ($index === 0 || $author->getData('primaryContact')) {
                $primaryAuthor = $name;
            }
        }

        // Section
        $sectionTitle = '';
        $sectionId = (int) $publication->getData('sectionId');
        if ($sectionId) {
            $section = class_exists(\APP\facades\Repo::class)
                ? \APP\facades\Repo::section()->get($sectionId)
                : (\PKP\db\DAORegistry::getDAO('SectionDAO')?->getById($sectionId));
            $sectionTitle = $section ? $section->getLocalizedTitle() : '';
        }

        $toStr = function ($val): string {
            if (is_array($val)) {
                $primaryLocale = $this->context->getPrimaryLocale();
                if (isset($val[$primaryLocale]) && !empty($val[$primaryLocale])) {
                    return (string) $val[$primaryLocale];
                }
                foreach ($val as $v) {
                    if (!empty($v) && is_scalar($v)) {
                        return (string) $v;
                    }
                }
                return '';
            }
            return (string) ($val ?? '');
        };

        // ISSN
        $onlineIssn = $toStr($this->context->getData('onlineIssn'));
        $printIssn = $toStr($this->context->getData('printIssn'));
        $issn = $onlineIssn ?: $printIssn;

        // Acronym
        $acronym = $this->context->getLocalizedData('acronym');
        if (!$acronym) {
            $acronym = $toStr($this->context->getData('acronym'));
        }

        // Journal name
        $journalName = $this->context->getLocalizedName() ?: $toStr($this->context->getData('name'));

        // QR Code element
        $qrCodeHtml = '';
        if (!empty($extra['qrCodeDataUri'])) {
            $qrCodeHtml = '<img src="' . htmlspecialchars($toStr($extra['qrCodeDataUri'])) . '" alt="QR Code" style="width:90px;height:90px;display:inline-block;" />';
        }

        // Image data URIs for tokens
        $template = $extra['template'] ?? null;
        $logoDataUri = $this->convertPathToDataUri($extra['logoPath'] ?? ($template?->logo_path ?? null));
        $signatureDataUri = $this->convertPathToDataUri($extra['signaturePath'] ?? ($template?->signature_path ?? null));
        $stampDataUri = $this->convertPathToDataUri($extra['stampPath'] ?? ($template?->stamp_path ?? null));

        $logoImgTag = $logoDataUri ? '<img src="' . htmlspecialchars($logoDataUri) . '" alt="Official Header Logo" style="max-height:80px; max-width:250px; display:inline-block;" />' : '';
        $signatureImgTag = $signatureDataUri ? '<img src="' . htmlspecialchars($signatureDataUri) . '" alt="Editor Signature" style="max-height:60px; max-width:180px; display:inline-block;" />' : '';
        $stampImgTag = $stampDataUri ? '<img src="' . htmlspecialchars($stampDataUri) . '" alt="Journal Official Seal" style="max-height:80px; max-width:120px; display:inline-block;" />' : '';

        $replacements = [
            '{$journalName}'       => htmlspecialchars($toStr($journalName)),
            '{$journalInitials}'   => htmlspecialchars($toStr($acronym)),
            '{$issn}'              => htmlspecialchars($toStr($issn)),
            '{$submissionId}'      => (string) $submission->getId(),
            '{$articleTitle}'      => htmlspecialchars($toStr($publication->getLocalizedTitle())),
            '{$authorsList}'       => htmlspecialchars(implode(', ', $authorNames)),
            '{$primaryAuthor}'     => htmlspecialchars($toStr($primaryAuthor ?: ($authorNames[0] ?? ''))),
            '{$sectionTitle}'      => htmlspecialchars($toStr($sectionTitle)),
            '{$dateAccepted}'      => htmlspecialchars($toStr($extra['dateAccepted'] ?? date('Y-m-d'))),
            '{$dateIssued}'        => date('Y-m-d'),
            '{$certificateNumber}' => htmlspecialchars($toStr($extra['certificateNumber'] ?? 'DRAFT')),
            '{$editorName}'        => htmlspecialchars($toStr($extra['editorName'] ?? ($this->context->getData('contactName') ?? 'The Editorial Board'))),
            '{$editorRole}'        => htmlspecialchars($toStr($extra['editorRole'] ?? 'Editor-in-Chief')),
            '{$doi}'               => htmlspecialchars($toStr($publication->getStoredPubId('doi') ?? 'N/A')),
            '{$verificationUrl}'   => htmlspecialchars($toStr($extra['verificationUrl'] ?? '')),
            '{$qrCode}'            => $qrCodeHtml,
            '{$headerLogo}'        => $logoImgTag,
            '{$logo}'              => $logoImgTag,
            '{$editorSignature}'   => $signatureImgTag,
            '{$signature}'         => $signatureImgTag,
            '{$journalSeal}'       => $stampImgTag,
            '{$stamp}'             => $stampImgTag,
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $htmlTemplate);
    }

    /**
     * Wrap body HTML in a printable document layout
     */
    public function buildDocumentHtml(AcceptanceTemplate $template, string $compiledBody, array $extra = []): string
    {
        $orientation = $template->orientation ?? 'portrait';
        $pageSize = $template->page_size ?? 'A4';
        $isRtl = ($template->locale === 'ar' || str_starts_with($template->locale ?? '', 'ar_'));
        $dir = $isRtl ? 'rtl' : 'ltr';

        $extra['editorName'] = htmlspecialchars((string) ($extra['editorName'] ?? ''));
        $extra['editorRole'] = htmlspecialchars((string) ($extra['editorRole'] ?? ''));
        $extra['qrCodeImgTag'] = (string) ($extra['qrCodeImgTag'] ?? '');
        $extra['certificateNumber'] = htmlspecialchars((string) ($extra['certificateNumber'] ?? ''));
        $extra['dateIssued'] = htmlspecialchars((string) ($extra['dateIssued'] ?? date('Y-m-d')));
        $extra['verificationUrl'] = htmlspecialchars((string) ($extra['verificationUrl'] ?? ''));

        // Convert images to base64 Data URIs for reliable PDF embedding
        $logoDataUri = $this->convertPathToDataUri($template->logo_path ?? null);
        $signatureDataUri = $this->convertPathToDataUri($template->signature_path ?? null);
        $stampDataUri = $this->convertPathToDataUri($template->stamp_path ?? null);

        // Header logo (omit from header if explicitly placed into body via token)
        $logoHtml = '';
        $bodyHasLogo = str_contains($template->body_html ?? '', '{$headerLogo}') || str_contains($template->body_html ?? '', '{$logo}');
        if (!$bodyHasLogo && $logoDataUri) {
            $logoHtml = '<div class="header-logo"><img src="' . htmlspecialchars($logoDataUri) . '" style="max-height:80px; max-width:250px;" /></div>';
        }

        // Signature image (omit from bottom signoff if explicitly placed into body via token)
        $signatureHtml = '';
        $bodyHasSignature = str_contains($template->body_html ?? '', '{$editorSignature}') || str_contains($template->body_html ?? '', '{$signature}');
        if (!$bodyHasSignature && $signatureDataUri) {
            $signatureHtml = '<img src="' . htmlspecialchars($signatureDataUri) . '" style="max-height:60px; max-width:180px; vertical-align:middle; margin-right:12px; margin-left:12px;" />';
        }

        // Seal / Stamp image (omit from bottom signoff if explicitly placed into body via token)
        $stampHtml = '';
        $bodyHasStamp = str_contains($template->body_html ?? '', '{$journalSeal}') || str_contains($template->body_html ?? '', '{$stamp}');
        if (!$bodyHasStamp && $stampDataUri) {
            $stampHtml = '<img src="' . htmlspecialchars($stampDataUri) . '" style="max-height:80px; max-width:120px; vertical-align:middle;" />';
        }

        $headerStartAlign = $isRtl ? 'right' : 'left';
        $headerEndAlign = $isRtl ? 'left' : 'right';

        return <<<HTML
<!DOCTYPE html>
<html dir="{$dir}" lang="{$template->locale}">
<head>
    <meta charset="UTF-8">
    <title>Certificate of Acceptance</title>
    <style>
        @page {
            size: {$pageSize} {$orientation};
            margin: 20mm 20mm 20mm 20mm;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            color: #222222;
            line-height: 1.6;
            font-size: 13px;
            direction: {$dir};
            margin: 0;
            padding: 0;
        }
        .container {
            width: 100%;
            position: relative;
        }
        .header {
            border-bottom: 2px solid #005a9c;
            padding-bottom: 12px;
            margin-bottom: 25px;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-logo {
            text-align: {$headerStartAlign};
        }
        .header-title {
            text-align: {$headerEndAlign};
            font-size: 18px;
            font-weight: bold;
            color: #005a9c;
        }
        .body-content {
            min-height: 440px;
            margin-bottom: 30px;
        }
        .footer {
            border-top: 1px solid #dddddd;
            padding-top: 15px;
            margin-top: 30px;
            font-size: 11px;
            color: #666666;
        }
        .signoff-table {
            width: 100%;
            margin-top: 40px;
            border-collapse: collapse;
        }
        .signoff-table td {
            vertical-align: bottom;
        }
        .qr-section {
            text-align: {$headerEndAlign};
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <table class="header-table">
                <tr>
                    <td style="width: 50%; text-align: {$headerStartAlign};">{$logoHtml}</td>
                    <td style="width: 50%; text-align: {$headerEndAlign};">
                        <div class="header-title">{$this->context->getLocalizedName()}</div>
                        <div style="font-size: 11px; color: #555;">Official Acceptance Certificate</div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="body-content">
            {$compiledBody}
        </div>

        <div class="signoff-section">
            <table class="signoff-table">
                <tr>
                    <td style="width: 60%; text-align: {$headerStartAlign};">
                        <div><strong>{$extra['editorName']}</strong></div>
                        <div style="color: #666;">{$extra['editorRole']}</div>
                        <div style="margin-top: 10px;">{$signatureHtml}{$stampHtml}</div>
                    </td>
                    <td style="width: 40%; text-align: {$headerEndAlign};">
                        {$extra['qrCodeImgTag']}
                        <div style="font-size: 10px; color: #777; margin-top: 5px;">Scan to verify certificate</div>
                        <div style="font-size: 10px; color: #777;">ID: {$extra['certificateNumber']}</div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="footer">
            <div style="text-align: center;">
                Generated automatically by {$this->context->getLocalizedName()} on {$extra['dateIssued']}. 
                Verification URL: {$extra['verificationUrl']}
            </div>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Render the given HTML to PDF binary string
     */
    public function renderToPdf(string $html): string
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            throw new \Exception(
                'Dompdf library is not loaded. Please run "composer install --no-dev" inside plugins/generic/acceptanceLetter on your server.'
            );
        }

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $chrootDirs = array_filter([
            dirname(__DIR__, 4),
            dirname(__DIR__, 5),
            getcwd(),
            sys_get_temp_dir(),
        ]);
        if (class_exists(\PKP\core\Core::class) && method_exists(\PKP\core\Core::class, 'getBaseDir')) {
            $chrootDirs[] = \PKP\core\Core::getBaseDir();
        }
        $options->setChroot(array_values(array_unique($chrootDirs)));

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Convert an image path or URL to a Base64 data URI for reliable Dompdf embedding
     */
    public function convertPathToDataUri(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        // Already a data URI
        if (str_starts_with($path, 'data:image/')) {
            return $path;
        }

        $fullPath = $this->resolveLocalImagePath($path);

        // If found on local filesystem, read and encode
        if ($fullPath && is_readable($fullPath) && !is_dir($fullPath)) {
            $content = @file_get_contents($fullPath);
            if ($content !== false && strlen($content) > 0) {
                $mime = $this->detectMimeType($fullPath, $content);
                return 'data:' . $mime . ';base64,' . base64_encode($content);
            }
        }

        // If it is a web URL, attempt to fetch it directly
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $content = @file_get_contents($path);
            if ($content !== false && strlen($content) > 0) {
                $ext = strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
                $mime = match ($ext) {
                    'jpg', 'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'gif' => 'image/gif',
                    'svg' => 'image/svg+xml',
                    'webp' => 'image/webp',
                    default => 'image/png',
                };
                return 'data:' . $mime . ';base64,' . base64_encode($content);
            }
        }

        return $path;
    }

    /**
     * Try multiple candidate base paths to locate the image file on disk
     */
    protected function resolveLocalImagePath(string $path): ?string
    {
        // Direct match
        if (file_exists($path) && !is_dir($path)) {
            return realpath($path) ?: $path;
        }

        $cleanPath = ltrim($path, '/');

        // Gather possible base directories
        $candidateBases = [];

        if (class_exists(\PKP\core\Core::class) && method_exists(\PKP\core\Core::class, 'getBaseDir')) {
            $candidateBases[] = \PKP\core\Core::getBaseDir();
        }
        if (class_exists(\Core::class) && method_exists(\Core::class, 'getBaseDir')) {
            $candidateBases[] = \Core::getBaseDir();
        }
        if (function_exists('base_path')) {
            $candidateBases[] = base_path();
        }
        if (defined('INDEX_FILE_LOCATION')) {
            $candidateBases[] = dirname(INDEX_FILE_LOCATION);
        }

        // Relative to plugin path (plugins/generic/acceptanceLetter/classes/service)
        $candidateBases[] = dirname(__DIR__, 4);
        $candidateBases[] = dirname(__DIR__, 5);
        $candidateBases[] = getcwd();
        if (!empty($_SERVER['DOCUMENT_ROOT'])) {
            $candidateBases[] = $_SERVER['DOCUMENT_ROOT'];
        }

        // Try candidate bases directly
        foreach (array_unique(array_filter($candidateBases)) as $baseDir) {
            $test = rtrim($baseDir, '/') . '/' . $cleanPath;
            if (file_exists($test) && !is_dir($test)) {
                return realpath($test) ?: $test;
            }
        }

        // Try with public files directory
        $publicFileManager = class_exists(\APP\file\PublicFileManager::class)
            ? new \APP\file\PublicFileManager()
            : (class_exists(\PKP\file\PKPPublicFileManager::class) ? new \PKP\file\PKPPublicFileManager() : null);

        if ($publicFileManager && isset($this->context)) {
            $contextFilesPath = $publicFileManager->getContextFilesPath($this->context->getId());
            $filename = basename($path);

            // Test context files path directly
            $test = rtrim($contextFilesPath, '/') . '/' . $filename;
            if (file_exists($test) && !is_dir($test)) {
                return realpath($test) ?: $test;
            }

            // Test context files path under candidate bases
            foreach (array_unique(array_filter($candidateBases)) as $baseDir) {
                $test = rtrim($baseDir, '/') . '/' . ltrim($contextFilesPath, '/') . '/' . $filename;
                if (file_exists($test) && !is_dir($test)) {
                    return realpath($test) ?: $test;
                }
            }
        }

        return null;
    }

    /**
     * Determine mime type of an image file
     */
    protected function detectMimeType(string $filePath, string $content): string
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            default => (function_exists('mime_content_type') ? @mime_content_type($filePath) : null) ?: 'image/png',
        };
    }
}
