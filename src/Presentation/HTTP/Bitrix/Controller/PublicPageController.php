<?php
declare(strict_types=1);

namespace Presentation\HTTP\Bitrix\Controller;

use Bitrix\Main\Engine\Controller;
use CComponentEngine;

/**
 *
 */
class PublicPageController extends Controller
{
    public static string $urlPrefix = '/';
    public static string $appName = '';
    /** @var string[] */
    public static array $filesToInclude = [];
    
    /**
     * @param string $componentName
     * @return bool
     */
    protected function isComponentExists(string $componentName): bool
    {
        $componentPath = CComponentEngine::MakeComponentPath($componentName);
        $componentPath = getLocalPath('components' . $componentPath /*. '/class.php'*/);
        return ( $componentPath !== false && file_exists($_SERVER['DOCUMENT_ROOT'] . $componentPath) );
    }
}