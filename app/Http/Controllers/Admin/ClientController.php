<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClientRequest;
use App\Models\Client;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function __construct(protected AuditService $audit) {}
    /** GET /api/admin/clients */
    public function index(Request $request): JsonResponse
    {
        $clients = Client::query()
            ->when($request->search, fn ($q) => $q->where('client_name', 'like', "%{$request->search}%")
                                                   ->orWhere('company', 'like', "%{$request->search}%"))
            ->withCount('inspectionRequests')
            ->orderBy('client_name')
            ->paginate(20);

        return $this->successPaginated('Clients retrieved.', $clients);
    }

    /** POST /api/admin/clients */
    public function store(StoreClientRequest $request): JsonResponse
    {
        $client = Client::create($request->validated());

        $this->audit->log(
            'client_created',
            "Admin membuat data klien baru: {$client->client_name}",
            [
                'user_id'  => $request->user()->id,
                'metadata' => [
                    'client_id'   => $client->id,
                    'client_name' => $client->client_name,
                ],
            ]
        );

        return $this->success('Client created.', $client, 201);
    }

    /** GET /api/admin/clients/{client} */
    public function show(Client $client): JsonResponse
    {
        return $this->success('Client retrieved.', $client->load('inspectionRequests'));
    }

    /** PUT /api/admin/clients/{client} */
    public function update(StoreClientRequest $request, Client $client): JsonResponse
    {
        $client->update($request->validated());

        return $this->success('Client updated.', $client);
    }

    /** DELETE /api/admin/clients/{client} */
    public function destroy(Client $client): JsonResponse
    {
        if ($client->inspectionRequests()->exists()) {
            return $this->error('Cannot delete client with existing inspection requests.', null, 422);
        }

        $client->delete();

        return $this->success('Client deleted.');
    }
}
