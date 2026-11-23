<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\Category;
use App\Models\Customer;
use App\Models\PanelMcb;
use App\Models\PowerLoadRow;
use App\Models\PowerPanel;
use App\Models\PowerPdu;
use App\Models\Rack;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class LinkPowerAssets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'power:link-assets
                            {--dry-run : Report matches without writing them}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Link power PDUs, panels, and load rows to their asset/rack/customer/MCB records';

    /**
     * Execute the command: match the legacy free-text power columns against
     * the asset module and fill the nullable relations. Matching is
     * case-insensitive and ignores spaces/punctuation, so "PDU.2-A.1",
     * "PDU 2 A 1", and "pdu2a1" are equivalent. Ambiguous matches are
     * reported but never written.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $assetsByName = $this->groupByNormalized(
            Asset::query()
                ->whereIn('category_id', Category::idsByName('Panel Power'))
                ->get(['id', 'name']),
            'name',
        );

        $racksByCode = $this->groupByNormalized(
            Rack::query()->get(['id', 'rack_code']),
            'rack_code',
        );

        $customersByName = $this->groupByNormalized(
            Customer::query()->get(['id', 'customer_name', 'rack_id', 'panel_asset_id', 'panel_mcb_id']),
            'customer_name',
        );

        $mcbsByNumber = $this->groupByNormalized(
            PanelMcb::query()->get(['id', 'asset_id', 'mcb_number']),
            'mcb_number',
        );

        $stats = [
            'PDU' => $this->linkPdus($assetsByName, $dryRun),
            'Panel' => $this->linkPanels($assetsByName, $dryRun),
            'Rack' => $this->linkLoadRacks($racksByCode, $dryRun),
            'Customer' => $this->linkLoadCustomers($customersByName, $dryRun),
            'MCB' => $this->linkLoadMcbs($mcbsByNumber, $dryRun),
        ];

        $this->table(
            ['Target', 'Matched', 'Ambiguous', 'Unmatched'],
            collect($stats)
                ->map(fn (array $counts, string $target): array => [
                    $target,
                    $counts['matched'],
                    $counts['ambiguous'],
                    $counts['unmatched'],
                ])
                ->values()
                ->all(),
        );

        $this->info($dryRun
            ? 'Dry run: no rows were written.'
            : 'Power links written.');

        return self::SUCCESS;
    }

    /**
     * Link PDUs to Panel Power assets by code, then name.
     *
     * @param  Collection<string, Collection<int, Asset>>  $assetsByName
     * @return array{matched: int, ambiguous: int, unmatched: int}
     */
    private function linkPdus(Collection $assetsByName, bool $dryRun): array
    {
        return $this->applyLinks(
            PowerPdu::query()->whereNull('asset_id'),
            function (PowerPdu $pdu) use ($assetsByName): array {
                [$status, $asset] = $this->lookup($assetsByName, $this->normalize($pdu->code));

                if ($status === 'unmatched') {
                    [$status, $asset] = $this->lookup($assetsByName, $this->normalize($pdu->name));
                }

                return [$status, $asset];
            },
            fn (PowerPdu $pdu, Asset $asset): array => ['asset_id' => $asset->id],
            $dryRun,
        );
    }

    /**
     * Link PUE panels to Panel Power assets by name.
     *
     * @param  Collection<string, Collection<int, Asset>>  $assetsByName
     * @return array{matched: int, ambiguous: int, unmatched: int}
     */
    private function linkPanels(Collection $assetsByName, bool $dryRun): array
    {
        return $this->applyLinks(
            PowerPanel::query()->whereNull('asset_id'),
            fn (PowerPanel $panel): array => $this->lookup($assetsByName, $this->normalize($panel->name)),
            fn (PowerPanel $panel, Asset $asset): array => ['asset_id' => $asset->id],
            $dryRun,
        );
    }

    /**
     * Link load rows to racks by rack code.
     *
     * @param  Collection<string, Collection<int, Rack>>  $racksByCode
     * @return array{matched: int, ambiguous: int, unmatched: int}
     */
    private function linkLoadRacks(Collection $racksByCode, bool $dryRun): array
    {
        return $this->applyLinks(
            PowerLoadRow::query()->whereNull('rack_id'),
            fn (PowerLoadRow $row): array => $this->lookup($racksByCode, $this->normalize($row->rack)),
            fn (PowerLoadRow $row, Rack $rack): array => ['rack_id' => $rack->id],
            $dryRun,
        );
    }

    /**
     * Link load rows to customers by customer name. When a name is not
     * unique, the customer already assigned to the row's rack wins.
     *
     * @param  Collection<string, Collection<int, Customer>>  $customersByName
     * @return array{matched: int, ambiguous: int, unmatched: int}
     */
    private function linkLoadCustomers(Collection $customersByName, bool $dryRun): array
    {
        return $this->applyLinks(
            PowerLoadRow::query()->whereNull('customer_id'),
            fn (PowerLoadRow $row): array => $this->lookupCustomer(
                $customersByName,
                $this->normalize($row->customer),
                $row->rack_id,
            ),
            fn (PowerLoadRow $row, Customer $customer): array => ['customer_id' => $customer->id],
            $dryRun,
        );
    }

    /**
     * Link load rows to panel MCB slots. A linked customer supplies its
     * panel, so duplicate MCB numbers on other panels are not ambiguous.
     *
     * @param  Collection<string, Collection<int, PanelMcb>>  $mcbsByNumber
     * @return array{matched: int, ambiguous: int, unmatched: int}
     */
    private function linkLoadMcbs(Collection $mcbsByNumber, bool $dryRun): array
    {
        return $this->applyLinks(
            PowerLoadRow::query()
                ->whereNull('panel_mcb_id')
                ->with('linkedCustomer:id,panel_asset_id,panel_mcb_id'),
            fn (PowerLoadRow $row): array => $this->lookupMcb(
                $mcbsByNumber,
                $row->mcb,
                $row->linkedCustomer,
            ),
            fn (PowerLoadRow $row, PanelMcb $mcb): array => ['panel_mcb_id' => $mcb->id],
            $dryRun,
        );
    }

    /**
     * Walk the given query in id order, resolve a match for each row, and
     * write the update only when the match is unambiguous.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  callable(TModel): array{0: string, 1: Model|null}  $resolver
     * @param  callable(TModel, Model): array<string, mixed>  $updates
     * @return array{matched: int, ambiguous: int, unmatched: int}
     */
    private function applyLinks(Builder $query, callable $resolver, callable $updates, bool $dryRun): array
    {
        $counts = ['matched' => 0, 'ambiguous' => 0, 'unmatched' => 0];

        $query->chunkById(500, function (Collection $rows) use ($resolver, $updates, $dryRun, &$counts): void {
            foreach ($rows as $row) {
                [$status, $match] = $resolver($row);

                $counts[$status]++;

                if ($status === 'matched' && ! $dryRun) {
                    $row->update($updates($row, $match));
                }
            }
        });

        return $counts;
    }

    /**
     * Look up one normalized key. Returns "ambiguous" when several records
     * share the key so the caller never guesses.
     *
     * @param  Collection<string, Collection<int, Model>>  $groups
     * @return array{0: string, 1: Model|null}
     */
    private function lookup(Collection $groups, string $key): array
    {
        if ($key === '') {
            return ['unmatched', null];
        }

        $matches = $groups->get($key);

        if ($matches === null || $matches->isEmpty()) {
            return ['unmatched', null];
        }

        return $matches->count() === 1
            ? ['matched', $matches->first()]
            : ['ambiguous', null];
    }

    /**
     * Look up a customer by name, preferring the customer on the row's rack
     * when the name appears more than once.
     *
     * @param  Collection<string, Collection<int, Customer>>  $groups
     * @return array{0: string, 1: Customer|null}
     */
    private function lookupCustomer(Collection $groups, string $key, ?int $rackId): array
    {
        if ($key === '') {
            return ['unmatched', null];
        }

        $matches = $groups->get($key) ?? collect();

        if ($matches->isEmpty()) {
            return ['unmatched', null];
        }

        if ($matches->count() === 1) {
            return ['matched', $matches->first()];
        }

        if ($rackId !== null) {
            $sameRack = $matches->where('rack_id', $rackId);

            if ($sameRack->count() === 1) {
                return ['matched', $sameRack->first()];
            }
        }

        return ['ambiguous', null];
    }

    /**
     * Look up an MCB slot. Legacy rows store a bare number ("1") while the
     * slots are usually labelled "MCB-01", so trailing digits are compared
     * as a fallback. The linked customer's panel narrows the result.
     *
     * @param  Collection<string, Collection<int, PanelMcb>>  $groups
     * @return array{0: string, 1: PanelMcb|null}
     */
    private function lookupMcb(Collection $groups, ?string $mcb, ?Customer $customer): array
    {
        $key = $this->normalize($mcb);

        if ($key === '') {
            return ['unmatched', null];
        }

        $matches = $groups->get($key) ?? collect();

        if ($matches->isEmpty() && ctype_digit($key)) {
            $number = (int) $key;
            $matches = $groups->flatten(1)->filter(
                fn (PanelMcb $slot): bool => $this->trailingNumber($slot->mcb_number) === $number,
            );
        }

        if ($matches->isEmpty()) {
            return ['unmatched', null];
        }

        if ($customer?->panel_asset_id !== null) {
            $samePanel = $matches->where('asset_id', $customer->panel_asset_id);

            if ($samePanel->count() === 1) {
                return ['matched', $samePanel->first()];
            }
        }

        return $matches->count() === 1
            ? ['matched', $matches->first()]
            : ['ambiguous', null];
    }

    /**
     * Group records by a normalized attribute.
     *
     * @param  Collection<int, Model>  $records
     * @return Collection<string, Collection<int, Model>>
     */
    private function groupByNormalized(Collection $records, string $attribute): Collection
    {
        return $records->groupBy(
            fn (Model $record): string => $this->normalize($record->getAttribute($attribute)),
        );
    }

    /**
     * Uppercase and strip everything that is not a letter or digit.
     */
    private function normalize(?string $value): string
    {
        return preg_replace('/[^A-Z0-9]/', '', mb_strtoupper((string) $value)) ?? '';
    }

    /**
     * The number at the end of an MCB label ("MCB-01" -> 1).
     */
    private function trailingNumber(?string $value): ?int
    {
        if ($value === null || preg_match('/(\d+)\s*$/', $value, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }
}
