<?php

namespace APP\plugins\generic\acceptanceLetter\classes\handlers;

use APP\handler\Handler;
use APP\core\Application;
use PKP\security\authorization\ContextRequiredPolicy;
use APP\plugins\generic\acceptanceLetter\classes\model\IssuedCertificate;

class AcceptanceLetterVerifyHandler extends Handler
{
    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new ContextRequiredPolicy($request));
        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Verify certificate validity from token
     */
    public function verify($args, $request)
    {
        $token = (string) ($args[0] ?? $request->getUserVar('token'));
        $context = $request->getContext();
        
        $certificate = null;
        $submission = null;
        $isValid = false;

        if (!empty($token) && \Illuminate\Support\Facades\Schema::hasTable('acceptance_issued_letters')) {
            $certificate = IssuedCertificate::findByToken($token);
            if ($certificate && $certificate->context_id == $context->getId()) {
                $submissionRepo = Application::get()->getSubmissionDao();
                $submission = $submissionRepo->getById($certificate->submission_id);
                if ($submission) {
                    $isValid = true;
                }
            }
        }

        $templateMgr = \PKP\template\PKPTemplateManager::getManager($request);
        $templateMgr->assign([
            'token'        => $token,
            'isValid'      => $isValid,
            'certificate'  => $certificate,
            'submission'   => $submission,
            'publication'  => $submission ? $submission->getCurrentPublication() : null,
            'journal'      => $context,
        ]);

        $plugin = \PKP\plugins\PluginRegistry::getPlugin('generic', 'acceptanceletterplugin');
        $templatePath = $plugin ? $plugin->getTemplateResource('verify.tpl') : 'plugins/generic/acceptanceLetter/templates/verify.tpl';
        
        return $templateMgr->display($templatePath);
    }
}
