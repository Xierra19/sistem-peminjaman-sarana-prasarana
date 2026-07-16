<?php

namespace App\Http\Controllers;

use App\Exports\UsersExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ExportController extends Controller
{
    public function exportUsers(Request $request)
    {
        abort_unless($request->user()?->canManageUsers(), 403);

        return Excel::download(new UsersExport, 'users.xlsx');
    }
}
