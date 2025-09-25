<?php
declare(strict_types=1);

namespace Presentation\HTTP\Bitrix\Controller;

use Bitrix\Main\Engine\Controller;

/**
 *
 */
class PublicPageController extends Controller
{
    public static string $urlPrefix = '/';
    public static string $appName = '';
    /** @var string[] */
    public static array $filesToInclude = [];
}