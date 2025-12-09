<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\InPersonSlot;
use App\Models\InPersonSlotBook;
use App\Services\PaymentService;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="In Person Slots",
 *     description="API Endpoints for Available Slots and Booking"
 * )
 */
class InPersonSlotController extends Controller
{
    protected $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }
    
    /**
     * @OA\Get(
     *     path="/api/in-person-slots",
     *     summary="Get all available slots (filter by teacher/date)",
     *     tags={"In Person Slots"},
     *     @OA\Parameter(
     *         name="teacher_id",
     *         in="query",
     *         description="Filter slots by teacher ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="date",
     *         in="query",
     *         description="Filter slots by date (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of available slots",
     *         @OA\JsonContent(
     *             @OA\Property(property="slots", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="teacher", type="object",
     *                         @OA\Property(property="id", type="integer"),
     *                         @OA\Property(property="name", type="string"),
     *                         @OA\Property(property="email", type="string")
     *                     ),
     *                     @OA\Property(property="subject", type="object",
     *                         @OA\Property(property="id", type="integer"),
     *                         @OA\Property(property="name", type="string")
     *                     ),
     *                     @OA\Property(property="date", type="string", format="date"),
     *                     @OA\Property(property="time", type="string"),
     *                     @OA\Property(property="available_seats", type="integer")
     *                 )
     *             )
     *         )
     *     )
     * )
    */
    public function index(Request $request)
    {
        $query = InPersonSlot::with('teacher:id,name,email', 'subject:id,name');
        
        // Filter by teacher
        if ($request->filled('teacher_id')) {
            $query->where('teacher_id', $request->teacher_id);
        }
        
        // Filter by date
        if ($request->filled('date')) {
            $query->where('from_date', '<=', $request->date)
                ->where('to_date', '>=', $request->date);
        }

        // Never show past slots
        $query->where('to_date', '>=', now()->toDateString());

        // Pagination result
        $slots = $query->orderBy('id', 'desc')->paginate(10);

        // Filter out fully booked slots
        $filtered = $slots->getCollection()->filter(function ($slot) {

            // Create date range
            $period = CarbonPeriod::create($slot->from_date, $slot->to_date);
            $allDates = collect($period)->map(fn($d) => $d->format('Y-m-d'));

            // Get booked dates
            $bookedDates = InPersonSlotBook::where('slot_id', $slot->id)
                ->pluck('scheduled_date');

            // If all dates are booked, hide this slot
            $fullyBooked = $allDates->diff($bookedDates)->count() === 0;

            return !$fullyBooked; // only show not-fully-booked slots
        });

        // Transform visible slots only
        $slots->setCollection($filtered->values()->map(function ($slot) {
            return [
                'id' => $slot->id,
                'country' => $slot->country,
                'state' => $slot->state,
                'city' => $slot->city,
                'area' => $slot->area,
                'title' => $slot->title,
                'teacher' => $slot->teacher,
                'subject' => $slot->subject,
                'from_date' => $slot->from_date,
                'to_date' => $slot->to_date,
                'time' => date('g:i A', strtotime($slot->start_time)) . ' - ' . date('g:i A', strtotime($slot->end_time)),
                'available_seats' => $slot->max_students - $slot->booked_count,
                'price' => $slot->price,
            ];
        }));

        return response()->json([
            'slots' => $slots
        ], 200);
    }




    /**
     * @OA\Get(
     *     path="/api/in-person-slots/{id}",
     *     summary="Get full details of a slot",
     *     tags={"In Person Slots"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the slot",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Slot details",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="teacher", type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="email", type="string")
     *             ),
     *             @OA\Property(property="subject_id", type="integer"),
     *             @OA\Property(property="available_date", type="string", format="date"),
     *             @OA\Property(property="slots", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="start_time", type="string"),
     *                     @OA\Property(property="end_time", type="string")
     *                 )
     *             ),
     *             @OA\Property(property="price", type="number", format="float"),
     *             @OA\Property(property="description", type="string")
     *         )
     *     )
     * )
    */
    public function show($id)
    {
        $slot = InPersonSlot::with('teacher:id,name,email', 'subject:id,name')
            ->findOrFail($id);

        $fromDate = $slot->from_date < now()->toDateString()
            ? now()->toDateString()
            : $slot->from_date;

        $from = Carbon::parse($fromDate);
        $to = Carbon::parse($slot->to_date);

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        $dates = $this->generateDateRange($from->toDateString(), $to->toDateString());

        $bookedSessions = InPersonSlotBook::where('slot_id', $slot->id)
            ->get(['scheduled_date', 'scheduled_start_time', 'scheduled_end_time']);

        $dailyAvailability = [];

        foreach ($dates as $date) {
            $bookedCount = $bookedSessions
                ->where('scheduled_date', $date)
                ->count();

            $dailyAvailability[$date] = [
                'booked' => $bookedCount,
            ];
        }

        return response()->json([
            'id' => $slot->id,
            'country' => $slot->country,
            'state' => $slot->state,
            'city' => $slot->city,
            'area' => $slot->area,
            'title' => $slot->title,
            'teacher' => $slot->teacher,
            'subject' => $slot->subject,
            'subject_id' => $slot->subject_id,
            'from_date' => $from->toDateString(),
            'to_date' => $to->toDateString(),
            'start_time' => $slot->start_time,
            'end_time' => $slot->end_time,
            'price' => $slot->price,
            'description' => $slot->description,
            'daily_available_seats' => $dailyAvailability,
            'booked_slots' => $bookedSessions,
        ]);
    }


    // Helper function
    private function generateDateRange($start, $end)
    {
        $dates = [];
        $current = Carbon::parse($start);

        while ($current->lte($end)) {
            $dates[] = $current->toDateString();
            $current->addDay();
        }

        return $dates;
    }


    

    /**
     * @OA\Post(
     *     path="/api/in-person-slot/bookings/intent",
     *     summary="Create booking payment intent",
     *     description="Creates a Stripe payment intent for booking a slot. Returns client_secret for frontend payment processing.",
     *     operationId="createBookingIntent",
     *     tags={"In Person Slots"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Slot ID and schedule date for booking",
     *         @OA\JsonContent(
     *             required={"slot_id", "scheduled_date"},
     *             @OA\Property(
     *                 property="slot_id",
     *                 type="integer",
     *                 description="ID of the available slot to book",
     *                 example=1
     *             ),
     *             @OA\Property(
     *                 property="scheduled_date",
     *                 type="string",
     *                 format="date",
     *                 description="Date on which the session will be booked (must be within slot range)",
     *                 example="2025-11-20"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Booking intent created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="requires_payment", type="boolean", example=true),
     *             @OA\Property(property="client_secret", type="string", example="pi_xxxxx_secret_xxxxx"),
     *             @OA\Property(property="payment_intent_id", type="string", example="pi_xxxxx"),
     *             @OA\Property(property="amount", type="number", format="float", example=50.00),
     *             @OA\Property(
     *                 property="slot",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="subject", type="string", example="Mathematics"),
     *                 @OA\Property(property="teacher", type="string", example="John Doe"),
     *                 @OA\Property(property="start_time", type="string", format="date-time", example="2024-12-01 10:00:00"),
     *                 @OA\Property(property="end_time", type="string", format="date-time", example="2024-12-01 11:00:00"),
     *                 @OA\Property(property="price", type="number", format="float", example=50.00)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Slot is full or already booked",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="This slot is already full")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Slot not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Slot not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The slot id field is required."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="slot_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The slot id field is required.")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error - payment intent creation failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Failed to create payment intent"),
     *             @OA\Property(property="error", type="string", example="Stripe API error message")
     *         )
     *     )
     * )
    */

    public function createBookingIntent(Request $request)
    {
        $validated = $request->validate([
            'slot_id' => 'required|exists:available_slots,id',
            'scheduled_date' => 'required',
        ]);

        try {
            $slot = InPersonSlot::with('teacher', 'subject')->findOrFail($validated['slot_id']);

            // Check if price is zero (free session)
            if ($slot->price <= 0) {
                return response()->json([
                    'success' => true,
                    'requires_payment' => false,
                    'message' => 'This is a free session. Proceed to confirm booking.',
                    'slot' => [
                        'id' => $slot->id,
                        'title' => $slot->title,
                        'teacher' => $slot->teacher->name,
                        'start_time' => $slot->start_time,
                        'end_time' => $slot->end_time,
                    ]
                ]);
            }

            $price = $slot->price;

            // Create payment intent
            $paymentIntent = $this->paymentService->createPaymentIntent(
                $price,
                'usd', // or get from config/slot
                [
                    'slot_id' => $slot->id,
                    'student_id' => Auth::id(),
                    'teacher_id' => $slot->teacher_id,
                    'subject' => $slot->subject->name ?? 'Session',
                ]
            );

            if (!$paymentIntent['success']) {
                return response()->json([
                    'message' => 'Failed to create payment intent',
                    'error' => $paymentIntent['error']
                ], 500);
            }

            return response()->json([
                'success' => true,
                'requires_payment' => true,
                'client_secret' => $paymentIntent['client_secret'],
                'payment_intent_id' => $paymentIntent['payment_intent_id'],
                'amount' => $paymentIntent['amount'],
                'slot' => [
                    'id' => $slot->id,
                    'subject' => $slot->subject->name ?? 'Session',
                    'teacher' => $slot->teacher->name,
                    'scheduled_date' => $validated['scheduled_date'],
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->end_time,
                    'price' => $price,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create booking intent'
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/in-person-slot/bookings/confirm",
     *     summary="Confirm booking after payment",
     *     operationId="confirmBooking",
     *     tags={"In Person Slots"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Booking confirmation data",
     *         @OA\JsonContent(
     *             required={"slot_id", "scheduled_date"},
     *             @OA\Property(
     *                 property="slot_id",
     *                 type="integer",
     *                 description="ID of the slot to confirm booking",
     *                 example=1
     *             ),
     *             @OA\Property(
     *                 property="scheduled_date",
     *                 type="date",
     *                 description="Available Date selection by User"
     *             ),
     *             @OA\Property(
     *                 property="payment_intent_id",
     *                 type="string",
     *                 description="Stripe payment intent ID (required for paid sessions, optional for free sessions)",
     *                 example="pi_3QXxxxxxxxxxxxx",
     *                 nullable=true
     *             ),
     *            @OA\Property(property="points_to_use", type="integer", example=100, description="Number of points to use for discount", nullable=true),
     *            @OA\Property(property="new_payment_amount", type="number", format="float", example=79.99, description="New payment amount after applying points", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Booking confirmed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Booking confirmed successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="session_id", type="integer", example=42),
     *                 @OA\Property(property="meeting_link", type="string", example="https://meet.google.com/abc-defg-hij", nullable=true),
     *                 @OA\Property(property="payment_status", type="string", example="paid", enum={"paid", "free"}),
     *                 @OA\Property(property="amount_paid", type="number", format="float", example=50.00)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request - validation or payment error",
     *         @OA\JsonContent(
     *             oneOf={
     *                 @OA\Schema(
     *                     @OA\Property(property="message", type="string", example="This slot is already full")
     *                 ),
     *                 @OA\Schema(
     *                     @OA\Property(property="message", type="string", example="You already booked this slot")
     *                 ),
     *                 @OA\Schema(
     *                     @OA\Property(property="message", type="string", example="Payment intent ID is required")
     *                 ),
     *                 @OA\Schema(
     *                     @OA\Property(property="message", type="string", example="Failed to verify payment")
     *                 ),
     *                 @OA\Schema(
     *                     @OA\Property(property="message", type="string", example="Payment not completed"),
     *                     @OA\Property(property="payment_status", type="string", example="requires_payment_method")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Slot not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Slot not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The slot id field is required."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="slot_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The slot id field is required.")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error - booking confirmation failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Booking confirmation failed. Please contact support.")
     *         )
     *     )
     * )
    */

    public function confirmBooking(Request $request)
    {
        $validated = $request->validate([
            'slot_id' => 'required|exists:available_slots,id',
            'scheduled_date' => 'required',
            'payment_intent_id' => 'nullable|string', // Required for paid slots
            'points_to_use' => 'nullable',
            'new_payment_amount' => 'nullable',
        ]);

        DB::beginTransaction();
        // Lock the slot to prevent race conditions
        $slot = InPersonSlot::where('id', $validated['slot_id'])
            ->with('teacher', 'subject')
            ->lockForUpdate()
            ->firstOrFail();

        // Verify payment if required
        $paymentStatus = 'free';
        $paymentIntentId = null;
        $amountPaid = 0;

        if ($slot->price > 0) {
            if (!$validated['payment_intent_id']) {
                DB::rollBack();
                return response()->json(['message' => 'Payment intent ID is required'], 400);
            }
            $paymentStatus = 'paid';
            $paymentIntentId = $validated['payment_intent_id'];
            $amountPaid = $slot->price;
        }

        if (isset($validated['points_to_use']) && isset($validated['new_payment_amount'])) {
            // Deduct points from user
            $user = Auth::user();
            $pointsToUse = (int)$validated['points_to_use'];
            $user->points -= $pointsToUse;
            $user->save();

            // Adjust amount paid
            $amountPaid = (float)$validated['new_payment_amount'];
        }

        $amountPaid = $amountPaid + 7.95; // add fixed fee

        $data = [
            'slot_id' => $slot->id,
            'teacher_id' => $slot->teacher_id,
            'student_id' => Auth::id(),
            'subject_id' => $slot->subject_id,

            // date + time merged properly
            'scheduled_date' => $validated['scheduled_date'],
            'scheduled_start_time' => $validated['scheduled_date'] . ' ' . $slot->start_time,
            'scheduled_end_time' => $validated['scheduled_date'] . ' ' . $slot->end_time,

            'status' => 'scheduled',
            'price' => (float) $amountPaid,
            'payment_status' => $paymentStatus,
            'payment_intent_id' => $paymentIntentId,
            'payment_method' => $paymentIntentId ? 'stripe' : null,

            'amount_paid' => (float) $amountPaid,
            'currency' => 'usd',

            'paid_at' => $paymentStatus === 'paid' ? now()->toDateTimeString() : null,
        ];


        // Create lesson session
        $session = InPersonSlotBook::create($data);

        // Update slot
        $slot->update(['is_booked' => true]);
        
        DB::commit();

        // Send emails AFTER commit
        // Mail::to($slot->teacher->email)->queue(new SessionBookedMail($session, 'teacher'));
        // Mail::to(Auth::user()->email)->queue(new SessionBookedMail($session, 'student'));

        return response()->json([
            'success' => true,
            'message' => 'Booking confirmed successfully',
            'data' => [
                'session_id' => $session->id,
                'payment_status' => $paymentStatus,
                'amount_paid' => $amountPaid,
            ]
        ]);
    }


    /**
     * @OA\Get(
     *     path="/student/booked-in-person-slots",
     *     summary="Get list of slots booked by the authenticated student",
     *     tags={"In Person Slots"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of booked slots returned successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="slots",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=10),
     *                         @OA\Property(property="slot_id", type="integer", example=2),
     *                         @OA\Property(property="student_id", type="integer", example=4),
     *                         @OA\Property(property="scheduled_date", type="string", format="date", example="2025-11-20"),
     *                         @OA\Property(property="status", type="string", example="scheduled"),
     *
     *                         @OA\Property(
     *                             property="slot",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=2),
     *                             @OA\Property(property="title", type="string", example="Math Class"),
     *                             @OA\Property(property="type", type="string", example="one_to_one"),
     *                             @OA\Property(property="price", type="number", example=50.00),
     *
     *                             @OA\Property(
     *                                 property="teacher",
     *                                 type="object",
     *                                 @OA\Property(property="id", type="integer", example=7),
     *                                 @OA\Property(property="name", type="string", example="John Doe"),
     *                                 @OA\Property(property="email", type="string", example="john@example.com"),
     *                             ),
     *
     *                             @OA\Property(
     *                                 property="subject",
     *                                 type="object",
     *                                 @OA\Property(property="id", type="integer", example=1),
     *                                 @OA\Property(property="name", type="string", example="Mathematics"),
     *                             )
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(property="first_page_url", type="string", example="http://localhost/api/student/booked-slots?page=1"),
     *                 @OA\Property(property="from", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=3),
     *                 @OA\Property(property="last_page_url", type="string", example="http://localhost/api/student/booked-slots?page=3"),
     *                 @OA\Property(property="next_page_url", type="string", example=null),
     *                 @OA\Property(property="path", type="string", example="http://localhost/api/student/booked-slots"),
     *                 @OA\Property(property="per_page", type="integer", example=10),
     *                 @OA\Property(property="prev_page_url", type="string", example=null),
     *                 @OA\Property(property="to", type="integer", example=10),
     *                 @OA\Property(property="total", type="integer", example=27)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
    */

    public function studentBookedSlots()
    {
        $slots = InPersonSlotBook::with('slot.teacher', 'slot.subject')
            ->where('student_id', Auth::id())
            ->orderBy('scheduled_date', 'desc')
            ->paginate(10);

        return response()->json([
            'slots' => $slots
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/teacher/in-person-slots",
     *     summary="Teacher creates one or more slots",
     *     tags={"In Person Slots"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="subject_id", type="integer", example=1),
     *             @OA\Property(property="from_date", type="string", format="date"),
     *             @OA\Property(property="to_date", type="string", format="date"),
     *             @OA\Property(
     *                 property="slots",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="start_time", type="string", example="09:00"),
     *                     @OA\Property(property="end_time", type="string", example="11:00"),
     *                 )
     *             ),
     *             @OA\Property(property="price", type="number", example=50),
     *             @OA\Property(property="description", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Slots created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="title", type="string"),
     *                     @OA\Property(property="teacher_id", type="integer"),
     *                     @OA\Property(property="subject_id", type="integer"),
     *                     @OA\Property(property="from_date", type="string", format="date"),
     *                     @OA\Property(property="to_date", type="string", format="date"),
     *                     @OA\Property(property="start_time", type="string"),
     *                     @OA\Property(property="end_time", type="string"),
     *                     @OA\Property(property="price", type="number"),
     *                     @OA\Property(property="description", type="string")
     *                 )
     *             )
     *         )
     *     )
     * )
    */

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string',
            'country' => 'nullable',
            'state' => 'nullable',
            'city' => 'nullable',
            'area' => 'nullable|string|max:255',
            'subject_id' => 'required|exists:subjects,id',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'slots' => 'required|array|min:1',
            'slots.*.start_time' => 'required|date_format:H:i',
            'slots.*.end_time' => 'required|date_format:H:i|after:slots.*.start_time',
            'price' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
        ]);


        $created = [];

        foreach ($validated['slots'] as $slot) {
            $created[] = InPersonSlot::create([
                'country' => $validated['country'],
                'state' => $validated['state'],
                'city' => $validated['city'],
                'area' => $validated['area'],
                'title' => $validated['title'],
                'teacher_id' => Auth::id(),
                'subject_id' => $validated['subject_id'],
                'from_date' => $validated['from_date'],
                'to_date' => $validated['to_date'],
                'start_time' => $slot['start_time'],
                'end_time' => $slot['end_time'],
                'price' => $validated['price'] ?? 0,
                'description' => $validated['description'] ?? null,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Slots created successfully',
            'data' => $created
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/teacher/in-person-slots/{id}",
     *     summary="Update an available slot",
     *     description="Teacher updates their own slot. Cannot update if the slot has bookings. All fields are optional.",
     *     operationId="updateSlot",
     *     tags={"In Person Slots"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the slot to update",
     *         @OA\Schema(
     *             type="integer",
     *             example=1
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         description="Fields to update (all optional)",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="title",
     *                 type="string",
     *                 description="Slot title",
     *                 example="Advanced Mathematics Session"
     *             ),
     *             @OA\Property(
     *                 property="subject_id",
     *                 type="integer",
     *                 description="Subject ID",
     *                 example=1
     *             ),
     *             @OA\Property(
     *                 property="from_date",
     *                 type="string",
     *                 format="date",
     *                 description="Start date (YYYY-MM-DD)",
     *                 example="2024-12-01"
     *             ),
     *             @OA\Property(
     *                 property="to_date",
     *                 type="string",
     *                 format="date",
     *                 description="End date (YYYY-MM-DD), must be after or equal to from_date",
     *                 example="2024-12-31"
     *             ),
     *             @OA\Property(
     *                 property="start_time",
     *                 type="string",
     *                 format="time",
     *                 description="Start time in HH:MM format (24-hour)",
     *                 example="14:00"
     *             ),
     *             @OA\Property(
     *                 property="end_time",
     *                 type="string",
     *                 format="time",
     *                 description="End time in HH:MM format (24-hour), must be after start_time",
     *                 example="15:30"
     *             ),
     *             @OA\Property(
     *                 property="price",
     *                 type="number",
     *                 format="float",
     *                 description="Price per session (nullable, minimum 0)",
     *                 example=50.00,
     *                 nullable=true
     *             ),
     *             @OA\Property(
     *                 property="description",
     *                 type="string",
     *                 description="Slot description",
     *                 example="This session covers advanced calculus topics including derivatives and integrals.",
     *                 nullable=true
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Slot updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Slot updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="teacher_id", type="integer", example=10),
     *                 @OA\Property(property="title", type="string", example="Advanced Mathematics Session"),
     *                 @OA\Property(property="subject_id", type="integer", example=1),
     *                 @OA\Property(property="from_date", type="string", format="date", example="2024-12-01"),
     *                 @OA\Property(property="to_date", type="string", format="date", example="2024-12-31"),
     *                 @OA\Property(property="start_time", type="string", example="14:00:00"),
     *                 @OA\Property(property="end_time", type="string", example="15:30:00"),
     *                 @OA\Property(property="price", type="number", format="float", example=50.00),
     *                 @OA\Property(property="is_booked", type="boolean", example=false),
     *                 @OA\Property(property="description", type="string", example="Advanced calculus session"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-11-16T10:30:00.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2024-11-16T12:45:00.000000Z")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Cannot update - slot has bookings",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Cannot update a booked slot")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - not the slot owner",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Slot not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Slot not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The end time must be a time after start time."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="end_time",
     *                     type="array",
     *                     @OA\Items(type="string", example="The end time must be a time after start time.")
     *                 ),
     *                 @OA\Property(
     *                     property="to_date",
     *                     type="array",
     *                     @OA\Items(type="string", example="The to date must be a date after or equal to from date.")
     *                 ),
     *                 @OA\Property(
     *                     property="subject_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The selected subject id is invalid.")
     *                 )
     *             )
     *         )
     *     )
     * )
    */

    public function update(Request $request, InPersonSlot $inPersonSlot)
    {
        $validated = $request->validate([
            'title' => 'sometimes|string',
            'country' => 'nullable',
            'state' => 'nullable',
            'city' => 'nullable',
            'area' => 'nullable|string|max:255',
            'subject_id' => 'sometimes|exists:subjects,id',
            'from_date' => 'sometimes|date',
            'to_date' => 'sometimes|date|after_or_equal:from_date',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i|after:start_time',
            'price' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        $inPersonSlot->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Slot updated successfully',
            'data' => $inPersonSlot
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/teacher/in-person-slots/{id}",
     *     summary="Teacher deletes a slot",
     *     tags={"In Person Slots"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Slot deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
    */

    public function destroy(InPersonSlot $inPersonSlot)
    {
        $inPersonSlot->delete();

        return response()->json([
            'success' => true,
            'message' => 'Slot deleted successfully'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/teacher/in-person-slots",
     *     summary="Teacher views their own slots",
     *     tags={"In Person Slots"},
     *     @OA\Response(
     *         response=200,
     *         description="Teacher's slots",
     *     )
     * )
    */
    public function mySlots()
    {
        $query = InPersonSlot::where('teacher_id', Auth::id())
                ->with('subject')
                ->orderBy('from_date', 'asc')
                ->orderBy('start_time', 'asc');

        $slots = $query->paginate(10);

        return response()->json([
            'success' => true,
            'slots' => $slots
        ]);
    }



    /**
     * @OA\Get(
     *     path="teacher/in-person-slots/booked",
     *     summary="Teacher views their own booked slots",
     *     tags={"In Person Slots"},
     *     @OA\Response(
     *         response=200,
     *         description="Teacher's slots",
     *     )
     * )
    */

    public function bookedSlots()
    {
        $slots = InPersonSlot::where('teacher_id', Auth::id())
            ->where('is_booked', true)
            ->with(['subject', 'session', 'session.student'])
            ->orderBy('from_date', 'asc')
            ->orderBy('start_time', 'asc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'slots' => $slots
        ]);
    }

}
