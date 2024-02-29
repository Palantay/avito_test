<?php

namespace Tests\Feature;

use App\Helpers\Constants;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    //Элементарную логику не тестировал.

    /** @test */

    public function test_the_method_show_returns_correct_balance_values(): void
    {
        $balanceNewUser = 3000;

        /* Создадим пользователя и проверим корректность его создания. */
        $newUser = $this->post('api/users/', ['balance' => $balanceNewUser]);
        $newUser->assertCreated();

        $user = $this->get('api/users/' . $newUser['data']['id']);
        $user->assertOk();

        $this->assertEquals($user['data']['id'], $newUser['data']['id']);
        $this->assertEquals($user['data']['balance'], $balanceNewUser);

        /*
         Проверим корректно ли рассчитываются данные если пользователю нужно показать их в валюте.
         Для этого сделаем запрос к api валютных курсов. Рассчитаем какие должны быть значения
         и сравним с теми значениями, что приходят от приложения.
         */

        // Делаем запрос на получение курсов валют
        $currencyData = $this->getCurrencyExchangeRate();

        $USD = 'USD';
        $user = $this->get('api/users/' . $newUser['data']['id'] . '?currency=' . $USD);
        $balanceInUSD = $this->calculationUserBalance($balanceNewUser, $USD, $currencyData);
        $this->assertEquals($user['data']['balance'], $balanceInUSD);

        $EUR = 'EUR';
        $user = $this->get('api/users/' . $newUser['data']['id'] . '?currency=' . $EUR);
        $balanceInEUR = $this->calculationUserBalance($balanceNewUser, $EUR, $currencyData);
        $this->assertEquals($user['data']['balance'], $balanceInEUR);

        $CNY = 'CNY';
        $user = $this->get('api/users/' . $newUser['data']['id'] . '?currency=' . $CNY);
        $balanceInCNY = $this->calculationUserBalance($balanceNewUser, $CNY, $currencyData);
        $this->assertEquals($user['data']['balance'], $balanceInCNY);

        // Проверим реакцию приложения, если ввести валюту, которая не поддерживается.
        $AZN = 'AZN';
        $user = $this
            ->withHeaders([
                'Accept' => 'application/json',
            ])->get('api/users/' . $newUser['data']['id'] . '?currency=' . $AZN);
        $user->assertStatus(422);

    }

    public function test_the_method_update_balance(): void
    {
        $balanceNewUser = 300;

        // Создадим нового пользователя с заданными параметрами.
        $newUser = $this->post('api/users/', ['balance' => $balanceNewUser]);
        $newUser->assertCreated();
        $userId = $newUser['data']['id'];

        /*
        Проверка списания средств.
        Передаем параметры, а после этого получаем пользователя и сравниваем с расчетными данными.
         */
        $amount = 30;
        $data = [
            'amount' => $amount,
            'event' => Constants::BALANCE_DOWN_EVENT,
        ];

        $response = $this->post('api/users/' . $userId . '/transaction', $data);
        $response->assertOk();

        $balance = $balanceNewUser - $amount;

        $user = $this->get('api/users/' . $userId);
        $this->assertEquals($balance, $user['data']['balance']);

        // Проверяем создалась ли транзакция списания средств. А так же правильность её параметров.
        $response = $this->get('api/users/' . $userId . '/transaction');
        $transaction = $response['data'][0];

        $this->assertEquals($transaction['user_id'], $userId);
        $this->assertEquals($transaction['amount'], $amount);
        $this->assertEquals(Constants::BALANCE_DOWN_EVENT, $transaction['event']);

        /*
        Проверка начисления средств.
        Передаем параметры, а после этого получаем пользователя и сравниваем с расчетными данными.
         */
        $data = [
            'amount' => $amount,
            'event' => Constants::BALANCE_UP_EVENT,
        ];
        $balanceNewUser = 270;

        $response = $this->post('api/users/' . $userId . '/transaction', $data);
        $response->assertOk();

        $balance = $balanceNewUser + $amount;

        $user = $this->get('api/users/' . $userId);
        $this->assertEquals($balance, $user['data']['balance']);

        // Проверяем создалась ли транзакция начисления средств. А так же правильность её параметров.
        $response = $this->get('api/users/' . $userId . '/transaction');
        $transaction = $response['data'][1];

        $this->assertEquals($transaction['user_id'], $userId);
        $this->assertEquals($transaction['amount'], $amount);
        $this->assertEquals(Constants::BALANCE_UP_EVENT, $transaction['event']);

        // Проверяем ситуацию, когда пользователь не найден.
        $amount = 100;
        $data = [
            'amount' => $amount,
            'event' => Constants::BALANCE_UP_EVENT,
        ];
        $nonExistentId = 100;

        $response = $this->post('api/users/' . $nonExistentId . '/transaction', $data);
        $response->assertStatus(404);

        // Проверяем создалась ли транзакция в этом случаи.

        $response = $this->get('api/users/' . $userId . '/transaction');
        $response->assertOk();
        $transactions = $response['data'];
        $this->assertEquals(false, isset($transactions[2]));

        // Проверяем ситуацию, когда сумма списываемых средств больше, чем есть на балансе.
        $amount = 500;
        $data = [
            'amount' => $amount,
            'event' => Constants::BALANCE_DOWN_EVENT,
        ];

        $response = $this->post('api/users/' . $userId . '/transaction', $data);
        $response->assertStatus(402);

        // Проверяем создалась ли транзакция в этом случаи.

        $response = $this->get('api/users/' . $userId . '/transaction');
        $response->assertOk();
        $transactions = $response['data'];
        $this->assertEquals(false, isset($transactions[2]));

    }

    public function test_the_method_transaction_user_to_user()
    {
        // Протестируем транзакцию от пользователя к пользователю.
        $balance = 300;

        $user = $this->post('api/users/', ['balance' => $balance]);
        $user->assertCreated();
        $userId = $user['data']['id'];

        $userForTransaction = $this->post('api/users/', ['balance' => $balance]);
        $user->assertCreated();
        $userIdForTransaction = $userForTransaction['data']['id'];

        // Списываем деньги с одного пользователя и начисляем другому.
        $data = [
            'amount' => 30
        ];

        $request = $this->post('api/users/' . $userId . '/transaction/' . $userIdForTransaction, $data);
        $request->assertOk();

        //Проверяем списание и начисление средств пользователям.
        $balanceUser = $balance - $data['amount'];

        $user = $this->get('api/users/' . $userId);
        $user->assertOk();
        $this->assertEquals($balanceUser, $user['data']['balance']);

        $balanceUserForTransaction = $balance + $data['amount'];

        $userForTransaction = $this->get('api/users/' . $userIdForTransaction);
        $userForTransaction->assertOk();
        $this->assertEquals($balanceUserForTransaction, $userForTransaction['data']['balance']);

        /*
         Проверяем, что транзакции созданы для обоих пользователей.
         И проверяем, что значения параметров транзакции верные.
         */
        $response = $this->get('api/users/' . $userId . '/transaction');
        $response->assertOk();
        $transaction = $response['data'][0];

        $this->assertEquals($transaction['user_id'], $userId);
        $this->assertEquals($transaction['amount'], $data['amount']);
        $this->assertEquals(Constants::USER_TO_USER_DOWN_EVENT, $transaction['event']);

        $response = $this->get('api/users/' . $userIdForTransaction . '/transaction');
        $response->assertOk();
        $transaction = $response['data'][0];

        $this->assertEquals($transaction['user_id'], $userIdForTransaction);
        $this->assertEquals($transaction['amount'], $data['amount']);
        $this->assertEquals(Constants::USER_TO_USER_UP_EVENT, $transaction['event']);

        /*
        Проверяем ситуацию, когда у пользователя недостаточно средств для списания.
        Суммы на счету, у пользователей должны остаться прежними, а транзакции не должны быть созданы.
         */

        $data = [
            'amount' => 400
        ];

        $request = $this->post('api/users/' . $userId . '/transaction/' . $userIdForTransaction, $data);
        $request->assertStatus(402);

        $user = $this->get('api/users/' . $userId);
        $user->assertOk();
        $this->assertEquals($balanceUser, $user['data']['balance']);

        $user = $this->get('api/users/' . $userIdForTransaction);
        $user->assertOk();
        $this->assertEquals($balanceUserForTransaction, $user['data']['balance']);

        $response = $this->get('api/users/' . $userId . '/transaction');
        $response->assertOk();
        $transaction = $response['data'];
        $this->assertEquals(false, isset($transaction[1]));

        $response = $this->get('api/users/' . $userIdForTransaction . '/transaction');
        $response->assertOk();
        $transaction = $response['data'];
        $this->assertEquals(false, isset($transaction[1]));

    }

    public function test_the_method_filtering_and_sorting(): void
    {
        $counterCreatedTransactions  = 3;
        $user = User::factory(1)->create();
        $userId = $user->get(0)['id'];

        $transactions = Transaction::factory($counterCreatedTransactions)->create([
            'event' => Constants::BALANCE_DOWN_EVENT
        ])->merge(Transaction::factory($counterCreatedTransactions)->create([
            'event' => Constants::BALANCE_UP_EVENT
        ]))->merge(Transaction::factory($counterCreatedTransactions)->create([
            'event' => Constants::USER_TO_USER_UP_EVENT
        ]))->merge(Transaction::factory($counterCreatedTransactions)->create([
            'event' => Constants::USER_TO_USER_DOWN_EVENT
        ]));

        /*
        Проверим правильно ли приложение сортирует транзакции по возрастанию.
        Для этого отсортируем транзакции стандартными средствами Laravel и сравним с полученными от приложения.
         */

        $sortedTransactions = $transactions->sortBy('amount')->all();
        $sortedAmount = array_column($sortedTransactions, 'amount');

        $ASC = 'asc';
        $sortedTransactionsFromBase = $this->get
        ('api/users/' . $userId . '/transaction?amount='. $ASC);
        $sortedTransactionsFromBase->assertOk();

        $arraySortedTransactionsFromBase = collect($sortedTransactionsFromBase['data'])->all();
        $sortedAmountFromBase = array_column($arraySortedTransactionsFromBase, 'amount');

        $this->assertEquals($sortedAmount, $sortedAmountFromBase);

        // Проверим правильно ли приложение сортирует транзакции по убыванию.

        $DESC = 'desc';
        $sortedTransactionsFromBase = $this->get
        ('api/users/' . $userId . '/transaction?amount='. $DESC);
        $sortedTransactionsFromBase->assertOk();

        $arraySortedTransactionsFromBase = collect($sortedTransactionsFromBase['data'])->all();
        $sortedAmountFromBase = array_column($arraySortedTransactionsFromBase, 'amount');

        $sortedTransactions = $transactions->sortByDesc('amount')->all();
        $sortedAmount = array_column($sortedTransactions, 'amount');

        $this->assertEquals($sortedAmount, $sortedAmountFromBase);

        // Проверим фильтрацию по эвенту.

        $filteredTransactionsFromBase = $this->get
        ('api/users/' . $userId . '/transaction?event=' . Constants::BALANCE_DOWN_EVENT);
        $filteredTransactionsFromBase->assertOk();

        $jsonFilteredTransactionsFromBase = $filteredTransactionsFromBase->json();
        $collectFilteredTransactionsFromBase = collect($jsonFilteredTransactionsFromBase['data']);

        // Проверяем, что количество созданных транзакций, с этим эвентом, соответствует полученным.
        $this->assertCount($counterCreatedTransactions, $collectFilteredTransactionsFromBase);

        // Проверяем, что в каждой полученной транзакции нужный нам эвент.
        $collectFilteredTransactionsFromBase->each(function ($transaction) {
            $this->assertEquals(Constants::BALANCE_DOWN_EVENT, $transaction['event']);
        });

        // Проведем такую же проверку с другим эвентом.
        $filteredTransactionsFromBase = $this->get
        ('api/users/' . $userId . '/transaction?event=' . Constants::USER_TO_USER_UP_EVENT);
        $filteredTransactionsFromBase->assertOk();

        $jsonFilteredTransactionsFromBase = $filteredTransactionsFromBase->json();
        $collectFilteredTransactionsFromBase = collect($jsonFilteredTransactionsFromBase['data']);

        $this->assertCount($counterCreatedTransactions, $collectFilteredTransactionsFromBase);

        $collectFilteredTransactionsFromBase->each(function ($transaction) {
            $this->assertEquals(Constants::USER_TO_USER_UP_EVENT, $transaction['event']);
        });

        //Проведем проверку того, что фильтрация и сортировка работают одновременно.
        $filteredAndSortedTransaction = $transactions->filter(function ($transaction) {
            return $transaction['event'] === Constants::USER_TO_USER_UP_EVENT;
        })->sortBy('amount')->all();

        $filteredAndSortedAmount = array_column($filteredAndSortedTransaction, 'amount');

        $filteredAndSortedTransactionsFromBase = $this->get
        ('api/users/' . $userId . '/transaction?amount=asc&event=' . Constants::USER_TO_USER_UP_EVENT);
        $filteredAndSortedTransactionsFromBase->assertOk();

        $jsonSortedAndFilteredTransactionsFromBase = $filteredAndSortedTransactionsFromBase->json();
        $collectSortedAndFilteredTransactionsFromBase = collect($jsonSortedAndFilteredTransactionsFromBase['data']);

        $this->assertCount(3, $collectSortedAndFilteredTransactionsFromBase);

        $collectSortedAndFilteredTransactionsFromBase->each(function ($transaction) {
            $this->assertEquals(Constants::USER_TO_USER_UP_EVENT, $transaction['event']);
        });

        $arraySortedAndFilteredTransactionsFromBase = collect($collectSortedAndFilteredTransactionsFromBase)->all();
        $filteredAndSortedAmountFromBase = array_column($arraySortedAndFilteredTransactionsFromBase, 'amount');

        $this->assertEquals($filteredAndSortedAmount, $filteredAndSortedAmountFromBase);
    }

    private function getCurrencyExchangeRate(): Collection
    {
        $response = Http::timeout(5)->get('https://www.cbr-xml-daily.ru/daily_json.js')->throw();
        return $response->collect('Valute');
    }

    private function calculationUserBalance(int $balance, string $currency, Collection $currencyData): int
    {
        return round($balance / $currencyData[$currency]['Value']);
    }
}
