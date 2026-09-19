<?php

namespace APP\plugins\generic\acceptanceLetter\classes\service;

use Dompdf\Dompdf;
use Dompdf\Options;
use APP\submission\Submission;
use PKP\context\Context;
use APP\plugins\generic\acceptanceLetter\classes\model\AcceptanceTemplate;
use ArPHP\I18N\Arabic;

// Load bundled composer dependencies if needed
if (!class_exists(\Dompdf\Dompdf::class) || !class_exists(\ArPHP\I18N\Arabic::class)) {
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
            // Manuscript & Metadata
            '{$submissionId}'           => 'Manuscript submission ID (e.g. 1024)',
            '{$articleTitle}'           => 'Accepted article title',
            '{$articleFullTitle}'       => 'Full title including prefix and subtitle',
            '{$articlePrefix}'          => 'Article title prefix (e.g. The, A)',
            '{$articleSubtitle}'        => 'Article subtitle',
            '{$articleAbstract}'        => 'Article abstract text',
            '{$sectionTitle}'           => 'Journal section name (e.g. Articles, Reviews)',
            '{$doi}'                    => 'Assigned Digital Object Identifier (DOI)',
            '{$pages}'                  => 'Publication page range (e.g. 1-15)',
            '{$articleNumber}'          => 'Electronic article number / e-location ID',
            '{$keywords}'               => 'Article keywords separated by commas',
            '{$disciplines}'            => 'Academic disciplines of the submission',
            '{$subjects}'               => 'Subject classifications of the article',
            '{$supportingAgencies}'     => 'Research funding and supporting agencies',
            '{$licenseUrl}'             => 'URL of the article publication license',
            '{$copyrightHolder}'        => 'Copyright holder name',
            '{$copyrightYear}'          => 'Copyright year',
            '{$urlPublished}'           => 'Public URL of the published article',

            // Key Dates
            '{$dateAccepted}'           => 'Date the submission was formally accepted',
            '{$dateSubmitted}'          => 'Date the manuscript was submitted to journal',
            '{$dateLastActivity}'       => 'Date of the most recent activity on submission',
            '{$datePublished}'          => 'Date the article was published',
            '{$dateIssued}'             => 'Date this acceptance certificate is generated',

            // Authors & Contributors
            '{$authorsList}'            => 'All author names separated by commas',
            '{$authorsAffiliations}'    => 'All authors formatted with institutional affiliations',
            '{$primaryAuthor}'          => 'Primary / corresponding author full name',
            '{$primaryAuthorEmail}'     => 'Email address of primary corresponding author',
            '{$primaryAuthorAffiliation}'=> 'Institutional affiliation of primary author',
            '{$primaryAuthorOrcid}'     => 'ORCID identifier of primary author',

            // Issue & Volume
            '{$issueIdentification}'    => 'Full issue identification (e.g. Vol. 10 No. 2 (2025))',
            '{$issueTitle}'             => 'Title of the scheduled or published issue',
            '{$issueVolume}'            => 'Issue volume number',
            '{$issueNumber}'            => 'Issue issue number',
            '{$issueYear}'              => 'Issue publication year',

            // Journal & Publisher
            '{$journalName}'            => 'Full name of the journal',
            '{$journalInitials}'        => 'Journal initials or acronym',
            '{$issn}'                   => 'Online or Print ISSN of the journal',
            '{$onlineIssn}'             => 'Journal Online ISSN',
            '{$printIssn}'              => 'Journal Print ISSN',
            '{$publisherInstitution}'   => 'Publisher institution / organization name',
            '{$journalUrl}'             => 'Public homepage URL of the journal',
            '{$contactName}'            => 'Principal journal contact person name',
            '{$contactEmail}'           => 'Principal journal contact email address',

            // Certificate, Verification & Seals
            '{$certificateNumber}'      => 'Unique official certificate identification number',
            '{$editorName}'             => 'Name of issuing editor or Editor-in-Chief',
            '{$editorRole}'             => 'Title / Role of issuing editor',
            '{$verificationUrl}'        => 'URL link to publicly verify authenticity',
            '{$qrCode}'                 => 'QR code image linking to verification page',
            '{$headerLogo}'             => 'Official Header Logo image',
            '{$editorSignature}'        => 'Editor Signature image',
            '{$journalSeal}'            => 'Journal Official Seal / Stamp image',
        ];
    }

    /**
     * Grouped map of supported variables for UI organization
     */
    public static function getCategorizedVariables(): array
    {
        return [
            'Manuscript & Metadata' => [
                '{$submissionId}'           => 'Manuscript submission ID',
                '{$articleTitle}'           => 'Accepted article title',
                '{$articleFullTitle}'       => 'Full title including prefix and subtitle',
                '{$articlePrefix}'          => 'Article title prefix',
                '{$articleSubtitle}'        => 'Article subtitle',
                '{$articleAbstract}'        => 'Article abstract text',
                '{$sectionTitle}'           => 'Journal section name',
                '{$doi}'                    => 'Assigned DOI',
                '{$pages}'                  => 'Pages in issue',
                '{$articleNumber}'          => 'Article number',
                '{$keywords}'               => 'Article keywords',
                '{$disciplines}'            => 'Disciplines',
                '{$subjects}'               => 'Subjects',
                '{$supportingAgencies}'     => 'Funding / supporting agencies',
                '{$licenseUrl}'             => 'License URL',
                '{$copyrightHolder}'        => 'Copyright holder',
                '{$copyrightYear}'          => 'Copyright year',
                '{$urlPublished}'           => 'Public article URL',
            ],
            'Dates' => [
                '{$dateAccepted}'           => 'Date submission was accepted',
                '{$dateSubmitted}'          => 'Date manuscript was submitted',
                '{$dateLastActivity}'       => 'Date of last submission activity',
                '{$datePublished}'          => 'Date article was published',
                '{$dateIssued}'             => 'Date certificate was generated',
            ],
            'Authors & Affiliations' => [
                '{$authorsList}'            => 'All author names',
                '{$authorsAffiliations}'    => 'All authors with affiliations',
                '{$primaryAuthor}'          => 'Primary / corresponding author name',
                '{$primaryAuthorEmail}'     => 'Primary author email',
                '{$primaryAuthorAffiliation}'=> 'Primary author affiliation',
                '{$primaryAuthorOrcid}'     => 'Primary author ORCID iD',
            ],
            'Issue & Volume' => [
                '{$issueIdentification}'    => 'Full issue identification (e.g. Vol. 10 No. 2 (2025))',
                '{$issueTitle}'             => 'Issue title',
                '{$issueVolume}'            => 'Issue volume',
                '{$issueNumber}'            => 'Issue number',
                '{$issueYear}'              => 'Issue year',
            ],
            'Journal & Publisher' => [
                '{$journalName}'            => 'Full journal name',
                '{$journalInitials}'        => 'Journal acronym / initials',
                '{$issn}'                   => 'Online or Print ISSN',
                '{$onlineIssn}'             => 'Online ISSN',
                '{$printIssn}'              => 'Print ISSN',
                '{$publisherInstitution}'   => 'Publisher institution',
                '{$journalUrl}'             => 'Journal website URL',
                '{$contactName}'            => 'Principal contact name',
                '{$contactEmail}'           => 'Principal contact email',
            ],
            'Certificate & Graphics' => [
                '{$certificateNumber}'      => 'Certificate identification number',
                '{$editorName}'             => 'Issuing editor name',
                '{$editorRole}'             => 'Issuing editor role',
                '{$verificationUrl}'        => 'Public verification URL',
                '{$qrCode}'                 => 'Verification QR code image',
                '{$headerLogo}'             => 'Official Header Logo image',
                '{$editorSignature}'        => 'Editor Signature image',
                '{$journalSeal}'            => 'Journal Seal / Stamp image',
            ],
        ];
    }

    /**
     * Resolve the formal acceptance date of a submission according to OJS 3.5 decision workflows
     */
    public static function resolveDateAccepted(Submission $submission, $publication = null): string
    {
        $pub = $publication ?? $submission->getCurrentPublication();
        if ($pub && $pub->getData('dateAccepted')) {
            $val = (string) $pub->getData('dateAccepted');
            return strlen($val) >= 10 ? substr($val, 0, 10) : $val;
        }

        // Try Repo::decision() (OJS 3.4 & 3.5 standard decision repository)
        if (class_exists(\APP\facades\Repo::class) && method_exists(\APP\facades\Repo::class, 'decision')) {
            try {
                $acceptConstants = [2]; // Decision::ACCEPT
                if (defined('\PKP\decision\Decision::ACCEPT')) {
                    $acceptConstants[] = constant('\PKP\decision\Decision::ACCEPT');
                }
                if (defined('\PKP\decision\Decision::ACCEPT_INTERNAL')) {
                    $acceptConstants[] = constant('\PKP\decision\Decision::ACCEPT_INTERNAL');
                }
                $acceptConstants = array_values(array_unique($acceptConstants));

                $acceptDecisions = \APP\facades\Repo::decision()
                    ->getCollector()
                    ->filterBySubmissionIds([$submission->getId()])
                    ->filterByDecisionTypes($acceptConstants)
                    ->orderBy('dateDecided', 'desc')
                    ->getMany();

                $latest = $acceptDecisions->first();
                if ($latest && $latest->getData('dateDecided')) {
                    $val = (string) $latest->getData('dateDecided');
                    return strlen($val) >= 10 ? substr($val, 0, 10) : $val;
                }
            } catch (\Throwable $e) {
                // Ignore and try fallback
            }
        }

        // Fallback: direct query to edit_decisions table
        if (class_exists(\Illuminate\Support\Facades\DB::class)) {
            try {
                $row = \Illuminate\Support\Facades\DB::table('edit_decisions')
                    ->where('submission_id', $submission->getId())
                    ->whereIn('decision', [2, 19]) // 2: ACCEPT, 19: ACCEPT_INTERNAL
                    ->orderBy('date_decided', 'desc')
                    ->first();
                if ($row && !empty($row->date_decided)) {
                    $val = (string) $row->date_decided;
                    return strlen($val) >= 10 ? substr($val, 0, 10) : $val;
                }
            } catch (\Throwable $e) {
                // Ignore and try fallback
            }
        }

        if ($submission->getData('dateAccepted')) {
            $val = (string) $submission->getData('dateAccepted');
            return strlen($val) >= 10 ? substr($val, 0, 10) : $val;
        }

        return date('Y-m-d');
    }

    /**
     * Replace dynamic tokens with actual submission and journal details
     */
    public function substituteVariables(string $htmlTemplate, Submission $submission, array $extra = []): string
    {
        $publication = $submission->getCurrentPublication();

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

        $formatDate = function ($dateVal): string {
            if (empty($dateVal)) {
                return '';
            }
            $str = (string) $dateVal;
            return strlen($str) >= 10 ? substr($str, 0, 10) : $str;
        };

        $formatList = function ($data): string {
            if (empty($data)) {
                return '';
            }
            if (is_string($data)) {
                return $data;
            }
            if (is_array($data)) {
                $items = [];
                foreach ($data as $item) {
                    if (is_string($item)) {
                        $items[] = trim($item);
                    } elseif (is_array($item) && isset($item['name'])) {
                        $items[] = trim((string) $item['name']);
                    } elseif (is_object($item) && isset($item->name)) {
                        $items[] = trim((string) $item->name);
                    }
                }
                return implode(', ', array_filter($items));
            }
            return '';
        };

        // Authors and Affiliations
        $authors = $publication ? ($publication->getData('authors') ?? []) : [];
        $authorNames = [];
        $authorAffiliationsList = [];
        $primaryAuthor = '';
        $primaryAuthorEmail = '';
        $primaryAuthorAffiliation = '';
        $primaryAuthorOrcid = '';

        foreach ($authors as $index => $author) {
            $name = method_exists($author, 'getFullName') ? $author->getFullName() : ($author->getData('givenName') . ' ' . $author->getData('familyName'));
            $authorNames[] = $name;

            // Affiliation resolution across OJS versions
            $affil = '';
            if (method_exists($author, 'getLocalizedAffiliationNamesAsString')) {
                $affil = (string) $author->getLocalizedAffiliationNamesAsString();
            }
            if (!$affil && method_exists($author, 'getLocalizedData')) {
                $affil = $toStr($author->getLocalizedData('affiliation'));
            }
            if (!$affil && method_exists($author, 'getLocalizedOrganizationName')) {
                $affil = (string) $author->getLocalizedOrganizationName();
            }
            if (!$affil) {
                $affil = $toStr($author->getData('affiliation'));
            }

            if (!empty($affil)) {
                $authorAffiliationsList[] = $name . ' (' . $affil . ')';
            } else {
                $authorAffiliationsList[] = $name;
            }

            $isPrimary = ($index === 0 || $author->getData('primaryContact'));
            if ($isPrimary && empty($primaryAuthor)) {
                $primaryAuthor = $name;
                $primaryAuthorEmail = method_exists($author, 'getEmail') ? (string) $author->getEmail() : (string) $author->getData('email');
                $primaryAuthorAffiliation = $affil;
                $primaryAuthorOrcid = method_exists($author, 'getOrcid') ? (string) $author->getOrcid() : (string) $author->getData('orcid');
            } elseif ($author->getData('primaryContact')) {
                $primaryAuthor = $name;
                $primaryAuthorEmail = method_exists($author, 'getEmail') ? (string) $author->getEmail() : (string) $author->getData('email');
                $primaryAuthorAffiliation = $affil;
                $primaryAuthorOrcid = method_exists($author, 'getOrcid') ? (string) $author->getOrcid() : (string) $author->getData('orcid');
            }
        }

        // Section
        $sectionTitle = '';
        $sectionId = $publication ? (int) $publication->getData('sectionId') : 0;
        if ($sectionId) {
            $section = class_exists(\APP\facades\Repo::class) && method_exists(\APP\facades\Repo::class, 'section')
                ? \APP\facades\Repo::section()->get($sectionId)
                : (\PKP\db\DAORegistry::getDAO('SectionDAO')?->getById($sectionId));
            $sectionTitle = $section ? (method_exists($section, 'getLocalizedTitle') ? $section->getLocalizedTitle() : $toStr($section->getTitle())) : '';
        }

        // Titles, Abstract, Prefix, Subtitle
        $title = $publication ? $toStr($publication->getLocalizedTitle() ?: $publication->getLocalizedData('title')) : '';
        $prefix = $publication ? $toStr($publication->getLocalizedData('prefix')) : '';
        $subtitle = $publication ? $toStr($publication->getLocalizedData('subtitle')) : '';
        $fullTitle = $publication ? $toStr($publication->getLocalizedData('fullTitle')) : '';
        if (!$fullTitle) {
            $fullTitle = trim(($prefix ? $prefix . ' ' : '') . $title . ($subtitle ? ': ' . $subtitle : ''));
        }

        $abstractRaw = $publication ? $toStr($publication->getLocalizedData('abstract')) : '';
        $abstractClean = trim(strip_tags($abstractRaw));

        // Publication Metadata
        $pages = $publication ? $toStr($publication->getData('pages')) : '';
        $articleNumber = $publication ? $toStr($publication->getData('articleNumber')) : '';
        $licenseUrl = $publication ? $toStr($publication->getData('licenseUrl')) : '';
        $copyrightHolder = $publication ? $toStr($publication->getLocalizedData('copyrightHolder') ?: $publication->getData('copyrightHolder')) : '';
        $copyrightYear = $publication ? $toStr($publication->getData('copyrightYear')) : '';
        $urlPublished = $publication ? $toStr($publication->getData('urlPublished')) : '';

        // Keywords, Disciplines, Subjects, Supporting Agencies
        $keywords = $publication ? $formatList($publication->getLocalizedData('keywords')) : '';
        $disciplines = $publication ? $formatList($publication->getLocalizedData('disciplines')) : '';
        $subjects = $publication ? $formatList($publication->getLocalizedData('subjects')) : '';
        $supportingAgencies = $publication ? $formatList($publication->getLocalizedData('supportingAgencies')) : '';
        if (!$supportingAgencies && $publication) {
            $supportingAgencies = $toStr($publication->getLocalizedData('fundingStatement'));
        }

        // DOI
        $doi = 'N/A';
        if ($publication) {
            $storedDoi = $toStr($publication->getStoredPubId('doi'));
            if ($storedDoi) {
                $doi = $storedDoi;
            } else {
                $doiObj = $publication->getData('doiObject');
                if ($doiObj && method_exists($doiObj, 'getDoi')) {
                    $doi = (string) $doiObj->getDoi();
                }
            }
        }

        // Issue and Volume Metadata
        $issueTitle = '';
        $issueVolume = '';
        $issueNumber = '';
        $issueYear = '';
        $issueIdentification = '';

        $issueId = $publication ? (int) $publication->getData('issueId') : 0;
        if ($issueId) {
            $issue = class_exists(\APP\facades\Repo::class) && method_exists(\APP\facades\Repo::class, 'issue')
                ? \APP\facades\Repo::issue()->get($issueId)
                : (\PKP\db\DAORegistry::getDAO('IssueDAO')?->getById($issueId, $this->context->getId()));

            if ($issue) {
                $issueIdentification = method_exists($issue, 'getIssueIdentification') ? $issue->getIssueIdentification() : (string) $issue->getData('identification');
                $issueTitle = method_exists($issue, 'getLocalizedTitle') ? (string) $issue->getLocalizedTitle() : (string) $issue->getData('title');
                $issueVolume = (string) $issue->getData('volume');
                $issueNumber = (string) $issue->getData('number');
                $issueYear = (string) $issue->getData('year');
            }
        }

        // Dates
        $dateSubmitted = $formatDate($submission->getData('dateSubmitted'));
        $dateLastActivity = $formatDate($submission->getData('dateLastActivity'));
        $datePublished = $publication ? $formatDate($publication->getData('datePublished')) : '';
        $dateAccepted = !empty($extra['dateAccepted'])
            ? $formatDate($extra['dateAccepted'])
            : self::resolveDateAccepted($submission, $publication);

        // Journal details
        $onlineIssn = $toStr($this->context->getData('onlineIssn'));
        $printIssn = $toStr($this->context->getData('printIssn'));
        $issn = $onlineIssn ?: $printIssn;

        $acronym = $this->context->getLocalizedData('acronym');
        if (!$acronym) {
            $acronym = $toStr($this->context->getData('acronym'));
        }

        $journalName = $this->context->getLocalizedName() ?: $toStr($this->context->getData('name'));
        $publisherInstitution = $toStr($this->context->getLocalizedData('publisherInstitution') ?: $this->context->getData('publisherInstitution'));
        $journalUrl = method_exists($this->context, 'getUrl') ? (string) $this->context->getUrl() : '';
        $contactName = $toStr($this->context->getData('contactName'));
        $contactEmail = $toStr($this->context->getData('contactEmail'));

        // QR Code element
        $qrCodeHtml = '';
        if (!empty($extra['qrCodeDataUri'])) {
            $qrCodeHtml = '<img src="' . htmlspecialchars($toStr($extra['qrCodeDataUri'])) . '" alt="QR Code" style="width:70px;height:70px;display:inline-block;" />';
        }

        // Image data URIs and fitted dimensions for tokens (preserving aspect ratio)
        $template = $extra['template'] ?? null;
        $logoDataUri = $this->convertPathToDataUri($extra['logoPath'] ?? ($template?->logo_path ?? null));
        $signatureDataUri = $this->convertPathToDataUri($extra['signaturePath'] ?? ($template?->signature_path ?? null));
        $stampDataUri = $this->convertPathToDataUri($extra['stampPath'] ?? ($template?->stamp_path ?? null));

        $logoDim = $logoDataUri ? $this->computeFittedDimensions($logoDataUri, 160, 60) : null;
        $signatureDim = $signatureDataUri ? $this->computeFittedDimensions($signatureDataUri, 130, 45) : null;
        $stampDim = $stampDataUri ? $this->computeFittedDimensions($stampDataUri, 75, 75) : null;

        $logoImgTag = ($logoDataUri && $logoDim)
            ? '<img src="' . htmlspecialchars($logoDataUri) . '" alt="Official Header Logo" width="' . $logoDim['width'] . '" height="' . $logoDim['height'] . '" class="header-logo-img" style="width:' . $logoDim['width'] . 'px; height:' . $logoDim['height'] . 'px; display:inline-block; vertical-align:middle;" />'
            : '';
        $signatureImgTag = ($signatureDataUri && $signatureDim)
            ? '<img src="' . htmlspecialchars($signatureDataUri) . '" alt="Editor Signature" width="' . $signatureDim['width'] . '" height="' . $signatureDim['height'] . '" class="signature-img" style="width:' . $signatureDim['width'] . 'px; height:' . $signatureDim['height'] . 'px; display:inline-block; vertical-align:middle;" />'
            : '';
        $stampImgTag = ($stampDataUri && $stampDim)
            ? '<img src="' . htmlspecialchars($stampDataUri) . '" alt="Journal Official Seal" width="' . $stampDim['width'] . '" height="' . $stampDim['height'] . '" class="stamp-img" style="width:' . $stampDim['width'] . 'px; height:' . $stampDim['height'] . 'px; display:inline-block; vertical-align:middle;" />'
            : '';

        $replacements = [
            // Journal & Publisher
            '{$journalName}'            => htmlspecialchars($toStr($journalName)),
            '{$journalInitials}'        => htmlspecialchars($toStr($acronym)),
            '{$issn}'                   => htmlspecialchars($toStr($issn)),
            '{$onlineIssn}'             => htmlspecialchars($onlineIssn),
            '{$printIssn}'              => htmlspecialchars($printIssn),
            '{$publisherInstitution}'   => htmlspecialchars($publisherInstitution),
            '{$journalUrl}'             => htmlspecialchars($journalUrl),
            '{$contactName}'            => htmlspecialchars($contactName),
            '{$contactEmail}'           => htmlspecialchars($contactEmail),

            // Manuscript & Publication Metadata
            '{$submissionId}'           => (string) $submission->getId(),
            '{$articleTitle}'           => htmlspecialchars($title),
            '{$articleFullTitle}'       => htmlspecialchars($fullTitle),
            '{$articlePrefix}'          => htmlspecialchars($prefix),
            '{$articleSubtitle}'        => htmlspecialchars($subtitle),
            '{$articleAbstract}'        => htmlspecialchars($abstractClean),
            '{$sectionTitle}'           => htmlspecialchars($toStr($sectionTitle)),
            '{$doi}'                    => htmlspecialchars($doi),
            '{$pages}'                  => htmlspecialchars($pages),
            '{$articleNumber}'          => htmlspecialchars($articleNumber),
            '{$licenseUrl}'             => htmlspecialchars($licenseUrl),
            '{$copyrightHolder}'        => htmlspecialchars($copyrightHolder),
            '{$copyrightYear}'          => htmlspecialchars($copyrightYear),
            '{$urlPublished}'           => htmlspecialchars($urlPublished),
            '{$keywords}'               => htmlspecialchars($keywords),
            '{$disciplines}'            => htmlspecialchars($disciplines),
            '{$subjects}'               => htmlspecialchars($subjects),
            '{$supportingAgencies}'     => htmlspecialchars($supportingAgencies),

            // Authors & Contributors
            '{$authorsList}'            => htmlspecialchars(implode(', ', $authorNames)),
            '{$authorsAffiliations}'    => htmlspecialchars(implode(', ', $authorAffiliationsList)),
            '{$primaryAuthor}'          => htmlspecialchars($toStr($primaryAuthor ?: ($authorNames[0] ?? ''))),
            '{$primaryAuthorEmail}'     => htmlspecialchars($primaryAuthorEmail),
            '{$primaryAuthorAffiliation}'=> htmlspecialchars($primaryAuthorAffiliation),
            '{$primaryAuthorOrcid}'     => htmlspecialchars($primaryAuthorOrcid),

            // Issue & Volume
            '{$issueIdentification}'    => htmlspecialchars($issueIdentification),
            '{$issueTitle}'             => htmlspecialchars($issueTitle),
            '{$issueVolume}'            => htmlspecialchars($issueVolume),
            '{$issueNumber}'            => htmlspecialchars($issueNumber),
            '{$issueYear}'              => htmlspecialchars($issueYear),

            // Key Dates
            '{$dateAccepted}'           => htmlspecialchars($dateAccepted),
            '{$dateSubmitted}'          => htmlspecialchars($dateSubmitted),
            '{$dateLastActivity}'       => htmlspecialchars($dateLastActivity),
            '{$datePublished}'          => htmlspecialchars($datePublished),
            '{$dateIssued}'             => date('Y-m-d'),

            // Certificate & Verification
            '{$certificateNumber}'      => htmlspecialchars($toStr($extra['certificateNumber'] ?? 'DRAFT')),
            '{$editorName}'             => htmlspecialchars($toStr($extra['editorName'] ?? ($contactName ?: 'The Editorial Board'))),
            '{$editorRole}'             => htmlspecialchars($toStr($extra['editorRole'] ?? 'Editor-in-Chief')),
            '{$verificationUrl}'        => htmlspecialchars($toStr($extra['verificationUrl'] ?? '')),
            '{$qrCode}'                 => $qrCodeHtml,

            // Images & Seals
            '{$headerLogo}'             => $logoImgTag,
            '{$logo}'                   => $logoImgTag,
            '{$editorSignature}'        => $signatureImgTag,
            '{$signature}'              => $signatureImgTag,
            '{$journalSeal}'            => $stampImgTag,
            '{$stamp}'                  => $stampImgTag,
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
            $dim = $this->computeFittedDimensions($logoDataUri, 160, 60);
            $logoHtml = '<div class="header-logo"><img src="' . htmlspecialchars($logoDataUri) . '" width="' . $dim['width'] . '" height="' . $dim['height'] . '" class="header-logo-img" style="width:' . $dim['width'] . 'px; height:' . $dim['height'] . 'px; display:inline-block; vertical-align:middle;" /></div>';
        }

        // Signature image (omit from bottom signoff if explicitly placed into body via token)
        $signatureHtml = '';
        $bodyHasSignature = str_contains($template->body_html ?? '', '{$editorSignature}') || str_contains($template->body_html ?? '', '{$signature}');
        if (!$bodyHasSignature && $signatureDataUri) {
            $dim = $this->computeFittedDimensions($signatureDataUri, 130, 45);
            $signatureHtml = '<img src="' . htmlspecialchars($signatureDataUri) . '" width="' . $dim['width'] . '" height="' . $dim['height'] . '" class="signature-img" style="width:' . $dim['width'] . 'px; height:' . $dim['height'] . 'px; vertical-align:middle; margin-right:8px; margin-left:8px; display:inline-block;" />';
        }

        // Seal / Stamp image (omit from bottom signoff if explicitly placed into body via token)
        $stampHtml = '';
        $bodyHasStamp = str_contains($template->body_html ?? '', '{$journalSeal}') || str_contains($template->body_html ?? '', '{$stamp}');
        if (!$bodyHasStamp && $stampDataUri) {
            $dim = $this->computeFittedDimensions($stampDataUri, 75, 75);
            $stampHtml = '<img src="' . htmlspecialchars($stampDataUri) . '" width="' . $dim['width'] . '" height="' . $dim['height'] . '" class="stamp-img" style="width:' . $dim['width'] . 'px; height:' . $dim['height'] . 'px; vertical-align:middle; display:inline-block;" />';
        }

        $headerStartAlign = $isRtl ? 'right' : 'left';
        $headerEndAlign = $isRtl ? 'left' : 'right';
        $subTitle = $isRtl ? 'شهادة قبول رسمية' : 'Official Acceptance Certificate';
        $verifyText = $isRtl ? 'امسح الرمز للتحقق من الشهادة' : 'Scan to verify certificate';
        $genText = $isRtl
            ? "تم إصدار هذه الوثيقة آلياً بواسطة {$this->context->getLocalizedName()} بتاريخ {$extra['dateIssued']}."
            : "Generated automatically by {$this->context->getLocalizedName()} on {$extra['dateIssued']}.";

        return <<<HTML
<!DOCTYPE html>
<html lang="{$template->locale}">
<head>
    <meta charset="UTF-8">
    <title>Certificate of Acceptance</title>
    <style>
        @page {
            size: {$pageSize} {$orientation};
            margin: 12mm 15mm 10mm 15mm;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            color: #222222;
            line-height: 1.5;
            font-size: 12px;
            margin: 0;
            padding: 0;
            text-align: {$headerStartAlign};
        }
        .container {
            width: 100%;
            position: relative;
        }
        .header {
            border-bottom: 2px solid #005a9c;
            padding-bottom: 8px;
            margin-bottom: 15px;
            overflow: hidden;
            page-break-inside: avoid;
        }
        .header-col-start {
            float: {$headerStartAlign};
            width: 58%;
            text-align: {$headerStartAlign};
        }
        .header-col-end {
            float: {$headerEndAlign};
            width: 40%;
            text-align: {$headerEndAlign};
        }
        .header-title {
            font-size: 16px;
            font-weight: bold;
            color: #005a9c;
        }
        .header-subtitle {
            font-size: 11px;
            color: #555555;
            margin-top: 2px;
        }
        .clearfix {
            clear: both;
        }
        .body-content {
            margin-bottom: 12px;
            text-align: {$headerStartAlign};
        }
        .body-content h2 {
            font-size: 16px;
            margin: 0 0 10px 0;
            color: #005a9c;
            text-align: center;
        }
        .body-content p {
            margin: 0 0 7px 0;
            line-height: 1.5;
        }
        .body-content blockquote {
            margin: 8px 0;
            padding: 6px 12px;
            border-{$headerStartAlign}: 3px solid #005a9c;
            background: #f8fafc;
            text-align: {$headerStartAlign};
        }
        .signoff-section {
            margin-top: 25px;
            overflow: hidden;
            page-break-inside: avoid;
        }
        .signoff-editor {
            float: {$headerStartAlign};
            width: 58%;
            text-align: {$headerStartAlign};
        }
        .signoff-qr {
            float: {$headerEndAlign};
            width: 40%;
            text-align: {$headerEndAlign};
        }
        .signature-img {
            vertical-align: middle;
            margin-right: 8px;
            margin-left: 8px;
            display: inline-block;
        }
        .stamp-img {
            vertical-align: middle;
            display: inline-block;
        }
        .footer {
            border-top: 1px solid #dddddd;
            padding-top: 8px;
            margin-top: 25px;
            font-size: 9px;
            color: #666666;
            text-align: center;
            direction: ltr;
            page-break-inside: avoid;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-col-start">
                <div class="header-title">{$this->context->getLocalizedName()}</div>
                <div class="header-subtitle">{$subTitle}</div>
            </div>
            <div class="header-col-end">
                {$logoHtml}
            </div>
            <div class="clearfix"></div>
        </div>

        <div class="body-content">
            {$compiledBody}
        </div>

        <div class="signoff-section">
            <div class="signoff-editor">
                <div><strong>{$extra['editorName']}</strong></div>
                <div style="color: #666; font-size: 11px;">{$extra['editorRole']}</div>
                <div style="margin-top: 6px;">{$signatureHtml}{$stampHtml}</div>
            </div>
            <div class="signoff-qr">
                {$extra['qrCodeImgTag']}
                <div style="font-size: 9px; color: #777; margin-top: 3px;">{$verifyText}</div>
                <div style="font-size: 9px; color: #777;">ID: {$extra['certificateNumber']}</div>
            </div>
            <div class="clearfix"></div>
        </div>

        <div class="footer">
            <div>
                {$genText} 
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

        // Process and reshape Arabic characters for correct glyph rendering and bidirectional display
        $html = $this->processArabicHtml($html);

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
     * Reshape Arabic text in HTML so Dompdf renders connected letters and proper RTL ordering
     */
    public function processArabicHtml(string $html): string
    {
        if (!preg_match('/[\x{0600}-\x{06FF}]/u', $html)) {
            return $html;
        }

        if (!class_exists(\ArPHP\I18N\Arabic::class)) {
            $vendorAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
            if (file_exists($vendorAutoload)) {
                require_once $vendorAutoload;
            }
        }

        if (!class_exists(\ArPHP\I18N\Arabic::class)) {
            return $html;
        }

        try {
            $dom = new \DOMDocument();
            libxml_use_internal_errors(true);
            $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            libxml_clear_errors();

            if (!$loaded) {
                return $html;
            }

            $arabic = new \ArPHP\I18N\Arabic();
            $xpath = new \DOMXPath($dom);

            // Target block-level and container elements to reshape sentences as cohesive visual units,
            // preserving inline markup (<strong>, <em>, <span>, <a>, etc.) and line breaks (<br>).
            $blockQuery = '//p | //li | //blockquote | //h1 | //h2 | //h3 | //h4 | //h5 | //h6 | //td | //th | //div[not(div or p or ul or ol or table or h1 or h2 or h3 or h4 or h5 or h6 or blockquote)]';
            $blockNodes = $xpath->query($blockQuery);

            $processedNodes = new \SplObjectStorage();

            foreach ($blockNodes as $node) {
                // Skip script/style elements
                if (in_array(strtolower($node->nodeName), ['style', 'script'])) {
                    continue;
                }

                $textVal = $node->textContent;
                if (!preg_match('/[\x{0600}-\x{06FF}]/u', $textVal)) {
                    continue;
                }

                // Extract the inner HTML of this block node
                $innerHtml = '';
                foreach ($node->childNodes as $child) {
                    $innerHtml .= $dom->saveHTML($child);
                }

                // Process line-by-line (split on <br>) so multi-line blocks don't swap lines
                $lines = preg_split('/(<br\s*\/?>)/i', $innerHtml, -1, PREG_SPLIT_DELIM_CAPTURE);
                $shapedHtml = '';

                foreach ($lines as $line) {
                    if (preg_match('/^<br\s*\/?>$/i', $line)) {
                        $shapedHtml .= $line;
                        continue;
                    }

                    if (!preg_match('/[\x{0600}-\x{06FF}]/u', $line)) {
                        $shapedHtml .= $line;
                        continue;
                    }

                    // Tokenize HTML tags so they are not altered or scrambled by utf8Glyphs
                    $tags = [];
                    $tokenized = preg_replace_callback('/<[^>]+>/', function ($m) use (&$tags) {
                        $idx = count($tags);
                        $tags[] = $m[0];
                        return "___HTAG{$idx}___";
                    }, $line);

                    // Reshape Arabic glyphs and reverse direction for RTL rendering
                    $shaped = $arabic->utf8Glyphs($tokenized, 10000, false);
                    $shaped = $this->fixBiDiGlyphReversals($shaped);

                    // Restore HTML tags
                    foreach ($tags as $idx => $origTag) {
                        $shaped = str_replace("___HTAG{$idx}___", $origTag, $shaped);
                    }

                    $shapedHtml .= $shaped;
                }

                // Replace the block node's contents with the shaped fragment
                while ($node->hasChildNodes()) {
                    $node->removeChild($node->firstChild);
                }

                $fragDom = new \DOMDocument();
                libxml_use_internal_errors(true);
                $fragDom->loadHTML('<?xml encoding="UTF-8"><body>' . $shapedHtml . '</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                libxml_clear_errors();

                $body = $fragDom->getElementsByTagName('body')->item(0);
                if ($body) {
                    foreach ($body->childNodes as $fragChild) {
                        $imported = $dom->importNode($fragChild, true);
                        $node->appendChild($imported);
                    }
                }

                $processedNodes->attach($node);
            }

            // Fallback for any standalone text nodes outside the matched block elements
            $textNodes = $xpath->query('//text()[not(ancestor::style) and not(ancestor::script)]');
            foreach ($textNodes as $node) {
                if (preg_match('/[\x{0600}-\x{06FF}]/u', $node->nodeValue)) {
                    // Check if an ancestor was already processed
                    $curr = $node->parentNode;
                    $alreadyDone = false;
                    while ($curr) {
                        if ($processedNodes->contains($curr)) {
                            $alreadyDone = true;
                            break;
                        }
                        $curr = $curr->parentNode;
                    }

                    if (!$alreadyDone) {
                        $shaped = $arabic->utf8Glyphs($node->nodeValue, 10000, false);
                        $node->nodeValue = $this->fixBiDiGlyphReversals($shaped);
                    }
                }
            }

            $out = $dom->saveHTML();
            $out = str_replace('<?xml encoding="UTF-8">', '', $out);
            return html_entity_decode($out, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        } catch (\Throwable $e) {
            error_log('[AcceptanceLetter] processArabicHtml error: ' . $e->getMessage());
            return $html;
        }
    }

    /**
     * Fix BiDi artefacts caused by full-string Arabic glyph reversal:
     * - Dates: DD-MM-YYYY inverted back to YYYY-MM-DD
     * - Numbered IDs: 1024# inverted back to #1024
     * - Parentheses around Latin/alphanumeric tokens: e.g. )text( or text).)
     */
    protected function fixBiDiGlyphReversals(string $text): string
    {
        // 1. Fix date reversals like 18-09-2026 back to 2026-09-18
        $text = preg_replace_callback('/\b(\d{2})([-\/])(\d{2})([-\/])(\d{4})\b/', function ($m) {
            return $m[5] . $m[2] . $m[3] . $m[4] . $m[1];
        }, $text);

        // 2. Fix inverted hash numbers like 1024# back to #1024
        $text = preg_replace('/(\d+)#/', '#$1', $text);

        // 3. Fix inverted parentheses around ASCII words/acronyms/numbers (e.g. )#62331( -> (#62331), )TEST( -> (TEST), word).) -> (word).)
        $text = preg_replace_callback('/\)([^()\x{0600}-\x{06FF}]+)\(/u', function ($m) {
            return '(' . $m[1] . ')';
        }, $text);
        $text = preg_replace_callback('/([A-Za-z0-9_\-]+)\)\.\)/', function ($m) {
            return '(' . $m[1] . ').';
        }, $text);
        $text = preg_replace_callback('/([A-Za-z0-9_\-]+)\)\)/', function ($m) {
            return '(' . $m[1] . ')';
        }, $text);

        return $text;
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

    /**
     * Calculate fitted width and height preserving natural aspect ratio within a bounding box.
     * Works with data URIs, local file paths, and web URLs.
     *
     * @return array{width: int, height: int}
     */
    protected function computeFittedDimensions(string $imageSource, int $maxBoxWidth, int $maxBoxHeight): array
    {
        $rawBytes = null;

        if (str_starts_with($imageSource, 'data:image/')) {
            $commaPos = strpos($imageSource, ',');
            if ($commaPos !== false) {
                $rawBytes = base64_decode(substr($imageSource, $commaPos + 1), true);
            }
        } elseif (file_exists($imageSource) && is_readable($imageSource)) {
            $rawBytes = @file_get_contents($imageSource);
        }

        $origWidth = null;
        $origHeight = null;

        if ($rawBytes && strlen($rawBytes) > 0) {
            // Check SVG first
            if (str_contains(substr($rawBytes, 0, 500), '<svg')) {
                if (preg_match('/viewBox\s*=\s*["\']\s*[\d.-]+\s+[\d.-]+\s+([\d.]+)\s+([\d.]+)\s*["\']/i', $rawBytes, $matches)) {
                    $origWidth = (float) $matches[1];
                    $origHeight = (float) $matches[2];
                } elseif (preg_match('/width\s*=\s*["\']([\d.]+)p?x?["\']/i', $rawBytes, $wMatch) &&
                          preg_match('/height\s*=\s*["\']([\d.]+)p?x?["\']/i', $rawBytes, $hMatch)) {
                    $origWidth = (float) $wMatch[1];
                    $origHeight = (float) $hMatch[1];
                }
            } else {
                $info = @getimagesizefromstring($rawBytes);
                if ($info && !empty($info[0]) && !empty($info[1])) {
                    $origWidth = (float) $info[0];
                    $origHeight = (float) $info[1];
                }
            }
        }

        // If dimensions cannot be resolved, scale by max width and height ratio
        if (!$origWidth || !$origHeight || $origWidth <= 0 || $origHeight <= 0) {
            return ['width' => $maxBoxWidth, 'height' => $maxBoxHeight];
        }

        // Calculate aspect ratio preserving scaling factor to fit snugly within the bounding box
        $scale = min($maxBoxWidth / $origWidth, $maxBoxHeight / $origHeight);

        $targetWidth = max(1, (int) round($origWidth * $scale));
        $targetHeight = max(1, (int) round($origHeight * $scale));

        return [
            'width' => $targetWidth,
            'height' => $targetHeight,
        ];
    }
}

