<?php
namespace App\Http\Controllers;
use App\Models\AuditLog;
class AdminAuditController extends Controller
{
    public function index()
    {
        return view('admin.audit.index', ['logs' => AuditLog::with('user')->latest('created_at')->paginate(30)]);
    }
}
