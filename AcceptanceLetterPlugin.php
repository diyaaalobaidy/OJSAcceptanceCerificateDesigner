<?php

namespace APP\plugins\generic\acceptanceLetter;

use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use APP\core\Application;

class AcceptanceLetterPlugin extends GenericPlugin
{
    public function register($category, $path, $mainContextId = null): bool
    {
        $success = parent::register($category, $path, $mainContextId);
        if ($success) {
            // Load bundled composer dependencies
            $vendorAutoload = $this->getPluginPath() . '/vendor/autoload.php';
            if (file_exists($vendorAutoload)) {
                require_once $vendorAutoload;
            }

            // Workflow editorial action injection
            Hook::add('TemplateManager::display', [$this, 'callbackWorkflowActions']);
            
            // Route handlers for settings, workflow modal, and verification
            Hook::add('LoadHandler', [$this, 'callbackLoadHandler']);
        }
        return $success;
    }

    public function getDisplayName(): string
    {
        return __('plugins.generic.acceptanceLetter.displayName');
    }

    public function getDescription(): string
    {
        return __('plugins.generic.acceptanceLetter.description');
    }

    /**
     * Provide management action links in the plugin list
     */
    public function getActions($request, $actionArgs): array
    {
        $actions = parent::getActions($request, $actionArgs);
        if (!$this->getEnabled()) {
            return $actions;
        }

        $router = $request->getRouter();
        $settingsAction = new LinkAction(
            'settings',
            new AjaxModal(
                $router->url(
                    $request,
                    null,
                    null,
                    'manage',
                    null,
                    ['verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic']
                ),
                $this->getDisplayName()
            ),
            __('manager.plugins.settings'),
            null
        );

        array_unshift($actions, $settingsAction);
        return $actions;
    }

    /**
     * Define the install migration for OJS upgrade/install commands
     */
    public function getInstallMigration()
    {
        return new \APP\plugins\generic\acceptanceLetter\classes\migration\AcceptanceLetterSchemaMigration();
    }

    /**
     * Manage settings actions invoked from the plugin grid
     */
    public function manage($args, $request)
    {
        // Auto-run schema migration if tables do not exist yet
        if (!\Illuminate\Support\Facades\Schema::hasTable('acceptance_templates')) {
            $migration = new \APP\plugins\generic\acceptanceLetter\classes\migration\AcceptanceLetterSchemaMigration();
            $migration->up();
        }

        // Ensure lazy_load is 0 in the versions table so OJS loads the plugin on all pages
        \Illuminate\Support\Facades\DB::table('versions')
            ->where('product', 'acceptanceLetter')
            ->update(['lazy_load' => 0]);

        $context = $request->getContext();
        $verb = $request->getUserVar('verb');

        switch ($verb) {
            case 'settings':
                $template = \APP\plugins\generic\acceptanceLetter\classes\model\AcceptanceTemplate::getDefaultTemplate($context->getId());
                $templateMgr = \PKP\template\PKPTemplateManager::getManager($request);
                $templateMgr->assign([
                    'template'   => $template,
                    'variables'  => \APP\plugins\generic\acceptanceLetter\classes\service\CertificatePdfService::getSupportedVariables(),
                    'pluginName' => $this->getName(),
                ]);

                return new \PKP\core\JSONMessage(true, $templateMgr->fetch($this->getTemplateResource('settings.tpl')));

            case 'save':
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

                $publicFileManager = class_exists(\APP\file\PublicFileManager::class)
                    ? new \APP\file\PublicFileManager()
                    : new \PKP\file\PKPPublicFileManager();
                foreach (['logo', 'signature', 'stamp'] as $fileKey) {
                    if ($request->getUserVar('delete_' . $fileKey)) {
                        $data[$fileKey . '_path'] = null;
                    } elseif (!empty($_FILES[$fileKey]['name'])) {
                        $extension = strtolower(pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION));
                        if (in_array($extension, ['png', 'jpg', 'jpeg', 'svg'])) {
                            $filename = 'acceptance_' . $fileKey . '_' . uniqid() . '.' . $extension;
                            $publicFileManager->uploadContextFile($context->getId(), $_FILES[$fileKey]['tmp_name'], $filename);
                            $data[$fileKey . '_path'] = $publicFileManager->getContextFilesPath($context->getId()) . '/' . $filename;
                        }
                    }
                }

                if ($templateId) {
                    $template = \APP\plugins\generic\acceptanceLetter\classes\model\AcceptanceTemplate::find($templateId);
                    if ($template && $template->context_id == $context->getId()) {
                        $template->update($data);
                    }
                } else {
                    \APP\plugins\generic\acceptanceLetter\classes\model\AcceptanceTemplate::create($data);
                }

                return new \PKP\core\JSONMessage(true);
        }

        return parent::manage($args, $request);
    }

    /**
     * Route hook callback
     */
    public function callbackLoadHandler(string $hookName, array $args): bool
    {
        $page = $args[0];
        $op = $args[1] ?? '';

        if ($page === 'acceptanceWorkflow') {
            $args[3] = new \APP\plugins\generic\acceptanceLetter\classes\handlers\AcceptanceLetterWorkflowHandler();
            return true;
        }

        if ($page === 'acceptance') {
            $args[3] = new \APP\plugins\generic\acceptanceLetter\classes\handlers\AcceptanceLetterVerifyHandler();
            return true;
        }

        return false;
    }

    /**
     * Inject workflow script and actions into backend
     */
    public function callbackWorkflowActions(string $hookName, array $args): bool
    {
        $templateMgr = $args[0];
        $request = Application::get()->getRequest();

        // Register external JavaScript via PKP's standard asset pipeline
        $templateMgr->addJavaScript(
            'acceptanceLetterWorkflowJs',
            $request->getBaseUrl() . '/' . $this->getPluginPath() . '/js/workflow.js',
            ['contexts' => ['backend']]
        );

        return false;
    }
}
