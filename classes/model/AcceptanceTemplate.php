<?php

namespace APP\plugins\generic\acceptanceLetter\classes\model;

use Illuminate\Database\Eloquent\Model;

class AcceptanceTemplate extends Model
{
    protected $table = 'acceptance_templates';
    protected $primaryKey = 'template_id';

    protected $fillable = [
        'context_id',
        'name',
        'page_size',
        'orientation',
        'locale',
        'header_html',
        'body_html',
        'footer_html',
        'logo_path',
        'signature_path',
        'stamp_path',
        'background_path',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    /**
     * Scope query to context (journal)
     */
    public function scopeForContext($query, int $contextId)
    {
        return $query->where('context_id', $contextId);
    }

    /**
     * Get default template for journal
     */
    public static function getDefaultTemplate(int $contextId): ?self
    {
        return self::forContext($contextId)
            ->where('is_default', true)
            ->first();
    }

    /**
     * Get the default body HTML template based on locale
     */
    public static function getDefaultBodyHtml(string $locale = 'en'): string
    {
        if ($locale === 'ar' || str_starts_with($locale, 'ar_')) {
            return self::getDefaultArabicBodyHtml();
        }

        return <<<HTML
<h2 style="text-align: center; color: #005a9c;">OFFICIAL ACCEPTANCE LETTER</h2>
<p>Date: {\$dateAccepted}</p>
<p>We are delighted to inform you that the manuscript titled:</p>
<blockquote style="border-left: 3px solid #005a9c; padding-left: 10px; margin: 15px 0; font-style: italic;">
    {\$articleTitle}
</blockquote>
<p>authored by <strong>{\$authorsList}</strong> (Manuscript ID: <strong>#{\$submissionId}</strong>) has been formally <strong>ACCEPTED</strong> for publication in <strong>{\$journalName} ({\$journalInitials})</strong>.</p>
<p>The paper has been thoroughly evaluated by peer reviewers in our review process and meets the standards and rigor required by our editorial board.</p>
<p>Sincerely,<br>
<strong>{\$editorName}</strong><br>
<br>
</p>
HTML;
    }

    /**
     * Get the default Arabic body HTML template
     */
    public static function getDefaultArabicBodyHtml(): string
    {
        return <<<HTML
<h2 style="text-align: center; color: #005a9c;">شهادة قبول نشر بحث علمي</h2>
<p style="text-align: right;">التاريخ: {\$dateAccepted}</p>
<p>يسر هيئة التحرير إعلامكم بقبول البحث العلمي المعنون:</p>
<blockquote style="border-right: 3px solid #005a9c; padding-right: 10px; margin: 12px 0; background: #f8fafc; text-align: right;">
    {\$articleTitle}
</blockquote>
<p>المقدم من الباحثين للنشر في المجلة وفق البيانات الموضحة أدناه:</p>
<table style="width: 100%; border-collapse: collapse; margin: 14px 0;">
    <tr>
        <td style="width: 75%; text-align: right; padding: 4px 6px;"><strong>{\$authorsList}</strong></td>
        <td style="width: 25%; font-weight: bold; text-align: right; padding: 4px 6px;">:الباحثون</td>
    </tr>
    <tr>
        <td style="width: 75%; text-align: right; padding: 4px 6px;"><strong>#{\$submissionId}</strong></td>
        <td style="width: 25%; font-weight: bold; text-align: right; padding: 4px 6px;">:معرف البحث</td>
    </tr>
    <tr>
        <td style="width: 75%; text-align: right; padding: 4px 6px;"><strong>{\$journalName} ({\$journalInitials})</strong></td>
        <td style="width: 25%; font-weight: bold; text-align: right; padding: 4px 6px;">:مجلة النشر</td>
    </tr>
    <tr>
        <td style="width: 75%; text-align: right; padding: 4px 6px;">{\$dateAccepted}</td>
        <td style="width: 25%; font-weight: bold; text-align: right; padding: 4px 6px;">:تاريخ القبول</td>
    </tr>
</table>
<p>وقد اجتاز البحث كافة مراحل التحكيم والمراجعة العلمية الدقيقة واستوفى المعايير والشروط المعتمدة لدى هيئة التحرير في المجلة.</p>
<p>مع خالص التحية والتقدير،<br>
<strong>{\$editorName}</strong><br>
{\$editorRole}
</p>
HTML;
    }
}

