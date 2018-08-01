<?php
/**
 * Short description for Util.php
 *
 * @package Util
 * @author zhenyangze <zhenyangze@gmail.com>
 * @version 0.1
 * @copyright (C) 2018 zhenyangze <zhenyangze@gmail.com>
 * @license MIT
 */
namespace SpiderX\Lib;

class Util
{
    public static function substrByPreg($start, $end, $str, $isLimit = false)
    {
        $resultList = [];
        $firstList = preg_split($start, $str);
        foreach ($firstList as $key => $firstInfo) {
            if ($key == 0) {
                continue;
            }
            $secondList = preg_split($end, $firstInfo);
            if (count($secondList) == 0) {
                continue;
            }
            if ($isLimit) {
                return trim($secondList[0]);
            } else {
                $resultList[] = trim($secondList[0]);
            }
        }
        return $isLimit ? '' : $resultList;
    }

    public static function subStrByStr($start, $end, $str, $isLimit = false)
    {
        $resultList = [];
        $firstList = explode($start, $str);
        foreach ($firstList as $key => $firstInfo) {
            if ($key == 0) {
                continue;
            }
            $secondList = explode($end, $firstInfo);
            if (count($secondList) == 0) {
                continue;
            }
            if ($isLimit) {
                return trim($secondList[0]);
            } else {
                $resultList[] = trim($secondList[0]);
            }
        }
        return $isLimit ? '' : $resultList;
    }

    public static function tableToArray($html)
    {
        preg_match("/<table[^>]*>(.+)<\/table>/is", $html, $match);
        $trArr = explode("</tr>", $match[1]);
        array_pop($trArr);
        for ($i = 0; $i < count($trArr); $i++) {
            $trArr[$i] = explode("</td>", $trArr[$i]);  //  分裂各列
            array_pop($trArr[$i]);  //  去处尾部多余的元素
        }
        for ($i=0; $i < count($trArr); $i++) {
            for ($j = 0; $j < count($trArr[$j]); $j++) {
                if (preg_match('/colspan=[\'"]*(\d+)[\'"]*/i', $trArr[$i][$j], $regs)) {    //  如果跨列
                    $t = array();
                    while (--$regs[1]  >  0) {//  补足差额
                        array_push($t, "");
                    }
                    $trArr[$i] = array_merge(array_slice($trArr[$i], 0, $j+1), $t, array_splice($trArr[$i], $j+1));
                }
                if (preg_match('/rowspan=[\'"]*(\d+)[\'"]*/i', $trArr[$i][$j], $regs)) {    //  如果跨行
                    if (!isset($t)) {  //  跨列、跨行不同时存在
                        $t = array("");
                    } else {
                        array_push($t, "");
                    }
                    $k = $regs[1];
                    while (--$k  >  0) {//  补足差额
                        $trArr[$i+$k] = array_merge(array_slice($trArr[$i+$k], 0, $j), $t, array_splice($trArr[$i+$k], $j));
                    }
                }
                unset($t);
            }
        }
      //  除去html标记
        for ($i = 0; $i < count($trArr); $i++) {
            //填充
            for ($j=0; $j< count($trArr[$i]); $j++) {
                $trArr[$i][$j]  = strip_tags($trArr[$i][$j]);
                if (strlen(trim($trArr[$i][$j])) == 0) {
                    $trArr[$i][$j] = '-';
                }
            }

            $trArr = array_filter($trArr);

            if ($i > 0) {
                if (!isset($trArr[$i - 1])) {
                    continue;
                }
                if (count($trArr[$i - 1]) > count($trArr[$i])) {
                    for ($n = count($trArr[$i - 1]) - count($trArr[$i]) - 1; $n >= 0; $n--) {
                        array_unshift($trArr[$i], $trArr[$i - 1][$n]);
                    }
                }
            }
        }
        return $trArr;
    }

    // 获得当前使用内存
    public static function memoryGetUsage()
    {
        $memory = memory_get_usage();
        return self::formatBytes($memory);
    }
    
    // 获得最高使用内存
    public static function memoryGetPeakUsage()
    {
        $memory = memory_get_peak_usage();
        return self::formatBytes($memory);
    }
    
    // 转换大小单位
    public static function formatBytes($size)
    {
        $unit = array('b', 'kb', 'mb', 'gb', 'tb', 'pb');
        return @round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . ' ' . $unit[$i];
    }

    /**
     * 递归删除目录
     *
     * @param mixed $dir
     * @return void
     * @author seatle <seatle@foxmail.com>
     * @created time :2016-09-18 10:17
     */
    public static function delDir($dir)
    {
        //先删除目录下的文件：
        $dh = opendir($dir);
        while ($file = readdir($dh)) {
            if ($file!="." && $file!="..") {
                $fullpath = $dir."/".$file;
                if (!is_dir($fullpath)) {
                    unlink($fullpath);
                } else {
                    self::delDir($fullpath);
                }
            }
        }

        closedir($dh);
        //删除当前文件夹：
        if (rmdir($dir)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 递归修改目录权限
     *
     * @param mixed $path 目录
     * @param mixed $filemode 权限
     * @return bool
     */
    public static function chmodr($path, $filemode)
    {
        if (!is_dir($path)) {
            return @chmod($path, $filemode);
        }
        
        $dh = opendir($path);
        while (($file = readdir($dh)) !== false) {
            if ($file != '.' && $file != '..') {
                $fullpath = $path . '/' . $file;
                if (is_link($fullpath)) {
                    return false;
                } elseif (!is_dir($fullpath) && !@chmod($fullpath, $filemode)) {
                    return false;
                } elseif (!self::chmodr($fullpath, $filemode)) {
                    return false;
                }
            }
        }
        
        closedir($dh);
        
        if (@chmod($path, $filemode)) {
            return true;
        } else {
            return false;
        }
    }
    public static function time2second($time)
    {
        if (is_numeric($time)) {
            $value = array(
                "years" => 0, "days" => 0, "hours" => 0,
                "minutes" => 0, "seconds" => 0,
            );
            if ($time >= 31556926) {
                $value["years"] = floor($time/31556926);
                $time = ($time%31556926);
            }
            if ($time >= 86400) {
                $value["days"] = floor($time/86400);
                $time = ($time%86400);
            }
            if ($time >= 3600) {
                $value["hours"] = floor($time/3600);
                $time = ($time%3600);
            }
            if ($time >= 60) {
                $value["minutes"] = floor($time/60);
                $time = ($time%60);
            }
            $value["seconds"] = floor($time);
            return $value["days"] ."d ". $value["hours"] ."h ". $value["minutes"] ."m ".$value["seconds"]."s";
        } else {
            return false;
        }
    }
    public static function getPercent($num1, $num2)
    {
        if ($num2 <= 0) {
            return 0;
        }

        return round(($num1/$num2) * 100, 2);
    }
    public static function daemonize()
    {
        $pid = pcntl_fork();
        if ($pid == -1) {
            die("fork(1) failed!\n");
        } elseif ($pid > 0) {
        //让由用户启动的进程退出
            exit(0);
        }

        //建立一个有别于终端的新session以脱离终端
        posix_setsid();

        $pid = pcntl_fork();
        if ($pid == -1) {
            die("fork(2) failed!\n");
        } elseif ($pid > 0) {
        //父进程退出, 剩下子进程成为最终的独立进程
            exit(0);
        }
    }
}
