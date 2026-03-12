<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function index(Request $request)
    {
        // Recupera tutti i concorsi per la gestione admin
        $concorsi = DB::table('concorsi')->orderBy('scadenza', 'asc')->get();
        return view('admin.index', ['concorsi' => $concorsi]);
    }
}
