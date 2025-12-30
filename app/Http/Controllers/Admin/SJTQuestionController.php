<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SJTQuestion;
use App\Models\SJTOption;
use App\Models\QuestionVersion;
use App\Models\CompetencyDescription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class SJTQuestionController extends Controller
{
    /**
     * Display a listing of SJT questions
     */
    public function index(Request $request)
    {
        $activeVersion = QuestionVersion::getActive('sjt');
        $selectedVersion = null;

        // Get version from request or use active version
        if ($request->has('version')) {
            $selectedVersion = QuestionVersion::find($request->version);
        } else {
            $selectedVersion = $activeVersion;
        }

        // Get all SJT versions for dropdown
        $versions = QuestionVersion::where('type', 'sjt')
            ->orderBy('version', 'desc')
            ->get();

        $questions = collect();
        if ($selectedVersion) {
            $questions = SJTQuestion::where('version_id', $selectedVersion->id)
                ->with(['questionVersion', 'competencyDescription', 'options'])
                ->orderBy('number')
                ->get();
        }

        // Get competency distribution
        $competencyStats = $questions->groupBy('competency')
            ->map(fn($items) => $items->count())
            ->toArray();

        // Get all competencies for reference
        $competencies = CompetencyDescription::orderBy('competency_code')->get();

        return view('admin.questions.sjt.index', compact(
            'questions',
            'selectedVersion',
            'activeVersion',
            'versions',
            'competencyStats',
            'competencies'
        ));
    }

    /**
     * Show the form for creating a new SJT question
     */
    public function create(Request $request)
    {
        $selectedVersion = null;

        if ($request->has('version')) {
            $selectedVersion = QuestionVersion::find($request->version);
        } else {
            $selectedVersion = QuestionVersion::getActive('sjt');
        }

        if (!$selectedVersion) {
            return redirect()->route('admin.questions.index')
                ->with('error', 'Tidak ada versi SJT yang tersedia. Silakan buat versi terlebih dahulu.');
        }

        // Get next question number
        $nextNumber = SJTQuestion::where('version_id', $selectedVersion->id)
            ->max('number') + 1;

        if ($nextNumber > 50) {
            // FIX: Route updated to admin.questions.sjt.index
            return redirect()->route('admin.questions.sjt.index', ['version' => $selectedVersion->id])
                ->with('error', 'Versi ini sudah mencapai batas maksimum 50 pertanyaan.');
        }

        $competencies = CompetencyDescription::orderBy('competency_name')->get();
        $versions = QuestionVersion::where('type', 'sjt')->orderBy('version', 'desc')->get();

        return view('admin.questions.sjt.create', compact(
            'selectedVersion',
            'nextNumber',
            'competencies',
            'versions'
        ));
    }

    /**
     * Store a newly created SJT question with options
     */
    public function store(Request $request)
    {
        $request->validate([
            'version_id' => 'required|exists:question_versions,id',
            'number' => 'required|integer|min:1|max:50',
            'question_text' => 'required|string|max:1000',
            'competency' => 'required|exists:competency_descriptions,competency_code',
            'options' => 'required|array|size:5',
            'options.*.option_text' => 'required|string|max:500',
            'options.*.score' => 'required|integer|min:0|max:4',
        ]);

        // Check if number already exists in this version
        $exists = SJTQuestion::where('version_id', $request->version_id)
            ->where('number', $request->number)
            ->exists();

        if ($exists) {
            return back()->withErrors([
                'number' => 'Nomor pertanyaan sudah ada di versi ini.'
            ])->withInput();
        }

        DB::transaction(function () use ($request) {
            // Create the question
            $question = SJTQuestion::create([
                'version_id' => $request->version_id,
                'number' => $request->number,
                'question_text' => $request->question_text,
                'competency' => $request->competency,
                'page_number' => ceil($request->number / 10), // Auto-calculate page number
            ]);

            // Create options (A, B, C, D, E)
            $optionLetters = ['a', 'b', 'c', 'd', 'e'];
            foreach ($request->options as $index => $optionData) {
                SJTOption::create([
                    'question_id' => $question->id,
                    'option_letter' => $optionLetters[$index],
                    'option_text' => $optionData['option_text'],
                    'score' => $optionData['score'],
                    'competency_target' => $request->competency,
                ]);
            }
        });

        // FIX: Route updated to admin.questions.sjt.index
        return redirect()->route('admin.questions.sjt.index', ['version' => $request->version_id])
            ->with('success', 'Pertanyaan SJT beserta opsi berhasil dibuat.');
    }

    /**
     * Display the specified SJT question
     */
    public function show(SJTQuestion $sjtQuestion)
    {
        $sjtQuestion->load(['questionVersion', 'competencyDescription', 'options']);

        return view('admin.questions.sjt.show', compact('sjtQuestion'));
    }

    /**
     * Show the form for editing the specified SJT question
     */
    public function edit(SJTQuestion $sjtQuestion)
    {
        $sjtQuestion->load(['questionVersion', 'options']);
        $competencies = CompetencyDescription::orderBy('competency_name')->get();
        $versions = QuestionVersion::where('type', 'sjt')->orderBy('version', 'desc')->get();

        return view('admin.questions.sjt.edit', compact('sjtQuestion', 'competencies', 'versions'));
    }

    /**
     * Update the specified SJT question and options
     */
    public function update(Request $request, SJTQuestion $sjtQuestion)
    {
        $request->validate([
            'number' => 'required|integer|min:1|max:50',
            'question_text' => 'required|string|max:1000',
            'competency' => 'required|exists:competency_descriptions,competency_code',
            'options' => 'required|array|size:5',
            'options.*.option_text' => 'required|string|max:500',
            'options.*.score' => 'required|integer|min:0|max:4',
        ]);

        // Check if number already exists in this version (excluding current question)
        $exists = SJTQuestion::where('version_id', $sjtQuestion->version_id)
            ->where('number', $request->number)
            ->where('id', '!=', $sjtQuestion->id)
            ->exists();

        if ($exists) {
            return back()->withErrors([
                'number' => 'Nomor pertanyaan sudah ada di versi ini.'
            ])->withInput();
        }

        DB::transaction(function () use ($request, $sjtQuestion) {
            // Update the question
            $sjtQuestion->update([
                'number' => $request->number,
                'question_text' => $request->question_text,
                'competency' => $request->competency,
                'page_number' => ceil($request->number / 10), // Auto-calculate page number
            ]);

            // Update options
            $optionLetters = ['a', 'b', 'c', 'd', 'e'];
            foreach ($request->options as $index => $optionData) {
                $option = $sjtQuestion->options()->where('option_letter', $optionLetters[$index])->first();
                if ($option) {
                    $option->update([
                        'option_text' => $optionData['option_text'],
                        'score' => $optionData['score'],
                        'competency_target' => $request->competency,
                    ]);
                }
            }
        });

        // FIX: Route updated to admin.questions.sjt.index
        return redirect()->route('admin.questions.sjt.index', ['version' => $sjtQuestion->version_id])
            ->with('success', 'Pertanyaan SJT berhasil diperbarui.');
    }

    /**
     * Remove the specified SJT question
     */
    public function destroy(SJTQuestion $sjtQuestion)
    {
        // Check if question is used in responses
        if ($sjtQuestion->hasResponses()) {
            // FIX: Route updated to admin.questions.sjt.index
            return redirect()->route('admin.questions.sjt.index', ['version' => $sjtQuestion->version_id])
                ->with('error', 'Tidak dapat menghapus pertanyaan yang sudah digunakan dalam tes.');
        }

        $versionId = $sjtQuestion->version_id;

        DB::transaction(function () use ($sjtQuestion) {
            // Delete options first
            $sjtQuestion->options()->delete();
            // Delete question
            $sjtQuestion->delete();
        });

        // FIX: Route updated to admin.questions.sjt.index
        return redirect()->route('admin.questions.sjt.index', ['version' => $versionId])
            ->with('success', 'Pertanyaan SJT berhasil dihapus.');
    }

    /**
     * Bulk import SJT questions
     */
    public function import(Request $request)
    {
        $request->validate([
            'version_id' => 'required|exists:question_versions,id',
            'import_file' => 'required|file|mimes:csv,xlsx'
        ]);

        // TODO: Implement CSV/Excel import functionality

        // FIX: Route updated to admin.questions.sjt.index
        return redirect()->route('admin.questions.sjt.index', ['version' => $request->version_id])
            ->with('info', 'Fitur impor akan segera tersedia.');
    }

    /**
     * Bulk export SJT questions
     */
    public function export(Request $request)
    {
        $versionId = $request->get('version');

        if (!$versionId) {
            // FIX: Route updated to admin.questions.sjt.index
            return redirect()->route('admin.questions.sjt.index')
                ->with('error', 'Silakan pilih versi untuk diekspor.');
        }

        // TODO: Implement export functionality

        // FIX: Route updated to admin.questions.sjt.index
        return redirect()->route('admin.questions.sjt.index', ['version' => $versionId])
            ->with('info', 'Fitur ekspor akan segera tersedia.');
    }

    /**
     * Reorder questions
     */
    public function reorder(Request $request)
    {
        $request->validate([
            'version_id' => 'required|exists:question_versions,id',
            'questions' => 'required|array',
            'questions.*.id' => 'required|exists:sjt_questions,id',
            'questions.*.number' => 'required|integer|min:1|max:50',
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->questions as $questionData) {
                SJTQuestion::where('id', $questionData['id'])
                    ->update([
                        'number' => $questionData['number'],
                        'page_number' => ceil($questionData['number'] / 10)
                    ]);
            }
        });

        return response()->json(['success' => true]);
    }
}
