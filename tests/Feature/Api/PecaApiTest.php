<?php

namespace Tests\Feature\Api;

use App\Models\Peca;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PecaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_peca_endpoints_return_401_without_authentication(): void
    {
        $peca = Peca::query()->create([
            'nome' => 'Peça de teste',
            'imagem_principal_path' => 'pecas/main.png',
        ]);

        $this->getJson('/api/v1/pecas')->assertUnauthorized();
        $this->postJson('/api/v1/pecas')->assertUnauthorized();
        $this->getJson("/api/v1/pecas/{$peca->id}/files/imagem_principal")->assertUnauthorized();
    }

    public function test_authenticated_user_can_create_read_update_and_delete_peca_files(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;

        $created = $this->withToken($token)->post('/api/v1/pecas', [
            'nome' => 'Conector',
            'descricao' => 'Peça para montagem.',
            'imagem_principal' => UploadedFile::fake()->image('conector.png'),
            'html' => UploadedFile::fake()->create('conector.html', 10, 'text/html'),
            'pdf_3d' => UploadedFile::fake()->create('conector-3d.pdf', 100, 'application/pdf'),
            'pdf_2d' => UploadedFile::fake()->create('conector-2d.pdf', 100, 'application/pdf'),
            'projeto_solidworks' => UploadedFile::fake()->create('conector.sldprt', 100),
            'slt' => UploadedFile::fake()->create('conector.slt', 100),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.nome', 'Conector')
            ->assertJsonPath('data.descricao', 'Peça para montagem.');

        $pecaId = $created->json('data.id');
        $created->assertJsonPath('data.html', route('pecas.files.show', [$pecaId, 'html']));
        $peca = Peca::query()->findOrFail($pecaId);
        $originalPdfPath = $peca->pdf_2d_path;
        $originalHtmlPath = $peca->html_path;

        Storage::disk('local')->assertExists([
            $peca->imagem_principal_path,
            $peca->html_path,
            $peca->pdf_3d_path,
            $peca->pdf_2d_path,
            $peca->projeto_solidworks_path,
            $peca->slt_path,
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/pecas')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withToken($token)
            ->getJson("/api/v1/pecas/{$pecaId}")
            ->assertOk()
            ->assertJsonPath('data.nome', 'Conector')
            ->assertJsonPath('data.pdf_2d', route('pecas.files.show', [$pecaId, 'pdf_2d']));

        $imageResponse = $this->withToken($token)
            ->getJson("/api/v1/pecas/{$pecaId}/files/imagem_principal")
            ->assertOk();
        $this->assertStringStartsWith('inline;', $imageResponse->headers->get('content-disposition') ?? '');

        $this->call(
            'PUT',
            "/api/v1/pecas/{$pecaId}",
            ['nome' => 'Conector revisado', 'descricao' => null, 'html' => null],
            [],
            ['pdf_2d' => UploadedFile::fake()->create('conector-revisado.pdf', 100, 'application/pdf')],
            [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => "Bearer {$token}",
            ],
        )->assertOk()
            ->assertJsonPath('data.nome', 'Conector revisado')
            ->assertJsonPath('data.descricao', null)
            ->assertJsonPath('data.html', null);

        $peca->refresh();
        $this->assertNotSame($originalPdfPath, $peca->pdf_2d_path);
        $this->assertDatabaseHas('pecas', [
            'id' => $pecaId,
            'nome' => 'Conector revisado',
            'descricao' => null,
        ]);
        Storage::disk('local')->assertMissing([$originalPdfPath, $originalHtmlPath]);
        Storage::disk('local')->assertExists($peca->pdf_2d_path);

        $this->withToken($token)
            ->deleteJson("/api/v1/pecas/{$pecaId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('pecas', ['id' => $pecaId]);
        Storage::disk('local')->assertMissing([
            $peca->imagem_principal_path,
            $peca->pdf_3d_path,
            $peca->pdf_2d_path,
            $peca->projeto_solidworks_path,
            $peca->slt_path,
        ]);
    }

    public function test_creation_requires_a_name_and_main_image(): void
    {
        $this->postJson('/api/v1/pecas', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nome', 'imagem_principal']);

        $this->assertDatabaseCount('pecas', 0);
    }

    public function test_creation_rejects_a_non_image_as_the_main_image(): void
    {
        Storage::fake('local');

        $this->post('/api/v1/pecas', [
            'nome' => 'Invalid image',
            'imagem_principal' => UploadedFile::fake()->create('not-an-image.txt', 10, 'text/plain'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('imagem_principal');

        $this->assertDatabaseCount('pecas', 0);
    }
}
