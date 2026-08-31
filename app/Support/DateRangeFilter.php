<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class DateRangeFilter
{
    /**
     * @return array{from: string, to: string}
     */
    public static function defaults(): array
    {
        return [
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->toDateString(),
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function resolve(Request $request, bool $defaultToCurrentMonth = true): array
    {
        $fromInput = $request->input('from') ?? $request->input('date_from');
        $toInput = $request->input('to') ?? $request->input('date_to');

        if ($defaultToCurrentMonth && ! $fromInput && ! $toInput) {
            $from = now()->startOfMonth()->startOfDay();
            $to = now()->endOfDay();
        } else {
            $from = $fromInput
                ? Carbon::parse((string) $fromInput)->startOfDay()
                : now()->startOfMonth()->startOfDay();
            $to = $toInput
                ? Carbon::parse((string) $toInput)->endOfDay()
                : now()->endOfDay();
        }

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }

    /**
     * @return array{from: string, to: string}
     */
    public static function forView(Request $request): array
    {
        [$from, $to] = self::resolve($request);

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ];
    }

    public static function apply(Builder $query, Carbon $from, Carbon $to, string $column = 'created_at'): Builder
    {
        return $query->whereBetween($column, [$from, $to]);
    }
}
