<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 0;
            size: A4 portrait;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #ffffff;
            color: #171414;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 11px;
        }

        .page {
            height: 297mm;
            overflow: hidden;
            page-break-after: always;
            position: relative;
            width: 210mm;
        }

        .page:last-child {
            page-break-after: auto;
        }

        .brand-strip {
            background: #171414;
            border-bottom: 3px solid #c71414;
            display: table;
            height: 24mm;
            padding: 0 14mm;
            width: 100%;
        }

        .brand-inner {
            display: table-cell;
            height: 24mm;
            vertical-align: middle;
        }

        .brand-logo {
            display: block;
            height: auto;
            max-height: 13mm;
            max-width: 50mm;
            width: auto;
        }

        .brand-text {
            color: #ffffff;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: .4px;
        }

        .content {
            padding: 8mm 14mm 18mm;
        }

        .meta {
            align-items: flex-end;
            display: table;
            margin-bottom: 4mm;
            width: 100%;
        }

        .meta-main,
        .meta-count {
            display: table-cell;
            vertical-align: bottom;
        }

        .meta-title {
            color: #171414;
            font-size: 20px;
            font-weight: 700;
            line-height: 1.2;
            margin: 0 0 3px;
        }

        .meta-line {
            color: #4a4545;
            font-size: 10px;
            line-height: 1.35;
        }

        .meta-count {
            color: #4a4545;
            font-size: 10px;
            font-weight: 700;
            text-align: right;
            white-space: nowrap;
            width: 40mm;
        }

        .filters {
            background: #f4f1ea;
            border-top: 2px solid #c71414;
            color: #262222;
            font-size: 9px;
            line-height: 1.3;
            margin-bottom: 4.5mm;
            padding: 2.2mm 4mm;
        }

        .empty {
            background: #fbfaf7;
            border: 1px solid #ded8ca;
            color: #333030;
            font-size: 11px;
            padding: 7mm;
        }

        .reservation-card {
            background: #fcfbf7;
            border: 1px solid #ded8ca;
            margin-bottom: 4mm;
            min-height: 34mm;
            page-break-inside: avoid;
            position: relative;
        }

        .reservation-card:nth-child(even) {
            background: #f4f1ea;
        }

        .reservation-accent {
            background: #c71414;
            height: 1.2mm;
            left: 0;
            position: absolute;
            right: 0;
            top: 0;
        }

        .reservation-inner {
            padding: 4mm 4mm 3mm;
        }

        .reservation-head {
            display: table;
            margin-bottom: 3mm;
            width: 100%;
        }

        .reservation-person,
        .reservation-status {
            display: table-cell;
            vertical-align: top;
        }

        .reservation-person {
            color: #171414;
            font-size: 11.5px;
            font-weight: 700;
            line-height: 1.25;
        }

        .reservation-status {
            font-size: 10px;
            font-weight: 700;
            text-align: right;
            white-space: nowrap;
            width: 37mm;
        }

        .status-approved {
            color: #0d7a33;
        }

        .status-pending {
            color: #df7308;
        }

        .status-cancelled {
            color: #c71414;
        }

        .status-completed {
            color: #0d52b5;
        }

        .status-no-show {
            color: #000000;
        }

        .status-default {
            color: #4a4545;
        }

        .reservation-grid {
            display: table;
            table-layout: fixed;
            width: 100%;
        }

        .reservation-cell {
            display: table-cell;
            padding-right: 4mm;
            vertical-align: top;
        }

        .reservation-cell.wide {
            width: 33%;
        }

        .reservation-cell.medium {
            width: 27%;
        }

        .reservation-cell.narrow {
            width: 20%;
        }

        .label {
            color: #77706c;
            display: block;
            font-size: 8px;
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: .7mm;
        }

        .value {
            color: #171414;
            display: block;
            font-size: 9.2px;
            line-height: 1.28;
        }

        .reservation-extra {
            border-top: 1px solid #e4ded2;
            color: #514c4a;
            display: table;
            font-size: 8.5px;
            line-height: 1.28;
            margin-top: 2.5mm;
            padding-top: 1.7mm;
            width: 100%;
        }

        .reservation-extra span {
            display: table-cell;
            vertical-align: top;
            width: 50%;
        }

        .footer {
            bottom: 6mm;
            color: #504a4a;
            font-size: 9px;
            left: 14mm;
            position: absolute;
            right: 14mm;
        }

        .footer-line {
            background: #c71414;
            height: .5mm;
            margin-bottom: 3mm;
        }

        .footer-left {
            float: left;
        }

        .footer-right {
            float: right;
            font-weight: 700;
        }
    </style>
</head>
<body>
@foreach ($pages as $pageIndex => $page)
    <section class="page">
        <header class="brand-strip">
            <div class="brand-inner">
                @if ($meta['logo'])
                    <img class="brand-logo" src="{{ $meta['logo'] }}" alt="strzelnice.org">
                @else
                    <div class="brand-text">STRZELNICE.ORG</div>
                @endif
            </div>
        </header>

        <main class="content">
            <div class="meta">
                <div class="meta-main">
                    <h1 class="meta-title">{{ $meta['title'] }}</h1>
                    <div class="meta-line">Wygenerowano: {{ $meta['generated_at'] }}</div>
                    <div class="meta-line">Wygenerował: {{ $meta['generated_by'] }}</div>
                </div>
                <div class="meta-count">
                    Rezerwacje: {{ $page['range'] }} z {{ $totalRows }}
                </div>
            </div>

            <div class="filters">
                Filtry: {{ $meta['filters'] }}
            </div>

            @forelse ($page['rows'] as $index => $row)
                @php
                    $statusKey = mb_strtolower($row['status_label'] ?? '');
                    $statusClass = match ($statusKey) {
                        'zatwierdzona' => 'status-approved',
                        'oczekująca', 'oczekujaca' => 'status-pending',
                        'anulowana' => 'status-cancelled',
                        'zrealizowana' => 'status-completed',
                        'niezrealizowana' => 'status-no-show',
                        default => 'status-default',
                    };
                @endphp

                <article class="reservation-card">
                    <div class="reservation-accent"></div>
                    <div class="reservation-inner">
                        <div class="reservation-head">
                            <div class="reservation-person">
                                {{ $row['original_reservation_id'] ?? '' }} | {{ $row['customer_name'] ?: 'Brak danych' }}
                            </div>
                            <div class="reservation-status {{ $statusClass }}">
                                Status: {{ $row['status_label'] ?? '' }}
                            </div>
                        </div>

                        <div class="reservation-grid">
                            <div class="reservation-cell medium">
                                <span class="label">Termin</span>
                                <span class="value">{{ $row['start_label'] ?? '' }} - {{ $row['end_label'] ?? '' }}</span>
                            </div>
                            <div class="reservation-cell wide">
                                <span class="label">Strzelnica</span>
                                <span class="value">{{ $row['shooting_range_name'] ?? '' }}</span>
                            </div>
                            <div class="reservation-cell narrow">
                                <span class="label">Oś / stanowiska</span>
                                <span class="value">{{ $row['lane_name'] ?? '' }} / {{ $row['positions_count'] ?? '' }}</span>
                            </div>
                            <div class="reservation-cell medium">
                                <span class="label">Prowadzący</span>
                                <span class="value">
                                    {{ trim(($row['shooting_leader_label'] ?? '').' '.($row['shooting_leader_license_number'] ?? '')) }}
                                </span>
                            </div>
                        </div>

                        <div class="reservation-extra">
                            <span>
                                Dynamiczne: {{ $row['dynamic_shooting_label'] ?? '' }}
                                | Źródło: {{ $row['source_label'] ?? '' }}
                            </span>
                            <span>
                                Kontakt: {{ trim(($row['customer_email'] ?? '').' '.($row['customer_phone'] ?? '')) }}
                            </span>
                        </div>
                    </div>
                </article>
            @empty
                <div class="empty">Brak rekordów dla wybranych filtrów.</div>
            @endforelse
        </main>

        <footer class="footer">
            <div class="footer-line"></div>
            <span class="footer-left">strzelnice.org - raport wygenerowany automatycznie</span>
            <span class="footer-right">Strona {{ $pageIndex + 1 }}/{{ $pages->count() }}</span>
        </footer>
    </section>
@endforeach
</body>
</html>
