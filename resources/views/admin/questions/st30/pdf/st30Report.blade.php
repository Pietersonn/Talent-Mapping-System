@php
    use Carbon\Carbon;

    // --- 1. Setup Logo ---
    $logoPath = public_path('assets/public/images/logo-bcti1.png');
    $logoBase64 = '';
    if (file_exists($logoPath)) {
        $type = pathinfo($logoPath, PATHINFO_EXTENSION);
        $data = file_get_contents($logoPath);
        $logoBase64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
    }

    // --- 2. Setup Tanggal & Lokasi (Untuk Tanda Tangan) ---
    Carbon::setLocale('id');
    $currentDate  = Carbon::now()->isoFormat('D MMMM Y'); // Contoh: 21 Januari 2026
    $cityLocation = 'Barito Kuala'; // Kota tempat tanda tangan

    // --- 3. Data Default ---
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
  @page { size: A4 portrait; margin: 18mm 14mm 16mm 14mm; }
  body { font-family: "Times New Roman", Times, serif; font-size: 11px; color: #111; }

  .clearfix:after { content:""; display: table; clear: both; }
  .header { margin-bottom: 8px; }
  .h-left  { float:left;  width:38%; }
  .h-right { float:right; width:60%; text-align:right; }
  .h-logo  { height: 72px; width: auto; }

  .company-name { font-weight: 700; font-size: 14px; letter-spacing:.25px; }
  .company-sub  { font-size: 11px; line-height:1.35; color:#222; }

  .divider { border:0; border-top:2px solid #000; margin: 6px 0 12px; }

  .title-wrap { text-align:center; margin: 4px 0 10px; }
  .title   { font-size: 16px; font-weight:700; text-transform:uppercase; margin:0 0 4px; }
  .subtitle{ font-size: 11px; color:#333; }

  table { width:100%; border-collapse: collapse; background:#fff; margin-top: 10px; }
  thead th { background:#ededed; border:1px solid #000; font-weight:700; font-size: 11px; padding: 6px; text-align:center; vertical-align: middle; }
  tbody td { border:1px solid #000; padding: 6px; vertical-align: top; font-size: 11px; }

  .text-center{ text-align:center; }
  .footer { position: fixed; bottom: -10mm; left: 0; right: 0; text-align: right; font-size: 11px; color:#6b7280; }
  .pagenum:before { content: counter(page); }

  /* --- Tambahan CSS Tanda Tangan Resmi --- */
  .signature-table { width: 100%; margin-top: 40px; border: none; page-break-inside: avoid; }
  .signature-table td { border: none; padding: 0; vertical-align: top; }
</style>
</head>
<body>

  {{-- HEADER SURAT --}}
  <div class="header clearfix">
    <div class="h-left">
      @if(!empty($logoBase64))
        <img class="h-logo" src="{{ $logoBase64 }}" alt="BCTI">
      @else
        <strong class="company-name" style="font-size: 24px;">BCTI</strong>
      @endif
    </div>
    <div class="h-right">
      <div class="company-name">{{ $companyName }}</div>
      <div class="company-sub">{!! $companyAddr1 !!}<br>{!! $companyAddr2 !!}<br>{!! $companyContact !!}</div>
    </div>
  </div>

  <hr class="divider">

  {{-- JUDUL LAPORAN --}}
  <div class="title-wrap">
    <div class="title">{{ $reportTitle }}</div>
    <div class="subtitle">Versi: {{ $versionName }}</div>
    <div class="subtitle">Dicetak oleh: {{ $generatedBy }} &nbsp;•&nbsp; {{ $generatedAt }}</div>
  </div>

  {{-- TABEL DATA --}}
  <table>
    <thead>
      <tr>
        <th style="width:30px;">No.</th>
        <th>Pernyataan (Statement)</th>
        <th style="width:15%;">Tipologi</th>
        <th style="width:10%;">Kode</th>
      </tr>
    </thead>
    <tbody>
      @forelse($rows as $q)
        <tr>
          <td class="text-center">{{ $q->number }}</td>
          <td>{{ $q->statement }}</td>
          <td>{{ $q->typologyDescription->typology_name ?? '-' }}</td>
          <td class="text-center">{{ $q->typology_code }}</td>
        </tr>
      @empty
        <tr><td colspan="4" class="text-center" style="padding:20px;">Tidak ada data soal pada versi ini.</td></tr>
      @endforelse
    </tbody>
  </table>

  {{-- BAGIAN TANDA TANGAN (STYLE SURAT RESMI) --}}
  <table class="signature-table">
    <tr>
      {{-- Spacer Kiri (60%) agar tanda tangan di kanan --}}
      <td style="width: 60%;"></td>

      {{-- Blok Tanda Tangan Kanan (40%) --}}
      <td style="width: 40%; text-align: center;">
        {{-- Tanggal Surat --}}
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
