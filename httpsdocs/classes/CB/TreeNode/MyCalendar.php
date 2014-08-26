<?php
namespace CB\TreeNode;

use CB\L;
use CB\Templates;

class MyCalendar extends Base
{
    protected function acceptedPath()
    {
        $p = &$this->path;

        // Calendar can't be a root folder
        if (sizeof($p) == 0) {
            return false;
        }

        //get the configured 'pid' property for this tree plugin
        //default is 0
        //thats the parent node id where this class shold start to give result nodes
        $ourPid = @$this->config['pid'];
        if ($ourPid == '') {
            $ourPid = '0';
        }

        // ROOT NODE: check if last node is the one we should attach to
        if ($this->lastNode->id == (String)$ourPid) {
            return true;
        }

        // CHILDREN NODES: accept if last node is an instance of this class
        if ($this->lastNode instanceof self) {
            return true;
        }

        return false;


/*
        $lastId = 0;
        if (!empty($p)) {
            $lastId = $this->lastNode->id;
>>>>>>> origin/devel
        }

        //get the configured 'pid' property for this tree plugin
        //default is 0
        //thats the parent node id where this class shold start to give result nodes
        $ourPid = @$this->config['pid'];
        if ($ourPid == '') {
            $ourPid = '0';
        }

<<<<<<< HEAD
        // ROOT NODE: check if last node is the one we should attach to
        if ($this->lastNode->id == (String)$ourPid) {
            return true;
        }

        // CHILDREN NODES: accept if last node is an instance of this class
        if (get_class($this->lastNode) == get_class($this)) {
            return true;
        }

        return false;
=======

        return true;
*/
>>>>>>> origin/devel
    }

    protected function createDefaultFilter()
    {
        $this->fq = array();

        //select only task templates
        $taskTemplates = Templates::getIdsByType('task');
        if (!empty($taskTemplates)) {
            $this->fq[] = 'template_id:('.implode(' OR ', $taskTemplates).')';
        }

    }

    public function getChildren(&$pathArray, $requestParams)
    {

        $this->path = $pathArray;
        $this->lastNode = @$pathArray[sizeof($pathArray) - 1];
        $this->requestParams = $requestParams;

        if (!$this->acceptedPath()) {
            return;
        }

        $ourPid = @intval($this->config['pid']);

        $this->createDefaultFilter();

        if (empty($this->lastNode) ||
            (($this->lastNode->id == $ourPid) && (get_class($this->lastNode) != get_class($this)))
        ) {
            $rez = $this->getRootNodes();
        } else {
            $rez = $this->getChildrenTasks();
        }

        return $rez;
    }

    public function getName($id = false)
    {
        if ($id === false) {
            $id = $this->id;
        }
        switch ($id) {
            case 1:
                return L\get('MyCalendar');
        }

        return 'none';
    }

    protected function getRootNodes()
    {
        return array(
            'data' => array(
                array(
                    'name' => $this->getName(1)
                    ,'id' => $this->getId(1)
                    ,'iconCls' => 'icon-calendar'
                    ,'view' => 'calendar'
                )
            )
        );
    }

    protected function getChildrenTasks()
    {
        $p = $this->requestParams;

        if (@$p['from'] == 'tree') {
            return array();
        }

        $p['fq'] = $this->fq;
        $p['fq'][] = 'user_ids:'.$_SESSION['user']['id'];
        $p['fq'][] = 'task_status:[0 TO 2]';

        $s = new \CB\Search();
        $rez = $s->query($p);

        return $rez;
    }
}