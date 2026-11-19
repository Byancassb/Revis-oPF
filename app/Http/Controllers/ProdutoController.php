<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProdutoRequest;
use App\Models\Produto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class ProdutoController extends Controller
{
    public function index()
    {
        // with('vendedor') evita N+1 na coluna "Vendedor"
        $produtos = Produto::with('vendedor')->orderBy('id')->get();

        return view('produtos.index', compact('produtos'));
    }

    public function create()
    {
        return view('produtos.create');
    }

    public function store(StoreProdutoRequest $request): RedirectResponse
    {
        Produto::create($request->validated() + [
            'vendedor_id' => Auth::id(), // definido pelo sistema, nunca pelo formulário
        ]);

        return redirect()->route('produtos.index')
            ->with('sucesso', 'Produto cadastrado com sucesso!');
    }
}
