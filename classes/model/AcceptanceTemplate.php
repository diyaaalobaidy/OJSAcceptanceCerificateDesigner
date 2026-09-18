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
<div dir="rtl" style="text-align: right; direction: rtl; line-height: 1.9; max-width: 760px; margin: 30px auto; padding: 42px 48px; color: #243447; background: #ffffff; border: 1px solid #dbe7f0; border-top: 7px solid #005a9c; border-radius: 14px; box-shadow: 0 8px 24px rgba(0, 54, 93, 0.10);">
    <h2 style="text-align: center; color: #005a9c; margin: 0 0 24px; font-size: 26px;">شهادة قبول نشر بحث علمي</h2>
    <div style="height: 2px; width: 90px; margin: 0 auto 28px; background: #d4a84f;"></div>
    <p style="margin: 0 0 12px; color: #64748b; font-size: 14px;">التاريخ: <strong style="color: #243447;">{\$dateAccepted}</strong></p>
    <p style="margin-bottom: 4px;">يسر هيئة تحرير مجلة</p>
    <p style="text-align: center; color: #005a9c; font-size: 21px; margin: 0 0 18px;"><strong>{\$journalName}</strong></p>
    <p style="margin-bottom: 8px;">إعلامكم بقبول البحث العلمي المعنون:</p>
    <blockquote style="border-right: 4px solid #d4a84f; padding: 14px 14px; margin-bottom: 12px; background-color: #f5f9fc; color: #005a9c; font-size: 18px; font-weight: bold; border-radius: 5px 0 0 5px; text-align: center;">
        {\$articleTitle}
    </blockquote>
    <p style="margin: 0 0 8px;">المقدم من الباحثين:</p>
    <div style="background: #fafbfc; border: 1px solid #e7eef4; border-radius: 8px; padding: 15px 18px; margin-bottom: 12px; text-align: right;">
        <p style="margin: 0; color: #243447; text-align: center;"><strong>{\$authorsList}</strong></p>
    </div>
    <p style="margin-bottom: 12px;">حيث تم قبوله نهائياً للنشر في مجلتنا وقد اجتاز البحث كافة مراحل التحكيم والمراجعة العلمية الدقيقة</p>
    <p style="margin-bottom: 12px;">واستوفى المعايير المعتمدة لدى هيئة التحرير.</p>
    <p style="margin: 0; color: #005a9c; font-weight: bold;">مع خالص التحية والتقدير،</p>
</div>
HTML;
    }
}

