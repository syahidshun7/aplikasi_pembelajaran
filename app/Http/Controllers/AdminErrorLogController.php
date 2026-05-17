<?php

namespace App\Http\Controllers;

use App\Models\ErrorLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class AdminErrorLogController extends Controller
{
    public function index(Request $request)
    {
        $query = ErrorLog::query()->latest();

        if ($request->filled('status')) {
            $query->where('status_code', (int) $request->integer('status'));
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', Carbon::parse($request->input('from'))->startOfDay());
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', Carbon::parse($request->input('to'))->endOfDay());
        }

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('message', 'like', "%{$search}%")
                    ->orWhere('url', 'like', "%{$search}%")
                    ->orWhere('exception_class', 'like', "%{$search}%")
                    ->orWhere('trace_id', 'like', "%{$search}%");
            });
        }

        $logs = $query->paginate(20)->withQueryString();

        $stats = [
            'total' => ErrorLog::count(),
            'last_24h' => ErrorLog::where('created_at', '>=', now()->subDay())->count(),
            'last_7d' => ErrorLog::where('created_at', '>=', now()->subDays(7))->count(),
            'by_status' => ErrorLog::selectRaw('status_code, COUNT(*) as cnt')
                ->groupBy('status_code')
                ->orderBy('status_code')
                ->get()
                ->map(fn ($row) => ['status_code' => (int) $row->status_code, 'count' => (int) $row->cnt])
                ->all(),
        ];

        return Inertia::render('ErrorLogs/Index', [
            'logs' => $logs,
            'filters' => [
                'status' => $request->input('status'),
                'from' => $request->input('from'),
                'to' => $request->input('to'),
                'search' => $request->input('search'),
            ],
            'stats' => $stats,
        ]);
    }
}
