<?php

namespace App\Services;

use App\Models\Technician;
use App\Models\Transaction;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TechnicianReportService
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
     * Laporan Teknisi Bulanan (Per Hari)
     */
    public function getMonthlyReport(int $year, int $month): array
    {
        $technicians = Technician::query()
            ->orderBy('name')
            ->get();

        $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;

        // Ambil semua transaksi servis completed pada bulan tsb
        $transactions = Transaction::query()
            ->with(['items.item', 'technician'])
            ->where('status', 'completed')
            ->whereNotNull('technician_id')
            ->where(function ($q) {
                $q->whereNotNull('service_ticket_id')
                    ->orWhere('type', 'service');
            })
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->get();

        $rows = [];
        $today = now()->format('Y-m-d');

        // Struktur akumulasi total per teknisi
        $techTotals = [];
        foreach ($technicians as $tech) {
            $techTotals[$tech->id] = [
                'kali' => 0,
                'pemasukan' => 0,
                'modal' => 0,
                'profit' => 0,
            ];
        }

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = Carbon::createFromDate($year, $month, $d);
            $dayOfWeek = (int) $date->format('w');
            $dayName = self::DAY_NAMES[$dayOfWeek];
            $formattedDate = $d.' '.self::MONTH_NAMES[$month].' '.$year;
            $isSunday = ($dayOfWeek === 0);

            // Filter transaksi hari ini
            $dayTx = $transactions->filter(fn ($tx) => (int) $tx->created_at->format('j') === $d);

            $dayTechData = [];
            foreach ($technicians as $tech) {
                $techDayTx = $dayTx->where('technician_id', $tech->id);
                $count = $techDayTx->count();
                $pemasukan = (float) $techDayTx->sum(fn ($tx) => (float) $tx->total);

                $modal = 0;
                foreach ($techDayTx as $tx) {
                    foreach ($tx->items as $line) {
                        $unitCost = (float) $line->unit_cost > 0
                            ? (float) $line->unit_cost
                            : (float) ($line->item?->purchase_price ?? 0);
                        $modal += $unitCost * (int) $line->quantity;
                    }
                }

                $profit = $pemasukan - $modal;

                $dayTechData[$tech->id] = [
                    'count' => $count,
                    'pemasukan' => $pemasukan,
                    'modal' => $modal,
                    'profit' => $profit,
                ];

                $techTotals[$tech->id]['kali'] += $count;
                $techTotals[$tech->id]['pemasukan'] += $pemasukan;
                $techTotals[$tech->id]['modal'] += $modal;
                $techTotals[$tech->id]['profit'] += $profit;
            }

            $rows[] = [
                'day' => $d,
                'date_raw' => $date->format('Y-m-d'),
                'day_name' => $dayName,
                'date_formatted' => $formattedDate,
                'is_sunday' => $isSunday,
                'is_today' => ($date->format('Y-m-d') === $today),
                'tech_data' => $dayTechData,
            ];
        }

        return [
            'year' => $year,
            'month' => $month,
            'month_name' => self::MONTH_NAMES[$month],
            'technicians' => $technicians,
            'rows' => $rows,
            'totals' => $techTotals,
        ];
    }

    /**
     * Laporan Teknisi Tahunan (Per Bulan)
     */
    public function getYearlyReport(int $year): array
    {
        $technicians = Technician::query()
            ->orderBy('name')
            ->get();

        $transactions = Transaction::query()
            ->with(['items.item', 'technician'])
            ->where('status', 'completed')
            ->whereNotNull('technician_id')
            ->where(function ($q) {
                $q->whereNotNull('service_ticket_id')
                    ->orWhere('type', 'service');
            })
            ->whereYear('created_at', $year)
            ->get();

        $rows = [];
        $currentYear = (int) now()->format('Y');
        $currentMonth = (int) now()->format('n');

        $techTotals = [];
        foreach ($technicians as $tech) {
            $techTotals[$tech->id] = [
                'kali' => 0,
                'pemasukan' => 0,
                'modal' => 0,
                'profit' => 0,
            ];
        }

        for ($m = 1; $m <= 12; $m++) {
            $monthTx = $transactions->filter(fn ($tx) => (int) $tx->created_at->format('n') === $m);
            $monthTechData = [];

            foreach ($technicians as $tech) {
                $techMonthTx = $monthTx->where('technician_id', $tech->id);
                $count = $techMonthTx->count();
                $pemasukan = (float) $techMonthTx->sum(fn ($tx) => (float) $tx->total);

                $modal = 0;
                foreach ($techMonthTx as $tx) {
                    foreach ($tx->items as $line) {
                        $unitCost = (float) $line->unit_cost > 0
                            ? (float) $line->unit_cost
                            : (float) ($line->item?->purchase_price ?? 0);
                        $modal += $unitCost * (int) $line->quantity;
                    }
                }

                $profit = $pemasukan - $modal;

                $monthTechData[$tech->id] = [
                    'count' => $count,
                    'pemasukan' => $pemasukan,
                    'modal' => $modal,
                    'profit' => $profit,
                ];

                $techTotals[$tech->id]['kali'] += $count;
                $techTotals[$tech->id]['pemasukan'] += $pemasukan;
                $techTotals[$tech->id]['modal'] += $modal;
                $techTotals[$tech->id]['profit'] += $profit;
            }

            $rows[] = [
                'year' => $year,
                'month_num' => $m,
                'month_name' => self::MONTH_NAMES[$m],
                'is_current' => ($year === $currentYear && $m === $currentMonth),
                'tech_data' => $monthTechData,
            ];
        }

        return [
            'year' => $year,
            'technicians' => $technicians,
            'rows' => $rows,
            'totals' => $techTotals,
        ];
    }

    /**
     * Export Excel Laporan Teknisi
     */
    public function exportExcel(string $mode, int $year, int $month): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        if ($mode === 'yearly') {
            $data = $this->getYearlyReport($year);
            $sheet->setTitle('Laporan Teknisi '.$year);

            $sheet->setCellValue('A1', 'LAPORAN TEKNISI TAHUN '.$year);
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

            // Headers
            $headers = ['Tahun', 'Bulan'];
            foreach ($data['technicians'] as $tech) {
                $headers[] = $tech->name;
            }
            $sheet->fromArray($headers, null, 'A3');

            $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
            $headerRange = 'A3:'.$lastColLetter.'3';
            $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF64748B');

            $rowIdx = 4;
            foreach ($data['rows'] as $row) {
                $sheet->setCellValue('A'.$rowIdx, $row['year']);
                $sheet->setCellValue('B'.$rowIdx, $row['month_name']);

                $colIdx = 3;
                foreach ($data['technicians'] as $tech) {
                    $count = $row['tech_data'][$tech->id]['count'] ?? 0;
                    $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx).$rowIdx;
                    $sheet->setCellValue($cell, $count > 0 ? $count.' Kali' : '0');
                    $colIdx++;
                }

                if ($row['is_current']) {
                    $sheet->getStyle('A'.$rowIdx.':'.$lastColLetter.$rowIdx)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFEF08A');
                }

                $rowIdx++;
            }

            // Summary row 1: Menangani Servis
            $sheet->setCellValue('A'.$rowIdx, 'Total');
            $sheet->setCellValue('B'.$rowIdx, 'Menangani Servis Tahun Ini');
            $colIdx = 3;
            foreach ($data['technicians'] as $tech) {
                $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx).$rowIdx;
                $sheet->setCellValue($cell, ($data['totals'][$tech->id]['kali'] ?? 0).' Kali');
                $colIdx++;
            }
            $sheet->getStyle('A'.$rowIdx.':'.$lastColLetter.$rowIdx)->getFont()->setBold(true);
            $sheet->getStyle('A'.$rowIdx.':'.$lastColLetter.$rowIdx)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFBFDBFE');
            $rowIdx++;

            // Summary row 2: Pemasukan
            $sheet->setCellValue('A'.$rowIdx, 'Total');
            $sheet->setCellValue('B'.$rowIdx, 'Pemasukan Oleh Teknisi Tahun Ini');
            $colIdx = 3;
            foreach ($data['technicians'] as $tech) {
                $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx).$rowIdx;
                $sheet->setCellValue($cell, $data['totals'][$tech->id]['pemasukan'] ?? 0);
                $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0');
                $colIdx++;
            }
            $sheet->getStyle('A'.$rowIdx.':'.$lastColLetter.$rowIdx)->getFont()->setBold(true);
            $sheet->getStyle('A'.$rowIdx.':'.$lastColLetter.$rowIdx)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFBFDBFE');
            $rowIdx++;

            // Summary row 3: Profit
            $sheet->setCellValue('A'.$rowIdx, 'Total');
            $sheet->setCellValue('B'.$rowIdx, 'Profit Oleh Teknisi Tahun Ini');
            $colIdx = 3;
            foreach ($data['technicians'] as $tech) {
                $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx).$rowIdx;
                $sheet->setCellValue($cell, $data['totals'][$tech->id]['profit'] ?? 0);
                $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0');
                $colIdx++;
            }
            $sheet->getStyle('A'.$rowIdx.':'.$lastColLetter.$rowIdx)->getFont()->setBold(true);
            $sheet->getStyle('A'.$rowIdx.':'.$lastColLetter.$rowIdx)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDCFCE7');

            $filename = 'Laporan-Teknisi-Tahun-'.$year.'.xlsx';
        } else {
            $data = $this->getMonthlyReport($year, $month);
            $sheet->setTitle('Laporan Teknisi '.$data['month_name'].' '.$year);

            $sheet->setCellValue('A1', 'LAPORAN TEKNISI BULAN '.strtoupper($data['month_name']).' '.$year);
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

            $headers = ['Hari', 'Tanggal'];
            foreach ($data['technicians'] as $tech) {
                $headers[] = $tech->name;
            }
            $sheet->fromArray($headers, null, 'A3');

            $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
            $headerRange = 'A3:'.$lastColLetter.'3';
            $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF64748B');

            $rowIdx = 4;
            foreach ($data['rows'] as $row) {
                $sheet->setCellValue('A'.$rowIdx, $row['day_name']);
                $sheet->setCellValue('B'.$rowIdx, $row['date_formatted']);

                $colIdx = 3;
                foreach ($data['technicians'] as $tech) {
                    $count = $row['tech_data'][$tech->id]['count'] ?? 0;
                    $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx).$rowIdx;
                    $sheet->setCellValue($cell, $count > 0 ? $count.' Kali' : '0');
                    $colIdx++;
                }

                if ($row['is_sunday']) {
                    $sheet->getStyle('A'.$rowIdx.':'.$lastColLetter.$rowIdx)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFEE2E2');
                    $sheet->getStyle('A'.$rowIdx)->getFont()->getColor()->setARGB('FFDC2626');
                }

                $rowIdx++;
            }

            // Summary row 1: Menangani Servis
            $sheet->setCellValue('A'.$rowIdx, 'Total');
            $sheet->setCellValue('B'.$rowIdx, 'Menangani Servis Bulan Ini');
            $colIdx = 3;
            foreach ($data['technicians'] as $tech) {
                $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx).$rowIdx;
                $sheet->setCellValue($cell, ($data['totals'][$tech->id]['kali'] ?? 0).' Kali');
                $colIdx++;
            }
            $sheet->getStyle('A'.$rowIdx.':'.$lastColLetter.$rowIdx)->getFont()->setBold(true);
            $sheet->getStyle('A'.$rowIdx.':'.$lastColLetter.$rowIdx)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFBFDBFE');
            $rowIdx++;

            // Summary row 2: Pemasukan
            $sheet->setCellValue('A'.$rowIdx, 'Total');
            $sheet->setCellValue('B'.$rowIdx, 'Pemasukan Oleh Teknisi Bulan Ini');
            $colIdx = 3;
            foreach ($data['technicians'] as $tech) {
                $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx).$rowIdx;
                $sheet->setCellValue($cell, $data['totals'][$tech->id]['pemasukan'] ?? 0);
                $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0');
                $colIdx++;
            }
            $sheet->getStyle('A'.$rowIdx.':'.$lastColLetter.$rowIdx)->getFont()->setBold(true);
            $sheet->getStyle('A'.$rowIdx.':'.$lastColLetter.$rowIdx)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFBFDBFE');
            $rowIdx++;

            // Summary row 3: Profit
            $sheet->setCellValue('A'.$rowIdx, 'Total');
            $sheet->setCellValue('B'.$rowIdx, 'Profit Oleh Teknisi Bulan Ini');
            $colIdx = 3;
            foreach ($data['technicians'] as $tech) {
                $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx).$rowIdx;
                $sheet->setCellValue($cell, $data['totals'][$tech->id]['profit'] ?? 0);
                $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0');
                $colIdx++;
            }
            $sheet->getStyle('A'.$rowIdx.':'.$lastColLetter.$rowIdx)->getFont()->setBold(true);
            $sheet->getStyle('A'.$rowIdx.':'.$lastColLetter.$rowIdx)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDCFCE7');

            $filename = 'Laporan-Teknisi-'.$data['month_name'].'-'.$year.'.xlsx';
        }

        foreach (range(1, count($headers)) as $colNum) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNum);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
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
