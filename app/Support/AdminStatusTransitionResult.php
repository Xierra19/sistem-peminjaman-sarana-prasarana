<?php

namespace App\Support;

enum AdminStatusTransitionResult
{
    case Updated;
    case Expired;
    case Final;
    case PendingRequired;
    case ApprovedRequired;
    case Completed;
}
