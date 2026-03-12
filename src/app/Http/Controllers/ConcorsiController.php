<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

class ConcorsiController extends Controller
{
    public function index(Request $request)
    {
        $q         = trim($request->input('q', ''));
        $regione   = trim($request->input('regione', ''));
        $categoria = trim($request->input('categoria', ''));
        $requisiti = Arr::where($request->input('requisiti', []), fn($v) => trim($v) !== '');
        $matchAll  = $request->input('match_all') === '1';
        $page      = max(1, (int)$request->input('page', 1));
        $perPage   = 10;

        // Liste per i filtri
        $regioni   = DB::table('concorsi')->distinct()->orderBy('regione')->pluck('regione');
        $categorie = DB::table('concorsi')->distinct()->orderBy('categoria')->pluck('categoria');
        $allReq    = DB::table('requisiti')->orderBy('codice')->pluck('codice');

        // Costruzione query dinamica
        $query = DB::table('concorsi as c')->select('c.id', 'c.titolo', 'c.ente', 'c.regione', 'c.categoria', 'c.scadenza', 'c.link_bando');
        $query->where('c.scadenza', '>=', DB::raw('CURDATE()'));
        if ($q !== '') {
            $query->where(function($sub) use ($q) {
                $sub->where('c.titolo', 'like', "%$q%")
                    ->orWhere('c.ente', 'like', "%$q%");
            });
        }
        if ($regione !== '') {
            $query->where('c.regione', $regione);
        }
        if ($categoria !== '') {
            $query->where('c.categoria', $categoria);
        }

        if ($matchAll && !empty($requisiti)) {
            // AND: tutti i requisiti selezionati devono essere presenti
            $sub = DB::table('concorso_requisito as cr')
                ->join('requisiti as r', 'r.id', '=', 'cr.requisito_id')
                ->whereIn('r.codice', $requisiti)
                ->groupBy('cr.concorso_id')
                ->havingRaw('COUNT(DISTINCT r.codice) = ?', [count($requisiti)])
                ->pluck('cr.concorso_id');
            $query->whereIn('c.id', $sub);
        } elseif (!empty($requisiti)) {
            // OR: almeno uno dei requisiti
            $query->join('concorso_requisito as cr', 'cr.concorso_id', '=', 'c.id')
                ->join('requisiti as r', 'r.id', '=', 'cr.requisito_id')
                ->whereIn('r.codice', $requisiti);
        }

        $query->orderBy('c.scadenza', 'asc');

        // Paginazione manuale
        $results = $query->distinct()->get();
        $total = $results->count();
        $items = $results->slice(($page - 1) * $perPage, $perPage)->values();
        $paginator = new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        return view('concorsi.index', [
            'regioni'   => $regioni,
            'categorie' => $categorie,
            'allReq'    => $allReq,
            'concorsi'  => $paginator,
            'q'         => $q,
            'regione'   => $regione,
            'categoria' => $categoria,
            'requisiti' => $requisiti,
            'matchAll'  => $matchAll,
        ]);
    }
}
