@php
    $logoFile = public_path('assets/public/images/logo-bcti1.png');
    $logoBase64 = '';
    if (file_exists($logoFile)) {
        $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoFile));
    }

    $companyName = 'BUSINESS & COMMUNICATION TRAINING INSTITUTE';
    $companyAddr1 = 'Kompleks Sekolah Global Islamic Boarding School (GIBS)';
    $companyAddr2 = 'Gedung Nurhayati Kampus GIBS, Jl. Trans - Kalimantan Lantai 2, Sungai Lumbah, Barito Kuala, 70582';
    $companyContact = 'Email : bcti@hasnurcentre.org | Website: bcti.id';
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $reportTitle }}</title>
    <style>
        @page { size: A4 landscape; margin: 10mm; }
        body { font-family: "Times New Roman", Times, serif; font-size: 11px; color: #000; }

        /* Header */
        .header { margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .h-left { float: left; width: 15%; }
        .h-right { float: right; width: 84%; text-align: right; }
        .h-logo { height: 60px; }
        .company-name { font-weight: bold; font-size: 14px; text-transform: uppercase; }
        .company-sub { font-size: 10px; margin-top: 4px; }

        .title-wrap { text-align: center; margin-bottom: 15px; }
        .title { font-size: 14px; font-weight: bold; text-transform: uppercase; }
        .subtitle { font-size: 10px; margin-top: 5px; }

        /* Table */
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #000; padding: 5px; vertical-align: top; text-align: left; }

        th { background-color: #f0f0f0; font-weight: bold; text-align: center; font-size: 10px; }
        td { font-size: 10px; background-color: transparent; font-weight: normal; }

        .footer { position: fixed; bottom: -5mm; left: 0; right: 0; text-align: right; font-size: 9px; color: #666; }
        .pagenum:before { content: counter(page); }
    </style>
</head>
<body>

    <div class="header">
        <div class="h-left">
            @if($logoBase64)
                <img class="h-logo" src="{{ $logoBase64 }}" alt="Logo">
            @endif
        </div>
        <div class="h-right">
            <div class="company-name">{{ $companyName }}</div>
            <div class="company-sub">
                {{ $companyAddr1 }}<br>{{ $companyAddr2 }}<br>{{ $companyContact }}
            </div>
        </div>
        <div style="clear: both;"></div>
    </div>

    <div class="title-wrap">
        <div class="title">{{ $reportTitle }}</div>
        <div class="subtitle">
            Dicetak oleh: {{ $generatedBy }} | Tanggal: {{ $generatedAt }}
            @if($dateFrom || $dateTo)
                <br>Periode: {{ $dateFrom ?? '-' }} s/d {{ $dateTo ?? '-' }}
            @endif
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 4%;">No</th>
                <th style="width: 15%;">Nama</th>
                <th style="width: 15%;">Email</th>
                <th style="width: 15%;">Acara</th> {{-- KOLOM ACARA BARU --}}
                <th style="width: 10%;">Tgl Req</th>
                <th style="width: 8%;">Status</th>
                <th style="width: 13%;">Diproses Oleh</th>
                <th style="width: 10%;">Tgl Proses</th>
                <th style="width: 10%;">Catatan</th>
            </tr>
        </thead>
        <tbody>
            @php $no = 1; @endphp
            @forelse($rows as $row)
                <tr>
                    <td style="text-align: center;">{{ $no++ }}</td>
                    <td>{{ $row->user->name ?? '-' }}</td>
                    <td>{{ $row->user->email ?? '-' }}</td>

                    {{-- ISI KOLOM ACARA --}}
                    <td>{{ $row->testResult->event_title ?? '-' }}</td>

                    <td style="text-align: center;">{{ $row->request_date ? $row->request_date->format('d/m/Y') : '-' }}</td>
                    <td style="text-align: center;">
                        @if($row->status == 'approved')
                            Disetujui
                        @elseif($row->status == 'rejected')
                            Ditolak
                        @else
                            Menunggu
                        @endif
                    </td>
                    <td>{{ $row->approvedBy->name ?? '-' }}</td>
                    <td style="text-align: center;">{{ $row->approved_at ? $row->approved_at->format('d/m/Y') : '-' }}</td>
                    <td>
                        @if($row->status == 'rejected')
                            {{ $row->rejection_reason }}
                        @elseif($row->admin_notes)
                            {{ $row->admin_notes }}
                        @else
                            -
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" style="text-align: center; padding: 15px;">Tidak ada data riwayat.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">Halaman <span class="pagenum"></span></div>
</body>
</html>
