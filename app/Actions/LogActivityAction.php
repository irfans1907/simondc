<?php

namespace App\Actions;

use App\Events\ActivityLogChanged;
use Illuminate\Database\Eloquent\Model;

class LogActivityAction extends \Spatie\Activitylog\Actions\LogActivityAction
{
    /**
     * Save the activity and notify listeners that the log changed.
     */
    protected function save(Model $activity): void
    {
        parent::save($activity);

        ActivityLogChanged::dispatch();
    }
}
