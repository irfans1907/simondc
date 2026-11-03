<?php

namespace App\Models;

use App\Concerns\DescribesActivityChanges;
use Database\Factories\TroubleshootFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Troubleshoot extends Model
{
    /** @use HasFactory<TroubleshootFactory> */
    use DescribesActivityChanges, HasFactory, LogsActivity;

    /**
     * Allowed trouble ticket results.
     *
     * @var list<string>
     */
    public const RESULTS = ['Normal', 'Masih Bermasalah'];

    /**
     * Display labels for the stored results. The UI, CSV exports, and
     * search aliases speak Solved/Unresolved; the database keeps the
     * original strings because validation and existing data key off them.
     *
     * @var array<string, string>
     */
    public const RESULT_LABELS = [
        'Normal' => 'Solved',
        'Masih Bermasalah' => 'Unresolved',
    ];

    /**
     * The display label for a stored result.
     */
    public static function resultLabel(?string $result): ?string
    {
        if ($result === null) {
            return null;
        }

        return self::RESULT_LABELS[$result] ?? $result;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'maintenance_id',
        'asset_id',
        'troubleshoot_date',
        'started_date',
        'closed_date',
        'result',
        'evidence_photo',
        'document',
    ];

    /**
     * Stamp the human-readable ticket number (TKT-00001) right after the
     * insert, so every creation path (the troubleshoot page and the
     * maintenance sync) gets one, the number is never mass assignable, and
     * it can never collide — it is derived from the primary key.
     */
    protected static function booted(): void
    {
        static::created(function (Troubleshoot $troubleshoot) {
            $troubleshoot->forceFill([
                'ticket_number' => sprintf('TKT-%05d', $troubleshoot->id),
            ])->saveQuietly();
        });
    }

    /**
     * The maintenance this ticket was created from (NULL when the ticket
     * was entered manually from the Troubleshoot page).
     *
     * @return BelongsTo<Maintenance, $this>
     */
    public function maintenance(): BelongsTo
    {
        return $this->belongsTo(Maintenance::class);
    }

    /**
     * The asset this ticket belongs to.
     *
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('troubleshoot')
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(function (string $eventName): string {
                $ticket = $this->describeTicketNumber();

                return match ($eventName) {
                    'created' => 'created trouble ticket '.$ticket.' for asset "'.$this->describeAssetName().'"',
                    'updated' => $this->describeTroubleshootUpdate($ticket),
                    'deleted' => 'deleted trouble ticket '.$ticket.' for asset "'.$this->describeAssetName().'"',
                    default => $eventName,
                };
            });
    }

    /**
     * The human-readable ticket number. On create the number is stamped
     * by a "created" hook that runs after this description is built, so
     * fall back to deriving it from the primary key.
     */
    protected function describeTicketNumber(): string
    {
        return $this->ticket_number ?? sprintf('TKT-%05d', $this->id);
    }

    /**
     * The related asset's name, looked up fresh (the `asset` relation may
     * be cached from an earlier description), or its id when missing.
     */
    protected function describeAssetName(): string
    {
        if ($this->asset_id === null) {
            return 'no asset';
        }

        return Asset::query()->find($this->asset_id)?->name ?? '#'.$this->asset_id;
    }

    /**
     * Build a description listing each changed attribute and its old/new value.
     */
    protected function describeTroubleshootUpdate(string $ticket): string
    {
        $parts = $this->describeChangedAttributes([
            'asset_id' => fn (mixed $id): string => Asset::query()->find($id)?->name ?? '#'.$id,
        ]);

        if ($parts === []) {
            return 'updated trouble ticket '.$ticket;
        }

        return 'updated trouble ticket '.$ticket.': '.implode(', ', $parts);
    }
}
