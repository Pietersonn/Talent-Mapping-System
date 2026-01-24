@php
    use Carbon\Carbon;

    // --- 1. Setup Logo ---
    $logoFile = public_path('assets/public/images/logo-bcti1.png');
    $logoBase64 = '';
    if (file_exists($logoFile)) {
        $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoFile));
    }

    // --- 2. Setup Tanggal & Lokasi ---
    Carbon::setLocale('id');
    $currentDate  = Carbon::now()->isoFormat('D MMMM Y'); // Contoh: 21 Januari 2026
    $cityLocation = 'Barito Kuala';

    // --- 3. Data Default ---
    $reportTitle = $reportTitle ?? 'Laporan Kompetensi Peserta';
    $generatedBy = $generatedBy ?? (auth()->user()->name ?? 'Admin');
    $generatedAt = $generatedAt ?? now()->format('d M Y H:i') . ' WITA';

    $companyName = 'BUSINESS & COMMUNICATION TRAINING INSTITUTE';
    $companyAddr1 = 'Kompleks Sekolah Global Islamic Boarding School (GIBS)';
    $companyAddr2 = 'Gedung Nurhayati Kampus GIBS, Jl. Trans - Kalimantan Lantai 2, Sungai Lumbah, Kec. Alalak, Barito Kuala, Kalsel, 70582';
    $companyContact = 'Email : bcti@hasnurcentre.org | Website: bcti.id';
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $reportTitle }}</title>
    <style>
        @page { size: A4 landscape; margin: 18mm 14mm 16mm 14mm; }
        body { font-family: "Times New Roman", Times, serif; font-size: 12px; color: #111; }
        .clearfix:after { content: ""; display: table; clear: both; }

        /* Header */
        .header { margin-bottom: 8px; }
        .h-left { float: left; width: 38%; }
        .h-right { float: right; width: 60%; text-align: right; }
        .h-logo { height: 72px; }

        .company-name { font-weight: 700; font-size: 14px; letter-spacing: .25px; }
        .company-sub { font-size: 11px; line-height: 1.35; color: #222; }

        .divider { border: 0; border-top: 2px solid #000; margin: 6px 0 12px; }

        /* Judul */
        .title-wrap { text-align: center; margin: 4px 0 10px; }
        .title { font-size: 18px; font-weight: 700; text-transform: uppercase; margin: 0 0 4px; }
        .subtitle { font-size: 11px; color: #333; }

        /* Tabel Data */
        table.data-table { width: 100%; border-collapse: collapse; background: #fff; margin-bottom: 20px; }
        table.data-table thead th { background: #ededed; border: 1px solid #000; font-weight: 700; font-size: 12px; padding: 7px 8px; text-align: center; }
        table.data-table tbody td { border: 1px solid #000; padding: 7px 8px; vertical-align: middle; font-size: 12px; text-align: center; }
        table.data-table tbody td.name { text-align: left; }

        /* Tanda Tangan (Override) */
        .signature-table { width: 100%; margin-top: 30px; border: none; page-break-inside: avoid; }
        .signature-table td { border: none; padding: 0; vertical-align: top; text-align: center; }

        .muted { color: #6b7280; }
        .footer { position: fixed; bottom: -10mm; left: 0; right: 0; text-align: right; font-size: 11px; color: #6b7280; }
        .pagenum:before { content: counter(page); }
    </style>
</head>
<body>

    <div class="header clearfix">
        <div class="h-left">
            @if($logoBase64)
                <img class="h-logo" src="{{ $logoBase64 }}" alt="BCTI">
            @endif
        </div>
        <div class="h-right">
            <div class="company-name">{{ $companyName }}</div>
            <div class="company-sub">
                {{ $companyAddr1 }}<br>
                {{ $companyAddr2 }}<br>
                {{ $companyContact }}
            </div>
        </div>
    </div>

    <hr class="divider">

    <div class="title-wrap">
        <div class="title">{{ $reportTitle }}</div>
        <div class="subtitle">
            {{ $modeText ?? 'Semua Peserta' }}
            &nbsp;•&nbsp; Dicetak oleh: {{ $generatedBy }}
            &nbsp;•&nbsp; {{ $generatedAt }}
        </div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width:25px;">No.</th>
                <th style="width:20%;">Nama Peserta</th>
                <th style="width:12%;">No. Telepon</th>
                <th>SM</th>
                <th>CIA</th>
                <th>TS</th>
                <th>WWO</th>
                <th>CA</th>
                <th>L</th>
                <th>SE</th>
                <th>PS</th>
                <th>PE</th>
                <th>GH</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @php $no = 1; @endphp
            @forelse(($rows ?? []) as $r)
                <tr>
                    <td>{{ $no++ }}</td>
                    <td class="name">
                        {{ $r->name ?? '—' }}<br>
                    </td>
                    <td>{{ $r->phone_number ?? '—' }}</td>
                    <td>{{ $r->SM ?? 0 }}</td>
                    <td>{{ $r->CIA ?? 0 }}</td>
                    <td>{{ $r->TS ?? 0 }}</td>
                    <td>{{ $r->WWO ?? 0 }}</td>
                    <td>{{ $r->CA ?? 0 }}</td>
                    <td>{{ $r->L ?? 0 }}</td>
                    <td>{{ $r->SE ?? 0 }}</td>
                    <td>{{ $r->PS ?? 0 }}</td>
                    <td>{{ $r->PE ?? 0 }}</td>
                    <td>{{ $r->GH ?? 0 }}</td>
                    <td><strong>{{ $r->total_score ?? 0 }}</strong></td>
                </tr>
            @empty
                <tr>
                    <td colspan="14" class="muted" style="padding:14px;">Tidak ada data.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- BAGIAN TANDA TANGAN (STYLE SURAT RESMI - LANDSCAPE) --}}
    <table class="signature-table">
        <tr>
            {{-- Spacer Kiri (65%) --}}
            <td style="width: 65%; text-align: left;"></td>

            {{-- Blok Tanda Tangan Kanan (35%) --}}
            <td style="width: 35%;">
                {{-- Tanggal --}}
                <div style="margin-bottom: 5px;">
                    {{ $cityLocation }}, {{ $currentDate }}
                </div>

                {{-- Jabatan Atas --}}
                <div style="margin-bottom: 60px;">
                    Mengetahui, Pimpinan Unit
                </div>

                {{-- Nama (Bold & Underline) --}}
                <div style="font-weight: bold; text-decoration: underline;">
                    Muhammad Zain Mahbuby, B.Eng
                </div>

                {{-- Jabatan Bawah --}}
                <div>
                    Koordinator BCTI
                </div>
            </td>
        </tr>
    </table>

    <div class="footer">Halaman <span class="pagenum"></span></div>

</body>
</html>
