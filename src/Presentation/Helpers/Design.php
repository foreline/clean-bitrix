<?php
    declare(strict_types=1);
    
    namespace Presentation\Helpers;
    
    /**
     *
     */
    class Design
    {
        /** @var array $arMonths Месяцы */
        public static array $arMonths = [
            'January' => 'Январь',
            'February' => 'Февраль',
            'March' => 'Март',
            'April' => 'Апрель',
            'May' => 'Май',
            'June' => 'Июнь',
            'July' => 'Июль',
            'August' => 'Август',
            'September' => 'Сентябрь',
            'October' => 'Октябрь',
            'November' => 'Ноябрь',
            'December' => 'Декабрь',
        ];
        
        /**
         * Формирует и возвращает пагинатор
         *
         * @param array $arFields Массив с параметрами
         * <pre>
         * [
         *  PAGE_SIZE, // Количество элементов на странице
         *  TOTAL_COUNT, // Всего элементов
         *  PAGE_NUM, // Номер страницы. Если не задан, то рассчитывается автоматически по значению параметра $_GET['p']
         *  LIST_PAGE_URL, // URL страницы списка
         *  AR_URL_PARAMS, // Массив с параметрами URL, которые необходимо включить в ссылки. Если не задан, то рассчитывается автоматически на основании массива $_GET
         *  TEMPLATE, // Шаблон формирования ссылки, допускаются замены: #PAGE_NUM# (номер страницы). Например 'p/#PAGE_NUM#
         * ]
         * </pre>
         * @return string $navString
         *
         * @deprecated Use \Presentation\Helpers\Pagination
         */
        public static function getNav(array $arFields = []): string
        {
            $pageSize = !empty($arFields['PAGE_SIZE']) && 0 < (int)$arFields['PAGE_SIZE'] ? (int)$arFields['PAGE_SIZE'] : 25;
            $totalCount = !empty($arFields['TOTAL_COUNT']) && 0 < (int)$arFields['TOTAL_COUNT'] ? (int)$arFields['TOTAL_COUNT'] : 0;
            $pageNum = !empty($arFields['PAGE_NUM']) && 0 < (int)$arFields['PAGE_NUM'] ? (int)$arFields['PAGE_NUM'] : (isset($_GET['p']) ? (int)$_GET['p'] : 1);
            $listPageUrl = !empty($arFields['LIST_PAGE_URL']) ? $arFields['LIST_PAGE_URL'] : substr_replace((string)$_SERVER['REQUEST_URI'], '', 1 + strrpos((string)$_SERVER['REQUEST_URI'], '/'));
            $arUrlParams = isset($arFields['AR_URL_PARAMS']) && 0 < count((array)$arFields['AR_URL_PARAMS']) ? $arFields['AR_URL_PARAMS'] : $_GET;
            
            $template = !empty($arFields['TEMPLATE']) ? $arFields['TEMPLATE'] : 'p/#PAGE_NUM#';
            
            if ( str_starts_with($template, '/') ) {
                $template = substr($template, 1);
            }
            
            /** @var int $range Количество */
            $range = 5;
            
            /*
             * Расчет параметров
             */
            
            /** @var string $urlParamsPrefix Префикс для url-параметров. Если шаблон содержит "/", то скорее всего это ЧПУ - используется "?". */
            $urlParamsPrefix = ( !str_contains($template, '/') ) ? '&' : '?';
            
            /** @var int $lastPageNum Номер последней страницы (количество страниц) */
            $lastPageNum = (int)ceil($totalCount / $pageSize);
            
            unset($arUrlParams['p']);
            $urlParams = http_build_query($arUrlParams);
            $urlParams = !empty($urlParams) ? $urlParamsPrefix . $urlParams : '';
            
            /*
             * 
             */
            
            if ($pageSize >= $totalCount) {
                return '';
            }
            
            /*
             * Формирование постраничной навигации
             */
            
            $nav = '<nav>';
            
            $nav .= '<ul class="pagination">';
            
            /* В начало */
            $nav .= '
            <li class="page-item ' . (1 === $pageNum ? 'disabled' : '') . '">
                <a class="page-link has-ripple" data-page-num="1" href="' . $listPageUrl . $urlParams . '" aria-label="в начало">
                    <span aria-hidden="true">&laquo;</span>
                </a>
            </li>
            ';
            
            /* Предыдущая страница */
            $url = $listPageUrl . str_replace('#PAGE_NUM#', (string)($pageNum - 1), $template) . $urlParams;
            $nav .= '
            <li class="page-item ' . (1 === $pageNum ? 'disabled' : '') . '">
                <a class="page-link has-ripple" data-page-num="' . ($pageNum - 1) . '" href="' . $url . '" aria-label="предыдущая">
                    <span aria-hidden="true">&lsaquo;</span>
                </a>
            </li>
            ';
            
            /* Начальный блок */
            if ($lastPageNum < $range) {
                $np = $lastPageNum;
            } else {
                $np = (4 + floor($range / 2) > $pageNum ? $range + ($pageNum - 1) : $range);
            }
            
            if ($np > $lastPageNum) {
                $np = $lastPageNum;
            }
            
            for ($i = 1; $i <= $np; $i++) {
                $url = $listPageUrl . str_replace('#PAGE_NUM#', (string)$i, $template) . $urlParams;
                $nav .= '<li class="page-item' . ((int)$i === (int)$pageNum ? ' active ' : '') . '" ' . ($i === $pageNum ? ' aria-current="page" ' : '') . '>';
                $nav .= '<a class="page-link has-ripple" data-page-num="' . $i . '" href="' . $url . '">' . $i . '</a>';
                $nav .= '</li>';
            }
            
            /*
             * Средний блок
             */
            
            if ($pageNum <= ($lastPageNum - $range) && (4 + floor($range / 2)) <= $pageNum) {
                
                if (($range + ceil($range / 2)) < $pageNum) {
                    $nav .= '<li class="page-item disabled"><a class="page-link has-ripple" href="#">&hellip;</a></li>';
                }
                
                for ( $i = ($pageNum - floor($range / 2)); $i <= ($pageNum + floor($range / 2)); $i++) {
                    
                    if ( $range >= $i ) {
                        continue;
                    }
                    
                    if ($i > ($lastPageNum - $range)) {
                        continue;
                    }
                    
                    $url = $listPageUrl . str_replace('#PAGE_NUM#', (string)$i, $template) . $urlParams;
                    
                    $nav .= '<li class="page-item' . ((int)$i === (int)$pageNum ? ' active ' : '') . '" ' . ($i === (int)$pageNum ? ' aria-current="page" ' : '') . '>';
                    $nav .= '<a class="page-link has-ripple" data-page-num="' . $i . '" href="' . $url . '">' . $i . '</a>';
                    $nav .= '</li>';
                }
                
                if (($pageNum + floor($range / 2)) < ($lastPageNum - $range)) {
                    
                    $nav .= '<li class="page-item disabled"><a class="page-link has-ripple" href="#">&hellip;</a></li>';
                }
                
            } elseif ($lastPageNum > $range) {
                $nav .= '<li class="page-item disabled"><a class="page-link has-ripple" href="#">&hellip;</a></li>';
            }
            
            /*
             * Конечный блок
             */
            
            if ((2 * $range) <= $lastPageNum) {
                
                $sp = ($lastPageNum - ($range - 1));
                
                if (($pageNum > $sp - ceil($range / 2)) && ($pageNum > ($lastPageNum - $range))) {
                    $sp = $pageNum - ceil($range / 2);
                }
                
                for ($i = $sp; $i <= $lastPageNum; $i++) {
                    $url = $listPageUrl . str_replace('#PAGE_NUM#', (string)$i, $template) . $urlParams;
                    $nav .= '<li class="page-item' . ((int)$i === (int)$pageNum ? ' active ' : '') . '" ' . ($i === (int)$pageNum ? ' aria-current="page" ' : '') . '>';
                    $nav .= '<a class="page-link has-ripple" data-page-num="' . $i . '" href="' . $url . '">' . $i . '</a>';
                    $nav .= '</li>';
                }
            }
            
            /* Следующая страница */
            $url = $listPageUrl . str_replace('#PAGE_NUM#', (string)($pageNum + 1), $template) . $urlParams;
            $nav .= '
            <li class="page-item ' . ($lastPageNum === $pageNum ? 'disabled' : '') . '">
                <a class="page-link has-ripple" data-page-num="' . ($pageNum + 1) . '" href="' . $url . '" aria-label="следующая">
                    <span aria-hidden="true">&rsaquo;</span>
                </a>
            </li>
            ';
            
            /* В конец */
            $url = $listPageUrl . str_replace('#PAGE_NUM#', (string)$lastPageNum, $template) . $urlParams;
            $nav .= '
            <li class="page-item ' . ($lastPageNum === $pageNum ? 'disabled' : '') . '">
                <a class="page-link has-ripple" data-page-num="' . $lastPageNum . '" href="' . $url . '" aria-label="в конец">
                    <span aria-hidden="true">&raquo;</span>
                </a>
            </li>
            ';
            
            $nav .= '</ul>';
            $nav .= '</nav>';
            
            return $nav;
        }
        
        /**
         * @deprecated
         */
        public static function showMonthSelector(array $arFields = []): void
        {
            $month = self::getMonth();
            $year = self::getYear();
            $curMonth = date('n');
            
            $showFuture = !empty($arFields['SHOW_FUTURE']) ? $arFields['SHOW_FUTURE'] : false;
            $currentMonthActive = !empty($arFields['CURRENT_MONTH_ACTIVE']) && ('Y' === strtoupper($arFields['CURRENT_MONTH_ACTIVE']) || true === (bool)$arFields['CURRENT_MONTH_ACTIVE']) && !isset($_GET['month']);
            
            $showFuture = ('Y' === $showFuture || true === $showFuture || 1 == $showFuture);
            
            $arUrlParams = $_GET;
            unset($arUrlParams['month'], $arUrlParams['year'], $arUrlParams['DATE_FROM'], $arUrlParams['DATE_TO']);
            
            $urlParams = http_build_query($arUrlParams);
            
            echo '<div class="border p-2 mb-3 text-center">';
            
            echo '
            <script type="text/javascript">
                function getDateUrl(obDate) {
                    obDate = JSON.parse(obDate);
                    let urlParams = "' . $urlParams . '";
                    let year = obDate.year;
                    let month = obDate.month;
                    
                    return "?" + urlParams + "&year=" + year + "&month=" + month;
                }
            </script>
            
            <select class="float-start" onChange="window.location=getDateUrl(this.value); return false;">
                <option value="0">месяц...</option>';
            
            if ($showFuture) {
                $from = -5;
                $to = 5;
            } else {
                $from = -9;
                $to = 2;
            }
            
            //for ( $i = ($curMonth + $from); $i < ($curMonth + $to); $i ++ ) {
            for ( $i = ($month + $from); $i < ($month + $to); $i++ ) {
                $timeStamp = mktime(0, 0, 0, $i, 1, $year);
                $monthNumber = date('n', $timeStamp);
                echo '
                <option
                    value=\'{"month":' . date('n', $timeStamp) . ',"year":' . date('Y', $timeStamp) . '}\' ' .
                    ($monthNumber == $month ? ' selected="selected" ' : '') . '
                    >
                ' . self::$arMonths[date('F', $timeStamp)] . ' ' . date('Y', $timeStamp) . '</option>';
            }
            
            echo '
            </select>
            ';
            
            for ($i = 3; $i > 0; $i--) {
                
                if (false === $showFuture && $month === $curMonth) {
                    $mOffset = ($i - 1);
                } else {
                    $mOffset = ($i - 2);
                }
                
                $monthName = date('F', mktime(0, 0, 0, ($month - $mOffset), 1, $year));
                $monthNumber = date('n', mktime(0, 0, 0, ($month - $mOffset), 1, $year));
                $monthYear = date('Y', mktime(0, 0, 0, ($month - $mOffset), 1, $year));
                
                if ($monthNumber !== $month || true === $currentMonthActive) {
                    echo '<a href="?' . $urlParams . (0 < strlen($urlParams) ? '&' : '') . 'month=' . $monthNumber . '&year=' . $monthYear . '">' . self::$arMonths[$monthName] . '</a>';
                } else {
                    echo self::$arMonths[date('F', mktime(0, 0, 0, $monthNumber, 1, $year))];
                }
                
                echo($i > 1 ? ' &nbsp; ' : '');
            }
            
            echo '
            <div class="float-end">
                <small>сейчас:</small> <a href="?' . $urlParams . '&month=' . date('n') . '&year=' . date('Y') . '">' . self::$arMonths[date('F')] . '</a>
            </div>
            ';
            
            //echo '</h2>';
            echo '</div>';
        }
        
        /**
         * @return int $month
         */
        public static function getMonth(): int
        {
            return !empty($_GET['month']) && 0 < (int)$_GET['month'] && 13 > (int)$_GET['month'] ? (int)$_GET['month'] : (int)date(
                'n'
            );
        }
        
        /**
         * @return int $year
         */
        public static function getYear(): int
        {
            return !empty($_GET['year']) && 2011 < (int)$_GET['year'] && ((int)$_GET['year'] <= (int)date('Y') + 1) ? (int)$_GET['year'] : (int)date(
                'Y'
            );
        }
        
        /**
         * Выводит выпадающий список действий (bootstrap5)
         * @param array $arElements Массив с элементами выпадающего меню [ ['NAME', 'LINK', 'CLASS', 'DATA', 'ACCESS'], ... ]
         * @param array $arParams Параметры отображения ['CLASS', 'LABEL', 'ACCESS', 'TYPE']
         * @see getActionButton
         * @return void
         */
        public static function showActionButton(array $arElements = [], array $arParams = []): void
        {
            echo static::getActionButton($arElements, $arParams);
        }
        
        /**
         * Возвращает html-код для отображения выпадающего список действий (bootstrap5)
         * @param array $arElements Массив с элементами выпадающего меню [ ['NAME', 'LINK', 'CLASS', 'DATA', 'ACCESS'], ... ]
         * @param array $arParams Параметры отображения ['CLASS', 'LABEL', 'ACCESS', 'TYPE']
         * @return string $htmlOutput
         */
        public static function getActionButton(array $arElements = [], array $arParams = []): string
        {
            if ( array_key_exists('ACCESS', $arParams) && false === (bool)$arParams['ACCESS']) {
                return '';
            }
            
            if (empty($arParams['LABEL'])) {
                $arParams['LABEL'] = 'Действия';
            }
            
            if (empty($arParams['CLASS'])) {
                $arParams['CLASS'] = 'btn-primary';
            }
            
            $output = '
            <div class="btn-group float-end mb-2" role="group">
                <div class="btn-group" role="group">
            ';
            
            $btnClass = 'btn-primary';
            
            if ( 0 < count($classes = explode(' ', $arParams['CLASS'])) ) {
                foreach ( $classes as $class ) {
                    if ( 0 <= strpos($class, 'btn-') ) {
                        $btnClass = $class;
                        break;
                    }
                }
            }
            
            if ( 'split' === $arParams['TYPE'] ) {
    
                if (isset($arElements[0]['DATA'])) {
                    if (is_array($arElements[0]['DATA'])) {
            
                        $arData = [];
                        foreach ($arElements[0]['DATA'] as $dataAttr => $dataValue) {
                            if ( !str_starts_with($dataAttr, 'data-') ) {
                                $dataAttr = 'data-' . $dataAttr;
                            }
                            $arData[] = $dataAttr . '=' . $dataValue;
                        }
                        $data = implode(' ', $arData);
                    } elseif ( !str_starts_with($arElements[0]['DATA'], 'data-') ) {
                        $data = 'data-' . $arElements[0]['DATA'];
                    } else {
                        $data = $arElements[0]['DATA'];
                    }
                } else {
                    $data = '';
                }
                
                $output .= '
                    <a href="' . $arElements[0]['LINK'] . '" type="button" class="btn ' . $btnClass . ' ' . $arElements[0]['CLASS'] . '" ' . $data . '>
                        ' . $arParams['LABEL'] . '
                    </a>
                ';
            }
            
            $output .= '
                    <button id="btnGroupDrop1" type="button" class="btn ' . $arParams['CLASS'] . ' dropdown-toggle ' . ('split' === $arParams['TYPE'] ? 'dropdown-toggle-split border-start' : '') . '" data-bs-toggle="dropdown" aria-expanded="false">
                        ' . ( 'split' === $arParams['TYPE'] ? '<span class="visually-hidden">Toggle Dropdown</span>' : $arParams['LABEL'] ) . '
                    </button>
                    <ul class="dropdown-menu" aria-labelledby="btnGroupDrop1">
            ';
            
            foreach ($arElements as $class => $arElement) {
                
                if (!is_array($arElement)) {
                    $arElement = [
                        'NAME' => $arElement,
                        'CLASS' => $class,
                    ];
                }
                
                if (isset($arElement['ACCESS']) && 0 === (int)$arElement['ACCESS']) {
                    continue;
                }
                
                if (isset($arElement['DATA'])) {
                    if (is_array($arElement['DATA'])) {
                        
                        $arData = [];
                        foreach ($arElement['DATA'] as $dataAttr => $dataValue) {
                            if ( !str_starts_with($dataAttr, 'data-') ) {
                                $dataAttr = 'data-' . $dataAttr;
                            }
                            $arData[] = $dataAttr . '=' . $dataValue;
                        }
                        $data = implode(' ', $arData);
                    } elseif ( !str_starts_with($arElement['DATA'], 'data-') ) {
                        $data = 'data-' . $arElement['DATA'];
                    } else {
                        $data = $arElement['DATA'];
                    }
                } else {
                    $data = '';
                }
                
                if (empty($arElement['NAME'])) {
                    $output .= '<li class="border-bottom"></li>';
                } else {
                    $output .= '<li><a class="dropdown-item ' . $arElement['CLASS'] . '" href="' . (!empty($arElement['LINK']) ? $arElement['LINK'] : '#') . '" ' . $data . ' >' . $arElement['NAME'] . '</a></li>';
                }
            }
            
            $output .= '
                    </ul>
                </div>
            </div>
            ';
            
            return $output;
        }
        
        /**
         * Включает в цепочку навигации путь до текущего файла. Поднимается по вложенности директории,
         * начиная от текущей и включает раздел в цепочку навигации, основываясь на файле ".section.php"
         *
         * @param bool $includeCurrentDirectory [optional] Включать ли текущую директорию, по умолчанию включать.
         * @param bool $showLastLink [optional] Отображать ли ссылку на последнюю ссылку в цепочке навигации, по умолчанию не отображать
         *
         * @return void
         */
        
        public static function incChain(bool $includeCurrentDirectory = true, bool $showLastLink = false): void
        {
            $arBacktrace = debug_backtrace();
            
            $file = $arBacktrace[0]['file'];
            
            $arPath = pathinfo($file);
            
            $path = $arPath['dirname'];
            
            // if Windows hosting
            $path = str_replace('\\', '/', $path);
            
            if (false === $includeCurrentDirectory) {
                $path .= '/..';
            }
            
            global $APPLICATION;
            
            $i = 0;
            
            $arChain = [];
            
            while ($_SERVER['DOCUMENT_ROOT'] !== $path) {
                
                $sectionFile = $path . '/.section.php';
                
                if (file_exists($sectionFile)) {
                    
                    $arSection = [];
                    
                    include $sectionFile;
                    
                    if (!empty($arSection['NAME'])) {
                        // if Windows hosting
                        $path = str_replace('\\', '/', $path);
                        
                        $link = str_replace($_SERVER['DOCUMENT_ROOT'], '', $path) . '/';
                        
                        $arChain[] = [
                            'NAME' => $arSection['NAME'],
                            'LINK' => $link,
                        ];
                    }
                }
                
                $path = dirname($path);
                
                // @fixme Проверка изначально заданного пути
                if (10 < $i++) {
                    break;
                }
            }
            
            // Сортируем по ключам в обратном порядке
            krsort($arChain);
            
            foreach ( $arChain as $key => $arChainItem ) {
                if ( false === $showLastLink && 0 == $key ) {
                    $APPLICATION->AddChainItem($arChainItem['NAME']);
                } else {
                    $APPLICATION->AddChainItem($arChainItem['NAME'], $arChainItem['LINK']);
                }
            }
        }
        
        /**
         * Обертка для метода $APPLICATION->AddChainItem()
         * @param string $title
         * @param string $link
         */
        public static function addChainItem(string $title, string $link = ''): void
        {
            global $APPLICATION;
            $APPLICATION->AddChainItem($title, $link);
        }
        
        /**
         * Обертка для метода $APPLICATION->SetTitle();
         * @param string $title
         */
        public static function setTitle(string $title): void
        {
            global $APPLICATION;
            $APPLICATION->SetTitle($title);
        }
        
        /**
         * @param string $name
         * @param string $linkCode
         * @param string $defaultOrder
         * @return void
         * @see Design::getSort()
         */
        public static function showSort(string $name, string $linkCode, string $defaultOrder = 'desc'): void
        {
            echo static::getSort($name, $linkCode, $defaultOrder);
        }
    
        /**
         * @param string $name Название
         * @param string $linkCode Код сортировки, передаваемый в url параметр sort=$linkCode
         * @param string $defaultOrder Порядок сортировки [asc|desc]
         * @param string $classes Классы для ссылки (строчкой через пробелы)
         * @return string
         * @noinspection PhpTooManyParametersInspection
         */
        public static function getSort(string $name, string $linkCode, string $defaultOrder = 'desc', string $classes = ''): string
        {
            $output = '';
            
            $sort = ( $_REQUEST['sort'] ?? $_REQUEST['params']['sort'] ?? '' );
            $orderBy = ( $_REQUEST['order'] ?? $_REQUEST['by'] ?? $_REQUEST['params']['order'] ?? '' );
            
            $order = ($linkCode === $sort && 'desc' === $orderBy) ? 'asc' : $defaultOrder;
            
            // Необходимы параметры как $_GET, так и $_POST для ajax-запросов
            $arUrlParams = $_REQUEST;
            unset($arUrlParams['sort']);
            unset($arUrlParams['order']);
            unset($arUrlParams['by']);
            
            $urlParams = http_build_query($arUrlParams);
            
            $output .= '
            <a
                href="?sort=' . rawurlencode($linkCode) . '&order=' . $order . (!empty($urlParams) ? '&' . $urlParams : '') . '"
                class="' . ($linkCode === $sort ? ' text-danger ' : '') . $classes . '"
                style="white-space: nowrap;"
                data-sort="' . $linkCode . '"
                data-order="' . $order . '"
            >';
            $output .= $name;
            $output .= '&nbsp;';
            $output .= '<i class="bi-sort-' . (($linkCode === $sort && 'desc' === $order) ? 'down-alt' : 'down') . '"></i>';
            $output .= '</a>';
            
            return $output;
        }
    }
