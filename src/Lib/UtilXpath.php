<?php
/**
 * Short description for UtilXpath.php
 *
 * @package UtilXpath
 * @author zhenyangze <zhenyangze@gmail.com>
 * @version 0.1
 * @copyright (C) 2018 zhenyangze <zhenyangze@gmail.com>
 * @license MIT
 */
namespace SpiderX\Lib;

use Symfony\Component\DomCrawler\Crawler;

/**
 * UtilXpath
$html = file_get_contents('http://roll.news.sina.com.cn/news/gnxw/gdxw1/index.shtml');
$html = mb_convert_encoding($html, 'UTF-8', 'UTF-8,GBK,GB2312,BIG5');

$data = (new UtilXpath)->setHtml($html)->setRange('//ul[@class="list_009"]')->getResult();
print_r($data);

$data = (new UtilXpath)->setHtml($html)->setRepeat('//ul[@class="list_009//li"]')->getResult();
print_r($data);
 */
class UtilXpath
{
    /**
     * crawler
     */
    protected $crawler = null;
    /**
     * html
     */
    protected $html = '';
    /**
     * rangeRule
     */
    protected $rangeRule = '';
    /**
     * attrList
     */
    protected $attrList = ['href'];

    /**
     * @var mixed
     */
    protected $repeatRule;
    /**
     * __construct
     *
     * @return
     */
    public function __construct()
    {
        $this->crawler = new Crawler();
    }

    /**
     * __destruct
     *
     * @return
     */
    public function __destruct()
    {
        $this->crawler->clear();
    }

    /**
     * setHtml
     *
     * @param $html
     *
     * @return
     */
    public function setHtml($html = '')
    {
        $this->html = $html;
        return $this;
    }

    /**
     * setRange
     *
     * @param $rule
     *
     * @return
     */
    public function setRange($rule = '')
    {
        $this->rangeRule = $rule;
        return $this;
    }

    public function setRepeat($rule = '')
    {
        $this->repeatRule = $rule;
        return $this;
    }

    /**
     * getResult
     *
     * @return
     */
    public function getResult($reversal = false)
    {
        if (empty($this->html)) {
            return [];
        }
        $this->crawler->addHtmlContent($this->html);
        if (!empty($this->repeatRule)) {
            $data = $this->crawler->filterXPath($this->repeatRule)->each(function ($node, $i) {
                return $this->getSubNode($node);
            });
        } else {
            $rangeNode = $this->crawler->filterXPath($this->rangeRule);
            if ($rangeNode->count() == 0) {
                return [];
            }
            $data = $this->crawler->filterXPath($this->rangeRule)->children()->each(function ($node, $i) {
                return $this->getSubNode($node);
            });
        }

        $data = $this->formatData($data);

        if ($reversal) {
            $data = $this->reversal($data);
        }

        return $data;
    }

    /**
     * setAttr
     *
     * @param $attrList
     *
     * @return
     */
    public function setAttr($attrList)
    {
        $this->attrList = array_merge($this->attrList, $attrList);
        return $this;
    }

    /**
     * formatData
     *
     * @param $data
     *
     * @return
     */
    protected function formatData($data)
    {
        $deleteArr = [];
        foreach($data as &$item) {
            $item = $this->arrToOne($item);
        }
        foreach ($data as $item) {
            foreach ($item as $key => $value) {
                if (isset($deleteArr[$key]) && $deleteArr[$key] === false) {
                    continue;
                }
                if (strlen($value) == 0) {
                    $deleteArr[$key] = true;
                } else {
                    $deleteArr[$key] = false;
                }
            }
        }
        foreach ($deleteArr as $key => $isDelete) {
            if ($isDelete) {
                array_walk($data, function (&$item) use ($key) {
                    unset($item[$key]);
                });
            }
        }
        foreach ($data as &$item) {
            $item = array_merge($item);
        }

        return $data;
    }

    /**
     * getSubNode
     *
     * @param $node
     *
     * @return
     */
    public function getSubNode($node)
    {
        $self = $this;
        $subNodeList = $node->children();
        if ($subNodeList->count() == 0) {
            return $node->text();
        }

        $data = $subNodeList->each(function ($subNode, $i) use ($self) {
            return $self->getSubNode($subNode);
        });

        $attributeData = [];
        foreach ($this->attrList as $attr) {
            $currentAttributeData = $subNodeList->each(function ($subNode, $i) use ($attr) {
                if (!is_null($subNode->attr($attr))) {
                    return $subNode->attr($attr);
                }
            });
            $attributeData = array_merge($attributeData, $currentAttributeData);
        }

        $data = array_merge($data, $attributeData);

        return $data;
    }

    public function reversal($data)
    {
        $newData = [];
        foreach ($data as $item) {
            foreach ($item as $index => $value) {
                $newData[$index][] = $value;
            }
        }

        return $newData;
    }

    public function arrToOne($multi) {
        $arr = [];
        foreach ((array)$multi as $val) {
            if(is_array($val) ) {
                $arr = array_merge($arr, $this->arrToOne($val));
            } else {
                $arr[] = $val;
            }
        }
        return $arr;
    }
}
