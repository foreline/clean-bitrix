<?php
declare(strict_types=1);

namespace Infrastructure\Bitrix\Repository\ORM;

use Bitrix\Main\DB\SqlExpression;
use Bitrix\Main\ORM\Fields\BooleanField;

/** Булево поле с SQL NULL для false и единицей для true. */
final class NullableBooleanField extends BooleanField
{
    /** Настраивает хранение без изменения булева API ORM. */
    public function __construct($name, $parameters = [])
    {
        parent::__construct($name, $parameters);
        $this->configureValues(null, 1)->configureNullable(true);
    }

    /** Не позволяет SQL helper превратить false в пустую строку. */
    public function convertValueToDb($value)
    {
        if ($value instanceof SqlExpression) {
            return $value;
        }

        $normalized = $this->normalizeValue($value);
        return null === $normalized ? null : parent::convertValueToDb($normalized);
    }

    /** Bitrix определяет legacy-тип по точному имени класса, а не наследованию. */
    public function getDataType()
    {
        return 'boolean';
    }
}
