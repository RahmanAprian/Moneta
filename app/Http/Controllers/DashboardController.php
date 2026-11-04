<?php

namespace App\Http\Controllers;

use App\Models\DataSource;
use App\Models\TransactionCategory;
use App\Models\TransactionStatistic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DashboardController extends Controller
{
    public function index()
    {
        return view('dashboard.index');
    }

    public function data(Request $request): JsonResponse
    {
        $validated = Validator::make($request->query(), [
            'year' => ['nullable', 'integer', 'between:1900,2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'category' => ['nullable', 'string', 'exists:transaction_categories,slug'],
            'granularity' => ['nullable', 'in:annual,monthly'],
            'metric' => ['nullable', 'in:value,volume'],
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'in:period_start,category,value,volume'],
            'direction' => ['nullable', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:5,100'],
        ])->validate();

        $filters = [
            'year' => $validated['year'] ?? null,
            'month' => $validated['month'] ?? null,
            'category' => $validated['category'] ?? null,
            'granularity' => $validated['granularity'] ?? 'annual',
        ];
        $metric = $validated['metric'] ?? 'value';

        $all = TransactionStatistic::query()->with('category')->filtered($filters)->get();
        $totalValue = $all->sum(fn ($row) => $row->transaction_value === null ? 0 : (float) $row->transaction_value);
        $totalVolume = $all->sum(fn ($row) => $row->transaction_volume === null ? 0 : (float) $row->transaction_volume);
        $hasValue = $all->contains(fn ($row) => $row->transaction_value !== null);
        $hasVolume = $all->contains(fn ($row) => $row->transaction_volume !== null);

        $categoryRows = $all->groupBy('transaction_category_id')->map(function ($rows) {
            return [
                'slug' => $rows->first()->category->slug,
                'name' => $rows->first()->category->name,
                'value' => $rows->contains(fn ($row) => $row->transaction_value !== null) ? $rows->sum(fn ($row) => (float) ($row->transaction_value ?? 0)) : null,
                'volume' => $rows->contains(fn ($row) => $row->transaction_volume !== null) ? $rows->sum(fn ($row) => (float) ($row->transaction_volume ?? 0)) : null,
            ];
        })->sortBy(fn ($row) => ['purchase' => 1, 'top_up' => 2, 'transfer' => 3][$row['slug']])->values();

        $periodRows = $all->groupBy(fn ($row) => $row->period_start->format('Y-m-d'))->sortKeys();
        $trend = $periodRows->map(function ($rows, $period) use ($categoryRows) {
            $byCategory = $rows->keyBy('transaction_category_id');
            $values = [];
            foreach ($categoryRows as $category) {
                $record = $byCategory->first(fn ($row) => $row->category->slug === $category['slug']);
                $values[$category['slug']] = $record ? [
                    'value' => $record->transaction_value === null ? null : (float) $record->transaction_value,
                    'volume' => $record->transaction_volume === null ? null : (float) $record->transaction_volume,
                ] : ['value' => null, 'volume' => null];
            }
            return ['period' => $period, 'values' => $values];
        })->values();

        $growth = [];
        foreach ($categoryRows as $category) {
            $previous = null;
            foreach ($trend as $point) {
                $current = $point['values'][$category['slug']][$metric];
                $growth[] = [
                    'period' => $point['period'],
                    'category' => $category['slug'],
                    'growth' => $previous !== null && $previous != 0 && $current !== null ? (($current - $previous) / abs($previous)) * 100 : null,
                ];
                $previous = $current;
            }
        }

        $source = DataSource::query()->latest('id')->first();
        $query = TransactionStatistic::query()->with('category')->filtered($filters);
        if (($validated['search'] ?? '') !== '') {
            $term = $validated['search'];
            $query->where(function (Builder $builder) use ($term) {
                $builder->where('period_start', 'like', "%{$term}%")
                    ->orWhere('transaction_value', 'like', "%{$term}%")
                    ->orWhere('transaction_volume', 'like', "%{$term}%")
                    ->orWhereHas('category', fn (Builder $category) => $category->where('name', 'like', "%{$term}%"));
            });
        }
        $sort = $validated['sort'] ?? 'period_start';
        if ($sort === 'category') {
            $query->join('transaction_categories', 'transaction_categories.id', '=', 'transaction_statistics.transaction_category_id')
                ->orderBy('transaction_categories.name', $validated['direction'] ?? 'desc')
                ->select('transaction_statistics.*');
        } elseif ($sort === 'value' || $sort === 'volume') {
            $query->orderBy('transaction_'.$sort, $validated['direction'] ?? 'desc');
        } else {
            $query->orderBy('period_start', $validated['direction'] ?? 'desc');
        }
        $page = $query->paginate($validated['per_page'] ?? 10)->withQueryString();
        $rows = $page->getCollection()->map(function ($row) {
            $value = $row->transaction_value === null ? null : (float) $row->transaction_value;
            $volume = $row->transaction_volume === null ? null : (float) $row->transaction_volume;
            $monthNames = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
            return [
                'period' => $row->granularity === 'annual' ? $row->period_start->format('Y') : $monthNames[$row->period_start->month].' '.$row->period_start->year,
                'period_start' => $row->period_start->format('Y-m-d'),
                'granularity' => $row->granularity,
                'category' => $row->category->name,
                'value' => $value,
                'volume' => $volume,
                'average_transaction_rupiah' => $value !== null && $volume !== null && $volume > 0 ? round(($value / $volume) * 1_000_000, 2) : null,
                'value_unit' => $row->value_unit,
                'volume_unit' => $row->volume_unit,
            ];
        })->values();

        $valuesByCategory = $categoryRows->pluck('value');
        $topCategory = $hasValue && $valuesByCategory->filter(fn ($value) => $value !== null)->isNotEmpty()
            ? $categoryRows->sortByDesc('value')->first()
            : null;

        $annualYears = TransactionStatistic::query()->where('granularity', 'annual')->pluck('period_start')
            ->map(fn ($date) => (int) \Carbon\CarbonImmutable::parse($date)->year)->unique()->sort()->values();
        $monthlyYears = TransactionStatistic::query()->where('granularity', 'monthly')->pluck('period_start')
            ->map(fn ($date) => (int) \Carbon\CarbonImmutable::parse($date)->year)->unique()->sort()->values();
        $coverageFor = static function (string $granularity): array {
            $dates = TransactionStatistic::query()->where('granularity', $granularity)->orderBy('period_start')->pluck('period_start');
            return [
                'first' => $dates->first() ? \Carbon\CarbonImmutable::parse($dates->first())->format('Y-m-d') : null,
                'last' => $dates->last() ? \Carbon\CarbonImmutable::parse($dates->last())->format('Y-m-d') : null,
                'count' => $dates->count(),
            ];
        };
        $annualCoverage = $coverageFor('annual');
        $monthlyCoverage = $coverageFor('monthly');

        $comparisonRows = TransactionStatistic::query()->with('category')
            ->where('granularity', 'monthly')
            ->whereBetween('period_start', ['2025-01-01', '2026-12-31'])
            ->when($filters['category'], fn (Builder $query, string $slug) => $query->whereHas('category', fn (Builder $category) => $category->where('slug', $slug)))
            ->get();
        $monthsByYear = $comparisonRows->groupBy(fn ($row) => $row->period_start->year)
            ->map(fn ($rows) => $rows->map(fn ($row) => $row->period_start->month)->unique()->values()->all());
        $commonMonths = array_values(array_intersect($monthsByYear->get(2025, []), $monthsByYear->get(2026, [])));
        sort($commonMonths);
        $comparisonMonths = $filters['month'] ? (in_array((int) $filters['month'], $commonMonths, true) ? [(int) $filters['month']] : []) : $commonMonths;
        $comparisonCategories = TransactionCategory::query()
            ->when($filters['category'], fn (Builder $query, string $slug) => $query->where('slug', $slug))
            ->orderBy('sort_order')->get();
        $comparisonCategoryData = $comparisonCategories->map(function ($category) use ($comparisonRows, $comparisonMonths) {
            $yearData = [];
            foreach ([2025, 2026] as $year) {
                $records = $comparisonRows->filter(fn ($row) => $row->category->slug === $category->slug
                    && $row->period_start->year === $year
                    && in_array($row->period_start->month, $comparisonMonths, true));
                $complete = $comparisonMonths !== [] && $records->count() === count($comparisonMonths);
                $yearData[$year] = [
                    'value' => $complete && $records->every(fn ($row) => $row->transaction_value !== null) ? $records->sum(fn ($row) => (float) $row->transaction_value) : null,
                    'volume' => $complete && $records->every(fn ($row) => $row->transaction_volume !== null) ? $records->sum(fn ($row) => (float) $row->transaction_volume) : null,
                ];
            }
            $growthValue = $yearData[2025]['value'] !== null && $yearData[2025]['value'] != 0 && $yearData[2026]['value'] !== null
                ? round((($yearData[2026]['value'] - $yearData[2025]['value']) / abs($yearData[2025]['value'])) * 100, 2)
                : null;
            $growthVolume = $yearData[2025]['volume'] !== null && $yearData[2025]['volume'] != 0 && $yearData[2026]['volume'] !== null
                ? round((($yearData[2026]['volume'] - $yearData[2025]['volume']) / abs($yearData[2025]['volume'])) * 100, 2)
                : null;
            return ['slug' => $category->slug, 'name' => $category->name, 'data_2025' => $yearData[2025], 'data_2026' => $yearData[2026], 'growth_value_pct' => $growthValue, 'growth_volume_pct' => $growthVolume];
        })->values();
        $comparisonMonthNames = collect($comparisonMonths)->map(fn ($month) => \Carbon\CarbonImmutable::create(2026, $month, 1)->locale('id')->translatedFormat('F'))->values();

        $comparisonNote = match (true) {
            $comparisonMonths === [] => 'Tidak ada bulan yang sama-sama tersedia pada 2025 dan 2026 untuk filter ini.',
            $filters['month'] !== null => 'Perbandingan memakai bulan yang dipilih pada kedua tahun.',
            $comparisonMonths === range(1, 8) => 'Perbandingan memakai Januari–Agustus pada kedua tahun dan menggunakan nilai sumber berpresisi penuh.',
            default => 'Perbandingan hanya memakai bulan yang tersedia pada kedua tahun.',
        };

        return response()->json([
            'summary' => [
                'total_value' => $hasValue ? $totalValue : null,
                'total_volume' => $hasVolume ? $totalVolume : null,
                'average_transaction_rupiah' => $hasValue && $hasVolume && $totalVolume > 0 ? round(($totalValue / $totalVolume) * 1_000_000, 2) : null,
                'top_category' => $topCategory['name'] ?? null,
                'count' => $all->count(),
            ],
            'categories' => $categoryRows,
            'trend' => $trend,
            'growth' => $growth,
            'year_comparison' => [
                'years' => [2025, 2026],
                'months' => $comparisonMonths,
                'month_names' => $comparisonMonthNames,
                'categories' => $comparisonCategoryData,
                'note' => $comparisonNote,
            ],
            'table' => ['data' => $rows, 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
            'metadata' => [
                'source' => $source?->name,
                'source_file' => $source?->source_file,
                'value_unit' => $source?->value_unit,
                'volume_unit' => $source?->volume_unit,
                'imported_at' => $source?->imported_at?->toIso8601String(),
                'retrieved_at' => $source?->retrieved_at?->toIso8601String(),
                'years' => ['annual' => $annualYears, 'monthly' => $monthlyYears],
                'coverage' => ['annual' => $annualCoverage, 'monthly' => $monthlyCoverage],
                'study_window' => [
                    'annual' => '2020–2025',
                    'monthly' => 'Januari 2025–Agustus 2026',
                    'monthly_note' => 'Data bulanan mencakup 60 observasi Januari 2025–Agustus 2026; periode tahunan dan bulanan dihitung terpisah.',
                ],
                'notes' => $source?->notes,
                'categories' => TransactionCategory::query()->orderBy('sort_order')->get(['slug', 'name']),
            ],
            'filters' => ['granularity' => $filters['granularity'], 'metric' => $metric],
        ]);
    }
}
