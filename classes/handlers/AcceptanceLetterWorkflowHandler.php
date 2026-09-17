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
            ['showModal', 'previewPdf', 'downloadPdf', 'issueLetter']
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

        $templateMgr = \PKP\template\PKPTemplateManager::getManager($request);
        $templateMgr->assign([
            'submission' => $submission,
            'template'   => $template,
            'user'       => $request->getUser(),
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

        $template = AcceptanceTemplate::getDefaultTemplate($context->getId());
        if (!$template) {
            // Create fallback minimal template
            $template = new AcceptanceTemplate([
                'context_id'  => $context->getId(),
                'name'        => 'Default',
                'page_size'   => 'A4',
                'orientation' => 'portrait',
                'locale'      => $context->getPrimaryLocale(),
                'body_html'   => '<h2 style="text-align:center;">ACCEPTANCE LETTER</h2><p>This is to certify that the manuscript entitled <strong>{$articleTitle}</strong> authored by <strong>{$authorsList}</strong> has been formally accepted for publication in <strong>{$journalName}</strong>.</p>',
            ]);
        }

        // Generate token and verification data
        $token = VerificationService::generateToken($context->getId(), $submission->getId());
        $certNumber = VerificationService::generateCertificateNumber($context->getId(), $submission->getId());
        $verifyUrl = VerificationService::getVerificationUrl($token, $context->getPath());
        $qrCodeData = VerificationService::generateQrCodeDataUri($verifyUrl);

        $pdfService = new CertificatePdfService($context);
        $extra = [
            'dateAccepted'       => date('Y-m-d'),
            'dateIssued'         => date('Y-m-d'),
            'certificateNumber'  => $certNumber,
            'editorName'         => $user->getFullName(),
            'editorRole'         => 'Editor',
            'verificationUrl'    => $verifyUrl,
            'qrCodeImgTag'       => $qrCodeData ? '<img src="' . $qrCodeData . '" style="width:80px;height:80px;" />' : '',
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
            'issued_at'          => now(),
        ]);

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="Acceptance_Letter_' . $submission->getId() . '.pdf"');
        echo $pdfOutput;
        exit;
    }

    protected function getTemplateResource(string $templateName): string
    {
        $plugin = \PKP\plugins\PluginRegistry::getPlugin('generic', 'acceptanceletterplugin');
        return $plugin ? $plugin->getTemplateResource($templateName) : '';
    }
}
