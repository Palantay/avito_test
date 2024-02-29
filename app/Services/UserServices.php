<?php

namespace App\Services;

use App\Exceptions\IncorrectEventException;
use App\Exceptions\IncorrectQueryParamException;
use App\Exceptions\InsufficientFundsException;
use App\Exceptions\NotFoundUserException;
use App\Models\Transaction;
use App\Models\User;
use App\Helpers\Constants;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;


class UserServices
{


    public function update(array $data, int $id): JsonResponse
    {
        $user = User::find($id);
        if (!$user) {
            throw new NotFoundUserException();
        }

        switch ($data['event']) {

            case Constants::BALANCE_DOWN_EVENT:

                try {

                    DB::beginTransaction();
                    $response = $this->balanceDown($data, $user);
                    $this->createTransaction($data, $id);
                    DB::commit();

                } catch (InsufficientFundsException $exception) {
                    DB::rollBack();
                    return response()->json(['message' => $exception->getMessage()], 402);

                } catch (\Throwable $exception) {
                    DB::rollBack();
                    return response()->json(['message' => $exception->getMessage()]);
                }
                break;

            case Constants::BALANCE_UP_EVENT:

                try {
                    DB::beginTransaction();
                    $response = $this->balanceUp($data, $user);
                    $this->createTransaction($data, $id);
                    DB::commit();

                } catch (\Throwable $exception) {
                    DB::rollBack();
                    return response()->json(['message' => $exception->getMessage()]);
                }
                break;
            default:
                throw new IncorrectEventException();
        }
        return $response;
    }

    public function show(int $id, array $data): Model
    {
        $user = User::find($id);
        if (!$user) {
            throw new NotFoundUserException();
        }
        if (isset($data['currency'])) {
            $user = $this->calculationUserBalance($user, $data);
        }
        return $user;
    }

    public function getTransactionsByUserID(array $data, int $id): Collection
    {
        $user = User::find($id);
        if (!$user) {
            throw new NotFoundUserException();
        }

        if (isset($data['event']) && !isset($data['created_at']) && !isset($data['amount'])) {
            return $user->transactions()->where('event', $data['event'])->get();
        }

        if (isset($data['event']) && isset($data['created_at']) && !isset($data['amount'])) {
            return $user->transactions()->where('event', $data['event'])->orderBy('created_at', $data['created_at'])->get();
        }

        if (isset($data['event']) && !isset($data['created_at']) && isset($data['amount'])) {
            return $user->transactions()->where('event', $data['event'])->orderBy('amount', $data['amount'])->get();
        }

        if (!isset($data['event']) && isset($data['created_at']) && !isset($data['amount'])) {
            return $user->transactions()->orderBy('created_at', $data['created_at'])->get();
        }

        if (!isset($data['event']) && !isset($data['created_at']) && isset($data['amount'])) {
            return $user->transactions()->orderBy('amount', $data['amount'])->get();
        }

        if (isset($data['event']) && isset($data['created_at']) && isset($data['amount'])) {
            return throw new IncorrectQueryParamException();
        }

        if (!isset($data['event']) && isset($data['created_at']) && isset($data['amount'])) {
            return throw new IncorrectQueryParamException();
        }

        if (!isset($data['event']) && !isset($data['created_at']) && !isset($data['amount'])) {
            return $user->transactions()->get();
        }
        return throw new IncorrectQueryParamException();
    }

    public function userToUser(array $data, int $id, int $toUserID): JsonResponse
    {

        if($id === $toUserID) {
            return response()->json([
                'message' => 'Incorrect data'
            ], 422);
        }

        $user = User::find($id);
        if (!$user) {
            throw new NotFoundUserException();
        }
        $userForTransaction = User::find($toUserID);
        if (!$userForTransaction) {
            throw new NotFoundUserException();
        }

        try {
            DB::beginTransaction();
            $data['event'] = Constants::USER_TO_USER_DOWN_EVENT;
            $this->balanceDown($data, $user);
            $this->createTransaction($data, $id);

            $data['event'] = Constants::USER_TO_USER_UP_EVENT;
            $response = $this->balanceUp($data, $userForTransaction);
            $this->createTransaction($data, $toUserID);
            DB::commit();

        } catch (InsufficientFundsException $exception) {
            DB::rollBack();
            return response()->json(['message' => $exception->getMessage()], 402);
        } catch (\Throwable $exception) {
            DB::rollBack();
            return response()->json(['message' => $exception->getMessage()]);
        }
        return $response;
    }

    private function balanceUp(array $data, Model $user): JsonResponse
    {
        $newBalance = $user['balance'] + $data['amount'];
        $user->update([
            'balance' => $newBalance
        ]);
        return response()->json([
            'message' => 'Transaction successful'
        ], 200);
    }

    private function balanceDown(array $data, Model $user): JsonResponse
    {
        $newBalance = $user['balance'] - $data['amount'];
        if ($newBalance < 0) {
            throw new InsufficientFundsException('Insufficient funds exception');

        }
        $user->update([
            'balance' => $newBalance
        ]);
        return response()->json([
            'message' => 'Transaction successful'
        ], 200);
    }

    private function createTransaction(array $data, int $id): void
    {
        $data['user_id'] = $id;
        Transaction::create($data);
    }

    private function getCurrencyExchangeRate(array $data): array
    {
        $response = Http::timeout(2)->get('https://www.cbr-xml-daily.ru/daily_json.js')->throw();
        return $response->collect('Valute')->get($data['currency']);
    }

    private function calculationUserBalance(Model $user, array $data): Model
    {
        $currency = $this->getCurrencyExchangeRate($data);
        $user['balance'] = round($user['balance'] / $currency['Value']);
        return $user;
    }
}
