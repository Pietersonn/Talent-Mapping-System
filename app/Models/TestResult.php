<?php

namespace App\Models;

use App\Traits\HasCustomId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TestResult extends Model
{
    use HasFactory, HasCustomId;

    protected $table = 'test_results';

    /** Primary key config */
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps  = true;

    /**
     * Mass assignable attributes
     * ❌ id DIHAPUS (auto generated)
     */
    protected $fillable = [
        'session_id',
        'st30_results',
        'sjt_results',
        'dominant_typology',
        'report_generated_at',
        'email_sent_at',
        'pdf_path',
    ];

    /**
     * Attribute casting
     */
    protected $casts = [
        'st30_results'        => 'array',
        'sjt_results'         => 'array',
        'report_generated_at' => 'datetime',
        'email_sent_at'       => 'datetime',
    ];

    /**
     * =======================
     * CUSTOM ID CONFIG
     * =======================
     * Result:
     * TR001
     * TR002
     * TR003
     */
    protected string $customIdPrefix = 'TR';
    protected int $customIdLength    = 3;

    // =======================
    // RELATIONS
    // =======================

    public function testSession(): BelongsTo
    {
        return $this->belongsTo(TestSession::class, 'session_id', 'id');
    }

    /**
     * Alias untuk legacy code
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(TestSession::class, 'session_id', 'id');
    }

    public function resendRequests(): HasMany
    {
        return $this->hasMany(ResendRequest::class, 'test_result_id', 'id');
    }

    public function dominantTypologyDescription(): BelongsTo
    {
        return $this->belongsTo(
            TypologyDescription::class,
            'dominant_typology',
            'typology_code'
        );
    }

    // =======================
    // HELPERS / ACCESSORS
    // =======================

    public function hasPdfReport(): bool
    {
        return !empty($this->pdf_path)
            && file_exists(storage_path('app/' . $this->pdf_path));
    }

    public function isEmailSent(): bool
    {
        return !is_null($this->email_sent_at);
    }

    public function getSt30StrengthsAttribute(): array
    {
        return $this->st30_results['strengths'] ?? [];
    }

    public function getSt30DevelopmentAreasAttribute(): array
    {
        return $this->st30_results['development_areas'] ?? [];
    }

    public function getSjtStrengthsAttribute(): array
    {
        return $this->sjt_results['strengths'] ?? [];
    }

    public function getSjtDevelopmentAreasAttribute(): array
    {
        return $this->sjt_results['development_areas'] ?? [];
    }

    public function getSummaryAttribute(): array
    {
        return [
            'dominant_typology'    => $this->st30_strengths[0] ?? null,
            'top_competency'       => $this->sjt_strengths[0] ?? null,
            'total_st30_responses' => $this->st30_results['total_responses'] ?? 0,
            'total_sjt_questions'  => $this->sjt_results['total_questions'] ?? 0,
        ];
    }

    // =======================
    // SCOPES
    // =======================

    public function scopeEmailSent($q)
    {
        return $q->whereNotNull('email_sent_at');
    }

    public function scopeEmailPending($q)
    {
        return $q->whereNull('email_sent_at');
    }

    public function scopeWithPdf($q)
    {
        return $q->whereNotNull('pdf_path');
    }

    public function scopeByTypology($q, string $code)
    {
        return $q->where('dominant_typology', $code);
    }
}
