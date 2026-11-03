<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    /**
     * Show the activity log page.
     */
    public function index(Request $request): Response
    {
        $perPage = $request->integer('per_page', 10);
        $sort = $request->string('sort')->toString() ?: 'desc';

        $activities = Activity::query()
            ->with('causer:id,name,email')
            ->when($request->string('search')->toString(), function ($query, string $search) {
                $query->where('description', 'like', "%{$search}%")
                    ->orWhere('log_name', 'like', "%{$search}%")
                    ->orWhere('event', 'like', "%{$search}%")
                    ->orWhereHas('causer', function ($causerQuery) use ($search) {
                        $causerQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            })
            ->when($request->string('date')->toString(), function ($query, string $date) {
                $query->whereDate('created_at', $date);
            })
            ->orderBy('created_at', $sort === 'asc' ? 'asc' : 'desc')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('activity-log/index', [
            'activities' => $activities,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'per_page' => $perPage,
                'sort' => $sort,
                'date' => $request->string('date')->toString(),
            ],
        ]);
    }
}
