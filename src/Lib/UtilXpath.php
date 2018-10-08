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

$data = (new UtilXpath)->setHtml($html)->setRange('//ul[@class="list_009"]')->getResult(true);
print_r($data);

$data = (new UtilXpath)->setHtml($html)->setRepeat('//ul[@class="list_009//li"]')->getResult(true);
print_r($data);
 */
class UtilCrawler extends Crawler
{
    /**
     * sibling
     *
     * @param $node
     * @param $siblingDir
     *
     * @return
     */
    protected function sibling($node, $siblingDir = 'nextSibling')
    {
        $nodes = array();

        $currentNode = $this->getNode(0);
        do {
            if ($node !== $currentNode && (XML_ELEMENT_NODE === $node->nodeType || XML_TEXT_NODE === $node->nodeType )) {
                $nodes[] = $node;
            }
        } while ($node = $node->$siblingDir);

        return $nodes;
    }
}
/**
 *
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
    protected $attrList = []; // href id title

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
        $this->crawler = new UtilCrawler();
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

    /**
     * setRepeat
     *
     * @param $rule
     *
     * @return
     */
    public function setRepeat($rule = '')
    {
        $this->repeatRule = $rule;
        return $this;
    }

    /**
     * getResult 
     *
     * @param $isOriginal
     * @param $reversal
     *
     * @return 
     */
    public function getResult($isOriginal = false, $reversal = false)
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

        if (!$isOriginal) {
            $data = $this->formatData($data);
        }

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
        foreach ($data as $key => $item) {
            $data[$key] = $this->arrToOne($item);
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
                array_walk($data, function (&$subItem) use ($key) {
                    unset($subItem[$key]);
                });
            }
        }
        foreach ($data as $key => $item) {
            $data[$key] = array_merge($item);
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
        if ($node->getNode(0)->nodeType === XML_TEXT_NODE) {
            return trim($node->text());
        }
        $subNodeList = $node->children();
        if ($subNodeList->count() == 0) {
            return $node->html();
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

    /**
     * reversal
     *
     * @param $data
     *
     * @return
     */
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

    /**
     * arrToOne
     *
     * @param $multi
     *
     * @return
     */
    public function arrToOne($multi)
    {
        $arr = [];
        foreach ((array)$multi as $val) {
            if (is_array($val)) {
                $arr = array_merge($arr, $this->arrToOne($val));
            } else {
                $arr[] = $val;
            }
        }
        return $arr;
    }
}
