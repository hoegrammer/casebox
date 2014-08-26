<?php
namespace CB\TreeNode;

use CB\Config;
use CB\DB;
use CB\Util;
use CB\L;
use CB\Security;
use CB\User;
use CB\Log;

class ActionLog extends Base
{

    protected function acceptedPath()
    {
        $p = &$this->path;

        $lastId = 0;
        if (!empty($p)) {
            $lastId = $this->lastNode->id;
        }

        $ourPid = @intval($this->config['pid']);

        if (!Security::isAdmin()) {
            return false;
        }

        if ($this->lastNode instanceof Dbnode) {
            if ($ourPid != $lastId) {
                return false;
            }
        } elseif (get_class($this->lastNode) != get_class($this)) {
            return false;
        }

        return true;
    }

    protected function createDefaultFilter()
    {
        $this->fq = array(
            'core_id:' . Config::get('core_id')
        );
    }

    public function getChildren(&$pathArray, $requestParams)
    {

        $this->path = $pathArray;
        $this->lastNode = @$pathArray[sizeof($pathArray) - 1];
        $this->requestParams = &$requestParams;
        $this->rootId = \CB\Browser::getRootFolderId();

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
            switch (substr($this->lastNode->id, 0, 1)) {
                case 'a':
                    $rez = $this->getLogGroups();
                    break;
                case 'g':
                    $rez = $this->getUsers();
                    break;

                case 'q':
                    $rez = $this->getTypes();
                    break;

                default:
                    $rez = $this->getLogRecords();
                    break;
            }
        }

        return $rez;

    }

    public function getName($id = false)
    {
        if ($id === false) {
            $id = $this->id;
        }

        $rez = $id;

        switch (substr($id, 0, 1)) {
            case 'a':
                $rez = L\get('ActionLog');
                break;

            case 'd':
                $rez = Util\formatAgoDate(substr($id, 1, 10));
                break;

            case 'm':
                $rez = L\get('CurrentMonth');
                break;

            case 'g':
                $rez = L\get('Users');
                break;

            case 'q':
                $rez = L\get('Type');
                break;

            case 'u':
                $rez = User::getDisplayName(substr($id, 1));
                break;

            case 't':
                $rez = Util\coalesce(L\get('at' . substr($id, 1)), substr($id, 1));
                break;
            default:

                if (!empty($id) && is_numeric($id)) {
                    $res = DB\dbQuery(
                        'SELECT data FROM action_log WHERE id = $1',
                        $id
                    ) or die(DB\dbQueryError());

                    if ($r = $res->fetch_assoc()) {
                        $j = Util\toJSONArray($r['data']);
                        $rez = Util\coalesce($j['name'], 'unknown');
                    }
                    $res->close();
                }
                break;
        }

        return $rez;
    }

    protected function getRootNodes()
    {
        return array(
            'data' => array(
                array(
                    'name' => $this->getName('actionLog')
                    ,'id' => $this->getId('actionLog')
                    ,'iconCls' => 'i-book-open'
                    ,'has_childs' => true
                )
            )
        );
    }

    public function getLogGroups()
    {
        $rez = array('data' => array());
        $s = Log::getSolrLogConnection();

        $p = array(
            'rows' => 0
            ,'facet' => 'true'
            ,'facet.mincount' => 1
            ,'facet.sort' => 'index'
            ,'facet.range' => 'action_date'
            ,'facet.range.start' => 'NOW/DAY-7DAY'
            ,'facet.range.end' => 'NOW/DAY+1DAY'
            ,'facet.range.gap' => '+1DAY'
            ,'fq' => $this->fq
            ,'facet.query' => array(
                '{!ex=action_date key=action_date}action_date:["' . date('Y-m') . '-01T00:00:00Z" TO *]'
            )
        );

        $sr = $s->query($p);

        if (!empty($sr->facet_counts->facet_ranges->action_date->counts)) {
            foreach ($sr->facet_counts->facet_ranges->action_date->counts as $k => $v) {
                $k = 'd' . substr($k, 0, 10);
                $rez['data'][$k] = array(
                    'name' => $this->getName($k) . ' (' . $v . ')'
                    ,'id' => $this->getId($k)
                    ,'iconCls' => 'icon-folder'
                    ,'has_childs' => false
                );
            }
        }
        krsort($rez['data']);
        $rez['data'] = array_values($rez['data']);

        if (!empty($sr->facet_counts->facet_queries->action_date)) {
                $k = 'month';
                $rez['data'][] = array(
                    'name' => $this->getName($k) . ' (' . $sr->facet_counts->facet_queries->action_date . ')'
                    ,'id' => $this->getId($k)
                    ,'iconCls' => 'icon-folder'
                    ,'has_childs' => false
                );
        }

        $rez['data'][] = array(
            'name' => $this->getName('g')
            ,'id' => $this->getId('g')
            ,'iconCls' => 'icon-folder'
            ,'has_childs' => true
        );

        $rez['data'][] = array(
            'name' => $this->getName('q')
            ,'id' => $this->getId('q')
            ,'iconCls' => 'icon-folder'
            ,'has_childs' => true
        );

        return $rez;
    }

    public function getUsers()
    {
        $rez = array('data' => array());
        $s = Log::getSolrLogConnection();

        $p = array(
            'rows' => 0
            ,'facet' => 'true'
            ,'facet.mincount' => 1
            ,'fq' => $this->fq
            ,'facet.field' => array(
                '{!ex=user_id key=user_id}user_id'
            )
        );

        $sr = $s->search('*:*', 0, 0, $p);

        if (!empty($sr->facet_counts->facet_fields->user_id)) {
            foreach ($sr->facet_counts->facet_fields->user_id as $k => $v) {
                $k = 'u' . $k;
                $rez['data'][] = array(
                    'name' => $this->getName($k) . ' (' . $v . ')'
                    ,'id' => $this->getId($k)
                    ,'iconCls' => 'icon-user'
                    ,'has_childs' => false
                );
            }
        }

        return $rez;
    }

    public function getTypes()
    {
        $rez = array('data' => array());
        $s = Log::getSolrLogConnection();

        $p = array(
            'rows' => 0
            ,'facet' => 'true'
            ,'facet.mincount' => 1
            ,'fq' => $this->fq
            ,'facet.field' => array(
                '{!ex=action_type key=action_type}action_type'
            )
        );

        $sr = $s->search('*:*', 0, 0, $p);

        if (!empty($sr->facet_counts->facet_fields->action_type)) {
            foreach ($sr->facet_counts->facet_fields->action_type as $k => $v) {
                $k = 't' . $k;
                $rez['data'][] = array(
                    'name' => $this->getName($k) . ' (' . $v . ')'
                    ,'id' => $this->getId($k)
                    ,'iconCls' => 'icon-folder'
                    ,'has_childs' => false
                );
            }
        }

        return $rez;
    }

    public function getLogRecords()
    {
        $s = Log::getSolrLogConnection();

        $this->requestParams['sort'] = array('date desc');

        $p = array(
            'rows' => 50
            ,'fl' => 'id,action_id,user_id,object_id,object_pid,object_data'
            ,'fq' => $this->fq
            ,'strictSort' => 'action_date desc'
        );

        $id = substr($this->lastNode->id, 1);

        switch (substr($this->lastNode->id, 0, 1)) {
            case 'd':
                $p['fq'][] = 'action_date:["' . $id . 'T00:00:00Z" TO "' . $id . 'T23:59:99Z"]';
                break;

            case 'm':
                $p['fq'][] = 'action_date:["' . date('Y-m') . '-01T00:00:00Z" TO *]';
                break;

            case 't':
                $p['fq'][] = 'action_type:'.$id;
                break;

            case 'u':
                $p['fq'][] = 'user_id:'.$id;
                break;

            case 't':
                $p['fq'][] = 'action_type:'.$id;
                break;

        }

        $rez = $s->query($p);

        foreach ($rez['data'] as &$doc) {
            $k =  $doc['action_id'];
            $data = Util\toJSONArray($doc['object_data']);

            $doc['id']  = $this->getId($doc['action_id']);
            $doc['pid'] = @$doc['object_pid'];
            unset($doc['object_pid']);
            $doc['name'] = Util\coalesce($data['name'], $doc['object_data']);
            $doc['iconCls'] = $data['iconCls'];
            $doc['path'] = $data['path'];
            // $doc['template_id'] = $data['template_id'];
            $doc['case_id'] = $data['case_id'];
            if ($data['date']) {
                $doc['date'] = $data['date'];
            }
            $doc['size'] = $data['size'];
            $doc['cid'] = @$data['cid'];
            $doc['oid'] = @$data['oid'];
            $doc['uid'] = @$data['uid'];
            $doc['cdate'] = $data['cdate'];
            $doc['udate'] = $data['udate'];
            $doc['user'] = User::getDisplayName($doc['user_id'], true);
            $doc['has_childs'] = false;
        }

        return $rez;
    }

    public function getFacets()
    {
        $rez = parent::getFacets();

        return $rez;
    }
}