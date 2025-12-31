@extends('admin.layouts.app')

@section('title', 'SJT Questions')

@push('styles')
<style>
    /* --- STYLE TOMBOL --- */
    .btn-add { background: #22c55e; color: white; padding: 10px 20px; border-radius: 12px; font-weight: 600; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; border: none; box-shadow: 0 4px 6px -1px rgba(34, 197, 94, 0.3); transition: all 0.2s; }
    .btn-add:hover { background: #16a34a; transform: translateY(-1px); color: white; }

    /* Tombol Icon (Print/Export/Import) */
    .btn-icon-square {
        width: 44px; height: 44px;
        background: white; border: 1px solid #e2e8f0;
        border-radius: 12px; display: flex; align-items: center; justify-content: center;
        color: #64748b; cursor: pointer; transition: all 0.2s; text-decoration: none;
    }
    .btn-icon-square:hover { background: #f8fafc; color: #0f172a; border-color: #cbd5e1; transform: translateY(-1px); }

    /* --- INPUT & SEARCH --- */
    .search-group { position: relative; width: 250px; }
    .search-input { width: 100%; padding: 10px 12px 10px 40px; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 0.875rem; background: white; transition: all 0.2s; }
    .search-input:focus { outline: none; border-color: #22c55e; box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1); }
    .search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; }

    .select-version { padding: 10px 30px 10px 12px; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 0.875rem; background: white; cursor: pointer; outline: none; min-width: 180px; }
    .select-version:focus { border-color: #22c55e; }

    /* --- TABLE STYLES --- */
    .table-card { background: white; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-bottom: 1.5rem; }
    .custom-table { width: 100%; border-collapse: separate; border-spacing: 0; }
    .custom-table th { text-align: left; padding: 1.25rem; background: #f8fafc; font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; }
    .custom-table td { padding: 1.25rem; border-bottom: 1px solid #f1f5f9; vertical-align: top; font-size: 0.9rem; color: #334155; background: white; }
    .custom-table tr:hover td { background-color: #f8fafc; }

    /* --- COMPONENTS --- */
    .statement-text { font-size: 0.95rem; color: #0f172a; line-height: 1.5; }
    .text-expand-btn { color: #22c55e; border: none; background: none; font-size: 0.8rem; font-weight: 600; cursor: pointer; padding: 0; margin-left: 5px; }
    .text-expand-btn:hover { text-decoration: underline; }

    .badge-typology { background: #eff6ff; color: #2563eb; padding: 4px 8px; border-radius: 6px; font-weight: 600; font-size: 0.8rem; border: 1px solid #dbeafe; }
    .typology-desc { display: block; font-size: 0.75rem; color: #64748b; margin-top: 4px; }

    .status-dot { height: 8px; width: 8px; border-radius: 50%; display: inline-block; margin-right: 6px; }
    .dot-active { background-color: #22c55e; }
    .dot-inactive { background-color: #cbd5e1; }

    .action-buttons { display: flex; gap: 8px; }
    .btn-icon { width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; border: none; cursor: pointer; text-decoration: none; transition: all 0.2s; }
    .btn-view { background: #ecfdf5; color: #059669; }
    .btn-edit { background: #eff6ff; color: #2563eb; }
    .btn-delete { background: #fef2f2; color: #dc2626; }
    .btn-icon:hover { opacity: 0.8; transform: scale(1.05); }

    .badge-count { font-size: 0.75rem; font-weight: 700; padding: 4px 8px; border-radius: 6px; }
    .badge-count-success { background: #dcfce7; color: #15803d; }
    .badge-count-warning { background: #fef9c3; color: #854d0e; }

    /* Small Box Stats Adjustment */
    .stats-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
    .stat-card { background: white; padding: 1.25rem; border-radius: 16px; border: 1px solid #e2e8f0; display: flex; flex-direction: column; justify-content: space-between; }
    .stat-value { font-size: 1.5rem; font-weight: 700; color: #0f172a; }
    .stat-label { font-size: 0.875rem; color: #64748b; }
    .stat-icon { font-size: 1.5rem; color: #e2e8f0; align-self: flex-end; margin-top: -1.5rem; }

    @media print { body { visibility: hidden; } }
</style>
@endpush

@section('header')
    <div class="header-wrapper" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h1 style="font-size: 1.5rem; font-weight: 800; color: #0f172a; margin-bottom: 4px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-clipboard-check" style="color: #22c55e; background: #dcfce7; padding: 10px; border-radius: 12px; font-size: 1.1rem;"></i>
                SJT Questions
            </h1>
            <p style="font-size: 0.9rem; color: #64748b; margin-left: 54px; margin-top: -5px;">
                Manajemen soal Situational Judgment Test
            </p>
        </div>

        <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
            <div style="position: relative;">
                <select id="version_select" class="select-version" onchange="changeVersion()">
                    <option value="">-- Pilih Versi --</option>
                    @foreach($versions as $version)
                        <option value="{{ $version->id }}" {{ $selectedVersion && $selectedVersion->id == $version->id ? 'selected' : '' }}>
                            {{ $version->name }} {{ $version->is_active ? '(Active)' : '' }}
                        </option>
                    @endforeach
                </select>
                <i class="fas fa-chevron-down" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); font-size: 0.7rem; color: #94a3b8; pointer-events: none;"></i>
            </div>

            <div class="search-group">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="searchQuestions" class="search-input" placeholder="Cari situasi/kompetensi..." autocomplete="off">
            </div>

            @if($selectedVersion)
                <button onclick="exportQuestions()" class="btn-icon-square" title="Export Data">
                    <i class="fas fa-print"></i>
                </button>

                @if(Auth::user()->role === 'admin')
                    <a href="{{ route('admin.questions.sjt.create', ['version' => $selectedVersion->id]) }}" class="btn-add">
                        <i class="fas fa-plus"></i>
                    </a>
                @endif
            @endif
        </div>
    </div>
@endsection

@section('content')
    @if($selectedVersion)
        <div class="stats-container">
            <div class="stat-card">
                <div>
                    <div class="stat-value">{{ $questions->count() }}</div>
                    <div class="stat-label">Total Soal</div>
                </div>
                <div class="stat-icon"><i class="fas fa-question-circle"></i></div>
            </div>
            <div class="stat-card">
                <div>
                    <div class="stat-value" style="color: {{ $selectedVersion->is_active ? '#22c55e' : '#94a3b8' }}">
                        {{ $selectedVersion->is_active ? 'Active' : 'Inactive' }}
                    </div>
                    <div class="stat-label">Status Versi</div>
                </div>
                <div class="stat-icon"><i class="fas fa-toggle-on"></i></div>
            </div>
            <div class="stat-card">
                <div>
                    <div class="stat-value">{{ count($competencyStats) }}</div>
                    <div class="stat-label">Kompetensi</div>
                </div>
                <div class="stat-icon"><i class="fas fa-tags"></i></div>
            </div>
            <div class="stat-card">
                <div>
                    <div class="stat-value" style="color: {{ 50 - $questions->count() > 0 ? '#ef4444' : '#22c55e' }}">
                        {{ 50 - $questions->count() }}
                    </div>
                    <div class="stat-label">Kekurangan Soal</div>
                </div>
                <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
            </div>
        </div>

        <div class="table-card">
            <div style="overflow-x: auto;">
                @if($questions->count() > 0)
                    <table class="custom-table" id="questionsTable">
                        <thead>
                            <tr>
                                <th width="80">No.</th>
                                <th width="40%">Situation (Situasi)</th>
                                <th width="20%">Competency</th>
                                <th width="10%">Opsi</th>
                                <th width="10%">Status</th>
                                <th width="15%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($questions as $question)
                                <tr>
                                    <td>
                                        <span style="font-family: monospace; font-weight: 600; color: #64748b;">
                                            #{{ str_pad($question->number, 2, '0', STR_PAD_LEFT) }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="statement-text">
                                            <span class="short-text">{{ Str::limit($question->question_text, 80) }}</span>
                                            @if(strlen($question->question_text) > 80)
                                                <button class="text-expand-btn" onclick="toggleText(this, {{ $question->id }})">
                                                    Lihat
                                                </button>
                                                <span class="full-text" id="full-question-{{ $question->id }}" style="display: none;">
                                                    {{ $question->question_text }}
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge-typology">
                                            {{ $question->competency }}
                                        </span>
                                        @if($question->competencyDescription)
                                            <span class="typology-desc">
                                                {{ Str::limit($question->competencyDescription->competency_name, 25) }}
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge-count {{ $question->options->count() == 5 ? 'badge-count-success' : 'badge-count-warning' }}">
                                            {{ $question->options->count() }}/5
                                        </span>
                                    </td>
                                    <td>
                                        @if($question->questionVersion->is_active)
                                            <span class="status-dot dot-active"></span> <span style="font-size: 0.85rem; color: #0f172a;">Active</span>
                                        @else
                                            <span class="status-dot dot-inactive"></span> <span style="font-size: 0.85rem; color: #94a3b8;">Inactive</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="{{ route('admin.questions.sjt.show', $question) }}" class="btn-icon btn-view" title="View">
                                                <i class="fas fa-eye text-xs"></i>
                                            </a>
                                            @if(Auth::user()->role === 'admin')
                                                <a href="{{ route('admin.questions.sjt.edit', $question) }}" class="btn-icon btn-edit" title="Edit">
                                                    <i class="fas fa-pen text-xs"></i>
                                                </a>
                                                <button type="button" class="btn-icon btn-delete"
                                                        data-question-number="{{ $question->number }}"
                                                        data-delete-url="{{ route('admin.questions.sjt.destroy', $question) }}"
                                                        title="Delete">
                                                    <i class="fas fa-trash text-xs"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div style="text-align: center; padding: 4rem;">
                        <div style="background: #f1f5f9; width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem;">
                            <i class="fas fa-folder-open fa-2x" style="color: #94a3b8;"></i>
                        </div>
                        <h5 style="color: #0f172a; font-weight: 700; margin-bottom: 0.5rem;">Belum ada Data</h5>
                        <p style="color: #64748b;">Versi ini belum memiliki soal SJT.</p>
                        @if(Auth::user()->role === 'admin')
                            <a href="{{ route('admin.questions.sjt.create', ['version' => $selectedVersion->id]) }}" class="btn-add" style="margin-top: 1rem;">
                                <i class="fas fa-plus"></i> Tambah Soal Pertama
                            </a>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        @if($questions->count() > 0)
        <div class="card" style="border: 1px solid #e2e8f0; border-radius: 16px; box-shadow: none;">
            <div class="card-header" style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 1rem 1.5rem;">
                <h3 class="card-title" style="font-size: 1rem; font-weight: 700; color: #0f172a; margin: 0;">
                    <i class="fas fa-chart-pie mr-2 text-primary"></i> Distribusi Kompetensi
                </h3>
            </div>
            <div class="card-body p-4">
                <div class="row">
                    @foreach($competencies as $competency)
                        @php
                            $count = $competencyStats[$competency->competency_code] ?? 0;
                            // Asumsi target per kompetensi adalah 5 (jika total 50 soal dan 10 kompetensi, sesuaikan logika ini jika perlu)
                            $targetPerCompetency = 5;
                            $hasData = $count > 0;
                            $isComplete = $count >= $targetPerCompetency;
                        @endphp
                        <div class="col-md-2 col-sm-4 col-6 mb-3">
                            <div style="display: flex; align-items: center; gap: 8px; padding: 10px; border-radius: 8px; background: {{ $hasData ? ($isComplete ? '#f0fdf4' : '#fefce8') : '#f8fafc' }}; border: 1px solid {{ $hasData ? ($isComplete ? '#bbf7d0' : '#fef08a') : '#f1f5f9' }};">
                                <div style="display: flex; flex-direction: column;">
                                    <span style="font-weight: 700; color: {{ $hasData ? '#0f172a' : '#94a3b8' }}; font-size: 0.85rem;">
                                        {{ $competency->competency_code }}
                                    </span>
                                    <span style="font-size: 0.7rem; color: #64748b;">
                                        {{ Str::limit($competency->competency_name, 15) }}
                                    </span>
                                </div>
                                <span style="font-size: 0.9rem; font-weight: 700; color: {{ $isComplete ? '#15803d' : ($hasData ? '#854d0e' : '#cbd5e1') }}; margin-left: auto;">
                                    {{ $count }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

    @else
        <div style="text-align: center; padding: 5rem 1rem;">
            <div style="background: #f0fdf4; width: 100px; height: 100px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 2rem; box-shadow: 0 4px 6px -1px rgba(34, 197, 94, 0.1);">
                <i class="fas fa-clipboard-check fa-3x" style="color: #22c55e;"></i>
            </div>
            <h3 style="font-weight: 800; color: #0f172a; margin-bottom: 1rem;">Pilih Versi SJT</h3>
            <p style="color: #64748b; max-width: 400px; margin: 0 auto 2rem;">Silakan pilih versi soal di atas untuk melihat, mengelola, atau menambah pertanyaan SJT.</p>
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        // Version change handler
        function changeVersion() {
            var versionId = document.getElementById('version_select').value;
            if (versionId) {
                window.location.href = '{{ route('admin.questions.sjt.index') }}?version=' + versionId;
            } else {
                window.location.href = '{{ route('admin.questions.sjt.index') }}';
            }
        }

        // Export questions
        function exportQuestions() {
            var versionId = '{{ $selectedVersion ? $selectedVersion->id : '' }}';
            if (versionId) {
                // Perhatikan route-nya: admin.questions.sjt.export
                var url = '{{ route('admin.questions.sjt.export') }}?version=' + versionId;
                window.open(url, '_blank');
            } else {
                Swal.fire('Error', 'Please select a version to export.', 'error');
            }
        }

        // Toggle Expand Text Logic
        function toggleText(btn, id) {
            const shortText = $(btn).siblings('.short-text');
            const fullText = $(btn).siblings('.full-text');

            if (fullText.is(':visible')) {
                fullText.hide();
                shortText.show();
                $(btn).text('Lihat');
            } else {
                fullText.show();
                shortText.hide();
                $(btn).text('Tutup');
            }
        }

        $(document).ready(function() {
            // Client-side Search Functionality
            $('#searchQuestions').on('keyup', function() {
                var value = $(this).val().toLowerCase();
                $('#questionsTable tbody tr').filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
                });
            });

            // Delete confirmation with SweetAlert2
            $('.btn-delete').on('click', function(e) {
                e.preventDefault();
                var questionNumber = $(this).data('question-number');
                var deleteUrl = $(this).data('delete-url');

                Swal.fire({
                    title: 'Hapus Soal SJT?',
                    html: `Yakin ingin menghapus Soal <b>#${questionNumber}</b>?<br><small class="text-muted">Tindakan ini tidak dapat dibatalkan.</small>`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#f1f5f9',
                    confirmButtonText: 'Ya, Hapus',
                    cancelButtonText: '<span style="color:black">Batal</span>',
                    customClass: {
                        popup: 'rounded-2xl',
                        confirmButton: 'rounded-xl px-4 py-2',
                        cancelButton: 'rounded-xl px-4 py-2'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Create form dynamically
                        var form = $('<form>', {
                            'method': 'POST',
                            'action': deleteUrl
                        });
                        form.append('{{ csrf_field() }}');
                        form.append('<input type="hidden" name="_method" value="DELETE">');
                        $('body').append(form);
                        form.submit();
                    }
                });
            });
        });
    </script>
@endpush
