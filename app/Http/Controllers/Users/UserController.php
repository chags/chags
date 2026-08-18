<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\UserRequest;
use App\Models\User;
use App\Support\Authorization\RoleCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('users.view'), 403);

        return Inertia::render('users/index', [
            'users' => User::query()
                ->with('roles:id,name')
                ->orderBy('name')
                ->get()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'cpf' => $user->cpf,
                    'phone' => $user->phone,
                    'birth_date' => $user->birth_date?->format('Y-m-d'),
                    'gender' => $user->gender,
                    'postal_code' => $user->postal_code,
                    'address' => $user->address,
                    'address_number' => $user->address_number,
                    'address_complement' => $user->address_complement,
                    'district' => $user->district,
                    'city' => $user->city,
                    'state' => $user->state,
                    'avatar' => $user->avatar,
                    'role' => $user->roles->first()?->name,
                    'created_at' => $user->created_at?->toIso8601String(),
                    'is_current_user' => $user->is($request->user()),
                ]),
            'canManageSuperAdmins' => $request->user()->hasRole('super-admin'),
            'roles' => collect(RoleCatalog::labels())
                ->map(fn (string $label, string $name) => compact('name', 'label'))
                ->values(),
        ]);
    }

    public function store(UserRequest $request): JsonResponse
    {
        $this->guardRole($request, $request->string('role')->toString());
        $data = $request->validated();
        $role = Arr::pull($data, 'role');

        $user = User::query()->create([
            ...$data,
            'workos_id' => 'local-user-'.Str::uuid(),
            'avatar' => '',
            'email_verified_at' => now(),
        ]);
        $user->syncRoles([$role]);

        return response()->json(['message' => 'Usuário cadastrado com sucesso.'], 201);
    }

    public function update(UserRequest $request, User $user): JsonResponse
    {
        $this->guardTarget($request, $user);
        $this->guardRole($request, $request->string('role')->toString());
        $data = $request->validated();
        $role = Arr::pull($data, 'role');

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data);
        $user->syncRoles([$role]);

        return response()->json(['message' => 'Usuário atualizado com sucesso.']);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->can('users.delete'), 403);
        abort_if($request->user()->is($user), 422, 'Você não pode excluir sua própria conta.');
        $this->guardTarget($request, $user);
        $user->delete();

        return response()->json(['message' => 'Usuário excluído com sucesso.']);
    }

    private function guardTarget(Request $request, User $target): void
    {
        abort_if(
            $target->hasRole('super-admin') && ! $request->user()->hasRole('super-admin'),
            403,
            'Administradores não podem alterar um superadministrador.',
        );
    }

    private function guardRole(Request $request, string $role): void
    {
        abort_if(
            $role === 'super-admin' && ! $request->user()->hasRole('super-admin'),
            403,
            'Apenas um superadministrador pode atribuir esse papel.',
        );
    }
}
