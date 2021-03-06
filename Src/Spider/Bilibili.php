<?php
/**
 * Created by PhpStorm.
 * User: jinyi
 * Date: 2018/8/2
 * Time: 下午2:03
 */

//https://api.bilibili.com/x/web-interface/search/type?jsonp=jsonp&search_type=photo&highlight=1&keyword=%E5%A4%A9%E4%BD%BF&page=2&callback=__jp0
class Bilibili
{
    public $userAgent = [
        "Connection: keep-alive",
        "Accept:text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8",
        "Upgrade-Insecure-Requests: 1",
        "DNT:1",
        "Accept-Language:zh-CN,zh;q=0.8",
        "User-Agent:Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.101 Safari/537.36"
    ];//设置用户user-agent

    public $spider_name = "Bilibili";

    public $rank_type = [ //排行榜
        1 => 'day',
        2 => 'month',
        'week',
    ];

    public $biz = [
        1 => '1',//画友
        '2' //摄影
    ];

    public $get_date = [ //日期
        1 => '当前日期',
        2 => '自定义'

    ];

    public $mode = [ //爬虫模式
        1 => 'top50',
        'search',
        'allImages',
        'fuckBilibili'

    ];

    public $category = [ //类别
        1 => "all",
        2 => 'cos',
        'sifu'
    ];

    /**
     * 掏空bilibili专用
     */
    public function fuckBilibili($spiderCore)
    {
        //让用户输入参数
        $biz = $this->quick_input($spiderCore, $spiderCore->eol("1:画友，2:摄影") . "请输入要爬取的板块(默认为画友)：", $this->biz, "没有这个板块", '1');
        $rank_type = $this->quick_input($spiderCore, "请选择排行榜（默认为 2 月榜）：", $this->rank_type, "没有这种排行榜", '2');
        $get_date = $this->quick_input($spiderCore, "请选择日期（默认为 当日）：", $this->get_date, "日期", '1');

        if ($biz == 2) {
            $category = $this->quick_input($spiderCore, "请选择板块（默认为 2 Cos ）：", $this->category, "没有这种板块", '2');
        } else {
            $category = null;
        }
        if ($get_date == 2) {
            $spider_date = $spiderCore->user_input("请输入自定义的时间（Y-m-d）：", date('Y-m-d'));
        } else {
            $spider_date = date('Y-m-d');
        }
        while (true) { //无限循环，直到报错停止
            $spider_date = $this->getYesterday($spider_date);
            print_r("开始获取：" . $spider_date . PHP_EOL);
            //封装请求链接
            @$parm = "biz=" . $biz . "&category=" . $this->category[$category] . "&rank_type=" . $this->rank_type[$rank_type] . "&date=" . $spider_date . "&page_num=0&page_size=50";
            $url = "http://api.vc.bilibili.com/link_draw/v2/Doc/ranklist?" . $parm;
//            print_r(PHP_EOL . "爬取的URL为：" . $parm . PHP_EOL);
            //下载
            $result = $spiderCore->curl_get($url, $this->userAgent);
            $result = json_decode($result);
            $images_arr = $this->get_images($result);

            if (empty($images_arr)) {
                break;
            }
            @$spiderCore->quick_down_img("Bilibili" . "-" . $this->rank_type[$rank_type], $images_arr, "bilibili");
            $spiderCore->spider_wait(BILIBILI_SLEEP, BILIBILI_SLEEP_TIME_MIN, BILIBILI_SLEEP_TIME_MAX);
        }
    }

    /**
     * 最热爬取
     * @param $spiderCore
     */
    public function allImages($spiderCore)
    {
        $biz = [
            1 => 'Doc',
            'Photo'
        ];
        $mode = [
            1 => 'hot',
            'new'
        ];

        //获取用户选项
        $biz = $biz[$this->quick_input($spiderCore, $spiderCore->eol("1:画友，2:摄影") . "请输入要爬取的板块(默认为画友)：", $this->biz, "没有这个板块", '1')];
        $mode = $mode[$this->quick_input($spiderCore, "最热还是最新( 1:最热，2：最新 ) 默认\"最热\" ：", $mode, "参数非法,请输入 1 或 2", 1)];
        $posts_num = $spiderCore->user_input("请输入爬取页数(1页=20个作品)(默认为：1):", 1);
        $posts_num--; //bilibili第一页居然是0，其他页面又不一样

        if ($biz == 'Photo') {
            $category = $this->category[$this->quick_input($spiderCore, "请选择板块（默认为 2 Cos ）：", $this->category, "没有这种板块", '2')];
            !empty($category) ?: $category = "all";
        } else {
            $category = "all";
        }

        for ($i = 0; $i <= $posts_num; $i++) {
            $url = "https://api.vc.bilibili.com/link_draw/v2/" . $biz . "/list?category=" . $category .
                "&type=" . $mode .
                "&page_num=" . $i .
                "&page_size=20";
            $result = $spiderCore->curl_get($url, $this->userAgent);
            $result = json_decode($result);
            $images_arr = $this->get_images($result);

            if (empty($images_arr)) {
                break;
            }
            @$spiderCore->quick_down_img("Bilibili" . "-" . $biz, $images_arr, $mode);
            $spiderCore->spider_wait(BILIBILI_SLEEP, BILIBILI_SLEEP_TIME_MIN, BILIBILI_SLEEP_TIME_MAX);
        }


    }

    /**
     * 获取给定日期的前一天
     * @param string $date
     * @return string $yesterday
     */
    public function getYesterday($date)
    {
        if (empty($date)) {
            $yesterday = date("Y-m-d", strtotime("-1 day"));
        } else {
            $arr = explode('-', $date);
            $year = $arr[0];
            $month = $arr[1];
            $day = $arr[2];
            $unixtime = mktime(0, 0, 0, $month, $day, $year) - 86400;
            $yesterday = date('Y-m-d', $unixtime);
        }
        return $yesterday;
    }

    /**
     * Bilibili 搜索爬虫
     * @param $spiderCore
     */
    public function search($spiderCore)
    {
        $order = [
            1 => 'totalrank', //默认排序
            'pubdate', //最近更新
            'stow' //最多收藏
        ];

        $q = $spiderCore->user_input("请输入一个需要查询的字符串(不输入就随缘):", RAND_KEYWORD[mt_rand(0, count(RAND_KEYWORD) - 1)]); //获取查询内容
        $order = $order[$this->quick_input($spiderCore, "请选择排序方式（默认 1 ，最新 2 ，收藏 3）(默认：默认排序)：", $order, "参数非法，没有这种排序", 1)];
        $category = $spiderCore->user_input('请选择爬取的区（1 全部 ，2 画友 3 摄影）（默认全部）', 1);
        $category--;
        $posts_num = $spiderCore->user_input("请输入爬取页数(1页=20个作品)(默认为：1):", 1);


        for ($i = 1; $i <= $posts_num; $i++) {

            $searchUrlurl = "https://api.bilibili.com/x/web-interface/search/type?jsonp=jsonp&search_type=photo&highlight=1" .
                "&keyword=" . $q .
                "&order=" . $order .
                "&category_id=" . $category .
                "&page=" . $i;

            $searchUrlData = $spiderCore->curl_get($searchUrlurl, $this->userAgent); //爬取
            $searchUrlData = json_decode($searchUrlData);

            $imagesIdArr = [];
            foreach ($searchUrlData->data->result as $value) {
                array_push($imagesIdArr, $value->id);//获取详细页ID
            }

            $imagesArr = [];

            foreach ($imagesIdArr as $id) {
                $pageUrl = "https://api.vc.bilibili.com/link_draw/v1/doc/detail?doc_id=" . $id;
                $imagesPageData = $spiderCore->curl_get($pageUrl, $this->userAgent);
                $imagesPageData = json_decode($imagesPageData);
                $num = 1;
                foreach ($imagesPageData->data->item->pictures as $value) {
                    $src = $value->img_src;
                    $format = explode('.', $src);
                    $filename = $imagesPageData->data->item->title . "-" . $imagesPageData->data->user->name . "-" . $num . "." . $format['3'];
                    $num++;
                    array_push($imagesArr, [$filename => $src]);
                }
                unset($num);
            }
            @$spiderCore->quick_down_img("Bilibili" . "-" . $q, $imagesArr, "bilibili");
            $spiderCore->spider_wait(BILIBILI_SLEEP, BILIBILI_SLEEP_TIME_MIN, BILIBILI_SLEEP_TIME_MAX);

        }

    }

    /**
     * 输出菜单
     * @param $spiderCore
     * @param $string
     * @param $array
     * @param $exit_string
     * @return mixed
     */
    public function quick_input($spiderCore, $string, $array, $exit_string, $default)
    {
        $spiderCore->bMenu($array, $this->spider_name);
        $input = $spiderCore->user_input($string, $default);
        if (@empty($array[$input])) {
            die($exit_string);
        }
        return $input;
    }

    /**
     * 生成文件名和图片链接
     * @param $result
     * @return array
     */
    public function get_images($result)
    {
        $images_arr = [];
        foreach (@$result->data->items as $items) {
            $user_name = $items->user->name;//获得用户名
            $items_obj = $items->item->pictures;
            $image_num = 1;//图片没有单独的ID 当拥有多张图片当时候防止重复
            foreach ($items_obj as $src_obj) {
                $src = $src_obj->img_src;
                $format = explode('.', $src);
                $filename = $items->item->title . "-" . $user_name . "-" . $items->item->doc_id . "-" . $image_num . "." . $format['3'];
                array_push($images_arr, [$filename => $src]);
                $image_num++;
            }
            unset($image_num);
        }
        return $images_arr;
    }

    /**
     * 爬取top50
     * @param $spiderCore
     */
    public function top50($spiderCore)
    {
        //让用户输入参数
        $biz = $this->quick_input($spiderCore, $spiderCore->eol("1:画友，2:摄影") . "请输入要爬取的板块(默认为画友)：", $this->biz, "没有这个板块", '1');
        $rank_type = $this->quick_input($spiderCore, "请选择排行榜（默认为 2 月榜）：", $this->rank_type, "没有这种排行榜", '2');
        $get_date = $this->quick_input($spiderCore, "请选择日期（默认为 当日）：", $this->get_date, "日期", '1');

        if ($biz == 2) {
            $category = $this->quick_input($spiderCore, "请选择板块（默认为 2 Cos ）：", $this->category, "没有这种板块", '2');
        } else {
            $category = null;
        }

        if ($get_date == 2) {//设置要获取的排行榜时间
            $get_date = $spiderCore->user_input("请输入自定义的时间（Y-m-d）：", date('Y-m-d'));
        } else {
            $get_date = date('Y-m-d');
        }

        //封装请求链接
        @$parm = "biz=" . $biz . "&category=" . $this->category[$category] . "&rank_type=" . $this->rank_type[$rank_type] . "&date=" . $get_date . "&page_num=0&page_size=50";
        $url = "http://api.vc.bilibili.com/link_draw/v2/Doc/ranklist?" . $parm;
        //下载
        $result = $spiderCore->curl_get($url, $this->userAgent);
        $result = json_decode($result);
        $images_arr = $this->get_images($result);
        @$spiderCore->quick_down_img("Bilibili" . "-" . $this->rank_type[$rank_type], $images_arr, "bilibili");
    }

}

$bilibili = new Bilibili();

$spiderCore->bMenu($bilibili->mode, $bilibili->spider_name);
$mode = $spiderCore->user_input(PHP_EOL . "请选择你需要使用的模式：", null);
@empty($user_mode = $bilibili->mode[$mode]) ? die(PHP_EOL . '没有这个爬虫模式') : $bilibili->$user_mode($spiderCore); //调用爬虫，并传入公用function
