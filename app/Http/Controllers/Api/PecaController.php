<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PecaResource;
use App\Models\Peca;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class PecaController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return PecaResource::collection(Peca::query()->orderBy('id')->paginate());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules(creating: true));
        $peca = new Peca;
        $newPaths = [];

        try {
            $peca->fill(Arr::only($validated, ['nome', 'descricao']));
            $this->applyFiles($peca, $validated, $newPaths);

            if (! $peca->save()) {
                throw new RuntimeException('Unable to save the piece.');
            }
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($newPaths);

            throw $exception;
        }

        return response()->json(['data' => new PecaResource($peca)], 201);
    }

    public function show(Peca $peca): PecaResource
    {
        return new PecaResource($peca);
    }

    public function update(Request $request, Peca $peca): PecaResource
    {
        $validated = $request->validate($this->rules(creating: false));
        $newPaths = [];

        try {
            $peca->fill(Arr::only($validated, ['nome', 'descricao']));
            $oldPaths = $this->applyFiles($peca, $validated, $newPaths);

            if (! $peca->save()) {
                throw new RuntimeException('Unable to save the piece.');
            }
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($newPaths);

            throw $exception;
        }

        $this->deleteStoredFiles($oldPaths);

        return new PecaResource($peca);
    }

    public function destroy(Peca $peca): JsonResponse
    {
        $paths = array_values(array_filter(
            array_map(fn (string $column): ?string => $peca->{$column}, Peca::FILE_FIELDS),
        ));

        if (! $peca->delete()) {
            throw new RuntimeException('Unable to delete the piece.');
        }
        $this->deleteStoredFiles($paths);

        return response()->json(status: 204);
    }

    public function download(Peca $peca, string $field): StreamedResponse
    {
        abort_unless(array_key_exists($field, Peca::FILE_FIELDS), 404);

        $path = $peca->{Peca::FILE_FIELDS[$field]};

        abort_unless(is_string($path) && Storage::disk('local')->exists($path), 404);

        $disk = Storage::disk('local');

        return $field === 'imagem_principal'
            ? $disk->response($path)
            : $disk->download($path);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function rules(bool $creating): array
    {
        return [
            'nome' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'descricao' => ['sometimes', 'nullable', 'string'],
            'imagem_principal' => [
                $creating ? 'required' : 'sometimes',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'extensions:jpg,jpeg,png,webp',
                'max:10240',
            ],
            'html' => [
                'sometimes',
                'nullable',
                'file',
                'mimes:html,htm',
                'extensions:html,htm',
                'max:51200',
            ],
            'pdf_3d' => ['sometimes', 'nullable', 'file', 'mimes:pdf', 'extensions:pdf', 'max:51200'],
            'pdf_2d' => ['sometimes', 'nullable', 'file', 'mimes:pdf', 'extensions:pdf', 'max:51200'],
            'projeto_solidworks' => [
                'sometimes',
                'nullable',
                'file',
                'extensions:sldprt,sldasm,slddrw',
                'max:51200',
            ],
            'slt' => ['sometimes', 'nullable', 'file', 'extensions:slt,stl', 'max:51200'],
        ];
    }

    /**
     * @param  array<int, string>  $newPaths
     * @return array<int, string>
     */
    private function applyFiles(Peca $peca, array $validated, array &$newPaths): array
    {
        $oldPaths = [];

        foreach (Peca::FILE_FIELDS as $field => $column) {
            if (! array_key_exists($field, $validated)) {
                continue;
            }

            $oldPath = $peca->{$column};

            if (is_string($oldPath)) {
                $oldPaths[] = $oldPath;
            }

            $file = $validated[$field];

            if ($file === null) {
                $peca->{$column} = null;

                continue;
            }

            if (! $file instanceof UploadedFile) {
                throw new RuntimeException('Validated piece attachment is not an uploaded file.');
            }

            $path = $file->store('pecas', 'local');

            if (! is_string($path)) {
                throw new RuntimeException('Unable to store the uploaded piece attachment.');
            }

            $newPaths[] = $path;
            $peca->{$column} = $path;
        }

        return $oldPaths;
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function deleteStoredFiles(array $paths): void
    {
        foreach ($paths as $path) {
            $disk = Storage::disk('local');

            if ($disk->exists($path) && ! $disk->delete($path)) {
                throw new RuntimeException('Unable to remove a stored piece attachment.');
            }
        }
    }
}
