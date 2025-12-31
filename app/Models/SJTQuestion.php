<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SJTQuestion extends Model
{
    use HasFactory;

    protected $table = 'sjt_questions';

    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'version_id',
        'number',
        'question_text',
        'competency',
        'page_number',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'number' => 'integer',
        'page_number' => 'integer',
    ];

    /**
     * =============================
     * AUTO GENERATE ID (001,002,...)
     * =============================
     */
    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = $model->generateNextId();
            }
        });
    }

    /**
     * Generate next ID: 001, 002, 003 ...
     */
    public function generateNextId(): string
    {
        $lastId = static::orderBy('id', 'desc')->value('id');

        if (!$lastId) {
            return '001';
        }

        $nextNumber = ((int) $lastId) + 1;

        return str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }

    /**
     * =============================
     * RELATIONSHIPS
     * =============================
     */

    public function questionVersion(): BelongsTo
    {
        return $this->belongsTo(QuestionVersion::class, 'version_id');
    }

    public function competencyDescription(): BelongsTo
    {
        return $this->belongsTo(
            CompetencyDescription::class,
            'competency',
            'competency_code'
        );
    }

    public function options(): HasMany
    {
        return $this->hasMany(SJTOption::class, 'question_id')
            ->orderBy('option_letter');
    }

    public function activeOptions(): HasMany
    {
        return $this->hasMany(SJTOption::class, 'question_id')
            ->where('is_active', true)
            ->orderBy('option_letter');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SJTResponse::class, 'question_id');
    }

    /**
     * =============================
     * ACCESSORS
     * =============================
     */

    public function getQuestionPreviewAttribute(): string
    {
        return strlen($this->question_text) > 100
            ? substr($this->question_text, 0, 100) . '...'
            : $this->question_text;
    }

    public function getCompetencyCodeAttribute(): string
    {
        return $this->competency;
    }

    public function getCompetencyNameAttribute(): string
    {
        return $this->competencyDescription?->competency_name ?? 'Unknown Competency';
    }

    public function getUsageCountAttribute(): int
    {
        return $this->responses()->count();
    }

    public function getOptionsCountAttribute(): int
    {
        return $this->options()->count();
    }

    /**
     * =============================
     * HELPERS
     * =============================
     */

    public function hasResponses(): bool
    {
        return $this->responses()->exists();
    }

    public function hasCompleteOptions(): bool
    {
        return $this->options()->count() === 5;
    }

    public function getHighestScoringOption()
    {
        return $this->options()->orderBy('score', 'desc')->first();
    }

    public function getLowestScoringOption()
    {
        return $this->options()->orderBy('score', 'asc')->first();
    }

    public function getScoreDistribution(): array
    {
        return $this->options()
            ->selectRaw('score, COUNT(*) as count')
            ->groupBy('score')
            ->pluck('count', 'score')
            ->toArray();
    }

    /**
     * =============================
     * SCOPES
     * =============================
     */

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByVersion($query, string $versionId)
    {
        return $query->where('version_id', $versionId);
    }

    public function scopeByCompetency($query, string $competencyCode)
    {
        return $query->where('competency', $competencyCode);
    }

    public function scopeByPage($query, int $pageNumber)
    {
        return $query->where('page_number', $pageNumber);
    }

    public function scopeByNumberRange($query, int $start, int $end)
    {
        return $query->whereBetween('number', [$start, $end]);
    }

    /**
     * =============================
     * STATIC HELPERS
     * =============================
     */

    public static function getActiveQuestions()
    {
        $activeVersion = QuestionVersion::getActive('sjt');

        if (!$activeVersion) {
            return collect();
        }

        return static::where('version_id', $activeVersion->id)
            ->where('is_active', true)
            ->orderBy('number')
            ->get();
    }

    public function validateUniqueNumber(): bool
    {
        return !static::where('version_id', $this->version_id)
            ->where('number', $this->number)
            ->where('id', '!=', $this->id)
            ->exists();
    }
}
