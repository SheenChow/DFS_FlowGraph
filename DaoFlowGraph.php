<?php
namespace dao;

class DaoFlowGraph {

    private $logger; //日志对象
    private $pdodb;  //数据库对象

    public function __construct() {
        $this->logger = \Logger::getLogger( __CLASS__ );
        $this->db = \db\PdoDB::getInstance();
    }
    
    //获取符合需要的JSON格式数据 主体函数
    public function getFlowGraphJsonData($service_id, $height, $width) {

        //1.先从数据库获取Flow对象
        $result = $this->getFlowObj($service_id, $height);
        //2.深度优先遍历FlowGraph，给其划分等级
        $this->DFS_setLevel($result);

        //3.设置Y坐标
        $this->DFS_setHeight($result);

        //4.整合出需要的json格式的数据
        $json = $this->mergeJsonData($result, $width);

        return $json;
    } 

    //获取Flow对象
    public function getFlowObj($service_id, $height) {
        //SQL语句 
        $sql =<<<SQL
                SELECT step_id, cur_process_id, process_name, prev_process_ids, next_process_ids, cur_opt_type, next_opt_type, step_type FROM t_bp_flow_control AS t_a, t_bp_process_define AS t_b WHERE t_a.service_id = t_b.service_id AND t_a.service_id = :service_id AND t_a.cur_process_id = t_b.process_id ORDER BY step_id;
SQL;
        //绑定参数
        $parray = array(
            ":service_id" => $service_id
            );

        $result = $this->db->get_results($sql,"ARRAY",$parray, true);


        //对数据稍作处理，设置level,为后续求解其x坐标做准备；设置height，求其y坐标
        for ($i=0; $i < count($result); $i++) { 
            
            $result[$i]["level"] = 0;
            
            if($result[$i]["step_type"] == '0') {
                $result[0]["max_h"] = $height;
                $result[0]["min_h"] = 0;
            }

        }
        return $result;
    }
    
    //深度优先遍历，给结点划分等级level
    public function DFS_setLevel(&$Graph) {
        $next_process_ids_arr = array();
        //判断是否Graph为空
        if(!$Graph) {
            return false;
        }
        //依次对每个结点做深度递归遍历
        for ($i=0; $i < count($Graph); $i++) { 
            $next_process_ids_arr = explode(",", $Graph[$i]["next_process_ids"]);

            //如果当前结点并没有被访问过，则从此结点开始对图进行深度递归遍历
            if(!$Graph[$i]["level"]) {
                $this->DFSTraverseLev($Graph, $Graph[$i]["cur_process_id"], $next_process_ids_arr);
            }
        }
    }

    public function DFSTraverseLev(&$Graph, $process_id, $next_process_ids_arr) {
        //得到当前结点对象
        $node = $this->getFlowObjByProcessID($Graph, $process_id);
        //前结点数组
        $prev_arr = explode(",", $node["prev_process_ids"]);
       /* echo "prev:".json_encode($prev_arr);*/ //到这里是正确的
        //递归出口条件
        if(!$process_id) {
            return ;
        }
        //为第一个结点的等级赋值为1
        if(!$node["level"]) {
            if($node["step_type"] == '0') {
                $j = $this->getPosInGraphByProID($Graph, $process_id);
                $Graph[$j]["level"] = 1;
            } else {
             //上一个结点的等级最大值
               $max_level = $this->max_level_in_arr($Graph, $prev_arr);
               $node["level"] = $max_level + 1;
               $j = $this->getPosInGraphByProID($Graph, $process_id);
               $Graph[$j]["level"] = $node["level"];
              /* echo "GraphLevel:". $Graph[$j]["level"];*/
        }
        }

        for ($i=0; $i < count($next_process_ids_arr); $i++) { 
            //到这里$i值的变化是正确的
            //下一个结点的process_id
            $next_process_id = $next_process_ids_arr[$i];

            //下一个结点对象
            $next_node = $this->getFlowObjByProcessID($Graph, $next_process_id);
            //下一个结点的下一步数组
            $next_arr = explode(",", $next_node["next_process_ids"]);
            //递归调用DFS函数
            $this->DFSTraverseLev($Graph, $next_process_id, $next_arr);

        }


    }
    
    //通过process_id找到Flow对象
    public function getFlowObjByProcessID($Graph, $process_id) {
        //通过service_id和process_id得到Flow对象
         foreach ($Graph as $node) {
             if($node["cur_process_id"] == $process_id) {
                return $node;
             }
         }
    }
    
    //找到等级数组中的最大值
    public function max_level_in_arr($Graph, $level_arr) {
        $max_level = 0;
        for ($i=0; $i < count($level_arr); $i++) { 
            $temp_node = $this->getFlowObjByProcessID($Graph, $level_arr[$i]); 
            if($temp_node["level"] > $max_level) {
                 $max_level = $temp_node["level"];
            }
        }
        return $max_level;
    }

    public function getPosInGraphByProID($Graph, $process_id) {
        //通过process_id找出结点在Graph中的位置
        for ($i=0; $i < count($Graph); $i++) { 
            if($Graph[$i]["cur_process_id"] == $process_id) {
                 return $i;
            }
        }
    }

    public function DFS_setHeight(&$Graph) {
        if (!$Graph) {
            return ;
        }
        for ($i=0; $i < count($Graph); $i++) {           

            if(!$Graph[$i]["hei"]) {

                $next_process_ids_arr = explode(",", $Graph[$i]["next_process_ids"]);
                $this->DFSTraverseHei($Graph, $Graph[$i]["cur_process_id"], $next_process_ids_arr);
            }
        }

        return ;
    }

    public function DFSTraverseHei(&$Graph, $process_id, $next_process_ids_arr) {
        /*echo "process_id：".$process_id;*/
        if (!$process_id) {
            return ;
        }

        $node = $this->getFlowObjByProcessID($Graph, $process_id);
        //对节点进行处理
        if(!$node["hei"]) {

            $pos = $this->getPosInGraphByProID($Graph, $process_id);
            //说明是第一步骤
            if ($node["step_type"] == '0') {
                
                 $Graph[$pos]["hei"] = (float)($Graph[$pos]["max_h"] + $Graph[$pos]["min_h"]) / 2; 
            } else {
                $prev_arr = explode(",", $node["prev_process_ids"]);

                //说明当前步骤是合拢操作
                if($node["cur_opt_type"] == 'c1') {
                    $max = $this->max_height($Graph, $prev_arr);
                    $min = $this->min_height($Graph, $prev_arr);
                    //如果前一个结点有任何一个没被访问到，就不再继续往下访问了
                } elseif ($node["cur_opt_type"] == 'c0') {
                    //当前步骤为顺序c0的时候，可能正处于顺序或者分裂或者选择分支当中,但此时前一步骤均只有一个
                    $prev_node = $this->getFlowObjByProcessID($Graph, $prev_arr[0]);
                    $max = $prev_node["max_h"];
                    $min = $prev_node["min_h"];
                    //说明当前步骤是顺序,则结点的最大值和最小值和前一结点相同,不做处理
                    if($prev_node["next_opt_type"] == 'n0') {
                       
                        
                    }//说明此时处于分裂中
                    elseif ($prev_node["next_opt_type"] == 'n1') {
                        $prev_next_arr = explode(",", $prev_node["next_process_ids"]);
                        $num = count($prev_next_arr);
                        $aver = (float)(($max - $min) / $num);
                     /*   echo "max".$max."min".$min;*/
                        //查找当前结点在数组中的位置
                        $index = array_search($process_id, $prev_next_arr);
                        $max = (float)$max - ($num - 1 -$index) * $aver; 
                        $min = (float)$max - $aver;
                    }//说明此时处于选择分支中 
                    else {

                    }
                }

                $Graph[$pos]["max_h"] = $max;
                $Graph[$pos]["min_h"] = $min;
                if(isset($max) && isset($min)) {
                    $Graph[$pos]["hei"] = (float)($max + $min) / 2;
                } else {
                    return ;
                }

            }
        }
        //对接下来的结点遍历 由于中途会退出，所以不能用$i < count($next_process_ids_arr);作为结束条件
        for ($i=0; $i < count($next_process_ids_arr); $i++) { 
            
            $next_process_id = $next_process_ids_arr[$i];
            $next_node = $this->getFlowObjByProcessID($Graph, $next_process_id);
            $next_arr = explode(",", $next_node["next_process_ids"]);

            $this->DFSTraverseHei($Graph, $next_process_ids, $next_arr);
        }
    }

    public function max_height($Graph, $arr) {

        $node = $this->getFlowObjByProcessID($Graph, $arr[0]);
        $max = $node["max_h"];

        return $max;
    }

    public function min_height($Graph, $arr) {
        
        $node = $this->getFlowObjByProcessID($Graph, $arr[count($arr) - 1]);
        $min = $node["min_h"];
        if(!isset($node["min_h"])) {
            return ;
        }

        return $min;
    }

    public function mergeJsonData(&$Graph, $width) {
        $color = array(
            "#e6b9b9",
            "#c6daf2",
            "#d7e5bd",
            "#ccc2da",
            "#dadada"
        );

        $num = $this->max_level($Graph);
        $aver = (float)$width / $num;
        $node_arr = array();
        $edge_arr = array();
        $json_data = array();
        $n = 0;

        for ($i=0; $i < count($Graph); $i++) { 
            $Graph[$i]["x"] = 20 + ($Graph[$i]["level"] - 1) * $aver;
        }

        //整理出结点
        for ($i=0; $i < count($Graph); $i++) { 
            $temp_node = array (
                "id"=>$Graph[$i]["step_id"],
                "name"=>$Graph[$i]["process_name"],
                "x"=>$Graph[$i]["x"],
                "y"=>$Graph[$i]["hei"],
                "cur_opt_type"=>$Graph[$i]["cur_opt_type"],
                "next_opt_type"=>$Graph[$i]["next_opt_type"],
                "cur_process_id"=>$Graph[$i]["cur_process_id"],
                "prev_process_ids"=>$Graph[$i]["prev_process_ids"],
                "next_process_ids"=>$Graph[$i]["next_process_ids"],
                "step_type"=>$Graph[$i]["step_type"],
                "color"=>$color[$i % 5]
                );
            $node_arr[$i] = $temp_node;
        }

        //整理出边
        for ($j=0; $j < count($node_arr); $j++) {  
            //找出下一步骤的
            $next_process_ids_arr = explode(",", $node_arr[$j]["next_process_ids"]);
            
            for ($k=0; $k < count($next_process_ids_arr); $k++) {
                $next_node = $this->getFlowObjByProcessID($node_arr, $next_process_ids_arr[$k]); 
                $temp_edge = array (
                    "name"=>$node_arr[$j]["cur_process_id"].'-'.$next_process_ids_arr[$k],
                    "from"=>$node_arr[$j]["id"],
                    "to"=>$next_node["id"],
                    "type"=>$node_arr[$j]["next_opt_type"]
                );
                if($temp_edge["to"]) {
                    $edge_arr[$n++] = $temp_edge;
                }
            }
        }

        $json_data["nodes"] = $node_arr;
        $json_data["edges"] = $edge_arr;

        return $json_data;
    }

    public function max_level($Graph) {
        $max = 0;

        for ($i=0; $i < count($Graph); $i++) { 
            if($Graph[$i]["level"] > $max) {
                $max = $Graph[$i]["level"];
            }
        }
        return $max;
    }


} 
?>