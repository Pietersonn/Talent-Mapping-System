<?php

namespace App\Models;

use App\Traits\HasCustomId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ST30Question extends Model
{
    use HasFactory, HasCustomId;

    protected $table = 'st30_questions';

    // Primary key string: ST001, ST002, ...
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'version_id',
        'number',
        'statement',
        'typology_code',
    ];

    protected $casts = [
        'number' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Prefix untuk custom ID
     */
    protected string $customIdPrefix = 'ST';

    /**
     * Generate custom ID: ST001, ST002, ST003, ...
     */
    public function generateCustomId(): string
    {
        $lastId = static::where('id', 'like', $this->customIdPrefix . '%')
            ->orderBy('id', 'desc')
            ->value('id');

        if (!$lastId) {
            return $this->customIdPrefix . '001';
        }

        $lastNumber = (int) substr($lastId, strlen($this->customIdPrefix));
        $nextNumber = $lastNumber + 1;

        return $this->customIdPrefix . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }

    /* ======================================================
     | RELATIONSHIPS
     ====================================================== */

    public function questionVersion(): BelongsTo
    {
        return $this->belongsTo(QuestionVersion::class, 'version_id');
    }

    public function typologyDescription(): BelongsTo
    {
        return $this->belongsTo(
            TypologyDescription::class,
            'typology_code',
            'typology_code'
        );
    }

    public function selectedInResponses(): HasMany
    {
        return $this->hasMany(ST30Response::class, 'question_version_id', 'version_id')
            ->whereJsonContains('selected_items', $this->number);
    }

    public function excludedInResponses(): HasMany
    {
        return $this->hasMany(ST30Response::class, 'question_version_id', 'version_id')
            ->whereJsonContains('excluded_items', $this->number);
    }

    public function allResponses()
    {
        return ST30Response::where('question_version_id', $this->version_id)
            ->where(function ($q) {
                $q->whereJsonContains('selected_items', $this->number)
                  ->orWhereJsonContains('excluded_items', $this->number);
            });
    }

    /* ======================================================
     | ACCESSORS
     ====================================================== */

    public function getTypologyNameAttribute(): string
    {
        return $this->typologyDescription?->typology_name ?? $this->typology_code;
    }

    public function getStatementPreviewAttribute(): string
    {
        return Str::limit($this->statement, 100);
    }

    public function getUsageCountAttribute(): int
    {
        return $this->allResponses()->count();
    }

    public function getSelectedCountAttribute(): int
    {
        return ST30Response::where('question_version_id', $this->version_id)
            ->whereJsonContains('selected_items', $this->number)
            ->count();
    }

    public function getExcludedCountAttribute(): int
    {
        return ST30Response::where('question_version_id', $this->version_id)
            ->whereJsonContains('excluded_items', $this->number)
            ->count();
    }

    public function getSelectionRatioAttribute(): float
    {
        $selected = $this->selected_count;
        $excluded = $this->excluded_count;
        $total = $selected + $excluded;

        return $total > 0 ? $selected / $total : 0;
    }

    public function getPopularityScoreAttribute(): float
    {
        $totalResponses = ST30Response::where('question_version_id', $this->version_id)->count();

        return $totalResponses === 0
            ? 0
            : ($this->selected_count / $totalResponses);
    }

    /* ======================================================
     | HELPERS
     ====================================================== */

    public function hasResponses(): bool
    {
        return $this->allResponses()->exists();
    }

    public function isPopular(float $threshold = 0.5): bool
    {
        return $this->popularity_score >= $threshold;
    }

    public function isUnpopular(float $threshold = 0.2): bool
    {
        return $this->popularity_score <= $threshold;
    }

    public function getSimilarQuestions(int $limit = 5)
    {
        $ratio = $this->selection_ratio;

        return static::where('version_id', $this->version_id)
            ->where('id', '!=', $this->id)
            ->get()
            ->filter(fn ($q) => abs($q->selection_ratio - $ratio) <= 0.1)
            ->take($limit);
    }

    public function getUsageStatistics(): array
    {
        return [
            'total_usage'      => $this->usage_count,
            'selected_count'   => $this->selected_count,
            'excluded_count'   => $this->excluded_count,
            'selection_ratio'  => $this->selection_ratio,
            'popularity_score' => $this->popularity_score,
        ];
    }

    /* ======================================================
     | SCOPES
     ====================================================== */

    public function scopeByVersion($q, string $versionId)
    {
        return $q->where('version_id', $versionId);
    }

    public function scopeByTypology($q, string $code)
    {
        return $q->where('typology_code', $code);
    }

    public function scopeByNumberRange($q, int $start, int $end)
    {
        return $q->whereBetween('number', [$start, $end]);
    }

    /* ======================================================
     | STATIC
     ====================================================== */

    public static function getActiveQuestions()
    {
        $active = QuestionVersion::getActive('st30');

        if (!$active) {
            return collect();
        }

        return static::where('version_id', $active->id)
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
