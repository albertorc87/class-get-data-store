<?php
/**
 *
 * This class recover the an app info from play store (google and itunes)
 *
 * @author  Alberto Ramírez <alberto.r.caballero.87@gmail.com>
 * @version 1
 */

    class Store
    {

        /**  
         * Url for use the api for itunes
         */
        private const API_ITUNES = 'http://ax.itunes.apple.com/WebObjects/MZStoreServices.woa/wa/wsLookup?id=';

        /**
         * Text of the mail message
         *
         * @param $url          URL from google play or itunes
         * @param $country      Country of the store (in some countries the apps isn't availables)
         * @param $country_lang Language in which you want to receive the data (only if is available)
         *
         * @return array
         */
        public static function getDataStore( $url = '',  $country = 'us',  $country_lang = 'us')
        {
            $country = strtolower($country);

            if (empty($url) || !is_string($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
                return self::response(false, 'The format for this url is not valid: ' . $url);
            }

            if (preg_match('/play.google/', $url)) {
                return self::dataGoogle($url, $country, $country_lang);
            } elseif (preg_match('/(itunes|apps).apple/', $url)) {
                return self::dataItunes($url, $country);
            } else {
                return self::response(false, 'The url isn\'t play google or itunes');
            }
        }

        private static function dataGoogle($google_url, $user_country, $country_lang)
        {
            $language = '';

            if( !empty(  $country_lang ) ) {
                $language = '&hl=' . $country_lang;
            }
            else {
                $language = '&hl=' . $user_country;
            }
            $res = self::simpleCallCurl($google_url . '&gl=' . $user_country . $language);

            //Sino funciona probamos con us al menos que ya fuera us antes, en ese caso lo tiramos sin país a ver si cuela
            if ($res['code'] != '200' && $user_country != 'us') {
                $res = self::simpleCallCurl($google_url . '&gl=us&hl=en');
                //Si vuelve a fallar con us tiramos sin país a ver si cuela
                if ($res['code'] != '200') {
                    $res = self::simpleCallCurl($google_url);
                    if ($res['code'] != '200') {
                        return self::response(false, 'Error get data google play 1');
                    }
                }
            } elseif ($res['code'] != '200') {
                $res = self::simpleCallCurl($google_url . $language);
                if ($res['code'] != '200') {
                    return self::response(false, 'Error get data google play 2');
                }
            }

            $data = [
                'title'       => '',
                'description' => '',
                'icon'        => '',
                'images'      => '',
                'videos'      => '',
                'avg'         => '',
                'num_votes'   => ''
            ];

            if (preg_match('!<meta itemprop=.name. content=.(?<title>[^\'\"]+).!sm', $res['content'], $m)) {
                $data['title'] = trim($m['title']);
            } elseif (preg_match('!id=.main-title.>(?<title>[^-–]+)(-|–)!sm', $res['content'], $m)) {
                $data['title'] = trim($m['title']);
            } elseif (preg_match('!id=.main-title.>(?<title>(.*?)+)(-|–)!sm', $res['content'], $m)) {
                $data['title'] = trim(str_replace('Google Play', '', $m['title']));
            }

            if (preg_match('!meta name=.description. content=.(?<description>[^\'\"]+).!sm', $res['content'], $m)) {
                $data['description'] = $m['description'];
            }
            elseif( preg_match('!itemprop=.description.><content><div jsname=.([^\'\"]+).>(?<description>.*?)<\/div>!sm', $res['content'], $m) ) {
                $data['description'] = $m['description'];   
            }
            elseif( preg_match('!<div jsname="sngebd">(?<description>.*?)<\/div>!sm', $res['content'], $m) ) {
                $data['description'] = $m['description'];   
            }


            if (preg_match('!<img aria-hidden=.true. src=.(?<icon>[^\'\"]+s180). class=!sm', $res['content'], $m)) {
                $data['icon'] = str_replace('s180', 'w300', $m['icon']);
            } elseif (preg_match('!<img src=.(?<icon>[^\'\"]+s180)(-rw|). srcset=!sm', $res['content'], $m)) {
                $data['icon'] = str_replace('s180', 'w300', $m['icon']);
            }

            if (preg_match_all('!<img aria-hidden=.true. src=.(?<images>[^\'\"]+h310). class=!sm', $res['content'], $m)) {
                $data['images'] = $m['images'];
            } elseif (preg_match_all('!<img src=.(?<images>[^\'\"]+h310)(-rw|). srcset=!sm', $res['content'], $m)) {
                $data['images'] = $m['images'];
            }

            if (preg_match('!data-trailer-url=.(?<video>[^\'\"]+).!sm', $res['content'], $m)) {
                $data['video'] = $m['video'];
            }

            if (preg_match('!itemprop=.ratingValue. content=.(?<avg>[^\'\"]+).!sm', $res['content'], $m)) {
                $data['rank_avg'] = round(str_replace(',', '.', $m['avg']), 1);
            } elseif (preg_match('!<div class=.BHMmbe. aria-label=.[^\'\"]+.>(?<avg>[^\'\"]+)</div>!sm', $res['content'], $m)) {
                $data['rank_avg'] = round(str_replace(',', '.', $m['avg']), 1);
            }

            if (preg_match('!itemprop=.ratingCount. content=.(?<user_rating>[^\'\"]+).!sm', $res['content'], $m)) {
                $data['rank_votes'] = $m['user_rating'];
            } elseif (preg_match('!<span class=.AYi5wd TBRnV.><span class=.. aria-label=.[^\'\"]+.>(?<user_rating>[^<]+)</span>!sm', $res['content'], $m)) {
                $data['rank_votes'] = str_replace([',', ' '], ['', ''], $m['user_rating']);
            }


            // Revisamos si se han rellenado todos los campos pq pueden haber cambiado algo
            if (empty($data['title']) || empty($data['description']) || empty($data['icon'])) {
                return self::response(false, 'Empty data');
            }

            return self::response(true, 'Success', $data);
        }

        private static function dataItunes($itunes_url, $user_country)
        {
            $bundle_id = '';
            if (!preg_match('/id=(?<bundle_id>[0-9]+)/', $itunes_url, $m)) {
                if (!preg_match('/id(?<bundle_id>[0-9]+)/', $itunes_url, $m)) {
                    return self::response(false, 'Error get bundle id itunes');
                }
            }
            
            $bundle_id = $m['bundle_id'];

            $url = self::API_ITUNES . $bundle_id;

            $res = self::simpleCallCurl($url . '&country=' . $user_country);
            $content = json_decode($res['content'], 1);

            if (!isset($content['resultCount']) || $content['resultCount'] < 1) {
                $country = 'us';
                if (preg_match('|\/(?<country>[A-Za-z]{2})\/|', $itunes_url, $m)) {
                    $country = $m['country'];
                }

                if ($country == 'wa') {
                    $country = 'us';
                }

                if ($country != $user_country) {
                    $res = self::simpleCallCurl($url . '&country=' . $country);
                } else {
                    $res = self::simpleCallCurl($url);
                }

                $content = json_decode($res['content'], 1);

                if (!isset($content['resultCount']) || $content['resultCount'] < 1) {
                    $res = self::simpleCallCurl($url);
                    $content = json_decode($res['content'], 1);
                }
            }

            if (!isset($content['resultCount']) || $content['resultCount'] < 1) {
                return self::response(false, 'Error get api id');
            }
            if ($content['results'][0]['wrapperType'] !== 'software') {
                return self::response(false, 'Error bundle, no software');
            }

            $info = $content['results'][0];

            if (!isset($info['averageUserRating'])) {
                $url = self::API_ITUNES . $bundle_id;
                $res = self::simpleCallCurl($url);
                $content = json_decode($res['content'], 1);
                $info['averageUserRating'] = isset($content['results'][0]['averageUserRating']) ? $content['results'][0]['averageUserRating'] : 0;
                $info['userRatingCount'] = isset($content['results'][0]['userRatingCount']) ? $content['results'][0]['userRatingCount'] : 0;
            }
            
            $data = [
                'title'         => $info['trackCensoredName'],
                'description'   => $info['description'],
                'icon'          => $info['artworkUrl512'],
                'images'        => array_merge($info['screenshotUrls'], $info['ipadScreenshotUrls']),
                'images_iphone' => $info['screenshotUrls'],
                'images_ipad'   => $info['ipadScreenshotUrls'],
                'videos'        => '',
                'avg'           => $info['averageUserRating'],
                'num_votes'     => $info['userRatingCount']
            ];

            return self::response(true, 'Success', $data);
        }

        private static function simpleCallCurl($url) 
        {
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:67.0) Gecko/20100101 Firefox/67.0');
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 100);
         
            $data['content'] = curl_exec($ch);
            $data['info'] = curl_getinfo($ch);

            $data['code'] = $data['info']['http_code'];
         
            curl_close($ch);
         
            return $data;
        }

        private static function response($status = false, $msg = '', $data = []) {
            return ['status' => $status, 'data' => $data, 'msg' => $msg];

        }
    }