<?php

/**
 * Parst die unterschiedlichen Tabellen einer Seite
 * @param  [type] $url [description]
 * @return [type]      [description]
 */
function getDetailView($url) {
    $details['name'] = trim(getHTMLObj($url)->find('.breadcrumb', 0)->find('span[itemprop=name]', -1)->plaintext);
    $details['weburl'] = $url;
    $details['menu'] = tablesToArr(getHTMLObj($url)->find('div[itemprop=articleBody] div.moduletable', 0));
    
    return $details;
}

/**
 * Nächste Woche funktioniert noch nicht, weil Datum nicht ermittelt wird...
 * @param  [type] $obj    [description]
 * @return [type]         [description]
 */
function tablesToArr($obj) {
    $arr = array();
    $timeframe = explode(" - ", trim($obj->find('> text', 2), " \t\n\r\0\x0B()"));
    $dates[0] = createDateRangeArray($timeframe[0], $timeframe[1]);
    
    //$dates[1] = createDateRangeArray("14.12.15", "18.12.15");
    $tables = $obj->find('table');
    if (array_key_exists(0, $tables) && array_key_exists(1, $tables) && array_key_exists(0, $dates) && array_key_exists(1, $dates)) {
        return array_merge(tableToArr($tables[0], $dates[0]), tableToArr($tables[1], $dates[1]));
    } 
    elseif (array_key_exists(0, $tables) && array_key_exists(0, $dates)) {
        return tableToArr($tables[0], $dates[0]);
    } 
    else {
        return false;
    }
}

/**
 * Das eigentliche Parsen der Tabelle
 * @param  [type] $table [description]
 * @param  [type] $dates [description]
 * @return [type]        [description]
 */
function tableToArr($table, $dates) {
    foreach ($table->find('tr') as $rowNo => $row) {
        if ($rowNo == 0) continue;
        foreach ($row->find('td') as $clmNo => $clm) {
            if ($clmNo == 0) {
                $categoryPrice = trim(preg_replace('/^\D*/', '', $clm->plaintext));
                $category = trim(str_replace(array($categoryPrice, "-\r\n ", "\t", "\r", "\n", "           ", "    "), '', $clm->plaintext));
            } 
            else {
                
                foreach ($clm->find('div.speise_eintrag') as $article) {
                    if (empty($article)) continue;
                    $title = $article->plaintext;
                    $allergies = $price = array();
                    
                    preg_match('#\((.*?)\)#', $title, $allergies); // Matcht eingeklammerten Text
                    
                    // Parse Allergiestoffe
                    if (array_key_exists(1, $allergies)) $title = str_replace('(' . $allergies[1] . ')', '', $title);
                    
                    // entferne Allergiestoffe aus Titel
                    $allergies = (array_key_exists(1, $allergies) ? explode(",", $allergies[1]) : NULL);
                    
                    preg_match('/(\d{1,2},\d{2})/', $title, $price); // Matcht Dezimalzahlen
                    
                    // Parse den Preis
                    if (array_key_exists(1, $price)) $title = str_replace($price[1], '', $title); // entferne Preis aus Titel


                    $price = (empty($price[1]) ? $categoryPrice : $price[1]);
                    
                    $title = trim(str_replace("  ", " ", $title));
                    $price = tofloat($price);
                    
                    if (!array_key_exists($clmNo - 1, $dates) || empty($category) || empty($title)) continue;
                    $arr[$dates[$clmNo - 1]][$category][] = array(
                        'name' => $title,
                        'price' => $price,
                        'allergies' => $allergies
                    );
                    unset($title, $price, $allergies);
                }
            }
        }
    }
    return $arr;
}

/**
 * Ermittelt die URIs der Mensastandorte
 * @param  [type] $url URI der Übersicht
 * @return [type]      [description]
 */
function getOverView($url) {
    $links = array();
    $firstTitle = true;
    foreach (getHTMLObj($url)->find('div[itemprop=articleBody] tr') as $row) {
        $link = $row->find('td a', 0);
        if ($link) {
            $slug = str_replace(explode("%s", DETAILVIEW_URI), "", $link->attr['href']);
            $links[sanitize_title_with_dashes($slug)] = array(
                'name' => $link->attr['title'],
                'url' => SCRIPT_URL.$slug,
                'weburl' => WEBSITE.$link->attr['href']
            );
            
        } 
        elseif ($firstTitle && $row->find('td h5', 0)) {
            $firstTitle = false;
        } 
        else {
            break;
        }
    }
    return $links;
}

/**
 * Parst eine URL und gibt deren HTML als Objekt aus. Wenn es eine relative URL ist wird die Konstante WEBSITE davor gehängt
 * @param  [type] $url [description]
 * @return [type]      [description]
 */
function getHTMLObj($url) {
    if (filter_var($url, FILTER_VALIDATE_URL) === FALSE) $url = WEBSITE . $url;
    if (filter_var($url, FILTER_VALIDATE_URL) === FALSE) return false;
    $output = file_get_html($url);
    return $output;
}

/**
 * Frage JSON Code von URL aus Datei oder vom Server ab
 * @param  [type] $url [description]
 * @return [type]      [description]
 */
function getJson($url, $age = '120 minutes') {
    $cacheFile = 'cache' . DIRECTORY_SEPARATOR . md5($url) . ".json";
    
    if (file_exists($cacheFile)) {
        $fh = fopen($cacheFile, 'r');
        $cacheTime = trim(filemtime($cacheFile));
        // Wenn Datei vor unter X Minuten erzeugt wurde, gib einfach die Datei aus
        if ($cacheTime > strtotime('-' . $age)) {
            return fread($fh, filesize($cacheFile));
        }
        
        // andernfalls wird die Datei gelöscht
        fclose($fh);
        unlink($cacheFile);
    }
    if ($url == WEBSITE.OVERVIEW_URI) {
        $json = json_encode(getOverView($url));

    } else {
        // Generierung der JSON Daten
        $json = json_encode(getDetailView($url));
    }
    // Speicherung
    $fh = fopen($cacheFile, 'w');
    fwrite($fh, $json);
    fclose($fh);
    
    return $json;
}

/**
 * Wordpress Funktion um Titel von Großschreibung und Sonderzeichen zu befreien
 * @param  [type] $title [description]
 * @return [type]        [description]
 */
function sanitize_title_with_dashes($title) {
    $title = strip_tags($title);
    
    // Preserve escaped octets.
    $title = preg_replace('|%([a-fA-F0-9][a-fA-F0-9])|', '---$1---', $title);
    
    // Remove percent signs that are not part of an octet.
    $title = str_replace('%', '', $title);
    
    // Restore octets.
    $title = preg_replace('|---([a-fA-F0-9][a-fA-F0-9])---|', '%$1', $title);
    $title = remove_accents($title);
    if (seems_utf8($title)) {
        if (function_exists('mb_strtolower')) {
            $title = mb_strtolower($title, 'UTF-8');
        }
    }
    $title = strtolower($title);
    $title = preg_replace('/&.+?;/', '', $title);
    
    // kill entities
    $title = preg_replace('/[^%a-z0-9 _-]/', '', $title);
    $title = preg_replace('/\s+/', '-', $title);
    $title = preg_replace('|-+|', '-', $title);
    $title = trim($title, '-');
    return $title;
}

/**
 * Ermittelt die Datums zwischen zwei Datums
 * @param  string $strDateFrom d.m.y 20.10.05
 * @param  string $strDateTo   d.m.y 20.10.05
 * @return array               Array of strings (Y-m-d 2005-10-20)
 */
function createDateRangeArray($strDateFrom, $strDateTo) {
    
    $aryRange = array();
    
    $iDateFrom = mktime(1, 0, 0, substr($strDateFrom, 3, 2), substr($strDateFrom, 0, 2), substr($strDateFrom, 6, 2));
    $iDateTo = mktime(1, 0, 0, substr($strDateTo, 3, 2), substr($strDateTo, 0, 2), substr($strDateTo, 6, 2));
    
    if ($iDateTo >= $iDateFrom) {
        array_push($aryRange, date('Y-m-d', $iDateFrom));
        
        // first entry
        while ($iDateFrom < $iDateTo) {
            $iDateFrom+= 86400;
            
            // add 24 hours
            array_push($aryRange, date('Y-m-d', $iDateFrom));
        }
    }
    return $aryRange;
}

/**
 * Converts all accent characters to ASCII characters.
 *
 * If there are no accent characters, then the string given is just returned.
 *
 * @param string $string Text that might have accent characters
 * @return string Filtered string with replaced "nice" characters.
 */
function remove_accents($string) {
    if (!preg_match('/[\x80-\xff]/', $string)) return $string;
    
    if (seems_utf8($string)) {
        $chars = array(
        
        // Decompositions for Latin-1 Supplement
        chr(195) . chr(128) => 'A', chr(195) . chr(129) => 'A', chr(195) . chr(130) => 'A', chr(195) . chr(131) => 'A', chr(195) . chr(132) => 'A', chr(195) . chr(133) => 'A', chr(195) . chr(135) => 'C', chr(195) . chr(136) => 'E', chr(195) . chr(137) => 'E', chr(195) . chr(138) => 'E', chr(195) . chr(139) => 'E', chr(195) . chr(140) => 'I', chr(195) . chr(141) => 'I', chr(195) . chr(142) => 'I', chr(195) . chr(143) => 'I', chr(195) . chr(145) => 'N', chr(195) . chr(146) => 'O', chr(195) . chr(147) => 'O', chr(195) . chr(148) => 'O', chr(195) . chr(149) => 'O', chr(195) . chr(150) => 'O', chr(195) . chr(153) => 'U', chr(195) . chr(154) => 'U', chr(195) . chr(155) => 'U', chr(195) . chr(156) => 'U', chr(195) . chr(157) => 'Y', chr(195) . chr(159) => 's', chr(195) . chr(160) => 'a', chr(195) . chr(161) => 'a', chr(195) . chr(162) => 'a', chr(195) . chr(163) => 'a', chr(195) . chr(164) => 'a', chr(195) . chr(165) => 'a', chr(195) . chr(167) => 'c', chr(195) . chr(168) => 'e', chr(195) . chr(169) => 'e', chr(195) . chr(170) => 'e', chr(195) . chr(171) => 'e', chr(195) . chr(172) => 'i', chr(195) . chr(173) => 'i', chr(195) . chr(174) => 'i', chr(195) . chr(175) => 'i', chr(195) . chr(177) => 'n', chr(195) . chr(178) => 'o', chr(195) . chr(179) => 'o', chr(195) . chr(180) => 'o', chr(195) . chr(181) => 'o', chr(195) . chr(182) => 'o', chr(195) . chr(182) => 'o', chr(195) . chr(185) => 'u', chr(195) . chr(186) => 'u', chr(195) . chr(187) => 'u', chr(195) . chr(188) => 'u', chr(195) . chr(189) => 'y', chr(195) . chr(191) => 'y',
        
        // Decompositions for Latin Extended-A
        chr(196) . chr(128) => 'A', chr(196) . chr(129) => 'a', chr(196) . chr(130) => 'A', chr(196) . chr(131) => 'a', chr(196) . chr(132) => 'A', chr(196) . chr(133) => 'a', chr(196) . chr(134) => 'C', chr(196) . chr(135) => 'c', chr(196) . chr(136) => 'C', chr(196) . chr(137) => 'c', chr(196) . chr(138) => 'C', chr(196) . chr(139) => 'c', chr(196) . chr(140) => 'C', chr(196) . chr(141) => 'c', chr(196) . chr(142) => 'D', chr(196) . chr(143) => 'd', chr(196) . chr(144) => 'D', chr(196) . chr(145) => 'd', chr(196) . chr(146) => 'E', chr(196) . chr(147) => 'e', chr(196) . chr(148) => 'E', chr(196) . chr(149) => 'e', chr(196) . chr(150) => 'E', chr(196) . chr(151) => 'e', chr(196) . chr(152) => 'E', chr(196) . chr(153) => 'e', chr(196) . chr(154) => 'E', chr(196) . chr(155) => 'e', chr(196) . chr(156) => 'G', chr(196) . chr(157) => 'g', chr(196) . chr(158) => 'G', chr(196) . chr(159) => 'g', chr(196) . chr(160) => 'G', chr(196) . chr(161) => 'g', chr(196) . chr(162) => 'G', chr(196) . chr(163) => 'g', chr(196) . chr(164) => 'H', chr(196) . chr(165) => 'h', chr(196) . chr(166) => 'H', chr(196) . chr(167) => 'h', chr(196) . chr(168) => 'I', chr(196) . chr(169) => 'i', chr(196) . chr(170) => 'I', chr(196) . chr(171) => 'i', chr(196) . chr(172) => 'I', chr(196) . chr(173) => 'i', chr(196) . chr(174) => 'I', chr(196) . chr(175) => 'i', chr(196) . chr(176) => 'I', chr(196) . chr(177) => 'i', chr(196) . chr(178) => 'IJ', chr(196) . chr(179) => 'ij', chr(196) . chr(180) => 'J', chr(196) . chr(181) => 'j', chr(196) . chr(182) => 'K', chr(196) . chr(183) => 'k', chr(196) . chr(184) => 'k', chr(196) . chr(185) => 'L', chr(196) . chr(186) => 'l', chr(196) . chr(187) => 'L', chr(196) . chr(188) => 'l', chr(196) . chr(189) => 'L', chr(196) . chr(190) => 'l', chr(196) . chr(191) => 'L', chr(197) . chr(128) => 'l', chr(197) . chr(129) => 'L', chr(197) . chr(130) => 'l', chr(197) . chr(131) => 'N', chr(197) . chr(132) => 'n', chr(197) . chr(133) => 'N', chr(197) . chr(134) => 'n', chr(197) . chr(135) => 'N', chr(197) . chr(136) => 'n', chr(197) . chr(137) => 'N', chr(197) . chr(138) => 'n', chr(197) . chr(139) => 'N', chr(197) . chr(140) => 'O', chr(197) . chr(141) => 'o', chr(197) . chr(142) => 'O', chr(197) . chr(143) => 'o', chr(197) . chr(144) => 'O', chr(197) . chr(145) => 'o', chr(197) . chr(146) => 'OE', chr(197) . chr(147) => 'oe', chr(197) . chr(148) => 'R', chr(197) . chr(149) => 'r', chr(197) . chr(150) => 'R', chr(197) . chr(151) => 'r', chr(197) . chr(152) => 'R', chr(197) . chr(153) => 'r', chr(197) . chr(154) => 'S', chr(197) . chr(155) => 's', chr(197) . chr(156) => 'S', chr(197) . chr(157) => 's', chr(197) . chr(158) => 'S', chr(197) . chr(159) => 's', chr(197) . chr(160) => 'S', chr(197) . chr(161) => 's', chr(197) . chr(162) => 'T', chr(197) . chr(163) => 't', chr(197) . chr(164) => 'T', chr(197) . chr(165) => 't', chr(197) . chr(166) => 'T', chr(197) . chr(167) => 't', chr(197) . chr(168) => 'U', chr(197) . chr(169) => 'u', chr(197) . chr(170) => 'U', chr(197) . chr(171) => 'u', chr(197) . chr(172) => 'U', chr(197) . chr(173) => 'u', chr(197) . chr(174) => 'U', chr(197) . chr(175) => 'u', chr(197) . chr(176) => 'U', chr(197) . chr(177) => 'u', chr(197) . chr(178) => 'U', chr(197) . chr(179) => 'u', chr(197) . chr(180) => 'W', chr(197) . chr(181) => 'w', chr(197) . chr(182) => 'Y', chr(197) . chr(183) => 'y', chr(197) . chr(184) => 'Y', chr(197) . chr(185) => 'Z', chr(197) . chr(186) => 'z', chr(197) . chr(187) => 'Z', chr(197) . chr(188) => 'z', chr(197) . chr(189) => 'Z', chr(197) . chr(190) => 'z', chr(197) . chr(191) => 's',
        
        // Euro Sign
        chr(226) . chr(130) . chr(172) => 'E',
        
        // GBP (Pound) Sign
        chr(194) . chr(163) => '');
        
        $string = strtr($string, $chars);
    } 
    else {
        
        // Assume ISO-8859-1 if not UTF-8
        $chars['in'] = chr(128) . chr(131) . chr(138) . chr(142) . chr(154) . chr(158) . chr(159) . chr(162) . chr(165) . chr(181) . chr(192) . chr(193) . chr(194) . chr(195) . chr(196) . chr(197) . chr(199) . chr(200) . chr(201) . chr(202) . chr(203) . chr(204) . chr(205) . chr(206) . chr(207) . chr(209) . chr(210) . chr(211) . chr(212) . chr(213) . chr(214) . chr(216) . chr(217) . chr(218) . chr(219) . chr(220) . chr(221) . chr(224) . chr(225) . chr(226) . chr(227) . chr(228) . chr(229) . chr(231) . chr(232) . chr(233) . chr(234) . chr(235) . chr(236) . chr(237) . chr(238) . chr(239) . chr(241) . chr(242) . chr(243) . chr(244) . chr(245) . chr(246) . chr(248) . chr(249) . chr(250) . chr(251) . chr(252) . chr(253) . chr(255);
        
        $chars['out'] = "EfSZszYcYuAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy";
        
        $string = strtr($string, $chars['in'], $chars['out']);
        $double_chars['in'] = array(chr(140), chr(156), chr(198), chr(208), chr(222), chr(223), chr(230), chr(240), chr(254));
        $double_chars['out'] = array('OE', 'oe', 'AE', 'DH', 'TH', 'ss', 'ae', 'dh', 'th');
        $string = str_replace($double_chars['in'], $double_chars['out'], $string);
    }
    
    return $string;
}

function seems_utf8($str) {
    $length = strlen($str);
    for ($i = 0; $i < $length; $i++) {
        $c = ord($str[$i]);
        if ($c < 0x80) $n = 0; // 0bbbbbbb
        elseif (($c & 0xE0) == 0xC0) $n = 1; // 110bbbbb
        elseif (($c & 0xF0) == 0xE0) $n = 2; // 1110bbbb
        elseif (($c & 0xF8) == 0xF0) $n = 3; // 11110bbb
        elseif (($c & 0xFC) == 0xF8) $n = 4; // 111110bb
        elseif (($c & 0xFE) == 0xFC) $n = 5; // 1111110b
        else return false; // Does not match any model
        for ($j = 0; $j < $n; $j++) { // n bytes matching 10bbbbbb follow ?
            if ((++$i == $length) || ((ord($str[$i]) & 0xC0) != 0x80)) return false;
        }
    }
    return true;
}

/**
 * Sucht den letzten Punkt oder das letzte Komma und nutzt das als Dezimaltrennzeichen
 * @param  string $num 1,02 oder 1.02
 * @return float
 */
function tofloat($num) {
    $dotPos = strrpos($num, '.');
    $commaPos = strrpos($num, ',');
    $sep = (($dotPos > $commaPos) && $dotPos) ? $dotPos : ((($commaPos > $dotPos) && $commaPos) ? $commaPos : false);
    
    if (!$sep) {
        return floatval(preg_replace("/[^0-9]/", "", $num));
    }
    
    return floatval(preg_replace("/[^0-9]/", "", substr($num, 0, $sep)) . '.' . preg_replace("/[^0-9]/", "", substr($num, $sep + 1, strlen($num))));
}