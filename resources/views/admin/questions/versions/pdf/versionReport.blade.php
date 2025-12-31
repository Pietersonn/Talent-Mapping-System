<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Laporan Versi Soal - {{ $version->name }}</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #333; line-height: 1.4; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #0f172a; padding-bottom: 10px; }
        .header h1 { margin: 0; font-size: 18px; color: #0f172a; text-transform: uppercase; }
        .header p { margin: 2px 0; font-size: 10px; color: #64748b; }

        .meta-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
        .meta-table td { padding: 4px; vertical-align: top; }
        .label { font-weight: bold; width: 120px; color: #475569; }

        .content-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .content-table th, .content-table td { border: 1px solid #e2e8f0; padding: 8px; vertical-align: top; text-align: left; }
        .content-table th { background-color: #f8fafc; font-weight: bold; color: #0f172a; font-size: 11px; text-transform: uppercase; }
        .content-table tr:nth-child(even) { background-color: #fcfcfc; }

        .badge { padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: bold; display: inline-block; }
        .badge-active { background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .badge-inactive { background-color: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; }

        .option-list { list-style-type: none; padding: 0; margin: 5px 0 0 0; }
        .option-item { margin-bottom: 3px; padding-left: 15px; position: relative; }
        .option-letter { font-weight: bold; color: #0f172a; margin-right: 5px; }
        .score-badge { font-size: 9px; color: #64748b; background: #f1f5f9; padding: 1px 4px; border-radius: 3px; margin-left: 5px; }

        .footer { position: fixed; bottom: 0; left: 0; right: 0; font-size: 9px; color: #94a3b8; text-align: right; border-top: 1px solid #e2e8f0; padding-top: 5px; }
    </style>
</head>
<body>

    <div class="header">
        <h1>Laporan Bank Soal</h1>
        <p>Talent Mapping System</p>
    </div>

    <table class="meta-table">
        <tr>
            <td class="label">Nama Versi:</td>
            <td><strong>{{ $version->name }}</strong></td>
            <td class="label">Tipe Soal:</td>
            <td>{{ strtoupper($version->type == 'st30' ? 'Strength Typology (ST-30)' : 'Situational Judgment (SJT)') }}</td>
        </tr>
        <tr>
            <td class="label">Versi Ke:</td>
            <td>V{{ $version->version }}</td>
            <td class="label">Status:</td>
            <td>
                @if($version->is_active)
                    <span class="badge badge-active">AKTIF</span>
                @else
                    <span class="badge badge-inactive">TIDAK AKTIF</span>
                @endif
            </td>
        </tr>
        <tr>
            <td class="label">Tanggal Dibuat:</td>
            <td>{{ $version->created_at->format('d F Y, H:i') }}</td>
            <td class="label">Total Soal:</td>
            <td>{{ $questions->count() }} Soal</td>
        </tr>
        <tr>
            <td class="label">Deskripsi:</td>
            <td colspan="3">{{ $version->description ?: '-' }}</td>
        </tr>
    </table>

    @if($version->type === 'st30')
        {{-- TABEL KHUSUS ST-30 --}}
        <table class="content-table">
            <thead>
                <tr>
                    <th width="5%">No</th>
                    <th width="75%">Pernyataan</th>
                    <th width="10%">Kode Tipologi</th>
                    <th width="10%">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($questions as $q)
                <tr>
                    <td style="text-align: center;">{{ $q->number }}</td>
                    <td>{{ $q->statement }}</td>
                    <td style="text-align: center;"><strong>{{ $q->typology_code }}</strong></td>
                    <td style="text-align: center;">
                        @if($q->is_active)
                            <span style="color: green;">Aktif</span>
                        @else
                            <span style="color: #999;">Non-Aktif</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" style="text-align: center; padding: 20px; color: #94a3b8;">Belum ada soal dalam versi ini.</td>
                </tr>
                @endforelse
            </tbody>
        </table>

    @else
        {{-- TABEL KHUSUS SJT --}}
        <table class="content-table">
            <thead>
                <tr>
                    <th width="5%">No</th>
                    <th width="55%">Studi Kasus / Pertanyaan</th>
                    <th width="15%">Kompetensi</th>
                    <th width="25%">Opsi Jawaban & Bobot</th>
                </tr>
            </thead>
            <tbody>
                @forelse($questions as $q)
                <tr>
                    <td style="text-align: center;">{{ $q->number }}</td>
                    <td>
                        {{ $q->question_text }}
                        <div style="margin-top: 5px; font-size: 10px; color: #64748b;">
                            Halaman: {{ $q->page_number }} | Status: {{ $q->is_active ? 'Aktif' : 'Non-Aktif' }}
                        </div>
                    </td>
                    <td>{{ $q->competency }}</td>
                    <td>
                        <ul class="option-list">
                            @foreach($q->options as $opt)
                            <li class="option-item">
                                <span class="option-letter">{{ strtoupper($opt->option_letter) }}.</span>
                                {{ $opt->option_text }}
                                <span class="score-badge">Skor: {{ $opt->score }}</span>
                            </li>
                            @endforeach
                        </ul>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" style="text-align: center; padding: 20px; color: #94a3b8;">Belum ada soal dalam versi ini.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    @endif

    <div class="footer">
        Dicetak oleh: {{ $user }} pada {{ $generated_at }} | Halaman <span class="page-number"></span>
    </div>

    {{-- Script untuk nomor halaman (DomPDF support) --}}
    <script type="text/php">
        if (isset($pdf)) {
            $text = "Halaman {PAGE_NUM} dari {PAGE_COUNT}";
            $size = 9;
            $font = $fontMetrics->getFont("sans-serif");
            $width = $fontMetrics->get_text_width($text, $font, $size) / 2;
            $x = ($pdf->get_width() - $width) - 20;
            $y = $pdf->get_height() - 25;
            $pdf->page_text($x, $y, $text, $font, $size, array(0.5, 0.5, 0.5));
        }
    </script>
</body>
</html>
