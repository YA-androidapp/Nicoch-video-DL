<?php

/**
 * Nicoch=>video=>DL
 *
 *   Required:
 *     PHP Simple HTML DOM Parser( http://sourceforge.net/projects/simplehtmldom/ )
 *     youtube-dl( https://github.com/rg3/youtube-dl/blob/master/README.md )
 *
 *   Call: $php index.php
 *
 *   CSV file(conf.csv): 
 *      Day of the Week | URL1                                      | URL2       | ...
 *     --------------------------------------------------------------------------------
 *      3               | http://ch.nic›vide›.jp/yorimoi            | takagi3_me | 
 *      4               | http://ch.nic›vide›.jp/saikikusuo02/video | 
 *
 */

error_reporting(0); // E_ALL & ~E_NOTICE);

require_once 'simple_html_dom.php';
setlocale(LC_ALL, 'ja_JP.UTF-8');
mb_language('Japanese');

// const
$schedule_filepath = dirname($_SERVER["SCRIPT_NAME"]).'/conf.csv';

// What day is it today
// Sun.:0or7 Mon.:1 ... Sat.:6
$wday = date('w');

// SJIS to UTF8
// $data = file_get_contents($schedule_filepath);
// $data = mb_convert_encoding($data, 'UTF-8', 'sjis-win');
// $temp = tmpfile();
// $meta = stream_get_meta_data($temp);
// fwrite($temp, $data);
// rewind($temp);
// $schedule_file = new SplFileObject($meta['uri']);

// UTF8
$schedule_file = new SplFileObject($schedule_filepath);
$schedule_file->setFlags(SplFileObject::READ_CSV);

$contents = array();
foreach ($schedule_file as $line) {
    //  $contents[] = $line;

    $wday_csv = (7 + $line[0]) % 7;
    if ("$wday_csv" == "$wday") {
        // $line[1] ... $line[n] : URLs

        $channels = array_slice($line, 1);
        foreach ((array) $channels as $uri) {
            if ($uri != '') {
                echo $uri . "<br>\n";
                parseChPage($uri);
            }
        }
        ;
    }
}

// fclose($temp);
$schedule_file = null;

// var_dump($contents);






function startsWith($haystack, $needle)
{
    return $needle === "" || strpos($haystack, $needle) === 0;
}
function endsWith($haystack, $needle)
{
    return $needle === "" || substr($haystack, -strlen($needle)) === $needle;
}

function parseChPage($uri)
{
    if (!endsWith($uri, "/video")) {
        $uri = $uri . "/video";
    }
    if (!startsWith($uri, "http://ch.nic›vide›.jp/")) {
        $uri = "http://ch.nic›vide›.jp/" . $uri;
    }

    $options = array(
        'http' => array(
            'method' => 'GET',
            'header' => 'User-Agent: Mozilla/5.0 (Windows NT 6.1; rv:11.0) Gecko/20100101 Firefox/11.0',
        ),
    );
    $context = stream_context_create($options);
    //$content = mb_convert_encoding(file_get_contents($uri), 'UTF-8', 'auto');
    $content = file_get_contents($uri, false, $context);
    $html = str_get_html($content);

    echo $html->find('h1.channel_name a')[0]->innertext . "\n";

    foreach ($html->find('h6.title a') as $list) {
        echo "<br />\n<a href='" . $list->href . "'>";
        echo $list->title;
        echo "</a>\n";
        download($list->href);
    }

    $html->clear();
    unset($html);
}

function download($videouri)
{
    $user='<USER_ID>';
    $pass='<PASSWORD>';

    // $cmd = '"%USERPROFILE%\Downloads\youtube-dl\youtube-dl.exe"'
    $cmd = '"youtube-dl"'
        . ' --output "%(title)s_%(autonumber)s_%(format_id)s_%(epoch)s.%(ext)s"'
        . ' --ignore-errors --no-overwrites --all-formats --dateafter now-15days --username ' . $user . ' --password ' . $pass . ' ' . $videouri;

    $cmd = $cmd . ' 2>&1';
    $handle = popen($cmd, 'r');
    $log = fgets($handle);
    $result = '';
    $done = 0;
    while (!feof($handle)) {
        $log = fgets($handle);
        echo $log . "\n";
        if (strpos($log, '[download] Destination: ') !== false) {
            $result = substr($log, strlen('[download] Destination: '));
        }
        if (strpos($log, '[download] 100% of ') !== false) {
            $done = 2;
        } else if (strpos($log, 'upload date is not in range') !== false) {
            $done = 1;
        } else if (strpos($log, 'ERROR: Unable to find video URL; please report this issue on https://yt-dl.org/bug .') !== false) {
            $done = 1;
        }
        ob_flush();
        flush();
    }
    pclose($handle);
}
