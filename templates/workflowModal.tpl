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
            {if !empty($primaryAuthorEmail)}
            <tr>
                <td style="padding: 4px 8px;"><strong>{translate key="user.email"|default:"Email"}:</strong></td>
                <td style="padding: 4px 8px; color: #0284c7;">{$primaryAuthorEmail|escape}</td>
            </tr>
            {/if}
            {if !empty($dateAccepted)}
            <tr>
                <td style="padding: 4px 8px;"><strong>{translate key="submission.accepted"|default:"Date Accepted"}:</strong></td>
                <td style="padding: 4px 8px; color: #166534; font-weight: 500;">{$dateAccepted|escape}</td>
            </tr>
            {/if}
            <tr>
                <td style="padding: 4px 8px;"><strong>{translate key="plugins.generic.acceptanceLetter.issuingEditor"}:</strong></td>
                <td style="padding: 4px 8px;">{$user->getFullName()|escape}</td>
            </tr>
        </table>
    </div>

    <div class="pkp_form_actions" style="display: flex; gap: 12px; justify-content: flex-end; align-items: center; margin-top: 20px;">
        <button type="button" id="pkp-send-acceptance-email-btn" onclick="sendAcceptanceEmail({$submission->getId()})" class="pkp_button" style="background: #0284c7; color: #ffffff; border: 1px solid #0284c7; padding: 7px 15px; border-radius: 4px; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
            <span>✉️</span> {translate key="plugins.generic.acceptanceLetter.sendEmail"}
        </button>
        <a href="{url op="downloadPdf" submissionId=$submission->getId()}" target="_blank" class="pkp_button pkp_button_primary" style="display: inline-flex; align-items: center; gap: 6px;">
            <span>📄</span> {translate key="plugins.generic.acceptanceLetter.downloadPdf"}
        </a>
    </div>
</div>

<script>
function sendAcceptanceEmail(submissionId) {
    var btn = document.getElementById('pkp-send-acceptance-email-btn');
    if (!btn) return;

    if (!confirm('Send the official acceptance letter and certificate directly to the author via email?')) {
        return;
    }

    var origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span>⏳</span> Sending...';

    var sendUrl = '{url op="sendEmail" submissionId=$submission->getId()}';
    fetch(sendUrl, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
        btn.disabled = false;
        btn.innerHTML = origHtml;
        if (data && data.status === true) {
            var msg = (data.content && data.content.message) ? data.content.message : 'Acceptance letter sent successfully!';
            if (window.pkp && pkp.eventBus) {
                pkp.eventBus.$emit('notify', msg, 'success');
            } else {
                alert(msg);
            }
        } else {
            var err = (data && data.content) ? (typeof data.content === 'string' ? data.content : data.content.message) : 'Failed to send email.';
            alert(err || 'Failed to send email.');
        }
    })
    .catch(function(err) {
        btn.disabled = false;
        btn.innerHTML = origHtml;
        alert('Network error while sending email: ' + err.message);
    });
}
</script>
