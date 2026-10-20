<?php

namespace App\Http\Controllers;

use App\Models\Lane;
use App\Models\Reservation;
use App\Models\ShootingRange;
use App\Models\SystemConfiguration;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportController extends Controller
{
    private const PER_PAGE = 50;

    private const RESERVATION_HISTORY_SORTS = [
        'customer' => 'customer_name',
        'shooting_range' => 'shooting_range_name',
        'start' => 'start',
        'lane' => 'lane_name',
        'shooting_leader' => 'has_shooting_leader_license',
        'dynamic_shooting' => 'is_dynamic_shooting',
        'source' => 'source',
    ];

    private const RESERVATION_HISTORY_EXPORT_COLUMNS = [
        'original_reservation_id' => 'ID',
        'source_table_label' => 'Typ rekordu',
        'customer_name' => 'Klient',
        'customer_email' => 'E-mail',
        'customer_phone' => 'Telefon',
        'shooting_range_name' => 'Strzelnica',
        'shooting_range_town' => 'Miejscowość',
        'start_label' => 'Termin od',
        'end_label' => 'Termin do',
        'lane_name' => 'Oś',
        'positions_count' => 'Stanowiska',
        'status_label' => 'Status',
        'shooting_leader_label' => 'Prowadzący strzelanie',
        'shooting_leader_license_number' => 'Nr uprawnień',
        'dynamic_shooting_label' => 'Strzelanie dynamiczne',
        'source_label' => 'Źródło',
        'reservation_created_at_label' => 'Utworzono',
    ];

    public function index(Request $request): View
    {
        $configuration = SystemConfiguration::activeConfiguration();
        $reportsEnabled = (bool) ($configuration?->reports_enabled ?? true);
        $user = $request->user();

        $reports = collect();

        if ($reportsEnabled && ($user->isSuperAdmin() || $user->isAdmin())) {
            $reports->push([
                'title' => 'Historia rezerwacji',
                'description' => 'Rezerwacje bieżące i archiwalne z filtrami według dat, statusów, osi, prowadzącego i strzelania dynamicznego.',
                'icon' => 'bi-clipboard-data',
                'route' => route('reports.reservations.history'),
            ]);
        }

        return view('reports.index', [
            'reports' => $reports,
            'reportsEnabled' => $reportsEnabled,
        ]);
    }

    public function reservationHistory(Request $request): View
    {
        $this->ensureReportsEnabled($request);

        abort_unless($request->user()->isSuperAdmin() || $request->user()->isAdmin(), 403);

        $managedRanges = $this->managedRanges($request);

        abort_if($managedRanges->isEmpty(), 403);

        $filters = $this->reservationHistoryFilters($request, $managedRanges);
        $selectedRangeIds = $this->selectedRangeIds($request, $managedRanges, $filters);
        $lanes = $this->lanesForRanges($selectedRangeIds);
        $records = $this->reservationHistoryRecords($filters, $selectedRangeIds);

        return view('reports.reservation-history', [
            'records' => $records,
            'filters' => $filters,
            'managedRanges' => $managedRanges,
            'lanes' => $lanes,
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function exportReservationHistory(Request $request, string $format)
    {
        $this->ensureReportsEnabled($request);

        abort_unless($request->user()->isSuperAdmin() || $request->user()->isAdmin(), 403);

        $managedRanges = $this->managedRanges($request);

        abort_if($managedRanges->isEmpty(), 403);

        $filters = $this->reservationHistoryFilters($request, $managedRanges);
        $selectedRangeIds = $this->selectedRangeIds($request, $managedRanges, $filters);
        $rows = $this->reservationHistoryQuery($filters, $selectedRangeIds)
            ->get()
            ->map(fn ($record): array => $this->reservationHistoryExportRow($record))
            ->values();

        $fileName = 'historia-rezerwacji-'.now()->format('Ymd-His').'.'.$format;

        return match ($format) {
            'csv' => $this->exportReservationHistoryCsv($rows, $fileName),
            'xml' => $this->exportReservationHistoryXml($rows, $fileName),
            'xls' => $this->exportReservationHistorySpreadsheet($rows, $fileName, 'xls'),
            'xlsx' => $this->exportReservationHistorySpreadsheet($rows, $fileName, 'xlsx'),
            'pdf' => $this->exportReservationHistoryPdf($request, $rows, $filters, $fileName),
        };
    }

    private function ensureReportsEnabled(Request $request): void
    {
        $enabled = (bool) (SystemConfiguration::activeConfiguration()?->reports_enabled ?? true);

        abort_unless($enabled || $request->user()->isSuperAdmin(), 403);
    }

    private function managedRanges(Request $request)
    {
        if ($request->user()->isSuperAdmin()) {
            return ShootingRange::query()
                ->orderBy('name')
                ->get(['id', 'name', 'town']);
        }

        return $request->user()
            ->shootingRanges()
            ->wherePivot('is_approved', true)
            ->wherePivot('role', 'admin')
            ->orderBy('name')
            ->get(['shooting_ranges.id', 'shooting_ranges.name', 'shooting_ranges.town']);
    }

    private function reservationHistoryFilters(Request $request, $managedRanges): array
    {
        $statusKeys = array_keys($this->statusOptions());
        $rangeIds = $managedRanges->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'shooting_range_id' => ['nullable', 'integer', Rule::in($rangeIds)],
            'statuses' => ['nullable', 'array'],
            'statuses.*' => ['string', Rule::in($statusKeys)],
            'lane_ids' => ['nullable', 'array'],
            'lane_ids.*' => ['integer'],
            'shooting_leader' => ['nullable', Rule::in(['all', 'yes', 'no'])],
            'dynamic_shooting' => ['nullable', Rule::in(['all', 'yes', 'no'])],
            'sort' => ['nullable', Rule::in(array_keys(self::RESERVATION_HISTORY_SORTS))],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        return [
            'date_from' => $validated['date_from'] ?? now()->subMonth()->toDateString(),
            'date_to' => $validated['date_to'] ?? today()->toDateString(),
            'shooting_range_id' => $validated['shooting_range_id'] ?? null,
            'statuses' => $validated['statuses'] ?? [],
            'lane_ids' => $validated['lane_ids'] ?? [],
            'shooting_leader' => $validated['shooting_leader'] ?? 'all',
            'dynamic_shooting' => $validated['dynamic_shooting'] ?? 'all',
            'sort' => $validated['sort'] ?? 'start',
            'direction' => $validated['direction'] ?? 'desc',
        ];
    }

    private function selectedRangeIds(Request $request, $managedRanges, array $filters): array
    {
        $rangeIds = $managedRanges->pluck('id')->map(fn ($id): int => (int) $id)->all();

        if (filled($filters['shooting_range_id'])) {
            return [(int) $filters['shooting_range_id']];
        }

        if ($request->user()->isAdmin() && count($rangeIds) === 1) {
            return $rangeIds;
        }

        return $rangeIds;
    }

    private function lanesForRanges(array $rangeIds)
    {
        return Lane::query()
            ->whereIn('shooting_range_id', $rangeIds)
            ->orderBy('name')
            ->get(['id', 'shooting_range_id', 'name']);
    }

    private function reservationHistoryRecords(array $filters, array $rangeIds): LengthAwarePaginator
    {
        return $this->reservationHistoryQuery($filters, $rangeIds)
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    private function reservationHistoryQuery(array $filters, array $rangeIds)
    {
        $dateFrom = Carbon::parse($filters['date_from'])->startOfDay();
        $dateTo = Carbon::parse($filters['date_to'])->endOfDay();

        $active = DB::table('reservations')
            ->leftJoin('shooting_ranges', 'shooting_ranges.id', '=', 'reservations.shooting_range_id')
            ->leftJoin('lanes', 'lanes.id', '=', 'reservations.line_id')
            ->select([
                DB::raw("'active' as source_table"),
                'reservations.id as reservation_id',
                'reservations.id as original_reservation_id',
                'reservations.customer_name',
                'reservations.customer_email',
                'reservations.customer_phone',
                'reservations.shooting_range_id',
                'shooting_ranges.name as shooting_range_name',
                'shooting_ranges.town as shooting_range_town',
                'reservations.line_id',
                'lanes.name as lane_name',
                'reservations.start',
                'reservations.end',
                'reservations.positions_count',
                'reservations.status',
                'reservations.has_shooting_leader_license',
                'reservations.shooting_leader_license_number',
                'reservations.is_dynamic_shooting',
                'reservations.source',
                'reservations.created_at as reservation_created_at',
            ]);

        $archive = DB::table('reservation_archives')
            ->select([
                DB::raw("'archive' as source_table"),
                'reservation_archives.id as reservation_id',
                'reservation_archives.original_reservation_id',
                'reservation_archives.customer_name',
                'reservation_archives.customer_email',
                'reservation_archives.customer_phone',
                'reservation_archives.shooting_range_id',
                'reservation_archives.shooting_range_name',
                'reservation_archives.shooting_range_town',
                'reservation_archives.line_id',
                'reservation_archives.lane_name',
                'reservation_archives.start',
                'reservation_archives.end',
                'reservation_archives.positions_count',
                'reservation_archives.status',
                'reservation_archives.has_shooting_leader_license',
                'reservation_archives.shooting_leader_license_number',
                'reservation_archives.is_dynamic_shooting',
                'reservation_archives.source',
                'reservation_archives.reservation_created_at',
            ]);

        foreach ([$active, $archive] as $query) {
            $query
                ->whereIn($query === $active ? 'reservations.shooting_range_id' : 'reservation_archives.shooting_range_id', $rangeIds)
                ->whereBetween($query === $active ? 'reservations.start' : 'reservation_archives.start', [$dateFrom, $dateTo]);

            if ($filters['statuses'] !== []) {
                $query->whereIn($query === $active ? 'reservations.status' : 'reservation_archives.status', $filters['statuses']);
            }

            if ($filters['lane_ids'] !== []) {
                $query->whereIn($query === $active ? 'reservations.line_id' : 'reservation_archives.line_id', $filters['lane_ids']);
            }

            if ($filters['shooting_leader'] !== 'all') {
                $query->where(
                    $query === $active ? 'reservations.has_shooting_leader_license' : 'reservation_archives.has_shooting_leader_license',
                    $filters['shooting_leader'] === 'yes'
                );
            }

            if ($filters['dynamic_shooting'] !== 'all') {
                $query->where(
                    $query === $active ? 'reservations.is_dynamic_shooting' : 'reservation_archives.is_dynamic_shooting',
                    $filters['dynamic_shooting'] === 'yes'
                );
            }
        }

        $sortColumn = self::RESERVATION_HISTORY_SORTS[$filters['sort']] ?? 'start';
        $direction = $filters['direction'] === 'asc' ? 'asc' : 'desc';

        $query = DB::query()
            ->fromSub($active->unionAll($archive), 'reservation_history')
            ->orderBy($sortColumn, $direction);

        if ($sortColumn !== 'start') {
            $query->orderByDesc('start');
        }

        return $query;
    }

    private function statusOptions(): array
    {
        return [
            Reservation::STATUS_PENDING => 'Oczekująca',
            Reservation::STATUS_APPROVED => 'Zatwierdzona',
            Reservation::STATUS_CANCELLED => 'Anulowana',
            Reservation::STATUS_COMPLETED => 'Zrealizowana',
            Reservation::STATUS_NO_SHOW => 'Niezrealizowana',
        ];
    }

    private function sourceOptions(): array
    {
        return [
            Reservation::SOURCE_ONLINE_ACCOUNT => 'Klient z kontem',
            Reservation::SOURCE_STAFF_ACCOUNT => 'Obsługa dla klienta',
            Reservation::SOURCE_STAFF_GUEST => 'Obsługa dla gościa',
        ];
    }

    private function reservationHistoryExportRow(object $record): array
    {
        return [
            'original_reservation_id' => '#'.$record->original_reservation_id,
            'source_table_label' => $record->source_table === 'archive' ? 'Archiwum' : 'Rezerwacja bieżąca',
            'customer_name' => $record->customer_name ?: 'Brak danych',
            'customer_email' => $record->customer_email,
            'customer_phone' => $record->customer_phone,
            'shooting_range_name' => $record->shooting_range_name ?: 'Usunięta strzelnica',
            'shooting_range_town' => $record->shooting_range_town,
            'start_label' => Carbon::parse($record->start)->format('Y-m-d H:i'),
            'end_label' => Carbon::parse($record->end)->format('Y-m-d H:i'),
            'lane_name' => $record->lane_name ?: 'Usunięta oś',
            'positions_count' => $record->positions_count,
            'status_label' => $this->statusOptions()[$record->status] ?? $record->status,
            'shooting_leader_label' => $record->has_shooting_leader_license ? 'Tak' : 'Nie',
            'shooting_leader_license_number' => $record->shooting_leader_license_number,
            'dynamic_shooting_label' => $record->is_dynamic_shooting ? 'Tak' : 'Nie',
            'source_label' => $this->sourceOptions()[$record->source] ?? $record->source,
            'reservation_created_at_label' => $record->reservation_created_at
                ? Carbon::parse($record->reservation_created_at)->format('Y-m-d H:i')
                : null,
        ];
    }

    private function exportReservationHistoryCsv($rows, string $fileName)
    {
        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, array_values(self::RESERVATION_HISTORY_EXPORT_COLUMNS), ';');

            foreach ($rows as $row) {
                fputcsv($output, array_map(
                    fn ($key) => $row[$key] ?? '',
                    array_keys(self::RESERVATION_HISTORY_EXPORT_COLUMNS)
                ), ';');
            }

            fclose($output);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function exportReservationHistoryXml($rows, string $fileName)
    {
        return response()->streamDownload(function () use ($rows): void {
            $xml = new \XMLWriter;
            $xml->openMemory();
            $xml->startDocument('1.0', 'UTF-8');
            $xml->startElement('reservations');

            foreach ($rows as $row) {
                $xml->startElement('reservation');

                foreach (self::RESERVATION_HISTORY_EXPORT_COLUMNS as $key => $label) {
                    $xml->writeElement($key, (string) ($row[$key] ?? ''));
                }

                $xml->endElement();
            }

            $xml->endElement();
            $xml->endDocument();

            echo $xml->outputMemory();
        }, $fileName, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    private function exportReservationHistorySpreadsheet($rows, string $fileName, string $format)
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Historia rezerwacji');
        $sheet->fromArray(array_values(self::RESERVATION_HISTORY_EXPORT_COLUMNS), null, 'A1');

        $rowNumber = 2;
        foreach ($rows as $row) {
            $sheet->fromArray(array_map(
                fn ($key) => $row[$key] ?? '',
                array_keys(self::RESERVATION_HISTORY_EXPORT_COLUMNS)
            ), null, 'A'.$rowNumber);
            $rowNumber++;
        }

        $highestColumn = $sheet->getHighestColumn();
        $sheet->getStyle('A1:'.$highestColumn.'1')->getFont()->setBold(true);
        $sheet->setAutoFilter('A1:'.$highestColumn.max(1, $rowNumber - 1));

        foreach (range('A', $highestColumn) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $writer = $format === 'xlsx'
            ? new Xlsx($spreadsheet)
            : new Xls($spreadsheet);

        $contentType = $format === 'xlsx'
            ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            : 'application/vnd.ms-excel';

        return response()->streamDownload(function () use ($writer): void {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => $contentType,
        ]);
    }

    private function exportReservationHistoryPdf(Request $request, $rows, array $filters, string $fileName)
    {
        $meta = [
            'title' => 'Historia rezerwacji',
            'generated_at' => now()->format('Y-m-d H:i'),
            'generated_by' => trim(($request->user()->name ?? 'Użytkownik').' <'.($request->user()->email ?? '').'>'),
            'filters' => $this->reservationHistoryPdfFilterSummary($filters),
            'logo' => $this->pdfLogoDataUri(),
        ];

        $html = view('reports.pdf.reservation-history', [
            'pages' => $this->reservationHistoryPdfPages($rows),
            'meta' => $meta,
            'totalRows' => $rows->count(),
        ])->render();

        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->setChroot(public_path());

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ]);
    }

    private function reservationHistoryPdfFilterSummary(array $filters): string
    {
        $statusLabels = collect($filters['statuses'])
            ->map(fn (string $status): string => $this->statusOptions()[$status] ?? $status)
            ->implode(', ');

        return implode(' | ', [
            'Okres: '.$filters['date_from'].' - '.$filters['date_to'],
            'Status: '.($statusLabels ?: 'wszystkie'),
            'Strzelnica: '.($filters['shooting_range_id'] ?: 'wszystkie dostępne'),
            'Osie: '.($filters['lane_ids'] === [] ? 'wszystkie' : count($filters['lane_ids']).' wybr.'),
            'Prowadzący: '.$this->pdfFilterOptionLabel($filters['shooting_leader']),
            'Dynamiczne: '.$this->pdfFilterOptionLabel($filters['dynamic_shooting']),
        ]);
    }

    private function pdfFilterOptionLabel(string $value): string
    {
        return match ($value) {
            'yes' => 'tak',
            'no' => 'nie',
            default => 'wszystko',
        };
    }

    private function reservationHistoryPdfPages($rows)
    {
        $rowsPerPage = 5;
        $pages = $rows->chunk($rowsPerPage)->values();

        if ($pages->isEmpty()) {
            $pages = collect([collect()]);
        }

        return $pages->map(function ($pageRows, int $pageIndex) use ($rows, $rowsPerPage): array {
            $start = $rows->isEmpty() ? 0 : ($pageIndex * $rowsPerPage) + 1;
            $end = $rows->isEmpty() ? 0 : min(($pageIndex * $rowsPerPage) + $pageRows->count(), $rows->count());

            return [
                'rows' => $pageRows,
                'range' => "{$start}".($end !== $start ? "-{$end}" : ''),
            ];
        });
    }

    private function pdfLogoDataUri(): ?string
    {
        $path = public_path('img/strzelnice_brand.png');

        if (! is_file($path)) {
            return null;
        }

        if (! function_exists('imagecreatefrompng') || ! function_exists('imagepng')) {
            return 'data:image/png;base64,'.base64_encode((string) file_get_contents($path));
        }

        $source = @imagecreatefrompng($path);

        if (! $source) {
            return 'data:image/png;base64,'.base64_encode((string) file_get_contents($path));
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $targetWidth = 1200;
        $targetHeight = (int) round($targetWidth * $sourceHeight / max(1, $sourceWidth));
        $target = imagecreatetruecolor($targetWidth, $targetHeight);

        imagefill($target, 0, 0, imagecolorallocate($target, 23, 20, 20));
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);

        ob_start();
        imagepng($target, null, 6);
        $data = ob_get_clean();

        imagedestroy($source);
        imagedestroy($target);

        return $data ? 'data:image/png;base64,'.base64_encode($data) : null;
    }
}
