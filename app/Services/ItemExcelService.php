<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemUnit;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ItemExcelService
{
    private const IMPORT_SHEET = 'Import Barang';

    private const REF_CATEGORY_SHEET = 'Referensi Kategori';

    private const REF_UNIT_SHEET = 'Referensi Satuan';

    private const GUIDE_SHEET = 'Panduan';

    private const IMPORT_MAX_ROWS = 500;

    /** @var array<int, string> */
    private const IMPORT_HEADERS = [
        'Nama Barang *',
        'Kategori *',
        'Satuan *',
        'Stock Opname',
        'Harga Beli',
        'Harga Jual',
        'Deskripsi',
        'Aktif (Ya/Tidak)',
    ];

    public function downloadTemplate(): StreamedResponse
    {
        $spreadsheet = $this->buildTemplateSpreadsheet();

        return $this->streamSpreadsheet(
            $spreadsheet,
            'format-import-barang-'.now()->format('Ymd-His').'.xlsx'
        );
    }

    public function export(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data Barang');

        $headers = [
            'Kode', 'Nama Barang', 'Kategori', 'Satuan', 'Stok',
            'Stock Opname', 'Harga Beli', 'Harga Jual', 'Deskripsi', 'Aktif',
        ];

        $sheet->fromArray($headers, null, 'A1');
        $this->styleHeaderRow($sheet, 'A1:J1');

        $items = Item::query()
            ->with(['category:id,name', 'unit:id,name,abbreviation'])
            ->orderBy('name')
            ->get();

        $row = 2;
        foreach ($items as $item) {
            $sheet->fromArray([
                $item->code,
                $item->name,
                $item->category?->name ?? '',
                $this->unitLabel($item->unit),
                (int) $item->stock,
                (int) $item->stock_opname,
                (float) $item->purchase_price,
                (float) $item->selling_price,
                $item->description ?? '',
                $item->is_active ? 'Ya' : 'Tidak',
            ], null, "A{$row}");
            $row++;
        }

        $this->autoSizeColumns($sheet, 'A', 'J');

        return $this->streamSpreadsheet(
            $spreadsheet,
            'export-barang-'.now()->format('Ymd-His').'.xlsx'
        );
    }

    /**
     * @return array{created: int, skipped: int, errors: array<int, string>}
     */
    public function import(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getSheetByName(self::IMPORT_SHEET)
            ?? $spreadsheet->getActiveSheet();

        return $this->processImportRows($sheet, [
            'create_missing_masters' => true,
            'max_rows' => self::IMPORT_MAX_ROWS,
            'column_d_as_available_stock' => true,
        ]);
    }

    /**
     * @return array{created: int, skipped: int, errors: array<int, string>}
     */
    public function seedFromPath(string $path): array
    {
        if (! is_file($path)) {
            return [
                'created' => 0,
                'skipped' => 0,
                'errors' => ["File tidak ditemukan: {$path}"],
            ];
        }

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheetByName(self::IMPORT_SHEET)
            ?? $spreadsheet->getActiveSheet();

        return $this->processImportRows($sheet, [
            'create_missing_masters' => true,
            'max_rows' => null,
            'default_unit_label' => 'Pieces (pcs)',
            // Import seeder: kolom D = stok tersedia, bukan stock opname.
            'column_d_as_available_stock' => true,
        ]);
    }

    /**
     * @param  array{
     *     create_missing_masters: bool,
     *     max_rows?: ?int,
     *     default_unit_label?: string,
     *     column_d_as_available_stock?: bool
     * }  $options
     * @return array{created: int, skipped: int, errors: array<int, string>}
     */
    private function processImportRows(Worksheet $sheet, array $options): array
    {
        $createMissingMasters = $options['create_missing_masters'];
        $defaultUnitLabel = $options['default_unit_label'] ?? null;
        $columnDAsAvailableStock = (bool) ($options['column_d_as_available_stock'] ?? false);
        $stockService = $columnDAsAvailableStock ? app(StockService::class) : null;
        $seedUserId = $columnDAsAvailableStock
            ? (int) (User::query()->value('id') ?? 0)
            : 0;

        $categories = ItemCategory::query()->orderBy('name')->get()->keyBy(
            fn (ItemCategory $c) => mb_strtolower(trim($c->name))
        );

        $units = $this->buildUnitLookup(ItemUnit::query()->orderBy('name')->get());

        $created = 0;
        $skipped = 0;
        $errors = [];
        $highestRow = (int) $sheet->getHighestDataRow();
        $rowLimit = $options['max_rows'] ?? null;

        if ($rowLimit !== null) {
            $highestRow = min($highestRow, $rowLimit + 1);
        }

        // Deteksi mapping kolom secara cerdas berdasarkan teks header di baris 1
        $highestColumn = $sheet->getHighestDataColumn();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

        $headerMap = [
            'name' => 'A',
            'category' => 'B',
            'unit' => 'C',
            'stock_opname' => 'D',
            'purchase_price' => 'E',
            'selling_price' => 'F',
            'description' => 'G',
            'is_active' => 'H',
            'code' => null,
            'stock' => null,
        ];

        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            $headerVal = mb_strtolower(trim((string) $sheet->getCell("{$colLetter}1")->getValue()));
            if (str_contains($headerVal, 'nama')) {
                $headerMap['name'] = $colLetter;
            } elseif (str_contains($headerVal, 'kategori')) {
                $headerMap['category'] = $colLetter;
            } elseif (str_contains($headerVal, 'satuan')) {
                $headerMap['unit'] = $colLetter;
            } elseif (str_contains($headerVal, 'kode')) {
                $headerMap['code'] = $colLetter;
            } elseif (str_contains($headerVal, 'opname') || str_contains($headerVal, 'minimum')) {
                $headerMap['stock_opname'] = $colLetter;
            } elseif ($headerVal === 'stok' || (str_contains($headerVal, 'stok') && !str_contains($headerVal, 'opname'))) {
                $headerMap['stock'] = $colLetter;
            } elseif (str_contains($headerVal, 'beli')) {
                $headerMap['purchase_price'] = $colLetter;
            } elseif (str_contains($headerVal, 'jual')) {
                $headerMap['selling_price'] = $colLetter;
            } elseif (str_contains($headerVal, 'deskripsi')) {
                $headerMap['description'] = $colLetter;
            } elseif (str_contains($headerVal, 'aktif')) {
                $headerMap['is_active'] = $colLetter;
            }
        }

        for ($row = 2; $row <= $highestRow; $row++) {
            $colName = $headerMap['name'];
            $colCat = $headerMap['category'];
            $colUnit = $headerMap['unit'];
            $colCode = $headerMap['code'];
            $colOpname = $headerMap['stock_opname'];
            $colStock = $headerMap['stock'] ?? $colOpname;
            $colBuy = $headerMap['purchase_price'];
            $colSell = $headerMap['selling_price'];
            $colDesc = $headerMap['description'];
            $colActive = $headerMap['is_active'];

            $name = trim((string) $sheet->getCell("{$colName}{$row}")->getValue());
            $categoryName = trim((string) $sheet->getCell("{$colCat}{$row}")->getValue());
            $unitLabel = trim((string) $sheet->getCell("{$colUnit}{$row}")->getValue());

            if ($name === '' && $categoryName === '' && $unitLabel === '') {
                continue;
            }

            if ($name === '') {
                $errors[] = "Baris {$row}: Nama barang wajib diisi.";
                $skipped++;

                continue;
            }

            if ($categoryName === '') {
                $errors[] = "Baris {$row}: Kategori wajib diisi.";
                $skipped++;

                continue;
            }

            if ($unitLabel === '' && $defaultUnitLabel) {
                $unitLabel = $defaultUnitLabel;
            }

            $categoryKey = mb_strtolower($categoryName);
            $category = $categories->get($categoryKey);

            if (! $category && $createMissingMasters) {
                $category = ItemCategory::create(['name' => $categoryName]);
                $categories->put($categoryKey, $category);
            }

            if (! $category) {
                $errors[] = "Baris {$row}: Kategori \"{$categoryName}\" tidak ditemukan.";
                $skipped++;

                continue;
            }

            $unit = $units->get(mb_strtolower($unitLabel))
                ?? $units->get(mb_strtolower($this->normalizeUnitName($unitLabel)));

            if (! $unit && $createMissingMasters && $unitLabel !== '') {
                $unit = $this->createUnitFromLabel($unitLabel);
                $units->put(mb_strtolower($this->unitLabel($unit)), $unit);
                $units->put(mb_strtolower($unit->name), $unit);

                if ($unit->abbreviation) {
                    $units->put(mb_strtolower($unit->abbreviation), $unit);
                }
            }

            if (! $unit) {
                $errors[] = "Baris {$row}: Satuan \"{$unitLabel}\" tidak ditemukan.";
                $skipped++;

                continue;
            }

            $rawStockVal = max(0, (int) $sheet->getCell("{$colStock}{$row}")->getValue());
            $rawOpnameVal = max(0, (int) $sheet->getCell("{$colOpname}{$row}")->getValue());
            $rawBuyPrice = max(0, (float) $sheet->getCell("{$colBuy}{$row}")->getValue());
            $rawSellPrice = max(0, (float) $sheet->getCell("{$colSell}{$row}")->getValue());
            $rawDesc = trim((string) $sheet->getCell("{$colDesc}{$row}")->getValue()) ?: null;
            $rawActive = $this->parseYesNo($sheet->getCell("{$colActive}{$row}")->getValue(), true);

            $customCode = $colCode ? trim((string) $sheet->getCell("{$colCode}{$row}")->getValue()) : null;
            if ($customCode && Item::where('code', $customCode)->where('name', '!=', $name)->exists()) {
                $customCode = null;
            }

            if (Item::withTrashed()->where('name', $name)->exists()) {
                $existingItem = Item::withTrashed()->where('name', $name)->first();
                if ($existingItem) {
                    $newStock = $rawStockVal > 0 ? $rawStockVal : $existingItem->stock;
                    $existingItem->update([
                        'category_id' => $category->id,
                        'unit_id' => $unit->id,
                        'stock' => $newStock,
                        'stock_opname' => $rawOpnameVal > 0 ? $rawOpnameVal : ($columnDAsAvailableStock ? 0 : $rawStockVal),
                        'purchase_price' => $rawBuyPrice ?: $existingItem->purchase_price,
                        'selling_price' => $rawSellPrice ?: $existingItem->selling_price,
                        'is_active' => true,
                        'deleted_at' => null,
                    ]);
                    $created++;
                    continue;
                }
            }

            $item = Item::create([
                'code' => $customCode ?: Item::generateCode(),
                'name' => $name,
                'category_id' => $category->id,
                'unit_id' => $unit->id,
                'stock' => $columnDAsAvailableStock ? $rawStockVal : 0,
                'stock_opname' => $rawOpnameVal,
                'purchase_price' => $rawBuyPrice,
                'selling_price' => $rawSellPrice,
                'description' => $rawDesc,
                'is_active' => $rawActive,
            ]);

            if ($columnDAsAvailableStock && $columnDValue > 0 && $stockService && $seedUserId > 0) {
                $stockService->stockIn(
                    $item->id,
                    $columnDValue,
                    $seedUserId,
                    null,
                    'Stok awal dari seeder Excel'
                );
            }

            $created++;
        }

        return compact('created', 'skipped', 'errors');
    }

    private function createUnitFromLabel(string $label): ItemUnit
    {
        $name = $this->normalizeUnitName($label);
        $abbreviation = null;

        if (preg_match('/\(([^)]+)\)/u', $label, $matches)) {
            $abbreviation = trim($matches[1]);
        }

        return ItemUnit::firstOrCreate(
            ['name' => $name],
            ['abbreviation' => $abbreviation ?: mb_strtolower(mb_substr($name, 0, 3))]
        );
    }

    private function buildTemplateSpreadsheet(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;

        $categories = ItemCategory::query()->orderBy('name')->pluck('name')->all();
        $units = ItemUnit::query()->orderBy('name')->get()
            ->map(fn (ItemUnit $u) => $this->unitLabel($u))->all();

        $guideSheet = $spreadsheet->getActiveSheet();
        $guideSheet->setTitle(self::GUIDE_SHEET);
        $this->buildGuideSheet($guideSheet);

        $categorySheet = $spreadsheet->createSheet();
        $categorySheet->setTitle(self::REF_CATEGORY_SHEET);
        $categorySheet->setCellValue('A1', 'Kategori');
        $this->fillReferenceColumn($categorySheet, $categories);

        $unitSheet = $spreadsheet->createSheet();
        $unitSheet->setTitle(self::REF_UNIT_SHEET);
        $unitSheet->setCellValue('A1', 'Satuan');
        $this->fillReferenceColumn($unitSheet, $units);

        $importSheet = $spreadsheet->createSheet();
        $importSheet->setTitle(self::IMPORT_SHEET);
        $importSheet->fromArray(self::IMPORT_HEADERS, null, 'A1');
        $this->styleHeaderRow($importSheet, 'A1:H1');

        $categoryLast = max(2, count($categories) + 1);
        $unitLast = max(2, count($units) + 1);

        $categoryFormula = "'".self::REF_CATEGORY_SHEET."'!\$A\$2:\$A\${$categoryLast}";
        $unitFormula = "'".self::REF_UNIT_SHEET."'!\$A\$2:\$A\${$unitLast}";
        $activeFormula = '"Ya,Tidak"';

        $this->applyListValidation($importSheet, 'B', 2, self::IMPORT_MAX_ROWS + 1, $categoryFormula);
        $this->applyListValidation($importSheet, 'C', 2, self::IMPORT_MAX_ROWS + 1, $unitFormula);
        $this->applyListValidation($importSheet, 'H', 2, self::IMPORT_MAX_ROWS + 1, $activeFormula);

        $this->autoSizeColumns($importSheet, 'A', 'H');

        $categorySheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
        $unitSheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

        $spreadsheet->setActiveSheetIndexByName(self::IMPORT_SHEET);

        return $spreadsheet;
    }

    private function buildGuideSheet(Worksheet $sheet): void
    {
        $lines = [
            ['PANDUAN IMPORT DATA BARANG'],
            [''],
            ['1. Buka sheet "Import Barang" untuk mengisi data.'],
            ['2. Kolom Kategori & Satuan memiliki dropdown — pilihan diisi otomatis dari database.'],
            ['3. Kode barang dibuat otomatis oleh sistem saat import.'],
            ['4. Stok awal tetap 0 — gunakan menu Stok Masuk setelah import.'],
            ['5. Isi data mulai baris 2 ke bawah pada sheet Import Barang.'],
            ['6. Kolom wajib: Nama Barang, Kategori, Satuan.'],
            ['7. Harga Beli dan Harga Jual boleh dikosongkan (default 0).'],
            ['8. Aktif: isi "Ya" atau "Tidak" (default Ya jika dikosongkan).'],
            [''],
            ['Sheet referensi kategori & satuan tersembunyi — jangan dihapus dari file Excel.'],
        ];

        $sheet->fromArray($lines, null, 'A1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getColumnDimension('A')->setWidth(80);
    }

    /**
     * @param  array<int, string>  $values
     */
    private function fillReferenceColumn(Worksheet $sheet, array $values): void
    {
        $row = 2;
        foreach ($values as $value) {
            $sheet->setCellValue("A{$row}", $value);
            $row++;
        }

        if (empty($values)) {
            $sheet->setCellValue('A2', '(Belum ada data — tambahkan di menu master)');
        }

        $this->styleHeaderRow($sheet, 'A1');
    }

    private function applyListValidation(
        Worksheet $sheet,
        string $column,
        int $startRow,
        int $endRow,
        string $formula,
    ): void {
        for ($row = $startRow; $row <= $endRow; $row++) {
            $cell = $sheet->getCell("{$column}{$row}");
            $validation = $cell->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_STOP);
            $validation->setAllowBlank(true);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setShowDropDown(true);
            $validation->setErrorTitle('Nilai tidak valid');
            $validation->setError('Pilih salah satu opsi dari daftar.');
            $validation->setFormula1($formula);
        }
    }

    private function styleHeaderRow(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '1A1A1A']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'D4A017'],
            ],
        ]);
    }

    private function autoSizeColumns(Worksheet $sheet, string $from, string $to): void
    {
        foreach (range($from, $to) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    private function streamSpreadsheet(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * @return Collection<string, ItemUnit>
     */
    private function buildUnitLookup(Collection $units): Collection
    {
        $lookup = collect();

        foreach ($units as $unit) {
            $lookup->put(mb_strtolower($this->unitLabel($unit)), $unit);
            $lookup->put(mb_strtolower($unit->name), $unit);

            if ($unit->abbreviation) {
                $lookup->put(mb_strtolower($unit->abbreviation), $unit);
            }
        }

        return $lookup;
    }

    private function unitLabel(?ItemUnit $unit): string
    {
        if (! $unit) {
            return '';
        }

        return $unit->abbreviation
            ? "{$unit->name} ({$unit->abbreviation})"
            : $unit->name;
    }

    private function normalizeUnitName(string $label): string
    {
        if (preg_match('/^(.+?)\s*\([^)]+\)\s*$/u', $label, $matches)) {
            return trim($matches[1]);
        }

        return $label;
    }

    private function parseYesNo(mixed $value, bool $default = true): bool
    {
        $text = mb_strtolower(trim((string) $value));

        if ($text === '') {
            return $default;
        }

        return in_array($text, ['ya', 'y', 'yes', '1', 'true', 'aktif'], true);
    }
}
