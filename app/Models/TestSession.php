<?php

namespace App\Models;

use App\Traits\HasCustomId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TestSession extends Model
{
    use HasCustomId;

    protected $table = 'test_sessions';

    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = true;

    /**
     * Custom ID Config
     * TS001, TS002, TS003
     */
    protected string $customIdPrefix = 'TS';
    protected int $customIdLength = 3;

    protected $fillable = [
        'user_id',
        'event_id',
        'session_token',
        'current_step',
        'st30_version_id',
        'participant_name',
        'participant_background',
        'position',
        'is_completed',
        'completed_at',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
    ];

    // =======================
    // RELATIONSHIPS
    // =======================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function st30Responses(): HasMany
    {
        return $this->hasMany(ST30Response::class, 'session_id', 'id');
    }

    public function sjtResponses(): HasMany
    {
        return $this->hasMany(SJTResponse::class, 'session_id', 'id');
    }

    public function testResult(): HasOne
    {
        return $this->hasOne(TestResult::class, 'session_id', 'id');
    }

    // =======================
    // ACCESSORS
    // =======================

    public function getProgressDisplayAttribute(): string
    {
        $percentage = $this->progress_percentage ?? 0;

        if ($percentage === 0) {
            return 'Belum dimulai';
        } elseif ($percentage < 60) {
            return 'ST-30 Assessment (' . round($percentage) . '%)';
        } elseif ($percentage < 100) {
            return 'SJT Assessment (' . round($percentage) . '%)';
        }

        return 'Assessment Selesai';
    }

    public function getDetailedProgressAttribute(): array
    {
        return [
            'percentage'   => $this->progress_percentage ?? 0,
            'display'      => $this->progress_display,
            'current_step' => $this->current_step,
            'is_st30_phase'=> str_starts_with($this->current_step, 'st30'),
            'is_sjt_phase' => str_starts_with($this->current_step, 'sjt'),
            'is_completed' => $this->is_completed,
        ];
    }

    // =======================
    // SCOPES
    // =======================

    public function scopeActive($q)
    {
        return $q->where('is_completed', false);
    }

    public function scopeCompleted($q)
    {
        return $q->where('is_completed', true);
    }

    // =======================
    // HELPERS
    // =======================

    public function isReadyForResults(): bool
    {
        return $this->is_completed
            && $this->st30Responses()->where('for_scoring', true)->count() >= 2
            && $this->sjtResponses()->count() >= 50;
    }

    public function getNextStep(): ?string
    {
        $flow = [
            'form_data'   => 'st30_stage1',
            'st30_stage1' => 'st30_stage2',
            'st30_stage2' => 'st30_stage3',
            'st30_stage3' => 'st30_stage4',
            'st30_stage4' => 'sjt_page1',
            'sjt_page1'   => 'sjt_page2',
            'sjt_page2'   => 'sjt_page3',
            'sjt_page3'   => 'sjt_page4',
            'sjt_page4'   => 'sjt_page5',
            'sjt_page5'   => 'completed',
        ];

        return $flow[$this->current_step] ?? null;
    }

    public function canAccessStage(string $targetStep): bool
    {
        return $this->current_step === $targetStep;
    }
}
