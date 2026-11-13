<?php

namespace App\Http\Resources;

use App\Models\Peca;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PecaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $files = [];

        foreach (Peca::FILE_FIELDS as $field => $column) {
            $files[$field] = $this->{$column} === null
                ? null
                : route('pecas.files.show', [$this->id, $field]);
        }

        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'descricao' => $this->descricao,
            ...$files,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
