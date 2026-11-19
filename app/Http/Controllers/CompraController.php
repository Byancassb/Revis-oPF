<?php

namespace App\Http\Controllers;

use App\Models\Produto;
use App\Models\Venda;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompraController extends Controller
{
    public function index()
    {
        // só as compras do usuário logado, com os produtos já carregados
        $vendas = Venda::where('cliente_id', Auth::id())
            ->with('produtos')
            ->latest()
            ->latest('id')
            ->get();

        return view('compras.index', compact('vendas'));
    }

    public function create()
    {
        $produtos = Produto::orderBy('nome')->get();

        return view('compras.create', compact('produtos'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'itens'   => ['required', 'array'],
            'itens.*' => ['nullable', 'integer', 'min:0'],
        ], [
            'itens.*.integer' => 'As quantidades devem ser números inteiros.',
            'itens.*.min'     => 'As quantidades não podem ser negativas.',
        ]);

        // itens[id_do_produto] = quantidade; itens com quantidade 0 ficam de fora
        $itens = collect($request->input('itens'))
            ->map(fn ($quantidade) => (int) $quantidade)
            ->filter(fn ($quantidade) => $quantidade > 0);

        if ($itens->isEmpty()) {
            return back()
                ->withErrors(['itens' => 'Informe a quantidade de pelo menos um produto.'])
                ->withInput();
        }

        // Tudo numa transação: se faltar estoque, nada é gravado.
        DB::transaction(function () use ($itens) {
            // lockForUpdate evita que duas compras simultâneas estourem o estoque
            $produtos = Produto::whereIn('id', $itens->keys())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($produtos->count() !== $itens->count()) {
                throw ValidationException::withMessages([
                    'itens' => 'Um dos produtos informados não existe.',
                ]);
            }

            // BÔNUS: recusa a compra se alguma quantidade passar do estoque
            foreach ($itens as $produtoId => $quantidade) {
                $produto = $produtos[$produtoId];

                if ($quantidade > $produto->estoque) {
                    throw ValidationException::withMessages([
                        'itens' => "Estoque insuficiente para \"{$produto->nome}\" "
                            . "(pedido: {$quantidade}, disponível: {$produto->estoque}).",
                    ]);
                }
            }

            $venda = Venda::create(['cliente_id' => Auth::id()]);

            foreach ($itens as $produtoId => $quantidade) {
                $produto = $produtos[$produtoId];

                $venda->produtos()->attach($produto->id, [
                    'quantidade'     => $quantidade,
                    'preco_unitario' => $produto->preco, // preço atual do produto
                ]);

                $produto->decrement('estoque', $quantidade);
            }
        });

        return redirect()->route('compras.index')->with('sucesso', 'Compra realizada!');
    }
}
