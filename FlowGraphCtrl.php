<?php

namespace ui;

/**
 * @author SheenChow
 * @since 1.0
 * @copyright
 * DATE: Oct 11,2016
 * 
 * 流程图类 目前主要工作是从后台获取数据并且把数据加工成适合qunee使用的JSON格式
 */
class FlowGraphCtrl extends \framework\Controller
{
    //构造函数
    public function __construct(){

    }

    //处理参数接收
    public function getParamInfoInterface() {

		$actionParam=array(

			"getFlowGraphJsonDataAction"=>array("service_id"=>"string", "height"=>"float", "width"=>"float")
		);

		return $actionParam;
    }

    //获取适合流程图使用的JSON格式数据
    public function getFlowGraphJsonDataAction($service_id, $height, $width) {

        $dao = new \dao\DaoFlowGraph();

        $jsonData = $dao->getFlowGraphJsonData($service_id, $height, $width);

        return json_encode($jsonData);
    }
    
}
?>