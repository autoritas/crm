<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientAlias;
use App\Models\InfonaliaData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientNormalizationController extends Controller
{
    /**
     * GET /api/normalization/unlinked-aliases?company_id=X&limit=50
     * Aliases sin vincular a un cliente normalizado.
     */
    public function unlinkedAliases(Request $request): JsonResponse
    {
        $companyId = $request->integer('company_id', 0);
        $limit = $request->integer('limit', 50);

        $aliases = ClientAlias::whereNull('id_client')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->limit($limit)
            ->get(['id', 'company_id', 'raw_name']);

        return response()->json(['data' => $aliases, 'total' => $aliases->count()]);
    }

    /**
     * GET /api/normalization/similar-clients?company_id=X&limit=50
     * Clientes que podrían ser duplicados (mismo nombre en distintas empresas o similar).
     */
    public function similarClients(Request $request): JsonResponse
    {
        $companyId = $request->integer('company_id', 0);
        $limit = $request->integer('limit', 50);

        $clients = Client::select('id', 'company_id', 'name', 'cif')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return response()->json(['data' => $clients]);
    }

    /**
     * GET /api/normalization/clients?company_id=X&search=term
     * Buscar clientes normalizados.
     */
    public function searchClients(Request $request): JsonResponse
    {
        $companyId = $request->integer('company_id', 0);
        $search = $request->string('search', '');

        $clients = Client::select('id', 'company_id', 'name')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->when($search, fn ($q) => $q->where('name', 'LIKE', "%{$search}%"))
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json(['data' => $clients]);
    }

    /**
     * POST /api/normalization/link-alias
     * Vincular un alias a un cliente normalizado.
     * Body: { alias_id, client_id }
     */
    public function linkAlias(Request $request): JsonResponse
    {
        $request->validate([
            'alias_id' => 'required|integer|exists:client_aliases,id',
            'client_id' => 'required|integer|exists:clients,id',
        ]);

        $alias = ClientAlias::findOrFail($request->alias_id);
        $alias->update(['id_client' => $request->client_id]);

        // Propagar a infonalia_data
        $updated = InfonaliaData::where('company_id', $alias->company_id)
            ->where('cliente', $alias->raw_name)
            ->whereNull('id_client')
            ->update(['id_client' => $request->client_id]);

        return response()->json([
            'success' => true,
            'alias' => $alias->raw_name,
            'client' => Client::find($request->client_id)->name,
            'infonalia_updated' => $updated,
        ]);
    }

    /**
     * POST /api/normalization/create-and-link
     * Crear un cliente nuevo y vincular el alias.
     * Body: { alias_id, name, ?cif }
     */
    public function createAndLink(Request $request): JsonResponse
    {
        $request->validate([
            'alias_id' => 'required|integer|exists:client_aliases,id',
            'name' => 'required|string|max:255',
            'cif' => 'nullable|string|max:20',
        ]);

        $alias = ClientAlias::findOrFail($request->alias_id);

        $client = Client::firstOrCreate(
            ['company_id' => $alias->company_id, 'name' => $request->name],
            ['cif' => $request->cif]
        );

        $alias->update(['id_client' => $client->id]);

        $updated = InfonaliaData::where('company_id', $alias->company_id)
            ->where('cliente', $alias->raw_name)
            ->whereNull('id_client')
            ->update(['id_client' => $client->id]);

        return response()->json([
            'success' => true,
            'client_id' => $client->id,
            'client_name' => $client->name,
            'infonalia_updated' => $updated,
        ]);
    }

    /**
     * POST /api/normalization/merge-clients
     * Fusionar dos clientes (mover aliases + datos del duplicado al principal).
     * Body: { keep_id, merge_id }
     */
    public function mergeClients(Request $request): JsonResponse
    {
        $request->validate([
            'keep_id' => 'required|integer|exists:clients,id',
            'merge_id' => 'required|integer|exists:clients,id',
        ]);

        $keep = Client::findOrFail($request->keep_id);
        $merge = Client::findOrFail($request->merge_id);

        // Mover aliases
        $aliasesMoved = ClientAlias::where('id_client', $merge->id)
            ->update(['id_client' => $keep->id]);

        // Mover infonalia_data
        $infonaliaMoved = InfonaliaData::where('id_client', $merge->id)
            ->update(['id_client' => $keep->id]);

        // Mover ofertas
        $offersMoved = \App\Models\Offer::where('id_client', $merge->id)
            ->update(['id_client' => $keep->id]);

        // Eliminar el duplicado
        $mergeName = $merge->name;
        $merge->delete();

        return response()->json([
            'success' => true,
            'kept' => $keep->name,
            'merged' => $mergeName,
            'aliases_moved' => $aliasesMoved,
            'infonalia_moved' => $infonaliaMoved,
            'offers_moved' => $offersMoved,
        ]);
    }
}
