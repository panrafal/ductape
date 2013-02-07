<?php
/*
 * This file is part of DUCTAPE project.
 * 
 * Copyright (c)2013 Rafal Lindemann <rl@stamina.pl>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ductape\Utility;

class FileHelper {

    public static function relativePath($from, $to, $separator = DIRECTORY_SEPARATOR) {
        $arFrom = preg_split('/[\/\\\\]/', $from, -1, PREG_SPLIT_NO_EMPTY);
        $arTo = preg_split('/[\/\\\\]/', $to, -1, PREG_SPLIT_NO_EMPTY);
        while (count($arFrom) && count($arTo) && ($arFrom[0] == $arTo[0])) {
            array_shift($arFrom);
            array_shift($arTo);
        }
        return str_pad("", count($arFrom) * 3, '..' . $separator) . implode($separator, $arTo);
    }

}