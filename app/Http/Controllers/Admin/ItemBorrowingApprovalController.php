<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateItemBorrowingStatusRequest;
use App\Models\ItemBorrowing;
use App\Services\ItemBorrowingApprovalWorkflow;
use App\Support\AdminStatusTransitionResult;
use Inertia\Inertia;

class ItemBorrowingApprovalController extends Controller
{
    public function index()
    {
        $itemBorrowings = ItemBorrowing::with(['user', 'items.item', 'singleItem'])
            ->orderByRaw("
                CASE
                    WHEN status = 'waiting' THEN 1
                    WHEN status = 'needs_revision' THEN 2
                    WHEN status = 'approved' THEN 3
                    WHEN status = 'returned' THEN 4
                    WHEN status = 'cancelled' THEN 5
                    WHEN status = 'rejected' THEN 6
                    ELSE 7
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
            'items.item',
            'singleItem', // legacy
            'logs' => function ($query) {
                $query->with('user')->orderBy('created_at');
            },
        ]);

        return Inertia::render('Admin/ItemBorrowings/Show', [
            'itemBorrowing' => $itemBorrowing,
        ]);
    }

    public function updateStatus(
        UpdateItemBorrowingStatusRequest $request,
        ItemBorrowing $itemBorrowing,
        ItemBorrowingApprovalWorkflow $workflow,
    ) {
        $data = $request->validated();
        $result = $workflow->update(
            $itemBorrowing,
            $data['status'],
            $data['notes'] ?? null,
            $request->file('signed_letter'),
            $request->user(),
        );

        if ($result === AdminStatusTransitionResult::Final) {
            return back()
                ->withErrors(['status' => 'Status peminjaman barang sudah final.'])
                ->with('error', 'Status peminjaman barang sudah final.');
        }

        if ($result === AdminStatusTransitionResult::PendingRequired) {
            return back()
                ->withErrors(['status' => 'Hanya permintaan yang masih menunggu yang dapat diproses oleh admin.'])
                ->with('error', 'Aksi tidak valid untuk status saat ini.');
        }

        if ($result === AdminStatusTransitionResult::ApprovedRequired) {
            return back()
                ->withErrors(['status' => 'Hanya peminjaman yang sudah disetujui yang dapat dibatalkan atau ditandai kembali.'])
                ->with('error', 'Aksi tidak valid untuk status saat ini.');
        }

        if ($result === AdminStatusTransitionResult::Completed) {
            return back()
                ->withErrors(['status' => 'Peminjaman yang waktunya sudah selesai tidak dapat dibatalkan.'])
                ->with('error', 'Aksi tidak valid untuk status saat ini.');
        }

        return redirect()
            ->back()
            ->with('success', 'Status peminjaman barang berhasil diperbarui.');
    }
}
