<?php
declare(strict_types=1);

namespace Tests\Bitrix\Repository;

use Infrastructure\Bitrix\Repository\Iblock\Iblock;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Проверки аргументов инфоблока до обращения к Bitrix ORM. */
final class IblockValidationTest extends TestCase
{
    /** @dataProvider invalidInputs */
    public function testInvalidInputFailsBeforeQuery(string $method, mixed $value, string $message): void
    {
        // Arrange / Act / Assert
        try {
            Iblock::$method($value);
            self::fail('Invalid input accepted');
        } catch (InvalidArgumentException $error) {
            self::assertSame(InvalidArgumentException::class, $error::class);
            self::assertSame($message, $error->getMessage());
        }
    }

    public static function invalidInputs(): iterable
    {
        foreach (['', '0'] as $code) {
            yield ['getJson', $code, 'Iblock Code is empty'];
            yield ['getIblockByCode', $code, 'Iblock code is empty'];
        }
        foreach ([0, -1, PHP_INT_MIN] as $id) {
            yield ['getIblockById', $id, 'IBlock ID must be greater than 0. Given ID is "' . $id . '"'];
        }
    }
}
