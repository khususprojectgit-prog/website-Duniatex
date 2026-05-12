<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /** GET /api/admin/users */
    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->when($request->role,   fn ($q) => $q->where('role', $request->role))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%")
                                                   ->orWhere('email', 'like', "%{$request->search}%"))
            ->orderBy('name')
            ->paginate(20);

        return $this->successPaginated('Users retrieved.', $users);
    }

    /** POST /api/admin/users */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:100'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role'     => ['required', Rule::in(['admin', 'qc', 'operator'])],
            'status'   => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        $data['password'] = Hash::make($data['password']);
        $data['status']   = $data['status'] ?? 'active';

        $user = User::create($data);

        return $this->success('User created.', $user, 201);
    }

    /** GET /api/admin/users/{user} */
    public function show(User $user): JsonResponse
    {
        return $this->success('User retrieved.', $user);
    }

    /** PUT /api/admin/users/{user} */
    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name'     => ['sometimes', 'string', 'max:100'],
            'email'    => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => ['sometimes', 'string', 'min:8'],
            'role'     => ['sometimes', Rule::in(['admin', 'qc', 'operator'])],
            'status'   => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        return $this->success('User updated.', $user);
    }

    /** DELETE /api/admin/users/{user} */
    public function destroy(User $user): JsonResponse
    {
        // Prevent self-deletion
        if ($user->id === request()->user()->id) {
            return $this->error('You cannot delete your own account.');
        }

        $user->tokens()->delete();
        $user->delete();

        return $this->success('User deleted.');
    }
}
