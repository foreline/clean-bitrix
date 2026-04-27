<?php
declare(strict_types=1);

namespace Presentation\Lifecycle;

use Domain\Lifecycle\AbstractLifecycleStatus;
use Domain\Lifecycle\LifecycleColorPalette;

/**
 * Хелпер для рендера статуса жизненного цикла в виде цветной «таблички» (chip).
 */
final class LifecycleStatusViewHelper
{
    public static function renderChip(?AbstractLifecycleStatus $status): string
    {
        if (null === $status) {
            return '<span class="ui-label ui-label-light lifecycle-status-chip lifecycle-status-chip--empty">—</span>';
        }
        $palette = LifecycleColorPalette::tryFrom($status->getColor()) ?? LifecycleColorPalette::SECONDARY;
        return sprintf(
            '<span class="ui-label ui-label-%s lifecycle-status-chip" title="%s">%s</span>',
            htmlspecialcharsbx($palette->cssToken()),
            htmlspecialcharsbx($status->getHint() ?? $status->getName()),
            htmlspecialcharsbx($status->getName()),
        );
    }
}