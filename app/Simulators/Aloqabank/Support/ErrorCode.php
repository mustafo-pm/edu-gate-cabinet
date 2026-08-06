<?php

declare(strict_types=1);

namespace App\Simulators\Aloqabank\Support;

/**
 * Aloqabank's documented error codes, with the action their docs prescribe.
 *
 * The distinction that matters for money safety: on 1111 and 2222 the bank
 * explicitly says DO NOT retry — query /payment/{orderId} instead, because the
 * order may already exist on their side. Blindly retrying those is how you pay
 * an institution twice.
 */
class ErrorCode
{
    public const OK = 0;

    public const SERVICE_NOT_FOUND = 1001;

    public const SERVICE_NOT_CONFIGURED = 1002;

    public const USER_NOT_FOUND = 1004;

    public const FETCH_FAILED = 1008;          // retry the creation

    public const NO_ACCESS = 1009;

    public const ACCOUNT_NOT_FOUND = 1013;

    public const BANK_NOT_IN_SMP = 1014;       // receiver bank not an instant-payments member

    public const NAME_HAS_CONTROL_CHARS = 1015;

    public const PURPOSE_HAS_CONTROL_CHARS = 1016;

    public const MISSING_REQUIRED_FIELD = 1017;

    public const SYSTEM_ERROR = 1111;          // check status by orderId, do NOT retry

    public const CRITICAL_ERROR = 2222;        // check status by orderId, do NOT retry

    public const DOC_DATE_BEFORE_OPERATING_DAY = 3333;

    /** @var array<int, string> Messages as the bank words them. */
    private const MESSAGES = [
        self::SERVICE_NOT_FOUND => 'Сервис не найден',
        self::SERVICE_NOT_CONFIGURED => 'Настройки для сервиса отсутствуют',
        self::USER_NOT_FOUND => 'Пользователь не найден',
        self::FETCH_FAILED => 'Не удалось получить данные',
        self::NO_ACCESS => 'Нет доступа для метода',
        self::ACCOUNT_NOT_FOUND => 'Счёт не найден',
        self::BANK_NOT_IN_SMP => 'Банк получателя не является участником СМП',
        self::NAME_HAS_CONTROL_CHARS => 'Поле Наименование получателя содержит непечатаемые символы',
        self::PURPOSE_HAS_CONTROL_CHARS => 'Поле Назначение платежа содержит непечатаемые символы',
        self::MISSING_REQUIRED_FIELD => 'Отсутствуют обязательные поля',
        self::SYSTEM_ERROR => 'Системная ошибка',
        self::CRITICAL_ERROR => 'Критическая ошибка',
        self::DOC_DATE_BEFORE_OPERATING_DAY => 'Дата документа не может быть меньше даты операционного дня',
    ];

    public static function message(int $code): string
    {
        return self::MESSAGES[$code] ?? 'Неизвестная ошибка';
    }

    /**
     * Codes after which a retry is unsafe: the order may already exist at the
     * bank, so the only correct move is to query its status by orderId.
     *
     * @return array<int, int>
     */
    public static function mustQueryStatus(): array
    {
        return [self::SYSTEM_ERROR, self::CRITICAL_ERROR];
    }
}
