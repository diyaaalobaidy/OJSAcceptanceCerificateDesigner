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

        $logoHtml = '';
        if ($template->logo_path) {
            $logoHtml = '<div class="header-logo"><img src="' . htmlspecialchars($template->logo_path) . '" style="max-height:80px; max-width:250px;" /></div>';
        }

        $signatureHtml = '';
        if ($template->signature_path) {
            $signatureHtml = '<img src="' . htmlspecialchars($template->signature_path) . '" style="max-height:60px;" />';
        }

        $stampHtml = '';
        if ($template->stamp_path) {
            $stampHtml = '<img src="' . htmlspecialchars($template->stamp_path) . '" style="max-height:80px;" />';
        }

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
            text-align: left;
        }
        .header-title {
            text-align: right;
            font-size: 18px;
            font-weight: bold;
            color: #005a9c;
        }
        .body-content {
            min-height: 480px;
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
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <table class="header-table">
                <tr>
                    <td style="width: 50%;">{$logoHtml}</td>
                    <td style="width: 50%; text-align: right;">
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
                    <td style="width: 60%;">
                        <div><strong>{$extra['editorName']}</strong></div>
                        <div style="color: #666;">{$extra['editorRole']}</div>
                        <div style="margin-top: 10px;">{$signatureHtml} {$stampHtml}</div>
                    </td>
                    <td style="width: 40%; text-align: right;">
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

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        return $dompdf->output();
    }
}
