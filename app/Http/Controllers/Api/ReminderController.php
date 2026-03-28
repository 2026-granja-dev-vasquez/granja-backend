<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reminder;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ReminderController extends Controller
{
    /**
     * List active reminders.
     */
    public function index()
    {
        $reminders = Reminder::where('is_done', false)
            ->orderBy('remind_at', 'asc')
            ->get();

        return response()->json($reminders);
    }

    /**
     * Store a newly created reminder. (Admin only)
     */
    public function store(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Sin autorización. Solo administradores pueden crear tareas.'], 403);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'remind_at' => 'required|date',
            'frequency' => 'required|in:once,custom_days,monthly',
            'interval_days' => 'nullable|integer|min:1',
        ]);

        $reminder = Reminder::create([
            'user_id' => $request->user()->id,
            'title' => $request->title,
            'description' => $request->description,
            'remind_at' => Carbon::parse($request->remind_at),
            'frequency' => $request->frequency,
            'interval_days' => $request->interval_days,
            'is_done' => false,
        ]);

        return response()->json($reminder, 201);
    }

    /**
     * Mark a reminder as done, and reschedule if recurring. (Admin only)
     */
    public function markAsDone(Request $request, Reminder $reminder)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Sin autorización. Solo administradores pueden cerrar tareas.'], 403);
        }

        if ($reminder->is_done) {
            return response()->json(['message' => 'El recordatorio ya estaba marcado como realizado.'], 400);
        }

        // 1. Mark current task as done
        $reminder->update([
            'is_done' => true,
            'completed_at' => now(),
        ]);

        // 2. Schedule next if recurring
        $nextDate = null;
        $remindAt = Carbon::parse($reminder->remind_at);

        if ($reminder->frequency === 'custom_days' && $reminder->interval_days > 0) {
            $nextDate = $remindAt->copy()->addDays($reminder->interval_days);
        } elseif ($reminder->frequency === 'monthly') {
            $nextDate = $remindAt->copy()->addMonth();
        }

        if ($nextDate) {
            // Keep the same time of day but advance the date
            // Create the new reminder
            Reminder::create([
                'user_id' => $request->user()->id, // Whoever closed it becomes the creator of next
                'title' => $reminder->title,
                'description' => $reminder->description,
                'remind_at' => $nextDate,
                'frequency' => $reminder->frequency,
                'interval_days' => $reminder->interval_days,
                'is_done' => false,
            ]);
        }

        return response()->json(['message' => 'Tarea completada'. ($nextDate ? ' y reprogramada para '.$nextDate->format('d/m/Y') : '')]);
    }

    /**
     * History of completed tasks with date filters. (Admin only)
     */
    public function history(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Sin autorización.'], 403);
        }

        $query = Reminder::where('is_done', true)->orderBy('completed_at', 'desc');

        if ($request->has('from_date')) {
            $query->whereDate('completed_at', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('completed_at', '<=', $request->to_date);
        }

        $history = $query->get();

        return response()->json($history);
    }
}
