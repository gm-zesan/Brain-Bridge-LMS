<?php

namespace App\Services;

use App\Models\LessonSession;
use Carbon\Carbon;

class SlotService
{
    public static function formatSlot($slot)
    {
        $fromDate = $slot->from_date < now()->toDateString()
            ? now()->toDateString()
            : $slot->from_date;

        $from = Carbon::parse($fromDate);
        $to = Carbon::parse($slot->to_date);

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        $dates = self::generateDateRange($from->toDateString(), $to->toDateString());

        $bookedSessions = LessonSession::where('slot_id', $slot->id)
            ->get(['scheduled_date', 'scheduled_start_time', 'scheduled_end_time']);

        $dailyAvailability = [];

        foreach ($dates as $date) {
            $bookedCount = $bookedSessions->where('scheduled_date', $date)->count();
            $availableSeats = $slot->max_students - $bookedCount;

            $dailyAvailability[$date] = [
                'booked' => $bookedCount,
                'available' => max($availableSeats, 0),
            ];
        }

        return [
            'id' => $slot->id,
            'title' => $slot->title,
            'teacher' => $slot->teacher,
            'subject' => $slot->subject,
            'subject_id' => $slot->subject_id,
            'from_date' => $from->toDateString(),
            'to_date' => $to->toDateString(),
            'start_time' => $slot->start_time,
            'end_time' => $slot->end_time,
            'type' => $slot->type,
            'price' => $slot->price,
            'description' => $slot->description,
            'daily_available_seats' => $dailyAvailability,
            'booked_slots' => $bookedSessions,
        ];
    }

    private static function generateDateRange($startDate, $endDate)
    {
        $dates = [];
        $current = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        while ($current->lte($end)) {
            $dates[] = $current->toDateString();
            $current->addDay();
        }

        return $dates;
    }
}
