<?php

namespace App\Support;

enum CancellationResult
{
    case Cancelled;
    case AlreadyCancelled;
    case ExpiredNow;
    case Expired;
    case NotAllowed;
}
