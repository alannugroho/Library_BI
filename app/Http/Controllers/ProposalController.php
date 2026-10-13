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
                ->select('title', 'author', 'publisher', 'publication_year', 'isbn_issn', 'purpose', 'status', 'created_at')
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureMember();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'publisher' => ['required', 'string', 'max:255'],
            'publication_year' => ['required', 'integer', 'between:1000,2100'],
            'isbn_issn' => ['required', 'string', 'max:50'],
            'purpose' => ['required', 'string', 'max:1000'],
        ], [
            'required' => 'Mohon lengkapi semua data usulan.',
        ]);
        DB::table('book_proposals')->insert([
            'user_id' => auth()->id(),
            'title' => $data['title'],
            'author' => $data['author'],
            'publisher' => $data['publisher'],
            'publication_year' => $data['publication_year'],
            'isbn_issn' => $data['isbn_issn'],
            'purpose' => $data['purpose'],
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
