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
     * Get the default body HTML template
     */
    public static function getDefaultBodyHtml(): string
    {
        return <<<HTML
<h2 style="text-align: center; color: #005a9c;">OFFICIAL ACCEPTANCE LETTER</h2>
<p>Date: {$dateAccepted}</p>
<p>We are delighted to inform you that the manuscript titled:</p>
<blockquote style="border-left: 3px solid #005a9c; padding-left: 10px; margin: 15px 0; font-style: italic;">
    {$articleTitle}
</blockquote>
<p>authored by <strong>{$authorsList}</strong> (Manuscript ID: <strong>#{$submissionId}</strong>) has been formally <strong>ACCEPTED</strong> for publication in <strong>{$journalName} ({$journalInitials})</strong>.</p>
<p>The paper has been thoroughly evaluated by peer reviewers in our review process and meets the standards and rigor required by our editorial board.</p>
<p>Sincerely,<br>
<strong>{$editorName}</strong><br>
<br>
</p>
HTML;
    }
}
