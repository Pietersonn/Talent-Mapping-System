@php
    $logoPath = public_path('assets/public/images/logo-bcti1.png');
    $logoBase64 = '';
    if (file_exists($logoPath)) {
        $type = pathinfo($logoPath, PATHINFO_EXTENSION);
        $data = file_get_contents($logoPath);
        $logoBase64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
    }

    $companyName    = 'BUSINESS & COMMUNICATION TRAINING INSTITUTE';
    $companyAddr1   = 'Kompleks Sekolah Global Islamic Boarding School (GIBS)';
    $companyAddr2   = 'Gedung Nurhayati Kampus GIBS, Jl. Trans - Kalimantan Lantai 2, Sungai Lumbah, Kec. Alalak, Kab. Barito Kuala, Kalimantan Selatan 70582';
    $companyContact = 'Email : bcti@hasnurcentre.org | Website: bcti.id';
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>{{ $reportTitle }}</title>

<style>
    @page {
        size: A4 landscape;
        margin: 12mm;
    }

    body {
        font-family: "Times New Roman", Times, serif;
        font-size: 10px;
        color: #000;
        line-height: 1.4;
    }

    /* ===== HEADER ===== */
    .clearfix::after {
        content: "";
        display: table;
        clear: both;
    }

    .header {
        margin-bottom: 8px;
    }

    .h-left {
        float: left;
        width: 35%;
    }

    .h-right {
        float: right;
        width: 63%;
        text-align: right;
    }

    .h-logo {
        height: 65px;
        width: auto;
    }

    .company-name {
        font-weight: bold;
        font-size: 14px;
        letter-spacing: .3px;
    }

    .company-sub {
        font-size: 10px;
        line-height: 1.3;
    }

    .divider {
        border: 0;
        border-top: 2px solid #000;
        margin: 6px 0 10px;
    }

    /* ===== TITLE ===== */
    .title-wrap {
        text-align: center;
        margin: 10px 0 14px;
    }

    .title {
        font-size: 14px;
        font-weight: bold;
        text-transform: uppercase;
        margin-bottom: 4px;
    }

    .subtitle {
        font-size: 10px;
    }

    /* ===== TABLE ===== */
    table {
        width: 100%;
        border-collapse: collapse;
        background: #fff;
        table-layout: auto; /* PENTING */
    }

    thead th {
        background: #f2f2f2;
        border: 1px solid #000;
        font-weight: bold;
        font-size: 10px;
        padding: 6px 4px;
        text-align: center;
        vertical-align: middle;
    }

    tbody td {
        border: 1px solid #000;
        padding: 6px;
        vertical-align: top;
        font-size: 9.5px;
        text-align: justify;
        word-break: break-word;
        white-space: normal;
    }

    /* ===== COLUMN WIDTH (ONLY SMALL COLUMNS) ===== */
    .col-no {
        width: 30px;
        text-align: center;
    }

    .col-kode {
        width: 50px;
        text-align: center;
        font-weight: bold;
    }

    .col-nama {
        width: 120px;
        font-weight: bold;
    }

    /* ===== FOOTER ===== */
    .footer {
        position: fixed;
        bottom: -8mm;
        left: 0;
        right: 0;
        text-align: right;
        font-size: 9px;
        color: #555;
    }

    .pagenum::before {
        content: counter(page);
    }
</style>
</head>

<body>

    <!-- HEADER -->
    <div class="header clearfix">
        <div class="h-left">
            @if($logoBase64)
                <img src="{{ $logoBase64 }}" class="h-logo" alt="BCTI">
            @else
                <strong class="company-name">BCTI</strong>
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

    <!-- TITLE -->
    <div class="title-wrap">
        <div class="title">LAPORAN KOMPETENSI</div>
        <div class="subtitle">
            Dicetak oleh: {{ $generatedBy }} • Tanggal: {{ $generatedAt }}
        </div>
    </div>

    <!-- TABLE -->
    <table>
        <thead>
            <tr>
                <th class="col-no">No</th>
                <th class="col-kode">Kode</th>
                <th class="col-nama">Nama Kompetensi</th>
                <th>Kekuatan (Strength)</th>
                <th>Kelemahan (Weakness)</th>
                <th>Pengembangan</th>
                <th>Rekomendasi Training</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $index => $item)
                <tr>
                    <td class="col-no">{{ $index + 1 }}</td>
                    <td class="col-kode">{{ $item->competency_code }}</td>
                    <td class="col-nama">{{ $item->competency_name }}</td>
                    <td>{{ $item->strength_description ?? '-' }}</td>
                    <td>{{ $item->weakness_description ?? '-' }}</td>
                    <td>{{ $item->improvement_activity ?? '-' }}</td>
                    <td>{{ $item->training_recommendations ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align:center; padding:20px;">
                        Tidak ada data kompetensi.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- FOOTER -->
    <div class="footer">
        Halaman <span class="pagenum"></span>
    </div>

</body>
</html>
