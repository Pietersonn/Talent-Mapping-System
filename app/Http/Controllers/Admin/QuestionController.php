<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QuestionVersion;
use App\Models\ST30Question;
use App\Models\SJTQuestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class QuestionController extends Controller
{
    /**
     * Display a listing of question versions
     */
    public function index(Request $request)
    {
        // Ambil Data
        $st30Versions = QuestionVersion::where('type', 'st30')
            ->withCount(['st30Questions'])
            ->orderBy('is_active', 'desc')
            ->orderBy('version', 'desc')
            ->get();

        $sjtVersions = QuestionVersion::where('type', 'sjt')
            ->withCount(['sjtQuestions'])
            ->orderBy('is_active', 'desc')
            ->orderBy('version', 'desc')
            ->get();

        // Ambil Versi Aktif
        $activeVersions = [
            'st30' => $st30Versions->where('is_active', true)->first(),
            'sjt' => $sjtVersions->where('is_active', true)->first(),
        ];

        return view('admin.questions.versions.index', compact('st30Versions', 'sjtVersions', 'activeVersions'));
    }

    /**
     * Show the form for creating a new version
     */
    public function create()
    {
        return view('admin.questions.versions.create');
    }

    /**
     * Store a newly created version
     */
    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:st30,sjt',
            'name' => 'required|string|max:50',
            'description' => 'nullable|string|max:500',
        ]);

        $latestVersion = QuestionVersion::where('type', $request->type)
            ->orderBy('version', 'desc')
            ->first();

        $nextVersion = $latestVersion ? $latestVersion->version + 1 : 1;

        $questionVersion = QuestionVersion::create([
            'version' => $nextVersion,
            'type' => $request->type,
            'name' => $request->name,
            'description' => $request->description,
            'is_active' => false,
        ]);

        return redirect()
            ->route('admin.questions.show', ['questionVersion' => $questionVersion->id])
            ->with('success', 'Versi soal berhasil dibuat.');
    }

    /**
     * Display the specified version
     */
    public function show(QuestionVersion $questionVersion)
    {
        if ($questionVersion->type === 'st30') {
            $questionVersion->load('st30Questions');
            $typologyStats = $questionVersion->st30Questions
                ->groupBy('typology_code')
                ->map(fn($questions) => $questions->count())
                ->toArray();
            $competencyStats = [];
        } else {
            $questionVersion->load('sjtQuestions');
            $competencyStats = $questionVersion->sjtQuestions
                ->groupBy('competency')
                ->map(fn($questions) => $questions->count())
                ->toArray();
            $typologyStats = [];
        }

        $statistics = [
            'total_questions' => $questionVersion->questions_count,
        ];

        return view('admin.questions.versions.show', compact(
            'questionVersion',
            'statistics',
            'typologyStats',
            'competencyStats'
        ));
    }

    /**
     * Show the form for editing the specified version
     */
    public function edit(QuestionVersion $questionVersion)
    {
        return view('admin.questions.versions.edit', compact('questionVersion'));
    }

    /**
     * Update the specified version
     */
    public function update(Request $request, QuestionVersion $questionVersion)
    {
        $request->validate([
            'name' => 'required|string|max:50',
            'description' => 'nullable|string|max:500',
        ]);

        $questionVersion->update([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return redirect()
            ->route('admin.questions.show', ['questionVersion' => $questionVersion->id])
            ->with('success', 'Versi soal berhasil diperbarui.');
    }

    /**
     * Remove the specified version
     */
    public function destroy(QuestionVersion $questionVersion)
    {
        $hasResponses = false;
        if (method_exists($questionVersion, 'hasResponses')) {
            $hasResponses = $questionVersion->hasResponses();
        } else {
            // Fallback manual check
            if ($questionVersion->type === 'st30') {
                $hasResponses = DB::table('st30_responses')
                    ->join('st30_questions', 'st30_responses.question_id', '=', 'st30_questions.id')
                    ->where('st30_questions.version_id', $questionVersion->id)
                    ->exists();
            } else {
                $hasResponses = DB::table('sjt_responses')
                    ->join('sjt_questions', 'sjt_responses.question_id', '=', 'sjt_questions.id')
                    ->where('sjt_questions.version_id', $questionVersion->id)
                    ->exists();
            }
        }

        if ($hasResponses) {
            return redirect()
                ->route('admin.questions.index')
                ->with('error', 'Tidak dapat menghapus versi yang sudah memiliki data respon peserta.');
        }

        if ($questionVersion->is_active) {
            return redirect()
                ->route('admin.questions.index')
                ->with('error', 'Tidak dapat menghapus versi yang sedang aktif.');
        }

        $questionVersion->delete();

        return redirect()
            ->route('admin.questions.index')
            ->with('success', 'Versi soal berhasil dihapus.');
    }

    /**
     * Activate the specified version
     */
    public function activate(QuestionVersion $questionVersion)
    {
        // 1. Validasi Jumlah Soal
        $count = $questionVersion->questions_count;
        $required = $questionVersion->type === 'st30' ? 30 : 50;

        if ($count < $required) {
            return redirect()
                ->route('admin.questions.index')
                ->with('error', "Gagal aktivasi. Versi ini hanya memiliki {$count} soal (Minimal {$required}).");
        }

        // 2. Validasi Opsi SJT
        if ($questionVersion->type === 'sjt') {
            $questions = $questionVersion->sjtQuestions()->with('options')->get();
            $incompleteQuestions = 0;
            foreach ($questions as $q) {
                if ($q->options->count() < 5) $incompleteQuestions++;
            }

            if ($incompleteQuestions > 0) {
                return redirect()
                    ->route('admin.questions.index')
                    ->with('error', "Gagal aktivasi. Ada {$incompleteQuestions} soal SJT yang opsinya belum lengkap.");
            }
        }

        // 3. Proses Aktivasi
        DB::transaction(function () use ($questionVersion) {
            // Nonaktifkan semua versi tipe yang sama
            QuestionVersion::where('type', $questionVersion->type)->update(['is_active' => false]);
            // Aktifkan versi yang dipilih
            $questionVersion->update(['is_active' => true]);
        });

        return redirect()
            ->route('admin.questions.index')
            ->with('success', "Versi {$questionVersion->name} berhasil diaktifkan.");
    }

    /**
     * Clone version
     */
    public function clone(QuestionVersion $questionVersion)
    {
        DB::transaction(function () use ($questionVersion) {
            $latestVersion = QuestionVersion::where('type', $questionVersion->type)
                ->orderBy('version', 'desc')
                ->lockForUpdate()
                ->first();

            $newVersion = QuestionVersion::create([
                'version' => ($latestVersion ? $latestVersion->version : 0) + 1,
                'type' => $questionVersion->type,
                'name' => $questionVersion->name . ' (Copy)',
                'description' => 'Cloned from ' . $questionVersion->name,
                'is_active' => false,
            ]);

            if ($questionVersion->type === 'st30') {
                foreach ($questionVersion->st30Questions as $q) {
                    ST30Question::create([
                        'version_id' => $newVersion->id,
                        'number' => $q->number,
                        'statement' => $q->statement,
                        'typology_code' => $q->typology_code,
                        'is_active' => $q->is_active,
                    ]);
                }
            } else {
                foreach ($questionVersion->sjtQuestions as $q) {
                    $newQ = SJTQuestion::create([
                        'version_id' => $newVersion->id,
                        'number' => $q->number,
                        'question_text' => $q->question_text,
                        'competency' => $q->competency,
                        'page_number' => $q->page_number,
                        'is_active' => $q->is_active,
                    ]);
                    foreach ($q->options as $opt) {
                        $newQ->options()->create([
                            'option_letter' => $opt->option_letter,
                            'option_text' => $opt->option_text,
                            'score' => $opt->score,
                            'competency_target' => $opt->competency_target
                        ]);
                    }
                }
            }
        });

        return redirect()->route('admin.questions.index')->with('success', 'Versi berhasil diduplikasi.');
    }

    /**
     * Export PDF Report
     */
    public function exportPdf(QuestionVersion $questionVersion)
    {
        if ($questionVersion->type === 'st30') {
            $questionVersion->load(['st30Questions' => fn($q) => $q->orderBy('number')]);
            $questions = $questionVersion->st30Questions;
        } else {
            $questionVersion->load(['sjtQuestions.options' => fn($q) => $q->orderBy('option_letter')]);
            $questions = $questionVersion->sjtQuestions->sortBy('number');
        }

        $data = [
            'version' => $questionVersion,
            'questions' => $questions,
            'generated_at' => now()->format('d F Y H:i'),
            'user' => Auth::user()->name,
        ];

        $pdf = Pdf::loadView('admin.questions.versions.pdf.versionReport', $data);
        $pdf->setPaper('a4', 'portrait');

        return $pdf->download('Laporan_' . $questionVersion->name . '.pdf');
    }

    /**
     * AJAX Statistics
     */
    public function statistics(QuestionVersion $questionVersion)
    {
        return response()->json([
            'questions_count' => $questionVersion->questions_count,
            'is_active' => $questionVersion->is_active,
        ]);
    }
}
