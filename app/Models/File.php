<?php

namespace App\Models;

use Database\Factories\FileFactory;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $uploaded_by
 * @property int|null $parent_id
 * @property string $type
 * @property string $name
 * @property string|null $path
 * @property string|null $mime_type
 * @property int|null $size
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'uploaded_by', 'parent_id', 'type', 'name', 'path', 'mime_type', 'size'])]
#[Appends(['created_label', 'updated_label'])]
class File extends Model
{
    /** @use HasFactory<FileFactory> */
    use HasFactory, LogsActivity;

    /**
     * Get the user that owns this file.
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the user who uploaded this file (if different from owner).
     *
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get the parent folder.
     *
     * @return BelongsTo<File, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(File::class, 'parent_id');
    }

    /**
     * Get the child files and folders.
     *
     * @return HasMany<File, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(File::class, 'parent_id');
    }

    /**
     * Get the shares for this file.
     *
     * @return HasMany<FileShare, $this>
     */
    public function shares(): HasMany
    {
        return $this->hasMany(FileShare::class);
    }

    /**
     * Get the users this file is shared with.
     *
     * @return HasManyThrough<User, FileShare>
     */
    public function sharedWithUsers(): HasManyThrough
    {
        return $this->HasManyThrough(User::class, FileShare::class, 'file_id', 'shared_with', 'id', 'shared_with');
    }

    /**
     * Get a locale-stable date label for display.
     */
    protected function createdLabel(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->created_at?->format('d/m/Y') ?? '',
        );
    }

    /**
     * Get a locale-stable date label for display.
     */
    protected function updatedLabel(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->updated_at?->format('d/m/Y') ?? '',
        );
    }

    /**
     * Configure which attributes the activity log records for this model.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'type', 'parent_id', 'size'])
            ->useLogName('file')
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->setDescriptionForEvent(fn (string $eventName): string => match ($eventName) {
                'created' => 'created ":subject.name"',
                'updated' => 'updated ":subject.name"',
                'deleted' => 'deleted '.$this->type.' ":subject.name"',
                default => $eventName,
            });
    }
}
