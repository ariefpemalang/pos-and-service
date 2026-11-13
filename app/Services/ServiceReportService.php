<?php

namespace App\Services;

use App\Models\ServiceTicket;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ServiceReportService
{
    public const MONTH_NAMES = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    public const DAY_NAMES = [
        0 => 'Minggu',
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
    ];

    /**
     * Laporan Servis Tahunan (Per Bulan)
     *
     * @return array{year: int, rows: list<array<string, mixed>>, totals: array<string, mixed>}
     */
    public function getYearlyReport(int $year): array
    {
        // 1. Barang Masuk per bulan
        $masukPerMonth = ServiceTicket::query()
            ->whereYear('created_at', $year)
            ->selectRaw('MONTH(created_at) as month, COUNT(*) as count')
            ->groupBy('month')
            ->pluck('count', 'month');

        // 2. Barang Selesai Ditangani per bulan
        $selesaiPerMonth = ServiceTicket::query()
            ->whereIn('status', ['ready', 'completed'])
            ->where(function ($q) use ($year) {
                $q->whereYear('ready_at', $year)
                    ->orWhere(function ($q2) use ($year) {
                        $q2->whereNull('ready_at')->whereYear('completed_at', $year);
                    })
                    ->orWhere(function ($q3) use ($year) {
                        $q3->whereNull('ready_at')->whereNull('completed_at')->whereYear('updated_at', $year);
                    });
            })
            ->selectRaw('MONTH(COALESCE(ready_at, completed_at, updated_at)) as month, COUNT(*) as count')
            ->groupBy('month')
            ->pluck('count', 'month');

        // 3. Barang Keluar per bulan (status completed)
        $keluarPerMonth = ServiceTicket::query()
            ->where('status', 'completed')
            ->where(function ($q) use ($year) {
                $q->whereYear('completed_at', $year)
                    ->orWhere(function ($q2) use ($year) {
                        $q2->whereNull('completed_at')->whereYear('updated_at', $year);
                    });
            })
            ->selectRaw('MONTH(COALESCE(completed_at, updated_at)) as month, COUNT(*) as count')
            ->groupBy('month')
            ->pluck('count', 'month');

        // 4. Transaksi servis completed per bulan
        $transactions = Transaction::query()
            ->with(['items.item'])
            ->where('status', 'completed')
            ->where(function ($q) {
                $q->whereNotNull('service_ticket_id')
                    ->orWhere('type', 'service');
            })
            ->whereYear('created_at', $year)
            ->get();

        $txPerMonth = $transactions->groupBy(fn ($tx) => (int) $tx->created_at->format('n'));

        $rows = [];
        $totalMasuk = 0;
        $totalSelesai = 0;
        $totalKeluar = 0;
        $totalPemasukan = 0;
        $totalModal = 0;
        $totalProfit = 0;

        $currentYear = (int) now()->format('Y');
        $currentMonth = (int) now()->format('n');

        for ($m = 1; $m <= 12; $m++) {
            $monthTx = $txPerMonth->get($m, collect());
            $pemasukan = (float) $monthTx->sum(fn ($tx) => (float) $tx->total);

            $modal = 0;
            foreach ($monthTx as $tx) {
                foreach ($tx->items as $line) {
                    $unitCost = (float) $line->unit_cost > 0
                        ? (float) $line->unit_cost
                        : (float) ($line->item?->purchase_price ?? 0);
                    $modal += $unitCost * (int) $line->quantity;
                }
            }

            $profit = $pemasukan - $modal;
            $masuk = (int) ($masukPerMonth->get($m) ?? 0);
            $selesai = (int) ($selesaiPerMonth->get($m) ?? 0);
            $keluar = (int) ($keluarPerMonth->get($m) ?? 0);

            $isCurrent = ($year === $currentYear && $m === $currentMonth);

            $rows[] = [
                'year' => $year,
                'month_num' => $m,
                'month_name' => self::MONTH_NAMES[$m],
                'masuk' => $masuk,
                'selesai' => $selesai,
                'keluar' => $keluar,
                'pemasukan' => $pemasukan,
                'modal' => $modal,
                'profit' => $profit,
                'is_current' => $isCurrent,
            ];

            $totalMasuk += $masuk;
            $totalSelesai += $selesai;
            $totalKeluar += $keluar;
            $totalPemasukan += $pemasukan;
            $totalModal += $modal;
            $totalProfit += $profit;
        }

        return [
            'year' => $year,
            'rows' => $rows,
            'totals' => [
                'masuk' => $totalMasuk,
                'selesai' => $totalSelesai,
                'keluar' => $totalKeluar,
                'pemasukan' => $totalPemasukan,
                'modal' => $totalModal,
                'profit' => $totalProfit,
            ],
        ];
    }

    /**
     * Laporan Servis Bulanan (Per Hari)
     *
     * @return array{year: int, month: int, month_name: string, rows: list<array<string, mixed>>, totals: array<string, mixed>}
     */
    public function getMonthlyReport(int $year, int $month): array
    {
        $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;

        // 1. Barang Masuk per hari
        $masukPerDay = ServiceTicket::query()
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->selectRaw('DAY(created_at) as day, COUNT(*) as count')
            ->groupBy('day')
            ->pluck('count', 'day');

        // 2. Barang Selesai Ditangani per hari
        $selesaiPerDay = ServiceTicket::query()
            ->whereIn('status', ['ready', 'completed'])
            ->whereYear('ready_at', $year)
            ->whereMonth('ready_at', $month)
            ->selectRaw('DAY(COALESCE(ready_at, completed_at, updated_at)) as day, COUNT(*) as count')
            ->groupBy('day')
            ->pluck('count', 'day');

        // 3. Barang Keluar per hari
        $keluarPerDay = ServiceTicket::query()
            ->where('status', 'completed')
            ->whereYear('completed_at', $year)
            ->whereMonth('completed_at', $month)
            ->selectRaw('DAY(COALESCE(completed_at, updated_at)) as day, COUNT(*) as count')
            ->groupBy('day')
            ->pluck('count', 'day');

        // 4. Transaksi per hari
        $transactions = Transaction::query()
            ->with(['items.item'])
            ->where('status', 'completed')
            ->where(function ($q) {
                $q->whereNotNull('service_ticket_id')
                    ->orWhere('type', 'service');
            })
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->get();

        $txPerDay = $transactions->groupBy(fn ($tx) => (int) $tx->created_at->format('j'));

        $rows = [];
        $totalMasuk = 0;
        $totalSelesai = 0;
        $totalKeluar = 0;
        $totalPemasukan = 0;
        $totalModal = 0;
        $totalProfit = 0;

        $today = now()->format('Y-m-d');

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = Carbon::createFromDate($year, $month, $d);
            $dayOfWeek = (int) $date->format('w'); // 0 = Minggu
            $dayName = self::DAY_NAMES[$dayOfWeek];
            $formattedDate = $d.' '.self::MONTH_NAMES[$month].' '.$year;

            $dayTx = $txPerDay->get($d, collect());
            $pemasukan = (float) $dayTx->sum(fn ($tx) => (float) $tx->total);

            $modal = 0;
            foreach ($dayTx as $tx) {
                foreach ($tx->items as $line) {
                    $unitCost = (float) $line->unit_cost > 0
                        ? (float) $line->unit_cost
                        : (float) ($line->item?->purchase_price ?? 0);
                    $modal += $unitCost * (int) $line->quantity;
                }
            }

            $profit = $pemasukan - $modal;
            $masuk = (int) ($masukPerDay->get($d) ?? 0);
            $selesai = (int) ($selesaiPerDay->get($d) ?? 0);
            $keluar = (int) ($keluarPerDay->get($d) ?? 0);

            $isSunday = ($dayOfWeek === 0);
            $isToday = ($date->format('Y-m-d') === $today);

            $rows[] = [
                'day' => $d,
                'date_raw' => $date->format('Y-m-d'),
                'day_name' => $dayName,
                'date_formatted' => $formattedDate,
                'is_sunday' => $isSunday,
                'is_today' => $isToday,
                'masuk' => $masuk,
                'selesai' => $selesai,
                'keluar' => $keluar,
                'pemasukan' => $pemasukan,
                'modal' => $modal,
                'profit' => $profit,
            ];

            $totalMasuk += $masuk;
            $totalSelesai += $selesai;
            $totalKeluar += $keluar;
            $totalPemasukan += $pemasukan;
            $totalModal += $modal;
            $totalProfit += $profit;
        }

        return [
            'year' => $year,
            'month' => $month,
            'month_name' => self::MONTH_NAMES[$month],
            'rows' => $rows,
            'totals' => [
                'masuk' => $totalMasuk,
                'selesai' => $totalSelesai,
                'keluar' => $totalKeluar,
                'pemasukan' => $totalPemasukan,
                'modal' => $totalModal,
                'profit' => $totalProfit,
            ],
        ];
    }

    /**
     * Export Excel Laporan Servis (Tahunan atau Bulanan)
     */
    public function exportExcel(string $mode, int $year, int $month): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        if ($mode === 'yearly') {
            $data = $this->getYearlyReport($year);
            $sheet->setTitle('Laporan Servis '.$year);

            // Title
            $sheet->setCellValue('A1', 'LAPORAN SERVIS TAHUN '.$year);
            $sheet->mergeCells('A1:H1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Headers
            $headers = ['Tahun', 'Bulan', 'Barang Servis Masuk', 'Barang Servis Selesai Ditangani', 'Barang Servis Keluar', 'Total Pemasukan (Rp)', 'Modal Sparepart (Rp)', 'Profit (Rp)'];
            $sheet->fromArray($headers, null, 'A3');

            $headerRange = 'A3:H3';
            $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0284C7');
            $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $rowIdx = 4;
            foreach ($data['rows'] as $row) {
                $sheet->setCellValue('A'.$rowIdx, $row['year']);
                $sheet->setCellValue('B'.$rowIdx, $row['month_name']);
                $sheet->setCellValue('C'.$rowIdx, $row['masuk'].' Barang');
                $sheet->setCellValue('D'.$rowIdx, $row['selesai'].' Barang');
                $sheet->setCellValue('E'.$rowIdx, $row['keluar'].' Barang');
                $sheet->setCellValue('F'.$rowIdx, $row['pemasukan']);
                $sheet->setCellValue('G'.$rowIdx, $row['modal']);
                $sheet->setCellValue('H'.$rowIdx, $row['profit']);

                $sheet->getStyle('F'.$rowIdx.':H'.$rowIdx)->getNumberFormat()->setFormatCode('#,##0');

                // Color highlights
                $sheet->getStyle('F'.$rowIdx)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDCFCE7');
                $sheet->getStyle('G'.$rowIdx)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF1F5F9');
                $sheet->getStyle('H'.$rowIdx)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDBEAFE');

                if ($row['is_current']) {
                    $sheet->getStyle('A'.$rowIdx.':E'.$rowIdx)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFEF08A');
                }

                $rowIdx++;
            }

            // Totals row
            $sheet->setCellValue('A'.$rowIdx, 'Total');
            $sheet->setCellValue('B'.$rowIdx, 'Laporan Servis Tahun Ini');
            $sheet->setCellValue('C'.$rowIdx, $data['totals']['masuk'].' Barang');
            $sheet->setCellValue('D'.$rowIdx, $data['totals']['selesai'].' Barang');
            $sheet->setCellValue('E'.$rowIdx, $data['totals']['keluar'].' Barang');
            $sheet->setCellValue('F'.$rowIdx, $data['totals']['pemasukan']);
            $sheet->setCellValue('G'.$rowIdx, $data['totals']['modal']);
            $sheet->setCellValue('H'.$rowIdx, $data['totals']['profit']);

            $sheet->getStyle('A'.$rowIdx.':H'.$rowIdx)->getFont()->setBold(true);
            $sheet->getStyle('A'.$rowIdx.':H'.$rowIdx)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFBFDBFE');
            $sheet->getStyle('F'.$rowIdx.':H'.$rowIdx)->getNumberFormat()->setFormatCode('#,##0');

            $filename = 'Laporan-Servis-Tahun-'.$year.'.xlsx';
        } else {
            $data = $this->getMonthlyReport($year, $month);
            $sheet->setTitle('Laporan Servis '.$data['month_name'].' '.$year);

            $sheet->setCellValue('A1', 'LAPORAN SERVIS BULAN '.strtoupper($data['month_name']).' '.$year);
            $sheet->mergeCells('A1:H1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $headers = ['Hari', 'Tanggal', 'Barang Servis Masuk', 'Barang Servis Selesai Ditangani', 'Barang Servis Keluar', 'Total Pemasukan (Rp)', 'Modal Sparepart (Rp)', 'Profit (Rp)'];
            $sheet->fromArray($headers, null, 'A3');

            $headerRange = 'A3:H3';
            $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0284C7');
            $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $rowIdx = 4;
            foreach ($data['rows'] as $row) {
                $sheet->setCellValue('A'.$rowIdx, $row['day_name']);
                $sheet->setCellValue('B'.$rowIdx, $row['date_formatted']);
                $sheet->setCellValue('C'.$rowIdx, $row['masuk'] > 0 ? $row['masuk'].' Barang' : '0');
                $sheet->setCellValue('D'.$rowIdx, $row['selesai'] > 0 ? $row['selesai'].' Barang' : '0');
                $sheet->setCellValue('E'.$rowIdx, $row['keluar'] > 0 ? $row['keluar'].' Barang' : '0');
                $sheet->setCellValue('F'.$rowIdx, $row['pemasukan']);
                $sheet->setCellValue('G'.$rowIdx, $row['modal']);
                $sheet->setCellValue('H'.$rowIdx, $row['profit']);

                $sheet->getStyle('F'.$rowIdx.':H'.$rowIdx)->getNumberFormat()->setFormatCode('#,##0');

                if ($row['is_sunday']) {
                    $sheet->getStyle('A'.$rowIdx.':H'.$rowIdx)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFEE2E2');
                    $sheet->getStyle('A'.$rowIdx)->getFont()->getColor()->setARGB('FFDC2626');
                } else {
                    $sheet->getStyle('F'.$rowIdx)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDCFCE7');
                    $sheet->getStyle('G'.$rowIdx)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF1F5F9');
                    $sheet->getStyle('H'.$rowIdx)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDBEAFE');
                }

                $rowIdx++;
            }

            $sheet->setCellValue('A'.$rowIdx, 'Total');
            $sheet->setCellValue('B'.$rowIdx, 'Laporan Servis Bulan Ini');
            $sheet->setCellValue('C'.$rowIdx, $data['totals']['masuk'].' Barang');
            $sheet->setCellValue('D'.$rowIdx, $data['totals']['selesai'].' Barang');
            $sheet->setCellValue('E'.$rowIdx, $data['totals']['keluar'].' Barang');
            $sheet->setCellValue('F'.$rowIdx, $data['totals']['pemasukan']);
            $sheet->setCellValue('G'.$rowIdx, $data['totals']['modal']);
            $sheet->setCellValue('H'.$rowIdx, $data['totals']['profit']);

            $sheet->getStyle('A'.$rowIdx.':H'.$rowIdx)->getFont()->setBold(true);
            $sheet->getStyle('A'.$rowIdx.':H'.$rowIdx)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFBFDBFE');
            $sheet->getStyle('F'.$rowIdx.':H'.$rowIdx)->getNumberFormat()->setFormatCode('#,##0');

            $filename = 'Laporan-Servis-'.$data['month_name'].'-'.$year.'.xlsx';
        }

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
