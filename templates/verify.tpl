{**
 * plugins/generic/acceptanceLetter/templates/verify.tpl
 *
 * Public verification page for certificate authentication
 *}
{include file="frontend/components/header.tpl" pageTitleTranslated="Certificate Verification"}

<div class="page page_verify_certificate" style="max-width: 800px; margin: 40px auto; padding: 25px; background: #ffffff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.06);">
    <div style="text-align: center; margin-bottom: 30px;">
        <h1 style="color: #0f172a; margin-bottom: 8px;">Official Certificate Verification</h1>
        <p style="color: #64748b; font-size: 14px;">{$journal->getLocalizedName()|escape}</p>
    </div>

    {if $isValid}
        <div style="background: #ecfdf5; border: 1px solid #10b981; border-radius: 6px; padding: 18px; margin-bottom: 25px; display: flex; align-items: center; gap: 15px;">
            <div style="font-size: 32px; color: #10b981;">✓</div>
            <div>
                <h3 style="color: #065f46; margin: 0 0 4px 0;">Authentic & Verified Certificate</h3>
                <p style="color: #047857; margin: 0; font-size: 13px;">This acceptance letter was officially issued by {$journal->getLocalizedName()|escape}.</p>
            </div>
        </div>

        <table style="width: 100%; border-collapse: collapse; margin-bottom: 25px; font-size: 14px;">
            <tr style="border-bottom: 1px solid #e2e8f0;">
                <td style="padding: 12px 8px; font-weight: bold; width: 30%; color: #475569;">Certificate ID:</td>
                <td style="padding: 12px 8px; font-family: monospace; font-weight: 600; color: #0284c7;">{$certificate->certificate_number|escape}</td>
            </tr>
            <tr style="border-bottom: 1px solid #e2e8f0;">
                <td style="padding: 12px 8px; font-weight: bold; color: #475569;">Manuscript ID:</td>
                <td style="padding: 12px 8px;">#{$submission->getId()}</td>
            </tr>
            <tr style="border-bottom: 1px solid #e2e8f0;">
                <td style="padding: 12px 8px; font-weight: bold; color: #475569;">Article Title:</td>
                <td style="padding: 12px 8px; font-weight: 600; color: #1e293b;">{$publication->getLocalizedTitle()|escape}</td>
            </tr>
            <tr style="border-bottom: 1px solid #e2e8f0;">
                <td style="padding: 12px 8px; font-weight: bold; color: #475569;">Author(s):</td>
                <td style="padding: 12px 8px;">{$publication->getAuthorString()|escape}</td>
            </tr>
            <tr style="border-bottom: 1px solid #e2e8f0;">
                <td style="padding: 12px 8px; font-weight: bold; color: #475569;">Date of Issuance:</td>
                <td style="padding: 12px 8px;">{$certificate->issued_at->format('F d, Y')}</td>
            </tr>
        </table>
    {else}
        <div style="background: #fef2f2; border: 1px solid #ef4444; border-radius: 6px; padding: 20px; text-align: center;">
            <div style="font-size: 36px; color: #ef4444; margin-bottom: 10px;">✕</div>
            <h3 style="color: #991b1b; margin-top: 0;">Invalid or Unrecognized Certificate</h3>
            <p style="color: #b91c1c; font-size: 14px; margin-bottom: 0;">The validation token provided is either invalid, expired, or does not correspond to an authentic acceptance certificate issued by this journal.</p>
        </div>
    {/if}

    <div style="text-align: center; margin-top: 30px; font-size: 12px; color: #94a3b8;">
        &copy; {$journal->getLocalizedName()|escape} — All Rights Reserved.
    </div>
</div>

{include file="frontend/components/footer.tpl"}
