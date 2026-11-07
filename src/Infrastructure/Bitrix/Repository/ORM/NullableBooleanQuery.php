<?php
declare(strict_types=1);

namespace Infrastructure\Bitrix\Repository\ORM;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\ORM\Fields\ExpressionField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Query\Chain;
use Bitrix\Main\ORM\Query\Filter\ConditionTree;
use Bitrix\Main\ORM\Query\Query;

/** Сохраняет булеву семантику фильтров полей, хранящих false как SQL NULL. */
class NullableBooleanQuery extends Query
{
    /** @var array<string, string> Выражения регистрируются один раз на запрос. */
    private array $nullableBooleanAliases = [];

    /** Преобразует оба API фильтров перед штатной сборкой SQL. */
    protected function buildQuery($forceObjectPrimary = true)
    {
        $this->filter = $this->normalizeArray($this->filter);
        $this->filterHandler = $this->normalizeTree($this->filterHandler);
        return parent::buildQuery($forceObjectPrimary);
    }

    /** Обрабатывает вложенные AND/OR, отрицания и массивы значений. */
    private function normalizeArray(array $filter): array
    {
        $result = [];
        foreach ($filter as $key => $value) {
            if (is_int($key) && is_array($value)) {
                $result[$key] = $this->normalizeArray($value);
                continue;
            }
            if (!is_string($key) || 'LOGIC' === $key
                || !preg_match('/^(!?@|!==|!=|==|!|=)?([a-zA-Z_][a-zA-Z0-9_.]*)$/D', $key, $match)
                || !$this->isBooleanValue($value)) {
                $result[$key] = $value;
                continue;
            }
            $alias = $this->booleanAlias($match[2]);
            if (null === $alias) {
                $result[$key] = $value;
                continue;
            }
            $operator = match ($match[1]) { '==' => '=', '!==' => '!=', default => $match[1] };
            $result[$operator . $alias] = $this->integerValue($value);
        }
        return $result;
    }

    /** Не изменяет переданное вызывающим кодом дерево условий. */
    private function normalizeTree(ConditionTree $tree): ConditionTree
    {
        $result = clone $tree;
        $result->removeAllConditions();
        foreach ($tree->getConditions() as $condition) {
            if ($condition instanceof ConditionTree) {
                $result->addCondition($this->normalizeTree($condition));
                continue;
            }
            $copy = clone $condition;
            $column = $copy->getColumn();
            if (is_string($column) && $this->isBooleanValue($copy->getValue())
                && in_array(strtolower($copy->getOperator()), ['=', '!=', '<>', 'in', 'not in'], true)) {
                $alias = $this->booleanAlias($column);
                if (null !== $alias) {
                    $copy->setColumn($alias);
                    $copy->setValue($this->integerValue($copy->getValue()));
                }
            }
            $result->addCondition($copy);
        }
        return $result;
    }

    /** Выражения и неизвестные значения остаются в ведении Bitrix. */
    private function isBooleanValue(mixed $value): bool
    {
        foreach (is_array($value) ? $value : [$value] as $item) {
            if (!in_array($item, [false, true, 0, 1, '0', '1', null], true)) {
                return false;
            }
        }
        return true;
    }

    /** Преобразует только проверенные булевы значения. */
    private function integerValue(mixed $value): int|array
    {
        return is_array($value) ? array_map('intval', $value) : (int) $value;
    }

    /** COALESCE избегает потери NULL в IN/NOT IN и legacy-метаданных CSQLWhere. */
    private function booleanAlias(string $column): ?string
    {
        if (isset($this->nullableBooleanAliases[$column])) {
            return $this->nullableBooleanAliases[$column];
        }
        try {
            $field = Chain::getChainByDefinition($this->entity, $column)->getLastElement()->getValue();
        } catch (ArgumentException) {
            return null;
        }
        if (!$field instanceof NullableBooleanField) {
            return null;
        }
        $alias = 'NULLABLE_BOOL_' . md5($column);
        $this->registerRuntimeField($alias, (new ExpressionField($alias, 'COALESCE(%s, 0)', [$column]))->configureValueType(IntegerField::class));
        return $this->nullableBooleanAliases[$column] = $alias;
    }
}
