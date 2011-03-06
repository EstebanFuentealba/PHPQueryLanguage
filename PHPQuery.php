<?php

/*
 * @Autor: Esteban Fuentealba
 * @Version: 0.0 ALFA
 * 
 */
require_once('array2xml.php');
require_once('PHPSQLParser.php');
class PHPQuery {

    public $url = null;
    public $xpath = array();
    public $query = null;
    public $format = null;
    public $callback = null;
    public $columns = array();
    public $conditions = array();

    public function __construct($options=null) {
        if ($options['q'] == null) {
            throw new Exception("no hay consulta");
        }
        $s = new PHPSQLParser();
        $this->format = $options['format'];
        $this->callback = $options['callback'];
        $this->query = $options['q'];

        /* Parseo la consulta y la transformo a objeto php */
        $parsed = $s->parse($this->query);
        $columns = $parsed['SELECT'];
        $from = $parsed['FROM'];
        $where = $parsed['WHERE'];

        /* Seteo los atributos del objeto  */
        $this->mappingdata($columns, "S");
        $this->mappingdata($where, "W");
        $this->index = array_search('*', $this->columns);
    }

    public function verifyData($string) {
        preg_match_all("/^('|\"|\`)(.+)('|\"|\`)$/", $string, $result);
        if (count($result) == 4) {
            return $result[2][0];
        }
        return false;
    }

    public function mappingdata($data, $type) {
        for ($i = 0; $i < count($data); $i++) {
            if ($type == "S") {
                if ($data[$i]["expr_type"] == "colref") {
                    $this->columns[trim($this->verifyData($data[$i]["alias"]))] = trim($data[$i]["base_expr"]);
                }
            } else if ($type == "W") {
                if ($data[$i]["expr_type"] == "colref") {
                    if ($data[$i]["base_expr"] == "url") {
                        $this->url = $this->verifyData($data[$i + 2]["base_expr"]);
                    } else if ($data[$i]["base_expr"] == "xpath") {
                        $this->xpath[] = $this->verifyData($data[$i + 2]["base_expr"]);
                    } else {
                        $stdObj = new stdClass();
                        $stdObj->column = $data[$i]["base_expr"];
                        $stdObj->operator = $data[$i + 1]["base_expr"];
                        $stdObj->value = $this->verifyData($data[$i + 2]["base_expr"]);
                        $this->conditions[] = $stdObj;
                    }
                } else if ($data[$i]["expr_type"] == "expression") {
                    //Expresion
                    $this->mappingdata($data[$i]["sub_tree"], $type);
                }
                if ($data[$i]["base_expr"] == "OR" && $data[$i]["expr_type"] == "operator") { /* TODO */
                }
                if ($data[$i]["base_expr"] == "AND" && $data[$i]["expr_type"] == "operator") { /* TODO */
                }
            }
        }
    }

    public function query() {
        $html = @file_get_contents($this->url);
        $webDoc = new DOMDocument();
        @$webDoc->loadHTML($html);
        $result = array();
        if (count($this->xpath) > 0) {
            foreach ($this->xpath as $xpath) {
                $temp = $this->xpath($webDoc, $xpath);
                $result = array_merge_recursive($result, $temp);
            }
        } else {
            $result = $this->getArray($webDoc);
        }
        if (count($result) == 0) {
            throw new Exception("Error al parsear HTML");
        }
        $koalaResponse = array("koalaquery" => array("results" => $result));
        if ($this->format == "json") {
            header('Content-Type: application/json; charset=utf-8');
            return json_encode($koalaResponse);
        } else if ($this->format == "jsonp") {
            header('Content-Type: application/json; charset=utf-8');
            return $this->callback . "(" . json_encode($koalaResponse) . ")";
        } else if ($this->format == "xml") {
            header("content-type: text/xml");
            $xml = new array2xml();
            $xml->setArr($koalaResponse);
            return $xml->createXML();
        } else if ($this->format == "php") {
            header("Content-Type: text/plain");
            return var_export($koalaResponse);
        }
    }

    public function getArray($node) {
        $array = false;
        if ($node->hasAttributes()) {
            foreach ($node->attributes as $attr) {
                $array[$attr->nodeName] = $attr->nodeValue;
            }
        }
        if ($node->hasChildNodes()) {
            if ($node->childNodes->length == 1) {
                $array[$node->firstChild->nodeName] = $node->firstChild->nodeValue;
            } else {
                foreach ($node->childNodes as $childNode) {
                    if ($childNode->nodeType != XML_TEXT_NODE) {
                        $array[$childNode->nodeName][] = $this->getArray($childNode);
                    }
                }
            }
        }
        return $array;
    }

    public function getAttributes($attributes) {
        $attrlist = array();
        foreach ($attributes as $attrName => $attrNode) {
            $this->set_key_value($attrName, $attrNode->value, $attrlist);
        }
        return $attrlist;
    }

    private function set_key_value($key, $value, &$arr) {
        if ($this->index) {
            $arr[$key] = $value;
        } else {
            $indexColumn = false;
            $o = new stdClass();
            $o->isNull = true;
            if (is_array($key) && count($key) == 1) {
                foreach ($key as $k => $v) {
                    $o->key = $k;
                    $o->value = $v;
                    $o->isNull = false;
                    $indexColumn = array_search($o->key, $this->columns);
                }
            } else {
                $indexColumn = array_search($key, $this->columns);
            }
            if ($indexColumn) {
                if (count($this->conditions) > 0) {
                    foreach ($this->conditions as $condition) {
                        if (!$o->isNull) {
                            if ($condition->column == $o->key) {
                                $b = strtolower(utf8_decode($value));
                                $a = strtolower($condition->value);
                                /* OPERADORES */
                                if ($condition->operator == "=" && $b == $a) {
                                    $arr[(is_array($key) ? $o->value : $indexColumn)] = $value;
                                }
                            }
                        }
                    }
                } else {
                    $arr[$indexColumn] = $value;
                }
            }
        }
    }

    public function recursive_element($elements) {
        $r = array();
        foreach ($elements as $element) {
            $nodes = $element->childNodes;
            if (count($nodes) > 0) {
                $attrs = $this->getAttributes($element->attributes);
                if ($element->nodeValue != "") {
                    $e = $this->recursive_element($nodes);
                    if (array_key_exists('#text', $e)) {
                        if (count($attrs) > 0) {
                            //ATRIBUTO
                            $this->set_key_value("content", $e['#text'][0], $attrs);
                        } else {
                            $this->set_key_value(array("content" => $element->nodeName), $e['#text'][0], $r);
                            //$r[$element->nodeName] = $e['#text'][0];
                        }
                    } else {
                        $r[$element->nodeName][] = $e;
                    }
                }
                if (count($attrs) > 0) {
                    $r[$element->nodeName][] = $attrs;
                }
            } else {
                if (substr($element->nodeName, 0, 1) == "#") {
                    $r[$element->nodeName][] = $element->nodeValue;
                } else {
                    if ($element->nodeValue != "") {
                        $r[$element->nodeName][] = $element->nodeValue;
                    }
                }
            }
        }
        return $r;
    }

    public function xpath($webDoc, $xp) {
        $xpath = new DOMXpath($webDoc);
        $elements = $xpath->query($xp);
        return $this->recursive_element($elements);
    }

}

?>
