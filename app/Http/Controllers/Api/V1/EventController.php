<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventController extends Controller
{
    public function index(): JsonResponse
    {
        $events = Event::with(['category', 'organizer:id,name'])
            ->where('status', 'upcoming')
            ->whereDate('event_date', '>=', today())
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->paginate(15);

        return response()->json($events);
    }

    public function show(Event $event): JsonResponse
    {
        return response()->json($event->load(['category', 'organizer:id,name']));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'venue' => ['required', 'string', 'max:255'],
            'event_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'max_capacity' => ['required', 'integer', 'min:1'],
            'banner_path' => ['nullable', 'string', 'max:255'],
            'registration_deadline' => ['required', 'date', 'before_or_equal:event_date'],
        ]);

        $event = Event::create([
            ...$validated,
            'organizer_id' => $request->user()->id,
            'status' => 'upcoming',
        ]);

        return response()->json($event->load('category'), 201);
    }

    public function update(Request $request, Event $event): JsonResponse
    {
        $this->ensureCanManage($request, $event);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string'],
            'category_id' => ['sometimes', 'required', 'integer', 'exists:categories,id'],
            'venue' => ['sometimes', 'required', 'string', 'max:255'],
            'event_date' => ['sometimes', 'required', 'date', 'after_or_equal:today'],
            'start_time' => ['sometimes', 'required', 'date_format:H:i'],
            'end_time' => ['sometimes', 'required', 'date_format:H:i'],
            'max_capacity' => ['sometimes', 'required', 'integer', 'min:1'],
            'banner_path' => ['nullable', 'string', 'max:255'],
            'registration_deadline' => ['sometimes', 'required', 'date'],
        ]);

        // The end time has to be after the start time, using the new value if one was sent.
        $start = $validated['start_time'] ?? $event->start_time;
        $end = $validated['end_time'] ?? $event->end_time;

        if ($end <= $start) {
            throw ValidationException::withMessages([
                'end_time' => ['The end time must be after the start time.'],
            ]);
        }

        $event->update($validated);

        return response()->json($event->fresh()->load('category'));
    }

    public function destroy(Request $request, Event $event): JsonResponse
    {
        $this->ensureCanManage($request, $event);

        // Cancel instead of deleting, so existing registrations and images stay linked.
        $event->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Event cancelled.']);
    }

    public function register(Request $request, Event $event): JsonResponse
    {
        $user = $request->user();

        if ($event->status !== 'upcoming') {
            return response()->json(['message' => 'This event is not open for registration.'], 422);
        }

        if (now()->greaterThan(Carbon::parse($event->registration_deadline))) {
            return response()->json(['message' => 'The registration deadline has passed.'], 422);
        }

        if ($event->registrations()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'You are already registered for this event.'], 409);
        }

        // Lock the event row so two students can't take the last seat at the same moment.
        $registration = DB::transaction(function () use ($event, $user) {
            $locked = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();

            if ($locked->registrations()->count() >= $locked->max_capacity) {
                return null;
            }

            return EventRegistration::create([
                'event_id' => $locked->id,
                'user_id' => $user->id,
            ]);
        });

        if ($registration === null) {
            return response()->json(['message' => 'This event is full.'], 422);
        }

        return response()->json([
            'message' => 'Registration successful.',
            'registration' => $registration->load('event'),
        ], 201);
    }

    private function ensureCanManage(Request $request, Event $event): void
    {
        $user = $request->user();

        // Admins can manage any event. Organizers can only manage their own.
        if ($user->role?->name === 'admin') {
            return;
        }

        if ($event->organizer_id !== $user->id) {
            abort(403, 'You can only manage your own events.');
        }
    }
}