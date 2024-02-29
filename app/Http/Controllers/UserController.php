<?php

namespace App\Http\Controllers;

use App\Exceptions\NotFoundUserException;
use App\Http\Requests\User\FilterRequest;
use App\Http\Requests\User\ShowRequest;
use App\Http\Requests\User\StoreRequest;
use App\Http\Requests\User\TransactionsUserToUserRequest;
use App\Http\Requests\User\UpdateBalanceRequest;
use App\Http\Resources\User\GetTransactionsByUserIdResource;
use App\Http\Resources\User\IndexResource;
use App\Http\Resources\User\ShowResource;
use App\Http\Resources\User\StoreResource;
use App\Models\User;
use App\Services\UserServices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;


class UserController extends Controller
{

    public UserServices $service;

    public function __construct(UserServices $service)
    {
        $this->service = $service;
    }

    public function index(): JsonResource
    {
        $users = User::all();
        return IndexResource::collection($users);
    }

    public function store(StoreRequest $request): JsonResource
    {
        $data = $request->validated();
        $user = User::create($data);
        return StoreResource::make($user);
    }

    public function show(ShowRequest $request, int $id): JsonResource
    {
        $data= $request->validated();
        $user = $this->service->show($id, $data);
        return ShowResource::make($user);
    }

    public function destroy(int $id): JsonResponse
    {
        $user = User::find($id);
        if (!$user) {
            throw new NotFoundUserException();
        }
        $user->delete();
        return response()->json(['message' => 'User deleted successfully'], 200);
    }

    public function updateBalance(UpdateBalanceRequest $request, int $id): JsonResponse
    {
        $data = $request->validated();
        return $this->service->update($data, $id);
    }

    public function getTransactionsByUserID(FilterRequest $request, int $id): JsonResource
    {
        $data = $request->validated();
        $transactions = $this->service->getTransactionsByUserID($data, $id);
        return GetTransactionsByUserIdResource::collection($transactions);
    }

    public function transactionsUserToUser(TransactionsUserToUserRequest $request, int $id, int $toUserID): JsonResponse
    {
        $data = $request->validated();
        return $this->service->userToUser($data, $id, $toUserID);
    }
}
