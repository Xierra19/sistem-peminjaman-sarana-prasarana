<?php

namespace App\Policies;

use App\Models\ItemBorrowing;
use App\Models\User;

class ItemBorrowingPolicy
{
    public function view(User $user, ItemBorrowing $itemBorrowing): bool
    {
        return $this->owns($user, $itemBorrowing) || $user->canManageItemModule();
    }

    public function edit(User $user, ItemBorrowing $itemBorrowing): bool
    {
        return $this->owns($user, $itemBorrowing)
            && in_array($itemBorrowing->status, ItemBorrowing::EDITABLE_STATUSES, true);
    }

    public function update(User $user, ItemBorrowing $itemBorrowing): bool
    {
        return $this->edit($user, $itemBorrowing);
    }

    public function downloadAttachment(User $user, ItemBorrowing $itemBorrowing): bool
    {
        return $this->view($user, $itemBorrowing);
    }

    public function resubmit(User $user, ItemBorrowing $itemBorrowing): bool
    {
        return $this->owns($user, $itemBorrowing)
            && $itemBorrowing->status === ItemBorrowing::STATUS_REJECTED;
    }

    public function downloadSignedLetter(User $user, ItemBorrowing $itemBorrowing): bool
    {
        return $this->view($user, $itemBorrowing);
    }

    public function cancel(User $user, ItemBorrowing $itemBorrowing): bool
    {
        return $this->owns($user, $itemBorrowing);
    }

    private function owns(User $user, ItemBorrowing $itemBorrowing): bool
    {
        return $itemBorrowing->user_id === $user->id;
    }
}
