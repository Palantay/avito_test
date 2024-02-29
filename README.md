# User Balance API.

___


## Установка
Создайте в корне проекта файл .env, скопируйте в него содержимое файла .env-example

Для тестирования создайте файл в корне проекта .env.testing, скопируйте в него содержимое файла .env.testing-example

Данные для postman находятся в корне проекта.


Клонируем репозиторий.

    блаба

Запускаем докер

    docker-compose up -d

Заходим в докер контейнер

    docker exec -it avito bash

Накатываем миграции 

    php artisan migrate

Если нужны данные запускаем seed'ы.

    php artisan migrate --seed
    
Для тестирования нам надо накатить миграции в тестовую базу данных.

    php artisan migrate --env=env.testing

Для запуска тесторования 

    php artisan test

    
___


## Общая информация.

___

#### По мотивам тестового [задания](https://github.com/avito-tech/autumn-2021-intern-assignment). Разработан микросервис для работы с балансом пользователя.

#### Реализованы следующие методы.
___


#### Метод получения всех пользователей. 
Метод GET.

    http://localhost:8080/api/users/


---
#### Метод удаления пользователя(используется soft delete). 
Метод DELETE.


    http://localhost:8080/api/users/{id}


---
#### Метод создания пользователя. 
Метод POST.


    http://localhost:8080/api/users/ 


Пример содержания body запроса:

        {
            "balance" : "300",
        }

---

#### Метод получения баланса пользователя по id.
Метод GET.

Можно добавить query параметр "?currency="USD", тогда баланс расчитается в долларах США.

На данный момент доступны три валюты: американский доллар(USD), евро(EUR), китайский юань(CNY).

    http://localhost:8080/api/users/{id}

---

#### Метод начисления и списания средств пользователя.
Метод POST.

В зависимости от "event" сервис либо списывает, либо зачисляет средства на баланс пользователя.
Если event равен "1", то списывает. Если event равен "2", то начисляет.

При зачислении или списании средств, транзакция создается автоматически.

    http://localhost:8080/api/users/{id}/transaction/

Пример содержания body запроса:

    {
        "amount": "400",
        "event": "1"
    }
---

#### Метод начисления от пользователя к пользователю.
Метод POST.

При осуществлении перевода между пользователями транзакции, у обоих пользователей, создаются автоматически.
У пользователя, который переводит средства, запишется "event" равный "4". 
У пользователя, который получает средства, запишется "event" равный "3".

    http://localhost:8080/api/users/{id}/transaction/{toUserId}

Пример содержания body запроса:

    {
        "amount": "150"
    }

___

#### Метод получения транзакций пользователя.
Метод GET.

В данном методе предусмотрена фильтрация по значению "event". Пример query параметра: "?event=3". 
А так же сортировка, по возрастанию и убыванию, по параметру "amount". Пример query параметра: "?amount=asc".
Query параметры можно использовать как вместе, так и отдельно друг от друга.  

    http://localhost:8080/api/users/{id}/transaction/
___

