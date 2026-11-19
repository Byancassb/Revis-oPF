<?php

namespace Tests\Feature;

use App\Models\Produto;
use App\Models\User;
use App\Models\Venda;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LojaProdutosTest extends TestCase
{
    use RefreshDatabase;

    private User $ana;
    private User $bruno;
    private User $carla;
    private User $diego;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->ana   = User::where('email', 'ana@loja.test')->firstOrFail();
        $this->bruno = User::where('email', 'bruno@loja.test')->firstOrFail();
        $this->carla = User::where('email', 'carla@loja.test')->firstOrFail();
        $this->diego = User::where('email', 'diego@loja.test')->firstOrFail();
    }

    private function dadosValidos(array $sobrescrever = []): array
    {
        return array_merge([
            'nome'           => 'Liquidificador',
            'categoria'      => 'Casa',
            'garantia_meses' => 12,
            'preco'          => 149.90,
            'estoque'        => 7,
        ], $sobrescrever);
    }

    // R1
    public function test_listagem_publica_mostra_produtos_e_vendedores(): void
    {
        $this->assertSame(5, Produto::count());

        $this->get(route('produtos.index'))
            ->assertOk()
            ->assertSee('Fone Bluetooth')
            ->assertSee('Ana Vendedora')
            ->assertSee('Bruno Vendedor');
    }

    // R2
    public function test_login_com_senha_errada_volta_com_erro(): void
    {
        $this->from(route('login'))
            ->post(route('login.attempt'), ['email' => 'carla@loja.test', 'password' => 'errada'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_correto_e_logout(): void
    {
        $this->post(route('login.attempt'), ['email' => 'carla@loja.test', 'password' => 'senha123'])
            ->assertRedirect();
        $this->assertAuthenticatedAs($this->carla);

        $this->get(route('produtos.index'))->assertSee('Carla Cliente')->assertSee('cliente');

        $this->post(route('logout'));
        $this->assertGuest();
    }

    // R3
    public function test_visitante_e_enviado_ao_login_nas_rotas_protegidas(): void
    {
        $this->get(route('produtos.create'))->assertRedirect(route('login'));
        $this->get(route('compras.create'))->assertRedirect(route('login'));
        $this->get(route('compras.index'))->assertRedirect(route('login'));
        $this->post(route('compras.store'))->assertRedirect(route('login'));
        $this->post(route('produtos.store'), $this->dadosValidos())->assertRedirect(route('login'));
    }

    // R4
    public function test_cliente_recebe_403_e_vendedor_acessa_o_cadastro(): void
    {
        $this->actingAs($this->carla)->get(route('produtos.create'))->assertForbidden();
        $this->actingAs($this->carla)->post(route('produtos.store'), $this->dadosValidos())->assertForbidden();
        $this->assertDatabaseMissing('produtos', ['nome' => 'Liquidificador']);

        $this->actingAs($this->ana)->get(route('produtos.create'))->assertOk();
    }

    // R5
    public function test_formulario_vazio_mostra_erro_em_todos_os_campos(): void
    {
        $this->actingAs($this->ana)
            ->post(route('produtos.store'), [])
            ->assertSessionHasErrors(['nome', 'categoria', 'garantia_meses', 'preco', 'estoque']);
    }

    public function test_regras_de_validacao_do_cadastro(): void
    {
        $this->actingAs($this->ana);

        $this->post(route('produtos.store'), $this->dadosValidos(['garantia_meses' => 48]))
            ->assertSessionHasErrors('garantia_meses');
        $this->post(route('produtos.store'), $this->dadosValidos(['garantia_meses' => -1]))
            ->assertSessionHasErrors('garantia_meses');
        $this->post(route('produtos.store'), $this->dadosValidos(['preco' => 0]))
            ->assertSessionHasErrors('preco');
        $this->post(route('produtos.store'), $this->dadosValidos(['estoque' => -1]))
            ->assertSessionHasErrors('estoque');
        $this->post(route('produtos.store'), $this->dadosValidos(['categoria' => 'Brinquedos']))
            ->assertSessionHasErrors('categoria');
        $this->post(route('produtos.store'), $this->dadosValidos(['nome' => str_repeat('a', 256)]))
            ->assertSessionHasErrors('nome');

        $this->assertDatabaseMissing('produtos', ['nome' => 'Liquidificador']);
    }

    public function test_cadastro_valido_salva_e_redireciona_para_a_listagem(): void
    {
        $this->actingAs($this->ana)
            ->post(route('produtos.store'), $this->dadosValidos())
            ->assertRedirect(route('produtos.index'));

        $this->get(route('produtos.index'))->assertSee('Liquidificador');
    }

    // R6
    public function test_vendedor_id_e_o_usuario_logado_e_nunca_vem_do_formulario(): void
    {
        $this->actingAs($this->ana)->post(route('produtos.store'), $this->dadosValidos(['nome' => 'Item da Ana']));
        $this->actingAs($this->bruno)->post(
            route('produtos.store'),
            $this->dadosValidos(['nome' => 'Item do Bruno', 'vendedor_id' => $this->ana->id])
        );

        $this->assertSame($this->ana->id, Produto::where('nome', 'Item da Ana')->value('vendedor_id'));
        $this->assertSame($this->bruno->id, Produto::where('nome', 'Item do Bruno')->value('vendedor_id'));
    }

    // R7
    public function test_compra_ignora_itens_zerados_e_grava_quantidade_e_preco(): void
    {
        [$a, $b, $c] = Produto::orderBy('id')->take(3)->get()->all();

        $this->actingAs($this->carla)
            ->post(route('compras.store'), ['itens' => [$a->id => 2, $b->id => 1, $c->id => 0]])
            ->assertRedirect(route('compras.index'))
            ->assertSessionHas('sucesso', 'Compra realizada!');

        $this->assertSame(1, Venda::count());

        $venda = Venda::with('produtos')->first();
        $this->assertSame($this->carla->id, $venda->cliente_id);
        $this->assertCount(2, $venda->produtos);

        $itemA = $venda->produtos->firstWhere('id', $a->id);
        $this->assertSame(2, (int) $itemA->pivot->quantidade);
        $this->assertEquals((float) $a->preco, (float) $itemA->pivot->preco_unitario);

        $itemB = $venda->produtos->firstWhere('id', $b->id);
        $this->assertSame(1, (int) $itemB->pivot->quantidade);
    }

    public function test_compra_sem_nenhum_item_e_recusada(): void
    {
        $a = Produto::first();

        $this->actingAs($this->carla)
            ->post(route('compras.store'), ['itens' => [$a->id => 0]])
            ->assertSessionHasErrors('itens');

        $this->assertSame(0, Venda::count());
    }

    // R8
    public function test_minhas_compras_mostra_so_as_do_usuario_logado(): void
    {
        $a = Produto::first();

        $this->actingAs($this->carla)->post(route('compras.store'), ['itens' => [$a->id => 2]]);

        $this->actingAs($this->carla)->get(route('compras.index'))
            ->assertOk()
            ->assertSee($a->nome)
            ->assertSee('Compra #1');

        $this->actingAs($this->diego)->get(route('compras.index'))
            ->assertOk()
            ->assertDontSee($a->nome)
            ->assertDontSee('Compra #1')
            ->assertSee('Você ainda não fez nenhuma compra.');
    }

    // Bônus
    public function test_compra_acima_do_estoque_e_recusada_e_nada_e_gravado(): void
    {
        [$a, $b] = Produto::orderBy('id')->take(2)->get()->all();

        $this->actingAs($this->carla)
            ->post(route('compras.store'), ['itens' => [$a->id => 1, $b->id => $b->estoque + 1]])
            ->assertSessionHasErrors('itens');

        $this->assertSame(0, Venda::count());
        $this->assertSame($a->estoque, $a->fresh()->estoque);
        $this->assertSame($b->estoque, $b->fresh()->estoque);
    }

    public function test_compra_diminui_o_estoque(): void
    {
        $a = Produto::first();
        $antes = $a->estoque;

        $this->actingAs($this->carla)->post(route('compras.store'), ['itens' => [$a->id => 1]]);

        $this->assertSame($antes - 1, $a->fresh()->estoque);
    }

    public function test_pode_comprar_exatamente_o_estoque_restante(): void
    {
        $a = Produto::first();

        $this->actingAs($this->carla)->post(route('compras.store'), ['itens' => [$a->id => $a->estoque]]);

        $this->assertSame(0, $a->fresh()->estoque);
        $this->assertSame(1, Venda::count());
    }
}
