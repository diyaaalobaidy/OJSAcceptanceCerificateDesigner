<?php

namespace APP\plugins\generic\acceptanceLetter\classes\handlers;

use APP\handler\Handler;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\Role;
use APP\core\Application;
use PKP\core\JSONMessage;
use APP\plugins\generic\acceptanceLetter\classes\model\AcceptanceTemplate;
use APP\plugins\generic\acceptanceLetter\classes\service\CertificatePdfService;

class AcceptanceLetterSettingsHandler extends Handler
{
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN],
            ['index', 'saveTemplate', 'getVariables', 'previewTemplate']
        );
    }

    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Display settings and designer tab
     */
    public function index($args, $request): JSONMessage
    {
        $context = $request->getContext();
        $template = AcceptanceTemplate::getDefaultTemplate($context->getId());

        $templateMgr = \PKP\template\PKPTemplateManager::getManager($request);
        $templateMgr->assign([
            'template'  => $template,
            'variables' => CertificatePdfService::getSupportedVariables(),
        ]);

        return new JSONMessage(true, $templateMgr->fetch($this->getTemplateResource('settings.tpl')));
    }

    /**
     * Save template configuration
     */
    public function saveTemplate($args, $request): JSONMessage
    {
        $context = $request->getContext();
        $templateId = (int) $request->getUserVar('templateId');

        $data = [
            'context_id'      => $context->getId(),
            'name'            => (string) $request->getUserVar('name'),
            'page_size'       => (string) $request->getUserVar('pageSize') ?: 'A4',
            'orientation'     => (string) $request->getUserVar('orientation') ?: 'portrait',
            'locale'          => (string) $request->getUserVar('locale') ?: 'en',
            'header_html'     => (string) $request->getUserVar('headerHtml'),
            'body_html'       => (string) $request->getUserVar('bodyHtml'),
            'footer_html'     => (string) $request->getUserVar('footerHtml'),
            'is_default'      => true,
        ];

        // Handle uploaded assets (logo, stamp, signature)
        $publicFileManager = class_exists(\APP\file\PublicFileManager::class)
            ? new \APP\file\PublicFileManager()
            : new \PKP\file\PKPPublicFileManager();
        foreach (['logo', 'signature', 'stamp'] as $fileKey) {
            if ($request->getUserVar('delete_' . $fileKey)) {
                $data[$fileKey . '_path'] = null;
            } elseif ($request->checkCSRF() && !empty($_FILES[$fileKey]['name'])) {
                $extension = strtolower(pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION));
                if (in_array($extension, ['png', 'jpg', 'jpeg', 'svg'])) {
                    $filename = 'acceptance_' . $fileKey . '_' . uniqid() . '.' . $extension;
                    $publicFileManager->uploadContextFile($context->getId(), $_FILES[$fileKey]['tmp_name'], $filename);
                    $data[$fileKey . '_path'] = $publicFileManager->getContextFilesPath($context->getId()) . '/' . $filename;
                }
            }
        }

        if ($templateId) {
            $template = AcceptanceTemplate::find($templateId);
            if ($template && $template->context_id == $context->getId()) {
                $template->update($data);
            }
        } else {
            $template = AcceptanceTemplate::create($data);
        }

        return new JSONMessage(true, ['templateId' => $template->template_id, 'message' => __('plugins.generic.acceptanceLetter.saved')]);
    }

    protected function getTemplateResource(string $templateName): string
    {
        $plugin = \PKP\plugins\PluginRegistry::getPlugin('generic', 'acceptanceletterplugin');
        return $plugin ? $plugin->getTemplateResource($templateName) : '';
    }
}
