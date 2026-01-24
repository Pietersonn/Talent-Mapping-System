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

    // --- 2. Setup Tanggal & Lokasi ---
    Carbon::setLocale('id');
    $currentDate  = Carbon::now()->isoFormat('D MMMM Y'); // Contoh: 21 Januari 2026
    $cityLocation = 'Barito Kuala';

    // --- 3. Data Default ---
    $reportTitle    = $reportTitle ?? 'Laporan Data Pengguna';
    $generatedBy    = $generatedBy ?? 'Admin';
    $generatedAt    = $generatedAt ?? now()->format('d/m/Y H:i');

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

  /* --- HEADER & KOP SURAT --- */
  .clearfix:after { content:""; display: table; clear: both; }
  .header { margin-bottom: 8px; }
  .h-left  { float:left;  width:38%; }
  .h-right { float:right; width:60%; text-align:right; }
  .h-logo  { height: 72px; width: auto; }
  .company-name { font-weight: 700; font-size: 14px; letter-spacing:.25px; }
  .company-sub  { font-size: 11px; line-height:1.35; color:#222; }
  .divider { border:0; border-top:2px solid #000; margin: 6px 0 12px; }

  /* --- JUDUL --- */
  .title-wrap { text-align:center; margin: 4px 0 10px; }
  .title   { font-size: 18px; font-weight:700; text-transform:uppercase; margin:0 0 4px; }
  .subtitle{ font-size: 11px; color:#333; }

  /* --- TABEL DATA --- */
  table { width:100%; border-collapse: collapse; background:#fff; margin-top: 10px; }
  thead th { background:#ededed; border:1px solid #000; font-weight:700; font-size: 11px; padding: 6px; text-align:center; vertical-align: middle; }
  tbody td { border:1px solid #000; padding: 6px; vertical-align: top; font-size: 11px; }
  tbody td.name-col { text-align:left; font-weight: normal; }

  /* --- TANDA TANGAN STYLE (Override Borders) --- */
  .signature-table { width: 100%; margin-top: 30px; border: none; page-break-inside: avoid; }
  .signature-table td { border: none; padding: 0; vertical-align: top; text-align: center; }

  /* Utilities */
  .text-center{ text-align:center; }
  .footer { position: fixed; bottom: -10mm; left: 0; right: 0; text-align: right; font-size: 11px; color:#6b7280; }
  .pagenum:before { content: counter(page); }
</style>
</head>
<body>

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
      <div class="company-sub">{{ $companyAddr1 }}<br>{{ $companyAddr2 }}<br>{{ $companyContact }}</div>
    </div>
  </div>

  <hr class="divider">

  <div class="title-wrap">
    <div class="title">{{ $reportTitle }}</div>
    <div class="subtitle">Dicetak oleh: {{ $generatedBy }} &nbsp;•&nbsp; {{ $generatedAt }}</div>
  </div>

  <table>
    <thead>
      <tr>
        <th style="width:30px;">No.</th>
        <th>Nama Pengguna</th>
        <th style="width:25%;">Email</th>
        <th style="width:15%;">Peran (Role)</th>
        <th style="width:15%;">No. Kontak</th>
        <th style="width:10%;">Status</th>
      </tr>
    </thead>
    <tbody>
      @php $no = 1; @endphp
      @forelse($rows as $user)
        <tr>
          <td class="text-center">{{ $no++ }}</td>
          <td class="name-col">{{ $user->name }}</td>
          <td>{{ $user->email }}</td>
          <td class="text-center">{{ ucfirst($user->role) }}</td>
          <td class="text-center">{{ $user->phone_number ?? '-' }}</td>
          <td class="text-center">{{ $user->is_active ? 'Aktif' : 'Tidak Aktif' }}</td>
        </tr>
      @empty
        <tr><td colspan="6" class="text-center" style="padding:20px;">Tidak ada data pengguna.</td></tr>
      @endforelse
    </tbody>
  </table>

  <table class="signature-table">
    <tr>

      <td style="width: 60%;"></td>

      <td style="width: 40%;">

        <div style="margin-bottom: 5px;">
            {{ $cityLocation }}, {{ $currentDate }}
        </div>
        <div style="margin-bottom: 60px;">
            Mengetahui, Pimpinan Unit
        </div>
        <div style="font-weight: bold; text-decoration: underline;">
            Muhammad Zain Mahbuby, B.Eng
        </div>
        <div>
            Koordinator BCTI
        </div>
      </td>
    </tr>
  </table>

  <div class="footer">Halaman <span class="pagenum"></span></div>
</body>
</html>
