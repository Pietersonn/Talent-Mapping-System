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
  /* Landscape agar muat 5 kolom */
  @page { size: A4 landscape; margin: 10mm 10mm 10mm 10mm; }
  body { font-family: "Times New Roman", Times, serif; font-size: 11px; color: #000; }

  .clearfix:after { content:""; display: table; clear: both; }
  .header { margin-bottom: 8px; }
  .h-left  { float:left;  width:38%; }
  .h-right { float:right; width:60%; text-align:right; }
  .h-logo  { height: 60px; width: auto; }
  .company-name { font-weight: bold; font-size: 14px; letter-spacing:.25px; }
  .company-sub  { font-size: 10px; line-height:1.3; color:#000; }

  .divider { border:0; border-top:1px solid #000; margin: 6px 0 10px; }
  .title-wrap { text-align:center; margin: 4px 0 10px; }
  .title   { font-size: 14px; font-weight:bold; text-transform:uppercase; margin:0 0 4px; }
  .subtitle{ font-size: 10px; color:#000; }

  table { width:100%; border-collapse: collapse; background:#fff; margin-top: 10px; }
  thead th {
      background:#f0f0f0;
      border:1px solid #000;
      font-weight:bold;
      font-size: 11px;
      padding: 5px;
      text-align:center;
      vertical-align: middle;
  }
  tbody td {
      border:1px solid #000;
      padding: 5px;
      vertical-align: top;
      font-size: 11px;
      /* Memastikan tidak ada bold/italic otomatis */
      font-weight: normal;
      font-style: normal;
  }
  .text-center{ text-align:center; }

  /* Helper untuk list opsi agar rapi tanpa bullet default list HTML */
  .opt-row { margin-bottom: 4px; }

  .footer { position: fixed; bottom: -8mm; left: 0; right: 0; text-align: right; font-size: 10px; color:#555; }
  .pagenum:before { content: counter(page); }
</style>
</head>
<body>

  <div class="header clearfix">
    <div class="h-left">
      @if(!empty($logoBase64))
        <img class="h-logo" src="{{ $logoBase64 }}" alt="BCTI">
      @else
        <strong class="company-name" style="font-size: 20px;">BCTI</strong>
      @endif
    </div>
    <div class="h-right">
      <div class="company-name">{{ $companyName }}</div>
      <div class="company-sub">{!! $companyAddr1 !!}<br>{!! $companyAddr2 !!}<br>{!! $companyContact !!}</div>
    </div>
  </div>

  <hr class="divider">

  <div class="title-wrap">
    <div class="title">{{ $reportTitle }}</div>
    <div class="subtitle">Versi: {{ $versionName }}</div>
  </div>

  <table>
    <thead>
      <tr>
        <th style="width:25px;">No</th>
        <th style="width:35%;">Soal</th>
        <th style="width:35%;">Opsi</th>
        <th style="width:30px;">Skor</th>
        <th style="width:15%;">Kompetensi</th>
      </tr>
    </thead>
    <tbody>
      @forelse($rows as $q)
        <tr>
          <td class="text-center">{{ $q->number }}</td>

          <td>{{ $q->question_text }}</td>

          <td>
             @foreach($q->options as $opt)
                <div class="opt-row">
                    {{ strtoupper($opt->option_letter) }}. {{ $opt->option_text }}
                </div>
             @endforeach
          </td>

          <td class="text-center">
             @foreach($q->options as $opt)
                <div class="opt-row">
                    {{ $opt->score }}
                </div>
             @endforeach
          </td>

          <td>
             {{ $q->competencyDescription->competency_name ?? '-' }}
          </td>
        </tr>
      @empty
        <tr><td colspan="5" class="text-center" style="padding:20px;">Tidak ada data soal pada versi ini.</td></tr>
      @endforelse
    </tbody>
  </table>

  <div class="footer">Halaman <span class="pagenum"></span></div>
</body>
</html>
