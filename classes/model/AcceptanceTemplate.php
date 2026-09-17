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
}
