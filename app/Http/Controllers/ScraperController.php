<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Goutte\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;

class ScraperController extends Controller
{
    private $results = [];
    private $lyrics = '';


    public function getArtistSongs($id)
    {
        $json = Redis::get('mxm:artistSongs:'.$id);

        if(!isset($json)) {

            $client = new Client();
            $url    = 'https://www.musixmatch.com/artist/' . $id;
            $page   = $client->request('GET', $url);

            $items = [];
            $this->results['date'] = time();


            if ($loadPage = $page->filter('.page-load-more')->attr('href')) {
                $this->results['nextPage'] = $page->filter('.page-load-more')->attr('href');
            } else {
                $this->results['nextPage'] = null;
            }

            $page->filter(".showPosition")->each(function ($item) {
                $this->results['items'][] = [
                    'name'   => $item->filter("h2")->text(),
                    'artist' => $item->filter("span.artist-field")->text(),
                    'link'   => $this->remakeLink($item->filter("a.title")->attr("href"), 'lyrics' .
                        ''),

                    'imgUrl' => $this->imageScraper($item),
                ];
            });
            $json = json_encode($this->results);
            Redis::set('mxm:artistSongs:'.$id, $json, 'EX', 3600);
        }

        return response($json, 200)
            ->header('Content-Type', 'application/json');
    }

    public function getLyrics($id)
    {
        $json = Redis::get('mxm:lyrics:'.$id);

        if(!isset($json)) {

            $url      = 'https://www.musixmatch.com/lyrics/' . str_replace("_", "/", $id);
            $response = Http::get($url);


            $html                    = $response->body();
            $this->results           = $this->lyricsDataWrapper($html);
            $this->results['date'] = time();
            $this->results['lyrics'] = $this->lyricsWrapper($html);
            $json = json_encode($this->results);
            Redis::set('mxm:lyrics:'.$id, $json, 'EX', 3600);
        }

        return response($json, 200)
            ->header('Content-Type', 'application/json');
    }

    public function search($query)
    {
        $json = Redis::get('mxm:search:'.$query);

        if(!isset($json)) {

            $client = new Client();
            $url    = 'https://www.musixmatch.com/search/' . $query;
            $page   = $client->request('GET', $url);
            $this->results['date'] = time();
            $page->filter("ul.artists")->filter("a.cover")->each(function ($artist) {
                $this->results['artists'][] = [
                    'name' => $artist->text(),
                    'link' => $this->remakeLink($artist->attr("href"), 'artist'),
                ];
            });
            $page->filter("ul.tracks")->filter("a.title")->each(function ($tracks) {
                $this->results['tracks'][] = [
                    'name' => $tracks->text(),
                    'link' => $this->remakeLink($tracks->attr("href"), 'lyrics'),
                ];
            });
            $json = json_encode($this->results);
            Redis::set('mxm:search:'.$query, $json, 'EX', 3600);
        }

        return response($json, 200)
            ->header('Content-Type', 'application/json');
    }


    private function imageScraper($item)
    {
        $array = [];
        $crawledCode = $item->filter(".media-card-picture")->filter('img')->attr('srcset');
        $images = explode(",",$crawledCode);

        foreach ($images as $image) {
            $item = explode(" ",trim($image));
            $array[$item[1]] = $item[0];
        }

        return $array;
    }

    private function remakeLink($url,$type)
    {

        if($type == 'lyrics') {
            $url = '/getLyrics/' . str_replace("/", "_", str_replace("/lyrics/", "", $url));
        } else if($type == 'artist') {
            $url = '/getArtistSongs/' . str_replace("/artist/", "", $url);
        }

        return $url;
    }


    private function lyricsWrapper($html)
    {
        $re = '/"explicit":.*?,"body":"(.*?)",/m';
        preg_match_all($re, $html, $matches, PREG_SET_ORDER, 0);
        return trim(stripslashes(str_replace( "\\n", '<br>', $matches[0][1] )));
    }

    private function lyricsDataWrapper($html)
    {
        $re = '/"byArtist":{"@type":"MusicGroup","name":"(.*?)"},"name":"(.*?)",/m';
        preg_match_all($re, $html, $matches, PREG_SET_ORDER, 0);
        return ['name'=>trim(str_replace( "\\n", '<br>', $matches[0][1])),
                'artists' =>trim(str_replace( "\\n", '<br>', $matches[0][2] ))];
    }
}
