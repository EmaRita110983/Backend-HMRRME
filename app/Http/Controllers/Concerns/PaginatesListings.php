<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Http\Request;

// Paginación opcional para los listados: si el caller no manda "page", el
// comportamiento es EXACTAMENTE el de siempre (->get(), array completo) —
// nada de lo que ya consume estos endpoints hoy se rompe. Con "page", pagina
// de verdad en la base de datos en vez de traer todo para paginar del lado
// del cliente (ver AUDITORIA.md, hallazgo "Ningún listado pagina").
trait PaginatesListings
{
    protected function paginateOrGet(BuilderContract $query, Request $request, int $porPaginaDefault = 15)
    {
        if ($request->filled('page')) {
            return $query->paginate($request->integer('per_page', $porPaginaDefault));
        }

        return $query->get();
    }
}
