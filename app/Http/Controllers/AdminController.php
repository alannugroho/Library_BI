<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AdminController extends Controller
{
    public function members()
    {
        if (! $this->ensureLibrarian()) {
            return redirect('/dashboard');
        }

        return view('admin.members', [
            'members' => DB::table('users')
                ->select('id', 'nip', 'email', 'role', 'status', 'created_at')
                ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                ->orderByDesc('created_at')->get(),
        ]);
    }

    public function updateMember(Request $request)
    {
        if (! $this->ensureLibrarian()) {
            return redirect('/dashboard');
        }
        $data = $request->validate([
            'member_id' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'in:active,inactive,pending'],
        ]);
        $updated = DB::table('users')->where('id', $data['member_id'])->where('role', 'eksternal')->where('id', '!=', auth()->id())->update(['status' => $data['status']]);

        return redirect('/admin/members')->with('flash', ['type' => 'success', 'message' => $updated ? 'Status anggota berhasil diperbarui.' : 'Anggota tidak ditemukan atau tidak dapat diubah.']);
    }

    public function proposals()
    {
        if (! $this->ensureLibrarian()) {
            return redirect('/dashboard');
        }

        return view('admin.proposals', [
            'proposals' => DB::table('book_proposals as bp')->join('users as u', 'u.id', '=', 'bp.user_id')
                ->select('bp.id', 'bp.title', 'bp.author', 'bp.publisher', 'bp.status', 'bp.created_at', 'u.email')
                ->orderByRaw("CASE WHEN bp.status = 'submitted' THEN 0 ELSE 1 END")->orderByDesc('bp.created_at')->get(),
        ]);
    }

    public function updateProposal(Request $request)
    {
        if (! $this->ensureLibrarian()) {
            return redirect('/dashboard');
        }
        $data = $request->validate([
            'proposal_id' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'in:reviewed,approved,rejected'],
        ]);
        $updated = DB::table('book_proposals')->where('id', $data['proposal_id'])->where('status', '!=', 'rejected')->update(['status' => $data['status']]);

        return redirect('/admin/proposals')->with('flash', ['type' => 'success', 'message' => $updated ? 'Status usulan berhasil diperbarui.' : 'Usulan tidak ditemukan atau sudah ditolak.']);
    }

    private function ensureLibrarian(): bool
    {
        return auth()->check() && auth()->user()->role === 'pustakawan';
    }
}
