<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChecklistEntryRequest;
use App\Models\ChecklistCategory;
use App\Models\ChecklistDayNote;
use App\Models\ChecklistEntry;
use App\Models\Menu;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChecklistController extends Controller
{
    /**
     * Redirect the module root to the checklist overview page.
     */
    public function index(): RedirectResponse
    {
        return to_route('checklist.overview');
    }

    /**
     * Show the checklist overview: today's and this month's progress per
     * category, the risk level recorded for today, and the latest notes.
     */
    public function overview(Request $request): InertiaResponse
    {
        $today = today();
        $monthStart = $today->copy()->startOfMonth();
        $monthEnd = $today->copy()->endOfMonth();

        $categories = $this->categoriesWithItems();
        $categoryIds = $categories->pluck('id');

        $todayStats = $this->entryStats($today->toDateString(), $today->toDateString())
            ->keyBy('category_id');
        $monthStats = $this->entryStats($monthStart->toDateString(), $monthEnd->toDateString())
            ->keyBy('category_id');

        $notesToday = ChecklistDayNote::query()
            ->whereDate('entry_date', $today)
            ->whereIn('checklist_category_id', $categoryIds)
            ->get()
            ->keyBy('checklist_category_id');

        $riskCounts = $notesToday->countBy('risk_level');

        $childMenu = Menu::where('url', '/'.$request->path())->first();

        return Inertia::render('dynamic/checklist/overview', [
            ...$this->childMenuProps($childMenu?->parent, $childMenu),
            'date' => $today->toDateString(),
            'month' => $today->format('Y-m'),
            'daysInMonth' => $today->daysInMonth,
            'categories' => $categories->map(fn (ChecklistCategory $category): array => [
                'id' => $category->id,
                'slug' => $category->slug,
                'name' => $category->name,
                'time_slots' => $category->time_slots,
                'total_items' => $category->items_count,
                'total_slots' => $category->items_count * count($category->time_slots),
                'filled_today' => (int) ($todayStats->get($category->id)?->filled_slots ?? 0),
                'filled_month' => (int) ($monthStats->get($category->id)?->filled_slots ?? 0),
                'filled_today_items' => (int) ($todayStats->get($category->id)?->filled_items ?? 0),
                'filled_month_items' => (int) ($monthStats->get($category->id)?->filled_items ?? 0),
                'risk_level' => $notesToday->get($category->id)?->risk_level ?? 'LOW',
                'note' => $notesToday->get($category->id)?->note,
            ])->values(),
            'totals' => [
                'items' => $categories->sum('items_count'),
                'slots' => $categories->sum(
                    fn (ChecklistCategory $category): int => $category->items_count * count($category->time_slots),
                ),
                'filled_today' => (int) $todayStats->sum('filled_slots'),
                'filled_month' => (int) $monthStats->sum('filled_slots'),
                'high' => (int) ($riskCounts['HIGH'] ?? 0),
                'medium' => (int) ($riskCounts['MEDIUM'] ?? 0),
                'low' => (int) ($riskCounts['LOW'] ?? 0),
            ],
            'recentNotes' => ChecklistDayNote::query()
                ->with('category:id,name')
                ->whereNotNull('note')
                ->where('note', '!=', '')
                ->whereIn('checklist_category_id', $categoryIds)
                ->orderByDesc('updated_at')
                ->limit(5)
                ->get()
                ->map(fn (ChecklistDayNote $note): array => [
                    'id' => $note->id,
                    'category' => $note->category?->name ?? '-',
                    'date' => $note->entry_date->toDateString(),
                    'risk_level' => $note->risk_level,
                    'note' => $note->note,
                ])
                ->values(),
            'canManage' => $request->user()->hasPermissionTo('manage checklist'),
        ]);
    }

    /**
     * Show the daily check page: every category and item for one date, with
     * the entries already recorded for that date.
     */
    public function dailycheck(Request $request): InertiaResponse
    {
        $date = $this->resolveDate($request);
        $categories = $this->categoriesWithItems();
        $categoryIds = $categories->pluck('id');

        $entries = ChecklistEntry::query()
            ->with('user:id,name')
            ->whereDate('entry_date', $date)
            ->whereHas('item', fn ($query) => $query
                ->where('is_active', true)
                ->whereIn('checklist_category_id', $categoryIds))
            ->get()
            ->map(fn (ChecklistEntry $entry): array => [
                'id' => $entry->id,
                'item_id' => $entry->checklist_item_id,
                'time_slot' => $entry->time_slot,
                'value' => $entry->value,
                'status' => $entry->status,
                'note' => $entry->note,
                'source' => $entry->source,
                'user_name' => $entry->user?->name,
            ])
            ->values();

        $dayNotes = ChecklistDayNote::query()
            ->whereDate('entry_date', $date)
            ->whereIn('checklist_category_id', $categoryIds)
            ->get()
            ->map(fn (ChecklistDayNote $note): array => [
                'category_id' => $note->checklist_category_id,
                'risk_level' => $note->risk_level,
                'note' => $note->note,
            ])
            ->values();

        $selectedCategoryId = $request->integer('category');

        if (! $categoryIds->contains($selectedCategoryId)) {
            $selectedCategoryId = $categoryIds->first() ?? 0;
        }

        $childMenu = Menu::where('url', '/'.$request->path())->first();

        return Inertia::render('dynamic/checklist/dailycheck', [
            ...$this->childMenuProps($childMenu?->parent, $childMenu),
            'date' => $date,
            'today' => today()->toDateString(),
            'selectedCategoryId' => $selectedCategoryId,
            'categories' => $this->categoryPayload($categories),
            'entries' => $entries,
            'dayNotes' => $dayNotes,
            'canManage' => $request->user()->hasPermissionTo('manage checklist'),
        ]);
    }

    /**
     * Show the monthly recap page: one category for the selected month, with
     * a day-by-day grid per item and the daily risk notes.
     */
    public function monthlycheck(Request $request): InertiaResponse
    {
        $month = $this->resolveMonth($request);
        $start = Carbon::createFromFormat('!Y-m-d', $month.'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $categories = ChecklistCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'slug', 'name']);

        $selectedCategoryId = $request->integer('category');
        $category = $categories->firstWhere('id', $selectedCategoryId) ?? $categories->first();

        $entries = collect();
        $dayNotes = collect();

        if ($category) {
            $category->load(['items' => fn ($query) => $query->where('is_active', true)]);

            $entries = ChecklistEntry::query()
                ->whereBetween('entry_date', [$start->toDateString(), $end->toDateString()])
                ->whereHas('item', fn ($query) => $query
                    ->where('is_active', true)
                    ->where('checklist_category_id', $category->id))
                ->get(['id', 'checklist_item_id', 'entry_date', 'time_slot', 'value', 'status', 'note'])
                ->map(fn (ChecklistEntry $entry): array => [
                    'item_id' => $entry->checklist_item_id,
                    'date' => $entry->entry_date->toDateString(),
                    'time_slot' => $entry->time_slot,
                    'value' => $entry->value,
                    'status' => $entry->status,
                    'note' => $entry->note,
                ])
                ->values();

            $dayNotes = ChecklistDayNote::query()
                ->whereBetween('entry_date', [$start->toDateString(), $end->toDateString()])
                ->where('checklist_category_id', $category->id)
                ->get(['entry_date', 'risk_level', 'note'])
                ->map(fn (ChecklistDayNote $note): array => [
                    'date' => $note->entry_date->toDateString(),
                    'risk_level' => $note->risk_level,
                    'note' => $note->note,
                ])
                ->values();
        }

        $childMenu = Menu::where('url', '/'.$request->path())->first();

        return Inertia::render('dynamic/checklist/monthlycheck', [
            ...$this->childMenuProps($childMenu?->parent, $childMenu),
            'month' => $month,
            'daysInMonth' => $end->day,
            'categories' => $categories,
            'category' => $category ? $this->categoryPayload(new Collection([$category]))[0] : null,
            'entries' => $entries,
            'dayNotes' => $dayNotes,
            'canManage' => $request->user()->hasPermissionTo('manage checklist'),
        ]);
    }

    /**
     * Store the daily entries and the category note for one date. Empty cells
     * delete their stored record so clearing a value works from the form.
     */
    public function storeEntries(ChecklistEntryRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $userId = $request->user()->id;

        DB::transaction(function () use ($data, $userId): void {
            $note = trim((string) ($data['note'] ?? ''));

            if ($note === '' && $data['risk_level'] === 'LOW') {
                ChecklistDayNote::query()
                    ->where('checklist_category_id', $data['category_id'])
                    ->whereDate('entry_date', $data['entry_date'])
                    ->delete();
            } else {
                ChecklistDayNote::updateOrCreate(
                    [
                        'checklist_category_id' => $data['category_id'],
                        'entry_date' => $data['entry_date'],
                    ],
                    [
                        'risk_level' => $data['risk_level'],
                        'note' => $note !== '' ? $note : null,
                        'user_id' => $userId,
                    ],
                );
            }

            foreach ($data['entries'] as $entry) {
                $value = $entry['value'] ?? null;
                $status = $entry['status'] ?? null;
                $entryNote = trim((string) ($entry['note'] ?? ''));

                $key = [
                    'checklist_item_id' => $entry['item_id'],
                    'entry_date' => $data['entry_date'],
                    'time_slot' => $entry['time_slot'],
                ];

                if ($value === null && $status === null && $entryNote === '') {
                    ChecklistEntry::query()->where($key)->delete();

                    continue;
                }

                ChecklistEntry::updateOrCreate($key, [
                    'value' => $value,
                    'status' => $status,
                    'note' => $entryNote !== '' ? $entryNote : null,
                    'source' => 'manual',
                    'user_id' => $userId,
                ]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Checklist saved.')]);

        return redirect()->route('checklist.dailycheck', [
            'date' => $data['entry_date'],
            'category' => $data['category_id'],
        ]);
    }

    /**
     * Delete a single recorded entry.
     */
    public function destroyEntry(ChecklistEntry $entry): RedirectResponse
    {
        $entry->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Checklist entry deleted.')]);

        return back();
    }

    /**
     * Export the selected category and month as a CSV recap. Numeric items
     * show the daily average of their slots; status items show the count of
     * each status recorded that day.
     */
    public function export(Request $request): StreamedResponse
    {
        $month = $this->resolveMonth($request);
        $start = Carbon::createFromFormat('!Y-m-d', $month.'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $category = ChecklistCategory::query()
            ->with(['items' => fn ($query) => $query->where('is_active', true)])
            ->findOrFail($request->integer('category'));

        $entries = ChecklistEntry::query()
            ->whereBetween('entry_date', [$start->toDateString(), $end->toDateString()])
            ->whereHas('item', fn ($query) => $query
                ->where('is_active', true)
                ->where('checklist_category_id', $category->id))
            ->get()
            ->groupBy('checklist_item_id');

        $headers = ['No', 'Section', 'Group', 'Item', 'Unit'];

        for ($day = 1; $day <= $end->day; $day++) {
            $headers[] = (string) $day;
        }

        return Response::streamDownload(function () use ($category, $entries, $headers, $month, $end): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers, escape: '\\');

            $row = 0;

            foreach ($category->items as $item) {
                $itemEntries = $entries->get($item->id, collect())
                    ->groupBy(fn (ChecklistEntry $entry): string => $entry->entry_date->toDateString());

                $line = [
                    ++$row,
                    $item->section ?? '-',
                    $item->group ?? '-',
                    $item->label,
                    $item->unit ?? '-',
                ];

                for ($day = 1; $day <= $end->day; $day++) {
                    $date = sprintf('%s-%02d', $month, $day);
                    $dayEntries = $itemEntries->get($date, collect());

                    if ($dayEntries->isEmpty()) {
                        $line[] = '';

                        continue;
                    }

                    if ($item->input_type === 'status') {
                        $counts = $dayEntries->countBy('status');

                        $line[] = collect(ChecklistEntry::STATUSES)
                            ->filter(fn (string $status): bool => ($counts[$status] ?? 0) > 0)
                            ->map(fn (string $status): string => $status.' '.$counts[$status])
                            ->join(' / ');

                        continue;
                    }

                    $values = $dayEntries->pluck('value')->filter(fn ($value): bool => $value !== null);

                    $line[] = $values->isEmpty() ? '' : number_format((float) $values->avg(), 2, '.', '');
                }

                fputcsv($handle, $line, escape: '\\');
            }

            fclose($handle);
        }, 'checklist-'.$category->slug.'-'.$month.'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Load the active categories with their active items and item count.
     *
     * @return Collection<int, ChecklistCategory>
     */
    private function categoriesWithItems(): Collection
    {
        return ChecklistCategory::query()
            ->where('is_active', true)
            ->with(['items' => fn ($query) => $query->where('is_active', true)])
            ->withCount(['items' => fn ($query) => $query->where('is_active', true)])
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Entry totals per category between two dates, counted per item and per
     * filled slot.
     *
     * @return Collection<int, object{category_id: int, filled_items: int, filled_slots: int}>
     */
    private function entryStats(string $from, string $to): Collection
    {
        return ChecklistEntry::query()
            ->join('checklist_items', 'checklist_items.id', '=', 'checklist_entries.checklist_item_id')
            ->whereBetween('checklist_entries.entry_date', [$from, $to])
            ->selectRaw('checklist_items.checklist_category_id as category_id')
            ->selectRaw('count(distinct checklist_entries.checklist_item_id) as filled_items')
            ->selectRaw('count(*) as filled_slots')
            ->groupBy('checklist_items.checklist_category_id')
            ->get();
    }

    /**
     * Shape categories and their items for the client.
     *
     * @param  Collection<int, ChecklistCategory>  $categories
     * @return array<int, array<string, mixed>>
     */
    private function categoryPayload(Collection $categories): array
    {
        return $categories
            ->map(fn (ChecklistCategory $category): array => [
                'id' => $category->id,
                'slug' => $category->slug,
                'name' => $category->name,
                'time_slots' => $category->time_slots,
                'items' => $category->items
                    ->map(fn ($item): array => [
                        'id' => $item->id,
                        'section' => $item->section,
                        'group' => $item->group,
                        'label' => $item->label,
                        'unit' => $item->unit,
                        'input_type' => $item->input_type,
                        'min_value' => $item->min_value !== null ? (float) $item->min_value : null,
                        'max_value' => $item->max_value !== null ? (float) $item->max_value : null,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Resolve the requested date, falling back to today when it is missing
     * or not a real Y-m-d date.
     */
    private function resolveDate(Request $request): string
    {
        $date = $request->string('date')->toString();

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $parsed = Carbon::createFromFormat('!Y-m-d', $date);

            if ($parsed && $parsed->format('Y-m-d') === $date) {
                return $date;
            }
        }

        return today()->toDateString();
    }

    /**
     * Resolve the requested month (Y-m), falling back to the current month.
     */
    private function resolveMonth(Request $request): string
    {
        $month = $request->string('month')->toString();

        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)
            ? $month
            : now()->format('Y-m');
    }
}
