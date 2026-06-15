<?php

namespace App\Http\Requests;

use App\Models\Booking;
use Carbon\Carbon;

class UpdateBookingRequest extends StoreBookingRequest
{
    public function authorize(): bool
    {
        $booking = $this->route('booking');

        return $booking instanceof Booking
            && $this->user()?->can('update', $booking) === true;
    }

    protected function minimumStartDate(): Carbon
    {
        /** @var Booking $booking */
        $booking = $this->route('booking');
        $timezone = config('app.business_timezone');
        $originalCutoff = $booking->created_at
            ->copy()
            ->setTimezone($timezone)
            ->startOfDay()
            ->addDays(3);
        $today = Carbon::now($timezone)->startOfDay();

        return $originalCutoff->max($today);
    }
}
