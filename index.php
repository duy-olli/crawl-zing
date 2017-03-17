<?php
    set_time_limit(0);
    ini_set('default_charset', 'utf-8');
    define('ROOT_PATH', dirname(__FILE__));
    require_once ROOT_PATH . '/vendor/autoload.php';

    use Goutte\Client;
    use Symfony\Component\DomCrawler\Crawler;
    use League\Csv\Reader;
    use League\Csv\Writer;

    $client = new Client();

    // process search action
    $domain = 'http://mp3.zing.vn';
    $keyword = (string) codau2khongdau($_GET['keyword']);
    $urlSearch = $domain . '/tim-kiem/bai-hat.html?q=' . (implode('+', explode(' ', $keyword)));

    // search author
    $myAuthor = search($client, $urlSearch);

    if (!empty($myAuthor)) {
        $songLink = $domain . $myAuthor['link'];

        // get songs of author
        $mySongs = [];
        getSongs($client, $songLink, $mySongs);

        // create csv
        $filename = implode('-', explode(' ', $keyword)) . '.csv';
        $writer = Writer::createFromFileObject(new SplTempFileObject());
        $writer->setOutputBOM(Writer::BOM_UTF8);
        $writer->setNewline("\r\n");
        $header = [
            'STT',
            'Bài hát',
            'Ca sĩ',
            'Lượt nghe',
            'Đường dẫn',
            '128 Kbps'
        ];
        $writer->insertOne($header);

        $file = fopen(ROOT_PATH . '/public/' . $filename, 'w');
        fputcsv($file, $header);

        foreach ($mySongs as $index => $mySong) {
            $row = [
                $index + 1,
                $mySong['title'],
                $mySong['author'],
                $mySong['count_listen'],
                $mySong['link'],
                $mySong['128kbps']
            ];

            $writer->insertOne($row);

            fputcsv($file, $row);
        }

        // export to downloadable
        $writer->output($filename);

        // store to file
        fclose($file);
    }

    /////   DECLARE FUNCTION    /////

    // parse single page song of author
    function parseSongPage($client, $html) {
        $list = [];
        $songs = $html->filter('.list-item > ul > li');

        if ($songs->count() > 0) {
            foreach ($songs as $song) {
                $crawlerSong = new Crawler($song);

                $songId = $crawlerSong->attr('data-id');
                $title = $crawlerSong->filter('.info-dp > h3 > a');
                $titleArr = explode('-', $title->text());
                $listen = $crawlerSong->filter('.bar-chart > .fn-bar');

                // get download mp3 link
                $songUrl = 'http://api.mp3.zing.vn/api/mobile/song/getsonginfo?requestdata={"id":"'. $songId .'"}';
                // $responseJSON = json_decode(file_get_contents($songUrl), true);
                // var_dump($responseJSON['source']);

                $cURL = curl_init();
                curl_setopt($cURL, CURLOPT_URL, $songUrl);
                curl_setopt($cURL, CURLOPT_HTTPGET, true);
                curl_setopt($cURL, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($cURL, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json',
                    'Accept: application/json'
                ));
                $result = curl_exec($cURL);
                curl_close($cURL);

                $responseJSON = json_decode($result, true);
                $source = '';
                if (isset($responseJSON['source']['128'])) {
                    $source = $responseJSON['source']['128'];
                }

                $list[] = [
                    'title' => trim($titleArr[0]),
                    'author' => trim($titleArr[1]),
                    'link' => $title->attr('href'),
                    'count_listen' => $listen->attr('data-total'),
                    '128kbps' => $source
                ];
            }
        }

        return $list;
    }

    // get all songs of author
    function getSongs($client, $url, &$songs) {

        $totalPage = 1;
        $html = $client->request('GET', $url);

        $pagination = $html->filter('.pagination > ul > li > a');
        $paginationLink = $pagination->last()->attr('href');
        preg_match('/(.*)&page=([0-9]+)/', $paginationLink, $matches);
        if (isset($matches[2])) {
            $totalPage = 25;
        }

        $songs = parseSongPage($client, $html);

        if ($totalPage > 1) {
            for ($page = 2; $page <= $totalPage; $page++) {
                $html = $client->request('GET', $url . '?page=' . $page);
                $songs = array_merge($songs, parseSongPage($client, $html));
            }
        }
    }

    // search function
    function search($client, $url) {
        $html = $client->request('GET', $url);

        $author = $html->filter('.artist-info > h2 > a');
        if ($author->count()) {
            return [
                'name' => $author->attr('title'),
                'link' => $author->attr('href') . '/bai-hat'
            ];
        } else {
            return [];
        }
    }

    // remove utf8 character
    function codau2khongdau($string = '', $alphabetOnly = false, $tolower = true)
    {
        $output =  $string;
        if ($output != '') {
            //Tien hanh xu ly bo dau o day
            $search = array(
                '&#225;', '&#224;', '&#7843;', '&#227;', '&#7841;',                 // a' a` a? a~ a.
                '&#259;', '&#7855;', '&#7857;', '&#7859;', '&#7861;', '&#7863;',    // a( a('
                '&#226;', '&#7845;', '&#7847;', '&#7849;', '&#7851;', '&#7853;',    // a^ a^'..
                '&#273;',                                                       // d-
                '&#233;', '&#232;', '&#7867;', '&#7869;', '&#7865;',                // e' e`..
                '&#234;', '&#7871;', '&#7873;', '&#7875;', '&#7877;', '&#7879;',    // e^ e^'
                '&#237;', '&#236;', '&#7881;', '&#297;', '&#7883;',                 // i' i`..
                '&#243;', '&#242;', '&#7887;', '&#245;', '&#7885;',                 // o' o`..
                '&#244;', '&#7889;', '&#7891;', '&#7893;', '&#7895;', '&#7897;',    // o^ o^'..
                '&#417;', '&#7899;', '&#7901;', '&#7903;', '&#7905;', '&#7907;',    // o* o*'..
                '&#250;', '&#249;', '&#7911;', '&#361;', '&#7909;',                 // u'..
                '&#432;', '&#7913;', '&#7915;', '&#7917;', '&#7919;', '&#7921;',    // u* u*'..
                '&#253;', '&#7923;', '&#7927;', '&#7929;', '&#7925;',               // y' y`..
                '&#193;', '&#192;', '&#7842;', '&#195;', '&#7840;',                 // A' A` A? A~ A.
                '&#258;', '&#7854;', '&#7856;', '&#7858;', '&#7860;', '&#7862;',    // A( A('..
                '&#194;', '&#7844;', '&#7846;', '&#7848;', '&#7850;', '&#7852;',    // A^ A^'..
                '&#272;',                                                           // D-
                '&#201;', '&#200;', '&#7866;', '&#7868;', '&#7864;',                // E' E`..
                '&#202;', '&#7870;', '&#7872;', '&#7874;', '&#7876;', '&#7878;',    // E^ E^'..
                '&#205;', '&#204;', '&#7880;', '&#296;', '&#7882;',                 // I' I`..
                '&#211;', '&#210;', '&#7886;', '&#213;', '&#7884;',                 // O' O`..
                '&#212;', '&#7888;', '&#7890;', '&#7892;', '&#7894;', '&#7896;',    // O^ O^'..
                '&#416;', '&#7898;', '&#7900;', '&#7902;', '&#7904;', '&#7906;',    // O* O*'..
                '&#218;', '&#217;', '&#7910;', '&#360;', '&#7908;',                 // U' U`..
                '&#431;', '&#7912;', '&#7914;', '&#7916;', '&#7918;', '&#7920;',    // U* U*'..
                '&#221;', '&#7922;', '&#7926;', '&#7928;', '&#7924;'                // Y' Y`..
            );
            $search2 = array(
                'á', 'à', 'ả', 'ã', 'ạ',                // a' a` a? a~ a.
                'ă', 'ắ', 'ằ', 'ẳ', 'ẵ', 'ặ',   // a( a('
                'â', 'ấ', 'ầ', 'ẩ', 'ẫ', 'ậ',   // a^ a^'..
                'đ',                                                        // d-
                'é', 'è', 'ẻ', 'ẽ', 'ẹ',                // e' e`..
                'ê', 'ế', 'ề', 'ể', 'ễ', 'ệ',   // e^ e^'
                'í', 'ì', 'ỉ', 'ĩ', 'ị',                    // i' i`..
                'ó', 'ò', 'ỏ', 'õ', 'ọ',                    // o' o`..
                'ô', 'ố', 'ồ', 'ổ', 'ỗ', 'ộ',   // o^ o^'..
                'ơ', 'ớ', 'ờ', 'ở', 'ỡ', 'ợ',   // o* o*'..
                'ú', 'ù', 'ủ', 'ũ', 'ụ',                    // u'..
                'ư', 'ứ', 'ừ', 'ử', 'ữ', 'ự',   // u* u*'..
                'ý', 'ỳ', 'ỷ', 'ỹ', 'ỵ',                // y' y`..
                'Á', 'À', 'Ả', 'Ã', 'Ạ',                    // A' A` A? A~ A.
                'Ă', 'Ắ', 'Ằ', 'Ẳ', 'Ẵ', 'Ặ',   // A( A('..
                'Â', 'Ấ', 'Ầ', 'Ẩ', 'Ẫ', 'Ậ',   // A^ A^'..
                'Đ',                                                            // D-
                'É', 'È', 'Ẻ', 'Ẽ', 'Ẹ',                // E' E`..
                'Ê', 'Ế', 'Ề', 'Ể', 'Ễ', 'Ệ',   // E^ E^'..
                'Í', 'Ì', 'Ỉ', 'Ĩ', 'Ị',                    // I' I`..
                'Ó', 'Ò', 'Ỏ', 'Õ', 'Ọ',                    // O' O`..
                'Ô', 'Ố', 'Ồ', 'Ổ', 'Ỗ', 'Ộ',   // O^ O^'..
                'Ơ', 'Ớ', 'Ờ', 'Ở', 'Ỡ', 'Ợ',   // O* O*'..
                'Ú', 'Ù', 'Ủ', 'Ũ', 'Ụ',                    // U' U`..
                'Ư', 'Ứ', 'Ừ', 'Ử', 'Ữ', 'Ự',   // U* U*'..
                'Ý', 'Ỳ', 'Ỷ', 'Ỹ', 'Ỵ'             // Y' Y`..
            );
            $replace = array(
                'a', 'a', 'a', 'a', 'a',
                'a', 'a', 'a', 'a', 'a', 'a',
                'a', 'a', 'a', 'a', 'a', 'a',
                'd',
                'e', 'e', 'e', 'e', 'e',
                'e', 'e', 'e', 'e', 'e', 'e',
                'i', 'i', 'i', 'i', 'i',
                'o', 'o', 'o', 'o', 'o',
                'o', 'o', 'o', 'o', 'o', 'o',
                'o', 'o', 'o', 'o', 'o', 'o',
                'u', 'u', 'u', 'u', 'u',
                'u', 'u', 'u', 'u', 'u', 'u',
                'y', 'y', 'y', 'y', 'y',
                'A', 'A', 'A', 'A', 'A',
                'A', 'A', 'A', 'A', 'A', 'A',
                'A', 'A', 'A', 'A', 'A', 'A',
                'D',
                'E', 'E', 'E', 'E', 'E',
                'E', 'E', 'E', 'E', 'E', 'E',
                'I', 'I', 'I', 'I', 'I',
                'O', 'O', 'O', 'O', 'O',
                'O', 'O', 'O', 'O', 'O', 'O',
                'O', 'O', 'O', 'O', 'O', 'O',
                'U', 'U', 'U', 'U', 'U',
                'U', 'U', 'U', 'U', 'U', 'U',
                'Y', 'Y', 'Y', 'Y', 'Y'
            );
            //print_r($search);
            $output = str_replace($search, $replace, $output);
            $output = str_replace($search2, $replace, $output);

            if ($tolower) {
                $output = strtolower($output);
            }
        }
        return $output;
    }
