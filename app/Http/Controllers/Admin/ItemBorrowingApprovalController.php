<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateItemBorrowingStatusRequest;
use App\Models\ItemBorrowing;
use App\Models\ItemBorrowingLog;
use App\Notifications\ItemBorrowingStatusUpdatedNotification;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ItemBorrowingApprovalController extends Controller
{
    public function index()
    {
        $itemBorrowings = ItemBorrowing::with(['user', 'item'])
            ->orderByRaw("
                CASE
                    WHEN status = 'waiting' THEN 1
                    WHEN status = 'approved' THEN 2
                    WHEN status = 'returned' THEN 3
                    WHEN status = 'cancelled' THEN 4
                    WHEN status = 'rejected' THEN 5
                    ELSE 6
                END
            ")
            ->orderBy('borrow_date')
            ->get();

        return Inertia::render('Admin/ItemBorrowings/Index', [
            'itemBorrowings' => $itemBorrowings,
        ]);
    }

    public function show(ItemBorrowing $itemBorrowing)
    {
        $itemBorrowing->load([
            'user',
            'item',
            'logs' => function ($query) {
                $query->with('user')->orderBy('created_at');
            },
        ]);

        return Inertia::render('Admin/ItemBorrowings/Show', [
            'itemBorrowing' => $itemBorrowing,
        ]);
    }

    public function updateStatus(UpdateItemBorrowingStatusRequest $request, ItemBorrowing $itemBorrowing)
    {
        $data = $request->validated();
        $currentStatus = $itemBorrowing->status === 'requested' ? 'waiting' : $itemBorrowing->status;
        $targetStatus = $data['status'];

        if (in_array($currentStatus, ['rejected', 'cancelled', 'returned'], true)) {
            return back()
                ->withErrors(['status' => 'Status peminjaman barang sudah final.'])
                ->with('error', 'Status peminjaman barang sudah final.');
        }

        if (in_array($targetStatus, ['approved', 'rejected'], true) && $currentStatus !== 'waiting') {
            return back()
                ->withErrors(['status' => 'Hanya permintaan yang masih menunggu yang dapat disetujui atau ditolak.'])
                ->with('error', 'Aksi tidak valid untuk status saat ini.');
        }

        if (in_array($targetStatus, ['cancelled', 'returned'], true) && $currentStatus !== 'approved') {
            return back()
                ->withErrors(['status' => 'Hanya peminjaman yang sudah disetujui yang dapat dibatalkan atau ditandai kembali.'])
                ->with('error', 'Aksi tidak valid untuk status saat ini.');
        }

        $updatePayload = [
            'status' => $targetStatus,
        ];

        if ($targetStatus === 'approved') {
            $updatePayload['approved_at'] = now();
            $updatePayload['approved_by'] = Auth::id();
        }

        if ($targetStatus === 'returned') {
            $updatePayload['returned_at'] = now();
            $updatePayload['returned_by'] = Auth::id();
        }

        $itemBorrowing->update($updatePayload);

        $description = match ($targetStatus) {
            'approved' => 'Peminjaman barang disetujui',
            'rejected' => 'Peminjaman barang ditolak',
            'cancelled' => 'Peminjaman barang dibatalkan oleh admin',
            'returned' => 'Barang telah dikembalikan dan diverifikasi admin',
            default => 'Status peminjaman barang diperbarui',
        };

        if (! empty($data['notes'])) {
            $description .= ' - '.$data['notes'];
        }

        ItemBorrowingLog::create([
            'item_borrowing_id' => $itemBorrowing->id,
            'user_id' => Auth::id(),
            'action' => $targetStatus,
            'description' => $description,
        ]);

        $itemBorrowing->load(['user', 'item']);

        if ($itemBorrowing->user && ! empty($itemBorrowing->user->email)) {
            try {
                $itemBorrowing->user->notify(new ItemBorrowingStatusUpdatedNotification(
                    $itemBorrowing,
                    $targetStatus,
                    $data['notes'] ?? null,
                    Auth::user()?->name
                ));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return redirect()
            ->back()
            ->with('success', 'Status peminjaman barang berhasil diperbarui.');
    }
}
