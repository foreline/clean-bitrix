<?php
declare(strict_types=1);

namespace Presentation\Lifecycle;

use Domain\Lifecycle\AbstractLifecycleStatus;
use Domain\Lifecycle\LifecycleColorPalette;
use Domain\Lifecycle\LifecycleStatusEnumInterface;
use ReflectionEnum;

/**
 * Хелпер для рендера статуса жизненного цикла в виде цветной «таблички» (chip).
 */
final class LifecycleStatusViewHelper
{
    /**
     * Render a coloured status chip used in the detail page sidebar.
     *
     * @param AbstractLifecycleStatus|null $status
     * @return string
     */
    public static function renderChip(?AbstractLifecycleStatus $status): string
    {
        if ( null === $status ) {
            return '<span class="ui-label ui-label-light lifecycle-status-chip lifecycle-status-chip--empty">—</span>';
        }
        
        $token = $status->getColorPalette()->cssToken();
        $title = '' !== $status->getHint() ? $status->getHint() : $status->getName();
        
        return sprintf(
            '<span class="ui-label ui-label-%s lifecycle-status-chip" title="%s">%s</span>',
            self::escape($token),
            self::escape($title),
            self::escape($status->getName()),
        );
    }
    
    /**
     * Render a coloured status badge used in list grid cells.
     *
     * Same markup as {@see renderChip()} with an additional CSS hook so
     * consumers can target list-only styling without changing the chip.
     *
     * @param AbstractLifecycleStatus|null $status
     * @return string
     */
    public static function renderBadge(?AbstractLifecycleStatus $status): string
    {
        if ( null === $status ) {
            return '<span class="ui-label ui-label-light lifecycle-status-badge lifecycle-status-badge--empty">—</span>';
        }
        
        $token = $status->getColorPalette()->cssToken();
        $title = '' !== $status->getHint() ? $status->getHint() : $status->getName();
        
        return sprintf(
            '<span class="ui-label ui-label-%s lifecycle-status-badge" title="%s">%s</span>',
            self::escape($token),
            self::escape($title),
            self::escape($status->getName()),
        );
    }
    
    /**
     * Compute the list of enum case values whose `isInProgress()` is true.
     *
     * Used by the lifecycle filter widget to map the "В работе" mode to
     * a concrete status-code list, without requiring the consumer enum
     * to add bespoke static helpers.
     *
     * @param class-string<LifecycleStatusEnumInterface> $enumClass
     * @return string[]
     */
    public static function getInProgressCodes(string $enumClass): array
    {
        return self::collectCodes($enumClass, static fn (LifecycleStatusEnumInterface $case): bool => $case->isInProgress());
    }
    
    /**
     * Compute the list of enum case values whose `isFinal()` is true.
     *
     * @param class-string<LifecycleStatusEnumInterface> $enumClass
     * @return string[]
     */
    public static function getFinalCodes(string $enumClass): array
    {
        return self::collectCodes($enumClass, static fn (LifecycleStatusEnumInterface $case): bool => $case->isFinal());
    }
    
    /**
     * @param class-string<LifecycleStatusEnumInterface> $enumClass
     * @param callable(LifecycleStatusEnumInterface):bool $predicate
     * @return string[]
     */
    private static function collectCodes(string $enumClass, callable $predicate): array
    {
        if ( !is_subclass_of($enumClass, LifecycleStatusEnumInterface::class) ) {
            return [];
        }
        if ( !function_exists('enum_exists') || !enum_exists($enumClass) ) {
            return [];
        }
        
        $codes = [];
        $reflection = new ReflectionEnum($enumClass);
        foreach ( $reflection->getCases() as $caseRefl ) {
            /** @var LifecycleStatusEnumInterface $case */
            $case = $caseRefl->getValue();
            if ( !$predicate($case) ) {
                continue;
            }
            if ( property_exists($case, 'value') ) {
                $codes[] = (string)$case->value;
            }
        }
        
        return $codes;
    }
    
    /**
     * Convenience accessor for the palette CSS token, e.g. `success`.
     *
     * @param LifecycleColorPalette|string $color
     * @return string
     */
    public static function colorToken(LifecycleColorPalette|string $color): string
    {
        if ( $color instanceof LifecycleColorPalette ) {
            return $color->cssToken();
        }
        return (LifecycleColorPalette::tryFrom($color) ?? LifecycleColorPalette::DEFAULT)->cssToken();
    }
    
    /**
     * HTML-escape helper. Prefers Bitrix's `htmlspecialcharsbx()` when
     * present (it strips dangerous control characters in addition to
     * escaping); falls back to `htmlspecialchars()` outside Bitrix.
     *
     * @param string $value
     * @return string
     */
    private static function escape(string $value): string
    {
        if ( function_exists('htmlspecialcharsbx') ) {
            /** @phpstan-ignore-next-line Bitrix function defined at runtime. */
            return call_user_func('htmlspecialcharsbx', $value);
        }
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}