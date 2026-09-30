<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ProposalController extends Controller
{
    public function index()
    {
        $this->ensureMember();

        return view('proposals', [
            'proposals' => DB::table('book_proposals')
                ->where('user_id', auth()->id())
                ->select('title', 'author', 'status', 'created_at')
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureMember();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:255'],
        ]);
        DB::table('book_proposals')->insert([
            'user_id' => auth()->id(),
            'title' => $data['title'],
            'author' => $data['author'] ?: null,
        ]);

        return redirect('/proposals')->with('flash', [
            'type' => 'success',
            'message' => 'Usulan koleksi berhasil dikirim.',
        ]);
    }

    private function ensureMember(): void
    {
        abort_unless(auth()->check(), 403);
        if (auth()->user()->role !== 'anggota') {
            abort(redirect('/dashboard'));
        }
    }
}
