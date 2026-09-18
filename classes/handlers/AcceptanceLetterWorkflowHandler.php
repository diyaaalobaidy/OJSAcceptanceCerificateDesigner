<?php

namespace APP\plugins\generic\acceptanceLetter\classes\handlers;

use APP\handler\Handler;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\Role;
use APP\core\Application;
use PKP\core\JSONMessage;
use APP\plugins\generic\acceptanceLetter\classes\model\AcceptanceTemplate;
use APP\plugins\generic\acceptanceLetter\classes\model\IssuedCertificate;
use APP\plugins\generic\acceptanceLetter\classes\service\CertificatePdfService;
use APP\plugins\generic\acceptanceLetter\classes\service\VerificationService;

class AcceptanceLetterWorkflowHandler extends Handler
{
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_SITE_ADMIN],
            ['showModal', 'previewPdf', 'downloadPdf', 'issueLetter', 'sendEmail', 'checkEligibility']
        );
    }

    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new SubmissionAccessPolicy(
            $request,
            $args,
            $roleAssignments,
            'submissionId'
        ));
        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Check if submission is in Copyediting (stage 4), Production (stage 5), or Published (status 3)
     */
    protected function isSubmissionEligible($submission): bool
    {
        if (!$submission) {
            return false;
        }

        $stageId = (int) $submission->getData('stageId');
        $status = (int) $submission->getData('status');

        // Stage 4: Editing / Copyediting
        // Stage 5: Production
        // Status 3: STATUS_PUBLISHED
        $isCopyeditingOrProduction = in_array($stageId, [4, 5], true);
        $isPublished = ($status === 3);

        return ($isCopyeditingOrProduction || $isPublished);
    }

    /**
     * AJAX endpoint to check if acceptance certificate actions are allowed for a submission
     */
    public function checkEligibility($args, $request): JSONMessage
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $eligible = $this->isSubmissionEligible($submission);

        return new JSONMessage(true, [
            'submissionId' => $submission ? $submission->getId() : null,
            'eligible'     => $eligible,
            'stageId'      => $submission ? (int) $submission->getData('stageId') : null,
            'status'       => $submission ? (int) $submission->getData('status') : null,
        ]);
    }

    /**
     * Show the acceptance letter modal in workflow
     */
    public function showModal($args, $request): JSONMessage
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('acceptance_templates')) {
            (new \APP\plugins\generic\acceptanceLetter\classes\migration\AcceptanceLetterSchemaMigration())->up();
        }

        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $context = $request->getContext();
        $template = AcceptanceTemplate::getDefaultTemplate($context->getId());

        $publication = $submission ? $submission->getCurrentPublication() : null;
        $authorString = '';
        $primaryAuthorEmail = '';
        if ($publication) {
            $authors = $publication->getData('authors') ?? [];
            $names = [];
            foreach ($authors as $author) {
                $names[] = $author->getFullName();
                $email = method_exists($author, 'getEmail') ? $author->getEmail() : $author->getData('email');
                if (!$primaryAuthorEmail && !empty($email)) {
                    $primaryAuthorEmail = $email;
                }
                if ($author->getData('primaryContact') && !empty($email)) {
                    $primaryAuthorEmail = $email;
                }
            }
            $authorString = implode(', ', $names);
        }

        $templateMgr = \PKP\template\PKPTemplateManager::getManager($request);
        $templateMgr->assign([
            'submission'         => $submission,
            'template'           => $template,
            'user'               => $request->getUser(),
            'authorString'       => $authorString,
            'primaryAuthorEmail' => $primaryAuthorEmail,
        ]);

        return new JSONMessage(true, $templateMgr->fetch($this->getTemplateResource('workflowModal.tpl')));
    }

    /**
     * Generate and download PDF
     */
    public function downloadPdf($args, $request)
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('acceptance_templates')) {
            (new \APP\plugins\generic\acceptanceLetter\classes\migration\AcceptanceLetterSchemaMigration())->up();
        }

        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $context = $request->getContext();
        $user = $request->getUser();

        if (!$this->isSubmissionEligible($submission)) {
            header('HTTP/1.1 403 Forbidden');
            echo 'Acceptance certificates are only available for articles in Copyediting, Production, or Published.';
            exit;
        }

        $template = AcceptanceTemplate::getDefaultTemplate($context->getId());
        if (!$template) {
            $loc = $context->getPrimaryLocale() ?? 'en';
            // Create fallback minimal template
            $template = new AcceptanceTemplate([
                'context_id'  => $context->getId(),
                'name'        => 'Default',
                'page_size'   => 'A4',
                'orientation' => 'portrait',
                'locale'      => $loc,
                'body_html'   => AcceptanceTemplate::getDefaultBodyHtml($loc),
            ]);
        }

        // Generate token and verification data
        $token = VerificationService::generateToken($context->getId(), $submission->getId());
        $certNumber = VerificationService::generateCertificateNumber($context->getId(), $submission->getId());
        $verifyUrl = VerificationService::getVerificationUrl($token, $context->getPath());
        $qrCodeData = VerificationService::generateQrCodeDataUri($verifyUrl);

        $pdfService = new CertificatePdfService($context);
        $extra = [
            'template'           => $template,
            'logoPath'           => $template->logo_path,
            'signaturePath'      => $template->signature_path,
            'stampPath'          => $template->stamp_path,
            'dateAccepted'       => date('Y-m-d'),
            'dateIssued'         => date('Y-m-d'),
            'certificateNumber'  => $certNumber,
            'editorName'         => $user->getFullName(),
            'editorRole'         => 'Editor',
            'verificationUrl'    => $verifyUrl,
            'qrCodeImgTag'       => $qrCodeData ? '<img src="' . $qrCodeData . '" style="width:70px;height:70px;" />' : '',
            'qrCodeDataUri'      => $qrCodeData,
        ];

        $compiledBody = $pdfService->substituteVariables($template->body_html, $submission, $extra);
        $fullHtml = $pdfService->buildDocumentHtml($template, $compiledBody, $extra);
        $pdfOutput = $pdfService->renderToPdf($fullHtml);

        // Record issuance
        IssuedCertificate::create([
            'context_id'         => $context->getId(),
            'submission_id'      => $submission->getId(),
            'template_id'        => $template->template_id ?? null,
            'certificate_number' => $certNumber,
            'verification_token' => $token,
            'issued_by_user_id'  => $user->getId(),
            'issued_at'          => date('Y-m-d H:i:s'),
        ]);

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="Acceptance_Letter_' . $submission->getId() . '.pdf"');
        echo $pdfOutput;
        exit;
    }

    /**
     * Send acceptance letter and certificate directly to author via email
     */
    public function sendEmail($args, $request): JSONMessage
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('acceptance_templates')) {
            (new \APP\plugins\generic\acceptanceLetter\classes\migration\AcceptanceLetterSchemaMigration())->up();
        }

        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $context = $request->getContext();
        $user = $request->getUser();

        if (!$this->isSubmissionEligible($submission)) {
            return new JSONMessage(false, __('plugins.generic.acceptanceLetter.notEligible') ?: 'Acceptance certificates are only available for articles in Copyediting, Production, or Published.');
        }

        $publication = $submission ? $submission->getCurrentPublication() : null;

        if (!$publication) {
            return new JSONMessage(false, 'Publication details not found for this submission.');
        }

        // Find primary author and co-authors
        $authors = $publication->getData('authors') ?? [];
        $recipients = [];
        $primaryAuthor = null;
        foreach ($authors as $author) {
            $email = method_exists($author, 'getEmail') ? $author->getEmail() : $author->getData('email');
            $name = method_exists($author, 'getFullName') ? $author->getFullName() : ($author->getData('givenName') . ' ' . $author->getData('familyName'));
            if (!empty($email)) {
                $recipients[] = ['email' => $email, 'name' => $name];
                if ($author->getData('primaryContact') && !$primaryAuthor) {
                    $primaryAuthor = ['email' => $email, 'name' => $name];
                }
            }
        }

        if (!$primaryAuthor && !empty($recipients)) {
            $primaryAuthor = $recipients[0];
        }

        if (!$primaryAuthor || empty($primaryAuthor['email'])) {
            return new JSONMessage(false, __('plugins.generic.acceptanceLetter.noAuthorEmail'));
        }

        $template = AcceptanceTemplate::getDefaultTemplate($context->getId());
        if (!$template) {
            $loc = $context->getPrimaryLocale() ?? 'en';
            // Create fallback minimal template
            $template = new AcceptanceTemplate([
                'context_id'  => $context->getId(),
                'name'        => 'Default',
                'page_size'   => 'A4',
                'orientation' => 'portrait',
                'locale'      => $loc,
                'body_html'   => AcceptanceTemplate::getDefaultBodyHtml($loc),
            ]);
        }

        // Generate token and verification data
        $token = VerificationService::generateToken($context->getId(), $submission->getId());
        $certNumber = VerificationService::generateCertificateNumber($context->getId(), $submission->getId());
        $verifyUrl = VerificationService::getVerificationUrl($token, $context->getPath());
        $qrCodeData = VerificationService::generateQrCodeDataUri($verifyUrl);

        $editorName = $user ? $user->getFullName() : ($context->getData('contactName') ?? 'Editorial Office');
        $pdfService = new CertificatePdfService($context);
        $extra = [
            'template'           => $template,
            'logoPath'           => $template->logo_path,
            'signaturePath'      => $template->signature_path,
            'stampPath'          => $template->stamp_path,
            'dateAccepted'       => date('Y-m-d'),
            'dateIssued'         => date('Y-m-d'),
            'certificateNumber'  => $certNumber,
            'editorName'         => $editorName,
            'editorRole'         => 'Editor',
            'verificationUrl'    => $verifyUrl,
            'qrCodeImgTag'       => $qrCodeData ? '<img src="' . $qrCodeData . '" style="width:70px;height:70px;" />' : '',
            'qrCodeDataUri'      => $qrCodeData,
        ];

        $compiledBody = $pdfService->substituteVariables($template->body_html, $submission, $extra);
        $fullHtml = $pdfService->buildDocumentHtml($template, $compiledBody, $extra);
        $pdfBinary = $pdfService->renderToPdf($fullHtml);

        // Record issuance
        IssuedCertificate::create([
            'context_id'         => $context->getId(),
            'submission_id'      => $submission->getId(),
            'template_id'        => $template->template_id ?? null,
            'certificate_number' => $certNumber,
            'verification_token' => $token,
            'issued_by_user_id'  => $user ? $user->getId() : 0,
            'issued_at'          => date('Y-m-d H:i:s'),
        ]);

        $journalName = $context->getLocalizedName() ?: ($context->getData('name') ?? 'Journal');
        $articleTitle = $publication->getLocalizedTitle();
        $subject = "Official Acceptance Letter: #{$submission->getId()} - {$articleTitle}";
        $filename = 'Acceptance_Letter_' . $submission->getId() . '.pdf';

        $htmlBody = <<<HTML
<div style="font-family: Arial, sans-serif; font-size: 14px; line-height: 1.6; color: #222;">
    <p>Dear {$primaryAuthor['name']},</p>
    <p>We are pleased to inform you that your manuscript titled "<strong>{$articleTitle}</strong>" (Submission ID: <strong>#{$submission->getId()}</strong>) has been formally accepted for publication in <strong>{$journalName}</strong>.</p>
    <p>Please find attached your official, signed Acceptance Letter and Certificate.</p>
    <p>You can also verify the authenticity of this certificate at any time via the following link:<br>
    <a href="{$verifyUrl}" style="color: #005a9c; text-decoration: underline;">{$verifyUrl}</a></p>
    <br>
    <p>Sincerely,<br>
    <strong>{$editorName}</strong><br>
    {$journalName}</p>
</div>
HTML;

        // Additional CC authors
        $ccList = [];
        foreach ($recipients as $recipient) {
            if ($recipient['email'] !== $primaryAuthor['email']) {
                $ccList[] = $recipient;
            }
        }

        $sent = false;

        // Method 1: Laravel Mail facade (standard in OJS 3.4 & 3.5)
        if (class_exists(\Illuminate\Support\Facades\Mail::class)) {
            try {
                \Illuminate\Support\Facades\Mail::send([], [], function ($message) use ($primaryAuthor, $ccList, $subject, $htmlBody, $pdfBinary, $filename, $context) {
                    $senderEmail = $context->getData('contactEmail') ?: (\PKP\config\Config::getVar('email', 'default_envelope_sender') ?: null);
                    $senderName = $context->getLocalizedName() ?: $context->getData('name');
                    if ($senderEmail) {
                        $message->from($senderEmail, $senderName);
                    }
                    $message->to($primaryAuthor['email'], $primaryAuthor['name'])
                            ->subject($subject)
                            ->html($htmlBody);

                    foreach ($ccList as $cc) {
                        $message->cc($cc['email'], $cc['name']);
                    }

                    if (method_exists($message, 'attachData')) {
                        $message->attachData($pdfBinary, $filename, [
                            'mime' => 'application/pdf',
                        ]);
                    }
                });
                $sent = true;
            } catch (\Throwable $e) {
                error_log('[AcceptanceLetter] Mail facade error: ' . $e->getMessage());
            }
        }

        // Method 2: PKP MailTemplate fallback
        if (!$sent && (class_exists(\PKP\mail\MailTemplate::class) || class_exists('MailTemplate'))) {
            try {
                $mailClass = class_exists(\PKP\mail\MailTemplate::class) ? \PKP\mail\MailTemplate::class : 'MailTemplate';
                $mail = new $mailClass();
                if (method_exists($mail, 'setContext')) {
                    $mail->setContext($context);
                }
                $mail->addRecipient($primaryAuthor['email'], $primaryAuthor['name']);
                foreach ($ccList as $cc) {
                    $mail->addCc($cc['email'], $cc['name']);
                }
                $mail->setSubject($subject);
                $mail->setBody($htmlBody);

                $tempFile = tempnam(sys_get_temp_dir(), 'acc_') . '.pdf';
                file_put_contents($tempFile, $pdfBinary);
                if (method_exists($mail, 'addAttachment')) {
                    $mail->addAttachment($tempFile, $filename, 'application/pdf');
                }
                if (method_exists($mail, 'send')) {
                    $sent = $mail->send();
                }
                @unlink($tempFile);
            } catch (\Throwable $e) {
                error_log('[AcceptanceLetter] MailTemplate error: ' . $e->getMessage());
            }
        }

        if ($sent) {
            $msg = __('plugins.generic.acceptanceLetter.emailSent') . ' (' . $primaryAuthor['email'] . ')';
            return new JSONMessage(true, ['message' => $msg]);
        } else {
            return new JSONMessage(false, 'Unable to send email. Please check your journal mail / SMTP settings in config.inc.php.');
        }
    }

    protected function getTemplateResource(string $templateName): string
    {
        $plugin = \PKP\plugins\PluginRegistry::getPlugin('generic', 'acceptanceletterplugin');
        return $plugin ? $plugin->getTemplateResource($templateName) : '';
    }
}
