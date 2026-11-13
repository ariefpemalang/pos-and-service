<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockOpnameController extends Controller
{
    public function __construct(private StockService $stockService)
    {
        $this->middleware('permission:stock opname view')->only(['index', 'export']);
        $this->middleware('permission:stock opname create')->only('saveDraft');
        $this->middleware('permission:stock opname adjust')->only(['approve', 'reject']);
    }

    public function index(Request $request): View
    {
        $user = auth()->user();
        $isSuperAdmin = $user && ($user->hasRole('Super Admin') || $user->can('stock opname adjust'));

        $categories = ItemCategory::orderBy('name')->get();

        // Cek apakah ada draft pengajuan opname yang masih menunggu persetujuan (pending)
        $pendingOpname = StockOpname::with(['creator', 'items.item.category', 'items.item.unit'])
            ->where('status', 'pending_approval')
            ->latest()
            ->first();

        // Riwayat 5 opname terakhir yang sudah selesai / disetujui
        $recentOpnames = StockOpname::with(['creator', 'approver'])
            ->whereIn('status', ['approved', 'rejected'])
            ->latest()
            ->take(5)
            ->get();

        $query = Item::query()
            ->with(['category:id,name', 'unit:id,name,abbreviation'])
            ->where('is_active', true)
            ->orderBy('name');

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $items = $query->get();

        // Jika ada pending draft, buat peta data fisik yang sudah disimpan di draft
        $draftItemsMap = [];
        if ($pendingOpname) {
            foreach ($pendingOpname->items as $pItem) {
                $draftItemsMap[$pItem->item_id] = [
                    'physical_stock' => (int) $pItem->physical_stock,
                    'notes' => (string) ($pItem->notes ?? ''),
                ];
            }
        }

        // Statistik ringkasan
        $totalItems = $items->count();
        $totalSystemStock = (int) $items->sum('stock');
        $totalAssetCost = (float) $items->sum(fn ($i) => (int) $i->stock * (float) $i->purchase_price);

        return view('stock-opnames.index', compact(
            'items',
            'categories',
            'pendingOpname',
            'draftItemsMap',
            'recentOpnames',
            'isSuperAdmin',
            'totalItems',
            'totalSystemStock',
            'totalAssetCost'
        ));
    }

    /**
     * TAHAP 1 (KASIR / STAFF): Simpan Hitungan Fisik sebagai Draft / Ajukan ke Owner.
     * Tidak mengubah stok sistem live (items.stock).
     */
    public function saveDraft(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'exists:items,id'],
            'items.*.physical_stock' => ['required', 'integer', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
            'general_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $userId = (int) auth()->id();

        $savedOpname = DB::transaction(function () use ($validated, $userId) {
            // Ambil draft pending yang sudah ada atau buat baru
            $opname = StockOpname::where('status', 'pending_approval')->latest()->first();
            if (! $opname) {
                $opname = StockOpname::create([
                    'opname_number' => 'OPN-' . date('Ymd-His'),
                    'status' => 'pending_approval',
                    'created_by' => $userId,
                    'notes' => $validated['general_notes'] ?? null,
                ]);
            } else {
                $opname->update([
                    'created_by' => $userId,
                    'notes' => $validated['general_notes'] ?? $opname->notes,
                ]);
                $opname->items()->delete();
            }

            $totalLossUnits = 0;
            $totalLossCost = 0.0;
            $totalGainUnits = 0;
            $totalGainCost = 0.0;
            $itemCount = 0;

            foreach ($validated['items'] as $entry) {
                $item = Item::find($entry['id']);
                if (! $item) {
                    continue;
                }

                $sysStock = (int) $item->stock;
                $physStock = (int) $entry['physical_stock'];
                $diff = $physStock - $sysStock;
                $cost = (float) $item->purchase_price;
                $diffCost = $diff * $cost;

                if ($diff < 0) {
                    $totalLossUnits += abs($diff);
                    $totalLossCost += abs($diffCost);
                } elseif ($diff > 0) {
                    $totalGainUnits += $diff;
                    $totalGainCost += $diffCost;
                }

                StockOpnameItem::create([
                    'stock_opname_id' => $opname->id,
                    'item_id' => $item->id,
                    'system_stock' => $sysStock,
                    'physical_stock' => $physStock,
                    'difference' => $diff,
                    'purchase_price' => $cost,
                    'difference_cost' => $diffCost,
                    'notes' => trim((string) ($entry['notes'] ?? '')) ?: null,
                ]);

                $itemCount++;
            }

            $opname->update([
                'total_items' => $itemCount,
                'total_loss_units' => $totalLossUnits,
                'total_loss_cost' => $totalLossCost,
                'total_gain_units' => $totalGainUnits,
                'total_gain_cost' => $totalGainCost,
            ]);

            return $opname;
        });

        return response()->json([
            'success' => true,
            'message' => 'Hitungan fisik berhasil disimpan sebagai Draft dan diajukan ke Owner untuk ditinjau.',
            'opname_id' => $savedOpname->id,
            'data' => [
                'loss_units' => $savedOpname->total_loss_units,
                'loss_cost' => $savedOpname->total_loss_cost,
                'gain_units' => $savedOpname->total_gain_units,
                'gain_cost' => $savedOpname->total_gain_cost,
            ],
        ]);
    }

    /**
     * TAHAP 2 (KHUSUS SUPER ADMIN / OWNER): Menyetujui & Menerapkan Penyesuaian Stok.
     * Hanya Super Admin yang berhak mengeksekusi ini.
     */
    public function approve(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (! $user || (! $user->hasRole('Super Admin') && ! $user->can('stock opname adjust'))) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak! Anda tidak memiliki izin untuk menyetujui penyesuaian stok barang.',
            ], 403);
        }

        $validated = $request->validate([
            'stock_opname_id' => ['nullable', 'exists:stock_opnames,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'exists:items,id'],
            'items.*.physical_stock' => ['required', 'integer', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        $userId = (int) $user->id;
        $adjustments = [];
        $lossCount = 0;
        $lossUnits = 0;
        $lossAmount = 0.0;
        $gainCount = 0;
        $gainUnits = 0;
        $gainAmount = 0.0;

        DB::transaction(function () use (
            $validated,
            $userId,
            &$adjustments,
            &$lossCount,
            &$lossUnits,
            &$lossAmount,
            &$gainCount,
            &$gainUnits,
            &$gainAmount
        ) {
            $opname = null;
            if (! empty($validated['stock_opname_id'])) {
                $opname = StockOpname::find($validated['stock_opname_id']);
            }

            $refNo = $opname ? $opname->opname_number : ('OPN-' . date('Ymd-His'));

            foreach ($validated['items'] as $entry) {
                $item = Item::lockForUpdate()->find($entry['id']);
                if (! $item) {
                    continue;
                }

                $systemStock = (int) $item->stock;
                $physicalStock = (int) $entry['physical_stock'];
                $diff = $physicalStock - $systemStock;

                if ($diff === 0) {
                    $item->update(['stock_opname' => $physicalStock]);
                    continue;
                }

                $userNotes = trim((string) ($entry['notes'] ?? ''));

                if ($diff < 0) {
                    // Selisih Negatif = BARANG HILANG / BOCOR / RUSAK
                    $lossQty = abs($diff);
                    $defaultNote = "Stock Opname: Selisih fisik -{$lossQty} (Barang Hilang / Rusak)";
                    $note = $userNotes !== '' ? "Stock Opname: {$userNotes}" : $defaultNote;

                    $this->stockService->stockOut(
                        $item->id,
                        $lossQty,
                        $userId,
                        $refNo,
                        $note
                    );

                    $lossCount++;
                    $lossUnits += $lossQty;
                    $lossAmount += ($lossQty * (float) $item->purchase_price);
                } else {
                    // Selisih Positif = KELEBIHAN BARANG FISIK
                    $gainQty = $diff;
                    $defaultNote = "Stock Opname: Selisih fisik +{$gainQty} (Kelebihan / Temuan Fisik)";
                    $note = $userNotes !== '' ? "Stock Opname: {$userNotes}" : $defaultNote;

                    $this->stockService->stockIn(
                        $item->id,
                        $gainQty,
                        $userId,
                        $refNo,
                        $note
                    );

                    $gainCount++;
                    $gainUnits += $gainQty;
                    $gainAmount += ($gainQty * (float) $item->purchase_price);
                }

                $item->update(['stock_opname' => $physicalStock]);

                $adjustments[] = [
                    'id' => $item->id,
                    'code' => $item->code,
                    'name' => $item->name,
                    'diff' => $diff,
                ];
            }

            if ($opname) {
                $opname->update([
                    'status' => 'approved',
                    'approved_by' => $userId,
                    'approved_at' => now(),
                    'total_loss_units' => $lossUnits,
                    'total_loss_cost' => $lossAmount,
                    'total_gain_units' => $gainUnits,
                    'total_gain_cost' => $gainAmount,
                ]);
            } else {
                StockOpname::create([
                    'opname_number' => $refNo,
                    'status' => 'approved',
                    'created_by' => $userId,
                    'approved_by' => $userId,
                    'approved_at' => now(),
                    'total_items' => count($validated['items']),
                    'total_loss_units' => $lossUnits,
                    'total_loss_cost' => $lossAmount,
                    'total_gain_units' => $gainUnits,
                    'total_gain_cost' => $gainAmount,
                    'notes' => 'Stock opname disetujui langsung oleh Owner.',
                ]);
            }
        });

        $message = 'Penyesuaian stok berhasil disetujui dan diterapkan ke sistem.';
        if ($lossCount > 0) {
            $message .= " Tercatat {$lossCount} barang hilang (-{$lossUnits} unit, kerugian Rp " . number_format($lossAmount, 0, ',', '.') . ').';
        }
        if ($gainCount > 0) {
            $message .= " Tercatat {$gainCount} barang lebih (+{$gainUnits} unit).";
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'adjusted_items' => count($adjustments),
                'loss_count' => $lossCount,
                'loss_units' => $lossUnits,
                'loss_amount' => $lossAmount,
                'gain_count' => $gainCount,
                'gain_units' => $gainUnits,
                'gain_amount' => $gainAmount,
            ],
        ]);
    }

    /**
     * TAHAP 2B (KHUSUS SUPER ADMIN / OWNER): Menolak Draft Opname (minta hitung ulang).
     */
    public function reject(StockOpname $stockOpname): JsonResponse
    {
        $user = auth()->user();
        if (! $user || (! $user->hasRole('Super Admin') && ! $user->can('stock opname adjust'))) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak! Anda tidak memiliki izin untuk menolak pengajuan opname.',
            ], 403);
        }

        if ($stockOpname->status !== 'pending_approval') {
            return response()->json([
                'success' => false,
                'message' => 'Status opname ini tidak dalam posisi menunggu persetujuan.',
            ], 422);
        }

        $stockOpname->update([
            'status' => 'rejected',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Draft pengajuan Stock Opname telah ditolak. Kasir dapat melakukan penghitungan ulang.',
        ]);
    }

    /**
     * Cetak / Unduh Lembar Kerja Hitung Fisik dalam Format Excel (.xlsx).
     */
    public function export(Request $request): StreamedResponse
    {
        $user = auth()->user();
        $isSuperAdmin = $user && $user->hasRole('Super Admin');

        $query = Item::query()
            ->with(['category:id,name', 'unit:id,name,abbreviation'])
            ->where('is_active', true)
            ->orderBy('name');

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $items = $query->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Lembar Opname');

        // Header Dokumen
        $sheet->setCellValue('A1', 'LEMBAR PENGHITUNGAN STOK FISIK (STOCK OPNAME)');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $sheet->setCellValue('A2', 'Tanggal Cetak: ' . now()->translatedFormat('d F Y H:i') . ' | Petugas: ' . ($user->name ?? '-'));
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(10)->getColor()->setRGB('555555');

        // Header Kolom Tabel
        if ($isSuperAdmin) {
            $headers = ['No', 'Kode Barang', 'Nama Barang', 'Kategori', 'Satuan', 'HPP Modal (Rp)', 'Stok Sistem', 'Stok Fisik Nyata (Isi)', 'Catatan Fisik'];
            $lastCol = 'I';
        } else {
            // Blind Audit Kasir: Tanpa HPP dan Tanpa Stok Sistem
            $headers = ['No', 'Kode Barang', 'Nama Barang', 'Kategori', 'Satuan', 'Stok Fisik Nyata (Isi Manual)', 'Catatan Kondisi Fisik'];
            $lastCol = 'G';
        }

        $sheet->fromArray($headers, null, 'A4');

        // Styling Header Tabel
        $headerRange = "A4:{$lastCol}4";
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E293B'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension(4)->setRowHeight(26);

        $row = 5;
        foreach ($items as $idx => $item) {
            $unitName = $item->unit ? ($item->unit->abbreviation ?: $item->unit->name) : 'pcs';

            if ($isSuperAdmin) {
                $rowData = [
                    $idx + 1,
                    $item->code,
                    $item->name,
                    $item->category?->name ?? 'Tanpa Kategori',
                    $unitName,
                    (float) $item->purchase_price,
                    (int) $item->stock,
                    '', // Kolom kosong untuk diisi
                    '',
                ];
            } else {
                $rowData = [
                    $idx + 1,
                    $item->code,
                    $item->name,
                    $item->category?->name ?? 'Tanpa Kategori',
                    $unitName,
                    '', // Kolom kosong untuk diisi kasir
                    '',
                ];
            }

            $sheet->fromArray($rowData, null, "A{$row}");

            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            if ($isSuperAdmin) {
                $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle("G{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }

            // Highlight kolom input fisik dengan warna lembut agar kasir/staf mudah mengisi
            $physCol = $isSuperAdmin ? 'H' : 'F';
            $sheet->getStyle("{$physCol}{$row}")->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'FEFCE8'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ],
            ]);

            $row++;
        }

        $lastDataRow = max(5, $row - 1);

        // Border seluruh tabel
        $tableRange = "A4:{$lastCol}{$lastDataRow}";
        $sheet->getStyle($tableRange)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CCCCCC'],
                ],
            ],
        ]);

        // Auto-fit kolom
        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'lembar-stock-opname-' . now()->format('Ymd-His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
