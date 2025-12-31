@php
    $logoPath = public_path('assets/public/images/logo-bcti1.png');
    $logoBase64 = '';
    if (file_exists($logoPath)) {
        $type = pathinfo($logoPath, PATHINFO_EXTENSION);
        $data = file_get_contents($logoPath);
        $logoBase64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
    }

    $reportTitle    = $reportTitle ?? 'Laporan Versi Soal';
    $generatedBy    = $generatedBy ?? 'Admin';
    $generatedAt    = $generatedAt ?? now()->format('d/m/Y H:i');

    $companyName    = 'BUSINESS & COMMUNICATION TRAINING INSTITUTE';
    $companyAddr1   = 'Kompleks Sekolah Global Islamic Boarding School (GIBS)';
    // Memecah alamat agar rapi dan tidak kepanjangan
    $companyAddr2   = 'Gedung Nurhayati Kampus GIBS, Jl. Trans - Kalimantan Lantai 2,';
    $companyAddr3   = 'Sungai Lumbah, Kec. Alalak, Kab. Barito Kuala, Kalimantan Selatan 70582';
    $companyContact = 'Email : bcti@hasnurcentre.org | Website: bcti.id';
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>{{ $reportTitle }}</title>
<style>
  @page { size: A4 potrait; margin: 18mm 14mm 16mm 14mm; }
  body { font-family: "Times New Roman", Times, serif; font-size: 11px; color: #000; }
  .clearfix:after { content:""; display: table; clear: both; }

  .header { margin-bottom: 15px; border-bottom: 2px solid #000; padding-bottom: 8px; }
  .h-left  { float:left;  width:10%; }
  .h-right { float:right; width:88%; text-align:right; }
  .h-logo  { height: 60px; width: auto; }

  .company-name { font-weight: 700; font-size: 14px; text-transform: uppercase; }
  .company-sub  { font-size: 10px; line-height:1.3; }

  .title-wrap { text-align:center; margin: 15px 0 20px; }
  .title   { font-size: 16px; font-weight:700; text-transform:uppercase; margin-bottom: 5px; }
  .subtitle{ font-size: 11px; }

  table { width:100%; border-collapse: collapse; margin-top: 10px; }
  thead th { border:1px solid #000; font-weight:700; font-size: 11px; padding: 6px; text-align:left; background-color: #f0f0f0; }
  tbody td { border:1px solid #000; padding: 6px; vertical-align: top; font-size: 11px; }

  .text-center{ text-align:center; }
  .footer { position: fixed; bottom: -10mm; left: 0; right: 0; text-align: right; font-size: 10px; font-style: italic; }
  .pagenum:before { content: counter(page); }
</style>
</head>
<body>

  <div class="header clearfix">
    <div class="h-left">
      @if(!empty($logoBase64))
        <img class="h-logo" src="{{ $logoBase64 }}" alt="BCTI">
      @else
        <strong>BCTI</strong>
      @endif
    </div>
    <div class="h-right">
      <div class="company-name">{{ $companyName }}</div>
      <div class="company-sub">
          {{ $companyAddr1 }}<br>
          {{ $companyAddr2 }}<br>
          {{ $companyAddr3 }}<br>
          {{ $companyContact }}
      </div>
    </div>
  </div>

  <div class="title-wrap">
    <div class="title">{{ $reportTitle }}</div>
    <div class="subtitle">Dicetak oleh: {{ $generatedBy }}</div>
    @if(!empty($search))
        <div class="subtitle" style="margin-top:2px;">Filter Pencarian: "{{ $search }}"</div>
    @endif
  </div>

  <table>
    <thead>
      <tr>
        <th style="width:30px; text-align:center;">No.</th>
        <th style="width:20%;">Nama Versi</th>
        <th style="width:35%;">Deskripsi</th>
        <th style="width:10%; text-align:center;">Tipe</th>
        <th style="width:10%; text-align:center;">Jml Soal</th>
        <th style="width:10%; text-align:center;">Status</th>
      </tr>
    </thead>
    <tbody>
      @php $no = 1; @endphp
      @forelse($versions as $v)
        <tr>
          <td class="text-center">{{ $no++ }}</td>
          <td>
              {{ $v->name }}
          </td>
          <td>{{ $v->description ?: '-' }}</td>
          <td class="text-center">{{ strtoupper($v->type) }}</td>
          <td class="text-center">{{ $v->questions_count }}</td>
          <td class="text-center">
              @if($v->is_active)
                  Aktif
              @else
                  Tidak Aktif
              @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="text-center" style="padding:20px;">Tidak ada data versi soal.</td></tr>
      @endforelse
    </tbody>
  </table>

  <div class="footer">Halaman <span class="pagenum"></span></div>
</body>
</html>
