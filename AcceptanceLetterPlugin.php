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
        if ($success && $this->getEnabled($mainContextId)) {
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

        if ($page === 'acceptanceSettings') {
            define('HANDLER_CLASS', 'APP\plugins\generic\acceptanceLetter\classes\handlers\AcceptanceLetterSettingsHandler');
            return true;
        }

        if ($page === 'acceptanceWorkflow') {
            define('HANDLER_CLASS', 'APP\plugins\generic\acceptanceLetter\classes\handlers\AcceptanceLetterWorkflowHandler');
            return true;
        }

        if ($page === 'acceptance') {
            define('HANDLER_CLASS', 'APP\plugins\generic\acceptanceLetter\classes\handlers\AcceptanceLetterVerifyHandler');
            return true;
        }

        return false;
    }

    /**
     * Inject button into the editorial workflow
     */
    public function callbackWorkflowActions(string $hookName, array $args): bool
    {
        $template = $args[1] ?? '';
        if (strpos($template, 'workflow') === false) {
            return false;
        }

        $templateMgr = $args[0];
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $journalPath = $context ? $context->getPath() : 'test';

        $btnLabel = __('plugins.generic.acceptanceLetter.displayName');
        $btnAction = __('plugins.generic.acceptanceLetter.downloadPdf');

        $templateMgr->registerFilter('output', function ($output) use ($journalPath, $btnLabel, $btnAction) {
            $js = <<<JS
<script type="text/javascript">
(function() {
    function injectAcceptanceLetterButtons() {
        if (document.getElementById('pkp-acceptance-letter-header-btn')) return;

        // 1. Extract submission ID from URL (e.g. /workflow/access/64150)
        var submissionId = null;
        var match = window.location.pathname.match(/\/workflow\/(?:access|index)\/(\d+)/);
        if (match && match[1]) {
            submissionId = match[1];
        } else {
            var params = new URLSearchParams(window.location.search);
            submissionId = params.get('submissionId');
        }
        if (!submissionId) return;

        var downloadUrl = '/index.php/{$journalPath}/acceptanceWorkflow/downloadPdf?submissionId=' + submissionId;

        // 2. Locate header actions bar (next to Activity Log / Library buttons)
        var allButtons = Array.from(document.querySelectorAll('button, a'));
        var targetBtn = allButtons.find(function(el) {
            var text = el.textContent.trim();
            return text === 'Activity Log' || text === 'Library' || text === 'Preview';
        });

        if (targetBtn && targetBtn.parentElement) {
            var headerBtn = document.createElement('a');
            headerBtn.id = 'pkp-acceptance-letter-header-btn';
            headerBtn.href = downloadUrl;
            headerBtn.target = '_blank';
            headerBtn.className = 'pkpButton pkpButton--primary';
            headerBtn.style.cssText = 'display: inline-flex; align-items: center; gap: 5px; padding: 6px 13px; background: #006798; color: #ffffff; border-radius: 4px; font-weight: 600; font-size: 13px; text-decoration: none; margin-left: 8px; vertical-align: middle; border: 1px solid #005077; cursor: pointer; box-shadow: 0 1px 2px rgba(0,0,0,0.1);';
            headerBtn.innerHTML = '<span>📜</span> Acceptance Letter';
            headerBtn.title = 'Generate & Download Official Acceptance Letter (PDF)';
            targetBtn.parentElement.appendChild(headerBtn);
        }

        // 3. Locate left navigation menu (under Workflow tabs)
        if (!document.getElementById('pkp-acceptance-letter-nav-btn')) {
            var allLinks = Array.from(document.querySelectorAll('a, button, li'));
            var prodLink = allLinks.find(function(el) {
                return el.textContent.trim() === 'Production';
            });

            if (prodLink) {
                var container = prodLink.closest('ul') || prodLink.parentElement;
                if (container) {
                    var navItem = document.createElement('li');
                    navItem.id = 'pkp-acceptance-letter-nav-btn';
                    navItem.style.cssText = 'margin: 6px 0; list-style: none;';
                    navItem.innerHTML = '<a href="' + downloadUrl + '" target="_blank" style="display: flex; align-items: center; gap: 6px; padding: 8px 14px; color: #006798; font-weight: bold; font-size: 13px; text-decoration: none; background: #f0f9ff; border-left: 3px solid #0284c7; border-radius: 0 4px 4px 0;"><span>📜</span> Acceptance Letter</a>';
                    container.appendChild(navItem);
                }
            }
        }
    }

    // Attempt immediately and repeat periodically until Vue components mount
    injectAcceptanceLetterButtons();
    var tries = 0;
    var poller = setInterval(function() {
        tries++;
        injectAcceptanceLetterButtons();
        if (document.getElementById('pkp-acceptance-letter-header-btn') || tries > 25) {
            clearInterval(poller);
        }
    }, 400);
})();
</script>
JS;

            if (strpos($output, '</body>') !== false) {
                return str_replace('</body>', $js . "\n" . '</body>', $output);
            }
            return $output . $js;
        });

        return false;
    }
}
