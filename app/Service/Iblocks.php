<?php

namespace App\Service;

use App\Models\iblock;
use App\Models\iblock_element;
use App\Models\iblock_property;
use App\Models\iblock_prop_value;
use Illuminate\Support\Facades\Cache;


class Iblocks
{
    public static function getBreadcrumbIblock($iblock)
    {
        $sectionTree = iblock::where("left","<=",$iblock->left)->where("right",">=",$iblock->right)->get();
        $res = [["name" => $iblock->name, "id" => $iblock->id]];
        while ($iblock->parent_id != 0) {
            $iblock = $sectionTree->where("id", "=", $iblock->parent_id)->first();
            $res[] = ["name" => $iblock->name, "id" => $iblock->id];
        }
        return array_reverse($res);
    }

    public static function getPropsParrents($iblock, $is_admin = false)
    {
        $sectionTree = iblock::with("properties")->where("left","<=",$iblock->left)->where("right",">=",$iblock->right)->get();
        $res = [];
        foreach ($iblock->properties as $prop) {
            if ($iblock->id == 1 && !$is_admin) {
                continue;
            }
            $res[] = $prop;
        }
        while ($iblock->parent_id != 0) {
            $iblock = $sectionTree->where("id", "=", $iblock->parent_id)->first();
            foreach ($iblock->properties as $prop) {
                if ($iblock->id == 1 && !$is_admin) {
                    continue;
                }
                $res[] = $prop;
            }
        }
        return $res;
    }

    public static function getAllProps($iblock, $values = false)
    {
        $res = [];
        foreach (self::getPropsParrents(iblock::find($iblock)) as $c) {
            $res[] = $c;
        }
        if ($values) {
            $allProps = $res;
            $cAllProps = array_map(function ($item) {
                return $item->id;
            }, $allProps);
            $allPropValue = [];
            if (!empty($cAllProps)) {
                foreach ($cAllProps as $id) {
                    $c = iblock_prop_value::where("prop_id", "=", $id)->get()->unique("value");
                    foreach ($c as $item) {
                        $allPropValue[$item->prop_id][] = $item;
                    }
                }
            }
            return ["res" => $res, "values" => $allPropValue];
        }
        //todo
        return $res;
        $deep = function ($childs) use (&$res, &$deep) {
            foreach ($childs as $child) {
                foreach ($child->properties as $prop) {
                    $res[] = $prop;
                }
            }
            foreach ($childs as $child) {
                $c = iblock::where("parent_id", "=", $child->id)->get();
                if (count($c)) {
                    $deep($c);
                }
            }
        };
        $c = iblock::where("parent_id", "=", $iblock)->get();
        if (count($c)) {
            $deep($c);
        }
        return $res;
    }

    /*
    $where["prop"];
    $where["type"];
    $where["value"];
    $params["range"]["id"]["to"]
    $params["range"]["id"]["from"]
    $params["param"][$c[1]] = $param;
    */
    public static function GetList($iblockID, $elId = false, $itemPerPage = 5, $page = false, $where = null, $params = null)
    {
        $stack = [$iblockID];
        $res = [];
        $ids = [];
        $iblock = iblock::find($iblockID);
        //nested set
        $sectionTree = iblock::where("left",">=",$iblock->left)->where("right","<=",$iblock->right)->get();
        $getChilds = function ($iblock, &$c) use (&$getChilds, &$stack, $elId, &$ids, &$sectionTree) {
            $c[$iblock->id]["key"] = $iblock->name;
            $c[$iblock->id]["path"] = $stack;
            if (in_array($elId, $stack)) {
                $ids[] = $iblock->id;
            }
            //
            $childs = $sectionTree->where("parent_id", "=", $iblock->id)->all();
            foreach ($childs as $child) {
                $stack[] = $child->id;
                $getChilds($child, $c[$iblock->id]);
                array_pop($stack);
            }
        };
        $getChilds($iblock, $res);

        $els = iblock_element::with("propvalue.prop")->whereIn("iblock_id", $ids);
        if ($page) {
            $els = $els->where("name", "!=", "op");
        }
        if ($where) {
            foreach ($where as $cond) {
                $els->whereHas('propvalue', function ($query) use ($cond) {
                    $cProp = iblock_property::where("name", "=", $cond["prop"])->first();
                    $query->where('prop_id', '=', $cProp->id)->where(function ($query) use ($cProp, $cond) {
                        $type = ($cProp->is_number) ? "value_number" : "value";
                        $query->where($type, $cond["type"], $cond["value"]);
                    });
                });
            }
        }
        if (isset($params["param"])) {
            foreach ($params["param"] as $id => $param) {
                $els->whereHas('propvalue', function ($query) use ($id, $param) {
                    $query->where("prop_id", "=", $id)->where(function ($query) use ($param) {
                        $param = array_map(function ($id) {
                            return iblock_prop_value::find($id)->value;
                        }, $param);
                        $query->where("value", '=', $param[0]);
                        for ($i = 1; $i <= count($param) - 1; $i++) {
                            $query->orWhere("value", '=', $param[$i]);
                        }
                    });
                });
            }
        }
        if (isset($params["range"])) {
            foreach ($params["range"] as $id => $param) {
                $els->whereHas('propvalue', function ($query) use ($id, $param) {
                    $query->where("prop_id", "=", $id)->where(function ($query) use ($param) {
                        $query->where("value_number", '>=', $param["from"]);
                        $query->where("value_number", '<=', $param["to"]);
                    });
                });
            }
        }
        $count = $els->count();
        if ($page) {
            $els = $els->offset($itemPerPage * ($page - 1))->take($itemPerPage);
        }
        $els = $els->get();
        foreach ($els as $el) {
            $t = $el->toArray();
            $t["prop"] = [];
            foreach ($el["propvalue"] as $prop) {
                $type = ($prop["prop"]["is_number"]) ? "value_number" : "value";
                if (isset($t["prop"][$prop["prop"]["name"]])) {
                    if (is_array($t["prop"][$prop["prop"]["name"]])) {
                        $t["prop"][$prop["prop"]["name"]][] = $prop[$type];
                    } else {
                        $t["prop"][$prop["prop"]["name"]] = [$t["prop"][$prop["prop"]["name"]]];
                        $t["prop"][$prop["prop"]["name"]][] = $prop[$type];
                    }
                } else {
                    $t["prop"][$prop["prop"]["name"]] = $prop[$type];
                }
            }
            unset($t["propvalue"]);
            $deep = function (&$c) use (&$kek, &$deep, &$el, $t) {
                foreach ($c as $key => $value) {
                    if ($key == $el->iblock_id) {
                        $c[$key]["elements"][] = $t;
                        return;
                    }
                    if (is_numeric($key)) {
                        $deep($c[$key]);
                    }
                }
            };
            $deep($res);
        }

        if ($page) {
            return ["count" => $count, "res" => $res];
        }
        return $res;
    }

    public static function ElementsGetList($ids)
    {
        $els = iblock_element::with("propvalue.prop")->whereIn('id', $ids)->get();
        $res = [];
        foreach ($els as $el) {
            $t = $el->toArray();
            $t["prop"] = [];
            foreach ($el["propvalue"] as $prop) {
                $type = ($prop["prop"]["is_number"]) ? "value_number" : "value";
                if (isset($t["prop"][$prop["prop"]["name"]])) {
                    if (is_array($t["prop"][$prop["prop"]["name"]])) {
                        $t["prop"][$prop["prop"]["name"]][] = $prop[$type];
                    } else {
                        $t["prop"][$prop["prop"]["name"]] = [$t["prop"][$prop["prop"]["name"]]];
                        $t["prop"][$prop["prop"]["name"]][] = $prop[$type];
                    }
                } else {
                    $t["prop"][$prop["prop"]["name"]] = $prop[$type];
                }
            }
            unset($t["propvalue"]);
            $res[] = $t;
        }
        return $res;
    }

    /**
     * $obj = ["name"=>"air core2dd", "prop"=>["prop1"=>"aaa"]];
     * Iblocks::addElement($obj, 1);
     */
    public static function addElement($obj, $iblockId)
    {
        //$el = iblock_element::where("name", "=", $obj["name"])->first();
        //if (empty($el)) {
        $el = new iblock_element();
        //}
        $el->name = $obj["name"];
        $el->iblock_id = $iblockId;
        $el->save();
        foreach ($obj["prop"] as $id => $prop) {
            if (empty($prop)) {
                continue;
            }
            $propsIds = self::getAllProps($iblockId, false);
            $propsIds = array_map(function ($prop) {
                return $prop->id;
            }, $propsIds);
            if (is_int($id)) {
                $prop = iblock_property::where("id", "=", $id)->whereIn("id", $propsIds)->first();
            } else {
                $prop = iblock_property::where("name", "=", $id)->whereIn("id", $propsIds)->first();
            }
            if (empty($prop)) {
                $prop = new iblock_property();
                $prop->name = $id;
                $prop->iblock_id = $iblockId;
                if (is_array($obj["prop"][$id])) {
                    $isMulty = true;
                    $isNumber = is_int($obj["prop"][$id][0]);
                } else {
                    $isMulty = false;
                    $isNumber = is_int($obj["prop"][$id]);
                }
                $prop->is_multy = $isMulty;
                $prop->is_number = $isNumber;
                $prop->save();
            } else {
                iblock_prop_value::where("el_id", "=", $el->id)->where("prop_id", "=", $prop->id)->delete();
            }
            $count = 0;
            $p = new iblock_prop_value();
            $p->prop_id = $prop->id;
            $p->el_id = $el->id;
            $p->value_id = ++$count;
            //multy shit
            if (is_array($obj["prop"][$id])) {
                $count = 0;
                foreach ($obj["prop"][$id] as $item) {
                    $p = new iblock_prop_value();
                    $p->prop_id = $prop->id;
                    $p->el_id = $el->id;
                    $p->value_id = ++$count;
                    if ($prop->is_number) {
                        $p->value_number = (integer)$item;
                    } else {
                        $p->value = $item;
                    }
                    $p->save();
                }
            } else {
                //
                if ($prop->is_number) {
                    $p->value_number = (integer)$obj["prop"][$id];
                } else {
                    $p->value = $obj["prop"][$id];
                }
                $p->save();
            }
        }
    }

    public static function addSection($obj, $parentId)
    {
        $el = new iblock();
        $el->name = $obj["name"];
        if ($parentId) {
            $el->parent_id = $parentId;
        }
        $el->save();
        return $el->id;
    }

    /**
     * $prop = ["prop1"=>"bbb"];
     * Iblocks::updateElement($prop, 16);
     */
    public static function updateElement($props, $elId)
    {
        foreach ($props as $key => $p) {
            $p = iblock_property::where("name", "=", $key)->first();
            if (isset($props[$p->name])) {
                iblock_prop_value::where("el_id", "=", $elId)->where("prop_id", "=", $p->id)->delete();
                if (is_array($props[$p->name])) {
                    $count = 0;
                    foreach ($props[$p->name] as $item) {
                        if (empty($item)) {
                            continue;
                        }
                        $c = new iblock_prop_value();
                        $c->el_id = $elId;
                        $c->prop_id = $p->id;
                        $c->value_id = ++$count;
                        if ($p->is_number) {
                            $c->value_number = (integer)$item;
                        } else {
                            $c->value = $item;
                        }
                        $c->save();
                    }
                } else {
                    $count = 0;
                    $c = new iblock_prop_value();
                    $c->el_id = $elId;
                    $c->prop_id = $p->id;
                    $c->value_id = ++$count;
                    if ($p->is_number) {
                        $c->value_number = (integer)$props[$p->name];
                    } else {
                        $c->value = $props[$p->name];
                    }
                    $c->save();
                }
            }
        }
    }

    public static function treeToArray($tree)
    {
        $resTree = [];
        $getTree = function ($tree) use (&$getTree, &$treeKeys, &$resTree) {
            foreach ($tree as $key => $el) {
                //key - iblock_id
                if (isset($el["key"])) { //if curr iblock
                    $resTree[$key] = $el;
                    $getTree($el);
                }
            }
        };
        $getTree($tree);
        return $resTree;
    }
}
