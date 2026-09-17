{**
 * plugins/generic/acceptanceLetter/templates/workflowModal.tpl
 *
 * Workflow modal to preview and download/issue acceptance letters
 *}
<div class="pkp_acceptance_workflow_modal" style="padding: 15px;">
    <div class="pkp_notification" style="margin-bottom: 15px;">
        <p><strong>{translate key="plugins.generic.acceptanceLetter.modalNotice"}</strong></p>
        <p>{translate key="plugins.generic.acceptanceLetter.modalNoticeDesc"}</p>
    </div>

    <div style="background: #f1f5f9; padding: 12px; border-radius: 6px; margin-bottom: 20px;">
        <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
            <tr>
                <td style="padding: 4px 8px; width: 25%;"><strong>{translate key="submission.submission"}:</strong></td>
                <td style="padding: 4px 8px;">#{$submission->getId()} - {$submission->getCurrentPublication()->getLocalizedTitle()|escape}</td>
            </tr>
            <tr>
                <td style="padding: 4px 8px;"><strong>{translate key="article.authors"}:</strong></td>
                <td style="padding: 4px 8px;">{$authorString|default:''|escape}</td>
            </tr>
            <tr>
                <td style="padding: 4px 8px;"><strong>{translate key="plugins.generic.acceptanceLetter.issuingEditor"}:</strong></td>
                <td style="padding: 4px 8px;">{$user->getFullName()|escape}</td>
            </tr>
        </table>
    </div>

    <div class="pkp_form_actions" style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px;">
        <a href="{url op="downloadPdf" submissionId=$submission->getId()}" target="_blank" class="pkp_button pkp_button_primary">
            {translate key="plugins.generic.acceptanceLetter.downloadPdf"}
        </a>
    </div>
</div>
