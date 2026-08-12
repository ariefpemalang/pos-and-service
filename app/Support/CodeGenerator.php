<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class CodeGenerator
{
  public const PREFIX_CUSTOMER = 'PLG';

  public const PREFIX_SUPPLIER = 'SUP';

  public const PREFIX_TECHNICIAN = 'TKN';

  public const PREFIX_ITEM = 'BRG';

  public const PREFIX_STOCK_IN = 'STM';

  public const PREFIX_STOCK_OUT = 'STK';

  /** Fase 2 — penjualan barang */
  public const PREFIX_SALE = 'JBL';

  /** Fase 2 — service jasa (transaksi POS) */
  public const PREFIX_SERVICE = 'SRV';

  /** Tiket servis barang nginep (HP / laptop) */
  public const PREFIX_SERVICE_TICKET = 'TKS';

  /** Uang muka / DP tiket servis */
  public const PREFIX_DOWN_PAYMENT = 'UMK';

  /** Fase 2 — transaksi gabungan barang + jasa */
  public const PREFIX_COMBINED = 'TRX';

  /** Fase 3 — pembelian barang / pengeluaran */
  public const PREFIX_PURCHASE = 'PBL';

  /** Pemasukan kas manual */
  public const PREFIX_MANUAL_INCOME = 'MIN';

  /** Pengeluaran kas manual */
  public const PREFIX_MANUAL_EXPENSE = 'MEX';

  /**
   * Format: {PREFIX}-{YYYYMMDD}-{0001}
   * Nomor urut reset per hari per prefix.
   */
  public static function next(string $prefix, string $modelClass, string $column = 'code'): string
  {
    /** @var Model $model */
    $model = new $modelClass;
    $query = $model->newQuery();

    if (in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)) {
      $query->withTrashed();
    }

    return self::nextFromQuery($prefix, $query, $column);
  }

  public static function nextFromTable(string $prefix, string $table, string $column): string
  {
    return self::nextFromQuery($prefix, DB::table($table), $column);
  }

  private static function nextFromQuery(string $prefix, $query, string $column): string
  {
    $date = now()->format('Ymd');
    $lockName = 'codegen:'.$prefix.':'.$date;
    $lock = DB::selectOne('SELECT GET_LOCK(?, 10) as acquired', [$lockName]);

    if (! $lock || (int) $lock->acquired !== 1) {
      throw new \RuntimeException('Gagal mengalokasikan nomor dokumen. Silakan coba lagi.');
    }

    try {
      $pattern = "{$prefix}-{$date}-%";

      $latest = (clone $query)
        ->where($column, 'like', $pattern)
        ->orderByDesc($column)
        ->value($column);

      $seq = 1;
      if ($latest && preg_match('/-(\d+)$/', $latest, $matches)) {
        $seq = (int) $matches[1] + 1;
      }

      $code = sprintf('%s-%s-%04d', $prefix, $date, $seq);
    } catch (\Throwable $e) {
      self::releaseLock($lockName);
      throw $e;
    }

    // Hold the advisory lock until the surrounding DB transaction commits so a
    // concurrent request cannot reuse the same sequence before the row is visible.
    if (DB::transactionLevel() === 0) {
      self::releaseLock($lockName);
    } else {
      DB::afterCommit(static fn () => self::releaseLock($lockName));
      DB::afterRollBack(static fn () => self::releaseLock($lockName));
    }

    return $code;
  }

  private static function releaseLock(string $lockName): void
  {
    DB::select('SELECT RELEASE_LOCK(?)', [$lockName]);
  }
}
