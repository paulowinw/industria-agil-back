<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'nome',
    'descricao',
    'imagem_principal_path',
    'html_path',
    'pdf_3d_path',
    'pdf_2d_path',
    'projeto_solidworks_path',
    'slt_path',
])]
class Peca extends Model
{
    public const FILE_FIELDS = [
        'imagem_principal' => 'imagem_principal_path',
        'html' => 'html_path',
        'pdf_3d' => 'pdf_3d_path',
        'pdf_2d' => 'pdf_2d_path',
        'projeto_solidworks' => 'projeto_solidworks_path',
        'slt' => 'slt_path',
    ];
}
