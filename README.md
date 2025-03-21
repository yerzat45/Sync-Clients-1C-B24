# README: Интеграция 1С с Битрикс24

## Описание
Этот PHP-скрипт предназначен для интеграции данных из 1С в CRM-систему Битрикс24. Он выполняет следующие задачи:
- Получает список компаний и контактов из API 1С.
- Проверяет наличие каждой компании/контакта в Битрикс24 по GUID (для компаний) и создает или обновляет записи.
- Ведет логирование процесса в текстовый файл.
- Сохраняет результат работы в JSON-файл.

## Требования
- PHP 7.4+
- Включенные модули cURL и JSON
- Доступ к API 1С
- Вебхук для работы с Bitrix24

## Установка
1. Скачайте скрипт и разместите его в рабочей директории веб-сервера.
2. Убедитесь, что у вас есть доступ к API 1С и Bitrix24.
3. Настройте параметры в блоке конфигурации `$config` в файле.

## Конфигурация

Редактируйте переменные в массиве `$config`:

```php
$config = [
    'api_url' => 'api_url',
    'username' => 'username',
    'password' => 'password',
    'bitrix_webhook_url' => 'https://crm.xxx.kz/rest/xxx/xxx/',
    'timezone' => 'Asia/Almaty'
];
```

**Объяснение параметров:**
- `api_url` — URL API 1С для получения списка контрагентов.
- `username` и `password` — учетные данные для доступа к 1С.
- `bitrix_webhook_url` — вебхук для взаимодействия с API Битрикс24.
- `timezone` — часовой пояс для корректного ведения логов.

## Использование
Запустите скрипт через веб-браузер или командную строку:

```sh
php script.php
```

После выполнения скрипта создаются:
- **Лог-файл** с детальной информацией о процессе (например, `log_YYYY-MM-DD_HH-MM-SS.txt`).
- **JSON-файл** с результатами обработки (`response_YYYY-MM-DD_HH-MM-SS.txt`).

## Функциональность
- Запрашивает данные из 1С и конвертирует их в UTF-8.
- Проверяет наличие компании в Битрикс24 по `GUID`.
- Если компания найдена, обновляет её данные (название, ИНН, дату обновления).
- Если компания не найдена, создает новую запись.
- Аналогично обрабатывает контакты (если у записи нет ИНН).
- Добавляет логирование на каждом этапе выполнения.
- Использует механизм повторного запроса при превышении лимита API Bitrix24 (`QUERY_LIMIT_EXCEEDED`).

## Логирование
Файл логов содержит:
- Дату и время выполнения.
- Ошибки подключения к API.
- Количество обновленных и созданных записей.
- Ошибки при обработке компаний/контактов.

## Возможные ошибки и их решения
| Ошибка | Возможная причина | Решение |
|--------|------------------|---------|
| `Ошибка cURL` | Неверный API URL или проблемы с сетью | Проверьте правильность URL и доступность сервера |
| `Ошибка HTTP: 401` | Ошибка аутентификации | Проверьте логин и пароль для доступа к 1С |
| `Ошибка Bitrix24 API: QUERY_LIMIT_EXCEEDED` | Превышен лимит запросов | Скрипт автоматически повторит запрос через 10 секунд |
| `Ошибка JSON` | Некорректный ответ от 1С | Проверьте корректность данных в API |

## Автор
Разработано для автоматической интеграции 1С и Битрикс24. Используйте скрипт на свой страх и риск. Для вопросов и предложений обращайтесь к разработчику.

