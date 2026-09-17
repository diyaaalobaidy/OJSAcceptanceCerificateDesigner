<?php

namespace APP\plugins\generic\acceptanceLetter\classes\model;

use Illuminate\Database\Eloquent\Model;

class IssuedCertificate extends Model
{
    protected $table = 'acceptance_issued_letters';
    protected $primaryKey = 'issue_id';

    protected $fillable = [
        'context_id',
        'submission_id',
        'template_id',
        'certificate_number',
        'verification_token',
        'issued_by_user_id',
        'issued_at',
        'file_path',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
    ];

    /**
     * Find by verification token
     */
    public static function findByToken(string $token): ?self
    {
        return self::where('verification_token', $token)->first();
    }
}
