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
        $publication = null;
        $authorString = '';
        $issuedDate = '';
        $isValid = false;

        if (!empty($token) && \Illuminate\Support\Facades\Schema::hasTable('acceptance_issued_letters')) {
            $certificate = IssuedCertificate::findByToken($token);
            if ($certificate && $certificate->context_id == $context->getId()) {
                $submission = class_exists(\APP\facades\Repo::class)
                    ? \APP\facades\Repo::submission()->get($certificate->submission_id)
                    : (\PKP\db\DAORegistry::getDAO('SubmissionDAO')?->getById($certificate->submission_id));
                if ($submission) {
                    $isValid = true;
                    $publication = $submission->getCurrentPublication();
                    if ($publication) {
                        $authors = $publication->getData('authors') ?? [];
                        $names = [];
                        foreach ($authors as $author) {
                            $names[] = $author->getFullName();
                        }
                        $authorString = implode(', ', $names);
                    }
                    if ($certificate->issued_at) {
                        $issuedDate = is_string($certificate->issued_at)
                            ? date('F d, Y', strtotime($certificate->issued_at))
                            : $certificate->issued_at->format('F d, Y');
                    }
                }
            }
        }

        $templateMgr = \PKP\template\PKPTemplateManager::getManager($request);
        $templateMgr->assign([
            'token'                => $token,
            'isValid'              => $isValid,
            'certificate'          => $certificate,
            'submission'           => $submission,
            'publication'          => $publication,
            'authorString'         => $authorString,
            'issuedDate'           => $issuedDate,
            'journal'              => $context,
            'pageTitle'            => 'Certificate Verification',
            'pageTitleTranslated'  => 'Certificate Verification',
        ]);

        $plugin = \PKP\plugins\PluginRegistry::getPlugin('generic', 'acceptanceletterplugin');
        $templatePath = $plugin ? $plugin->getTemplateResource('verify.tpl') : 'plugins/generic/acceptanceLetter/templates/verify.tpl';
        
        return $templateMgr->display($templatePath);
    }
}
