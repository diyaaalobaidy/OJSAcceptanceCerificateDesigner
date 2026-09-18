{**
 * plugins/generic/acceptanceLetter/templates/settings.tpl
 *
 * Acceptance Letter Designer Settings & Visual Editor
 *}
<div class="pkp_page_acceptance_designer">
    <form class="pkp_form" id="acceptanceLetterForm" method="post" action="{url op="manage" category="generic" plugin=$pluginName verb="save"}" enctype="multipart/form-data">
        {csrf}
        <input type="hidden" name="templateId" value="{$template->template_id|default:''}" />

        <div class="pkp_form_section">
            <h3>{translate key="plugins.generic.acceptanceLetter.designerTitle"}</h3>
            <p class="description">{translate key="plugins.generic.acceptanceLetter.designerDesc"}</p>
        </div>

        <div class="pkp_form_row">
            <label for="templateName">{translate key="plugins.generic.acceptanceLetter.templateName"} *</label>
            <input type="text" id="templateName" name="name" value="{$template->name|default:'Default Acceptance Template'|escape}" required class="pkp_input" />
        </div>

        <div class="pkp_form_row_grid" style="display: flex; gap: 20px; margin-bottom: 20px;">
            <div style="flex: 1;">
                <label for="pageSize">{translate key="plugins.generic.acceptanceLetter.pageSize"}</label>
                <select id="pageSize" name="pageSize" class="pkp_input">
                    <option value="A4" {if $template && $template->page_size == 'A4'}selected{/if}>A4 (210 × 297 mm)</option>
                    <option value="Letter" {if $template && $template->page_size == 'Letter'}selected{/if}>US Letter (8.5 × 11 in)</option>
                </select>
            </div>
            <div style="flex: 1;">
                <label for="orientation">{translate key="plugins.generic.acceptanceLetter.orientation"}</label>
                <select id="orientation" name="orientation" class="pkp_input">
                    <option value="portrait" {if !$template || $template->orientation == 'portrait'}selected{/if}>Portrait</option>
                    <option value="landscape" {if $template && $template->orientation == 'landscape'}selected{/if}>Landscape</option>
                </select>
            </div>
            <div style="flex: 1;">
                <label for="locale">{translate key="plugins.generic.acceptanceLetter.language"}</label>
                <select id="locale" name="locale" class="pkp_input">
                    <option value="en" {if !$template || $template->locale == 'en'}selected{/if}>English (LTR)</option>
                    <option value="ar" {if $template && $template->locale == 'ar'}selected{/if}>Arabic (العربية - RTL)</option>
                </select>
            </div>
        </div>

        <!-- Available Dynamic Tokens -->
        <div class="pkp_form_section" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 15px; margin-bottom: 20px;">
            <h4 style="margin-top: 0;">{translate key="plugins.generic.acceptanceLetter.tokensTitle"}</h4>
            <p style="font-size: 12px; color: #64748b; margin-bottom: 10px;">{translate key="plugins.generic.acceptanceLetter.tokensDesc"}</p>
            <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                {foreach from=$variables key=varCode item=varLabel}
                    <button type="button" class="pkp_button pkp_button_text token-chip" onclick="insertToken('{$varCode|escape}')" title="{$varLabel|escape}" style="background: #e2e8f0; padding: 4px 8px; border-radius: 4px; font-family: monospace; font-size: 11px;">
                        {$varCode|escape}
                    </button>
                {/foreach}
            </div>
        </div>

        <!-- Letter Body WYSIWYG / HTML -->
        <div class="pkp_form_row">
            <label for="bodyHtml">{translate key="plugins.generic.acceptanceLetter.bodyContent"} *</label>
            <textarea id="bodyHtml" name="bodyHtml" rows="14" class="pkp_input" style="font-family: monospace; width: 100%;">{if $template}{$template->body_html|escape}{else}<h2 style="text-align: center; color: #005a9c;">OFFICIAL ACCEPTANCE LETTER</h2>
<p>Date: {$dateAccepted}</p>
<p>Dear {$authorsList},</p>
<p>We are delighted to inform you that your manuscript titled:</p>
<blockquote style="border-left: 3px solid #005a9c; padding-left: 10px; margin: 15px 0; font-style: italic;">
    {$articleTitle}
</blockquote>
<p>authored by <strong></strong> (Manuscript ID: <strong>#{$submissionId}</strong>) has been formally <strong>ACCEPTED</strong> for publication in <strong>{$journalName} ({$journalInitials})</strong>.</p>
<p>The paper has been thoroughly evaluated by peer reviewers in our review process and meets the standards and rigor required by our editorial board.</p>
<p>Sincerely,<br>
<strong>{$editorName}</strong><br>
<br>
</p>{/if}</textarea>
        </div>

        <!-- Signature & Stamp uploads -->
        <div class="pkp_form_row_grid" style="display: flex; gap: 20px; margin-top: 20px;">
            <div style="flex: 1;">
                <label for="logo"><strong>{translate key="plugins.generic.acceptanceLetter.uploadLogo"}</strong></label>
                <input type="file" id="logo" name="logo" accept="image/png, image/jpeg, image/svg+xml, image/gif, image/webp" class="pkp_input" />
                {if $template && $template->logo_path}
                    <div style="margin-top: 6px; padding: 6px 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px;">
                        <small style="color: #475569; word-break: break-all;">Current: {$template->logo_path|escape}</small><br>
                        <label style="color: #dc2626; font-size: 11px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; margin-top: 4px;">
                            <input type="checkbox" name="delete_logo" value="1" /> {translate key="common.delete"}
                        </label>
                    </div>
                {/if}
            </div>
            <div style="flex: 1;">
                <label for="signature"><strong>{translate key="plugins.generic.acceptanceLetter.uploadSignature"}</strong></label>
                <input type="file" id="signature" name="signature" accept="image/png, image/jpeg, image/svg+xml, image/gif, image/webp" class="pkp_input" />
                {if $template && $template->signature_path}
                    <div style="margin-top: 6px; padding: 6px 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px;">
                        <small style="color: #475569; word-break: break-all;">Current: {$template->signature_path|escape}</small><br>
                        <label style="color: #dc2626; font-size: 11px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; margin-top: 4px;">
                            <input type="checkbox" name="delete_signature" value="1" /> {translate key="common.delete"}
                        </label>
                    </div>
                {/if}
            </div>
            <div style="flex: 1;">
                <label for="stamp"><strong>{translate key="plugins.generic.acceptanceLetter.uploadStamp"}</strong></label>
                <input type="file" id="stamp" name="stamp" accept="image/png, image/jpeg, image/svg+xml, image/gif, image/webp" class="pkp_input" />
                {if $template && $template->stamp_path}
                    <div style="margin-top: 6px; padding: 6px 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px;">
                        <small style="color: #475569; word-break: break-all;">Current: {$template->stamp_path|escape}</small><br>
                        <label style="color: #dc2626; font-size: 11px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; margin-top: 4px;">
                            <input type="checkbox" name="delete_stamp" value="1" /> {translate key="common.delete"}
                        </label>
                    </div>
                {/if}
            </div>
        </div>

        <div class="pkp_form_actions" style="margin-top: 25px;">
            <button type="submit" class="pkp_button pkp_button_primary">{translate key="common.save"}</button>
        </div>
    </form>
</div>

<script>
function insertToken(token) {
    const textarea = document.getElementById('bodyHtml');
    if (!textarea) return;
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const text = textarea.value;
    textarea.value = text.substring(0, start) + token + text.substring(end);
    textarea.focus();
    textarea.selectionEnd = start + token.length;
}

if (typeof jQuery !== 'undefined') {
    jQuery(function($) {
        var defaultBodies = {
            'en': {$defaultBodyEn|json_encode},
            'ar': {$defaultBodyAr|json_encode}
        };

        $('#locale').on('change', function() {
            var selectedLocale = $(this).val();
            var $body = $('#bodyHtml');
            var currentVal = $body.val().trim();
            
            // If empty or matches the default of another language, switch it automatically
            if (!currentVal || currentVal === defaultBodies['en'].trim() || currentVal === defaultBodies['ar'].trim()) {
                if (defaultBodies[selectedLocale]) {
                    $body.val(defaultBodies[selectedLocale]);
                }
            }
        });

        $('#acceptanceLetterForm').on('submit', function(e) {
            e.preventDefault();
            var form = this;
            var $form = $(form);
            var formData = new FormData(form);
            var $btn = $form.find('button[type="submit"]');
            var origText = $btn.text();
            $btn.prop('disabled', true).text('Saving...');

            $.ajax({
                url: form.action,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    $btn.prop('disabled', false).text(origText);
                    if (response && response.status === true) {
                        if (window.pkp && pkp.eventBus) {
                            pkp.eventBus.$emit('notify', '{translate key="plugins.generic.acceptanceLetter.saved"}', 'success');
                        } else {
                            alert('{translate key="plugins.generic.acceptanceLetter.saved"}');
                        }
                        var modal = $form.closest('.ui-dialog-content');
                        if (modal.length) {
                            modal.dialog('close');
                        }
                    } else {
                        var msg = (response && response.content) ? response.content : 'Failed to save settings.';
                        alert(msg);
                    }
                },
                error: function(xhr) {
                    $btn.prop('disabled', false).text(origText);
                    alert('Error saving settings: ' + (xhr.responseText || xhr.statusText));
                }
            });
        });
    });
}
</script>
