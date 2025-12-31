<?php

namespace App\Models;

use App\Traits\HasCustomId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionVersion extends Model
{
    use HasFactory, HasCustomId;

    protected $table = 'question_versions';

    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'version',
        'type',        // st30 | sjt
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'version'   => 'integer',
    ];

    /* =====================================================
     |  CUSTOM ID GENERATION (DINAMIS BERDASARKAN TYPE)
     ===================================================== */
    public function generateCustomId(): string
    {
        $prefix = match ($this->type) {
            'sjt'  => 'SJV',
            'st30' => 'STV',
            default => 'QV',
        };

        $lastId = static::where('id', 'like', $prefix.'%')
            ->orderBy('id', 'desc')
            ->value('id');

        if (!$lastId) {
            return $prefix . '01';
        }

        $num = (int) substr($lastId, strlen($prefix));
        return $prefix . str_pad($num + 1, 2, '0', STR_PAD_LEFT);
    }

    /* ===================== RELATION ===================== */

    public function st30Questions(): HasMany
    {
        return $this->hasMany(ST30Question::class, 'version_id');
    }

    public function sjtQuestions(): HasMany
    {
        return $this->hasMany(SJTQuestion::class, 'version_id');
    }

    public function st30Responses(): HasMany
    {
        return $this->hasMany(ST30Response::class, 'question_version_id');
    }

    public function sjtResponses(): HasMany
    {
        return $this->hasMany(SJTResponse::class, 'question_version_id');
    }

    /* ===================== ACCESSOR ===================== */

    public function getDisplayNameAttribute(): string
    {
        return $this->name ?: strtoupper($this->type).' Version '.$this->version;
    }

    public function getTypeDisplayAttribute(): string
    {
        return match ($this->type) {
            'st30' => 'ST-30 (Strength Typology)',
            'sjt'  => 'SJT (Situational Judgment Test)',
            default => strtoupper($this->type),
        };
    }

    public function getQuestionsCountAttribute(): int
    {
        return match ($this->type) {
            'st30' => $this->st30Questions()->count(),
            'sjt'  => $this->sjtQuestions()->count(),
            default => 0,
        };
    }

    public function getStatusDisplayAttribute(): string
    {
        return $this->is_active ? 'Active' : 'Inactive';
    }

    /* ===================== SCOPES ===================== */

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeST30($q)
    {
        return $q->where('type', 'st30');
    }

    public function scopeSJT($q)
    {
        return $q->where('type', 'sjt');
    }

    /* ===================== LOGIC ===================== */

    public static function getActive(string $type): ?self
    {
        return static::where('type', $type)
            ->where('is_active', true)
            ->first();
    }

    public function activate(): bool
    {
        static::where('type', $this->type)
            ->where('id', '!=', $this->id)
            ->update(['is_active' => false]);

        return $this->update(['is_active' => true]);
    }

    public function hasResponses(): bool
    {
        return $this->st30Responses()->exists()
            || $this->sjtResponses()->exists();
    }

    public function getUsageStatsAttribute(): array
    {
        $st30 = $this->st30Responses()->distinct('session_id')->count();
        $sjt  = $this->sjtResponses()->distinct('session_id')->count();

        return [
            'st30_responses' => $st30,
            'sjt_responses'  => $sjt,
            'total_usage'    => max($st30, $sjt),
        ];
    }
}
