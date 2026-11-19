<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Produto extends Model
{
    // vendedor_id é definido pelo sistema (Auth::id()), nunca vem do formulário;
    // o controller usa apenas $request->validated(), então é seguro deixá-lo aqui.
    protected $fillable = ['nome', 'categoria', 'garantia_meses', 'preco', 'estoque', 'vendedor_id'];

    protected function casts(): array
    {
        return [
            'garantia_meses' => 'integer',
            'estoque'        => 'integer',
            'preco'          => 'decimal:2',
        ];
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }
}
