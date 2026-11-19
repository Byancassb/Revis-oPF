<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProdutoRequest extends FormRequest
{
    /**
     * O acesso já é controlado pelos middlewares auth e vendedor.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome'           => ['required', 'string', 'max:255'],
            'categoria'      => ['required', Rule::in(['Eletrônicos', 'Vestuário', 'Casa', 'Alimentos'])],
            'garantia_meses' => ['required', 'integer', 'between:0,36'],
            'preco'          => ['required', 'numeric', 'gt:0'],
            'estoque'        => ['required', 'integer', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nome'           => 'nome',
            'categoria'      => 'categoria',
            'garantia_meses' => 'garantia (meses)',
            'preco'          => 'preço',
            'estoque'        => 'estoque',
        ];
    }

    public function messages(): array
    {
        return [
            'required'  => 'O campo :attribute é obrigatório.',
            'string'    => 'O campo :attribute deve ser um texto.',
            'max'       => 'O campo :attribute deve ter no máximo :max caracteres.',
            'in'        => 'Escolha uma categoria válida.',
            'integer'   => 'O campo :attribute deve ser um número inteiro.',
            'numeric'   => 'O campo :attribute deve ser um número.',
            'between'   => 'O campo :attribute deve estar entre :min e :max.',
            'gt'        => 'O campo :attribute deve ser maior que zero.',
            'estoque.min' => 'O estoque não pode ser negativo.',
        ];
    }
}
