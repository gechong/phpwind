<?php
defined('WEKIT_VERSION') || exit('Forbidden');

Wind::import('SRV:forum.bo.PwForumBo');
Wind::import('SRV:forum.srv.PwThreadList');

/**
 * 获取某个板块下的帖子列表接口
 *
 * @fileName: ThreadController.php
 * @author: yuliang<yuliang.lyl@alibaba-inc.com>
 * @license: http://www.phpwind.com
 * @version: $Id
 * @lastchange: 2014-12-16 19:08:17
 * @desc: 
 **/

class ThreadController extends PwBaseController {
	
	protected $topictypes;

	/**
        * 获取某个板块下的帖子列表（标准、精华）
        * @access public
        * @return string
         <pre>
         /index.php?m=native&c=thread&fid=板块id
         获取精华帖：
         /index.php?m=native&c=thread&fid=板块id&tab=digest
         response: html
         </pre>
        */
	public function run() {
		//某个模板下的帖子列表页
//            echo 111;exit;
		$tab = $this->getInput('tab');//是否是精华帖
		$fid = intval($this->getInput('fid'));//分类id
		$type = intval($this->getInput('type','get')); //主题分类ID
		$page = $this->getInput('page', 'get');
		$orderby = $this->getInput('orderby', 'get');
// 		var_dump($tab,$fid,$type,$page,$orderby);exit;//null,2,0,null,null
		$pwforum = new PwForumBo($fid, true);//板块信息
//		var_dump($pwforum);exit;
		if (!$pwforum->isForum()) {
			$this->showError('BBS:forum.exists.not');
		}
		if ($pwforum->allowVisit($this->loginUser) !== true) {
			$this->showError(array('BBS:forum.permissions.visit.allow', array('{grouptitle}' => $this->loginUser->getGroupInfo('name'))));
		}
		if ($pwforum->forumset['jumpurl']) {
			$this->forwardRedirect($pwforum->forumset['jumpurl']);
		}
		if ($pwforum->foruminfo['password']) {
			if (!$this->loginUser->isExists()) {
				$this->forwardAction('u/login/run', array('backurl' => WindUrlHelper::createUrl('bbs/cate/run', array('fid' => $fid))));
			} elseif (Pw::getPwdCode($pwforum->foruminfo['password']) != Pw::getCookie('fp_' . $fid)) {
				$this->forwardAction('bbs/forum/password', array('fid' => $fid));
			}
		}
		$isBM = $pwforum->isBM($this->loginUser->username);//检测用户是否是版主
		if ($operateThread = $this->loginUser->getPermission('operate_thread', $isBM, array())) {
			$operateThread = Pw::subArray($operateThread, array('topped', 'digest', 'highlight', 'up', 'copy', 'type', 'move', /*'unite',*/ 'lock', 'down', 'delete', 'ban'));
		}
		$this->_initTopictypes($fid, $type);

		$threadList = new PwThreadList();//帖子列表对象
//		var_dump($threadList);exit;
		$this->runHook('c_thread_run', $threadList);

		$threadList->setPage($page)
                        ->setPerpage(30)//帖子列表页一页展示30条
//			->setPerpage($pwforum->forumset['threadperpage'] ? $pwforum->forumset['threadperpage'] : Wekit::C('bbs', 'thread.perpage'))
			->setIconNew($pwforum->foruminfo['newtime']);
//		var_dump($page,$pwforum);exit;//null,
		$defaultOrderby = $pwforum->forumset['threadorderby'] ? 'postdate' : 'lastpost';
		!$orderby && $orderby = $defaultOrderby;

		if ($tab == 'digest') {
			Wind::import('SRV:forum.srv.threadList.PwDigestThread');
			$dataSource = new PwDigestThread($pwforum->fid, $type, $orderby);
		} elseif ($type) {
			Wind::import('SRV:forum.srv.threadList.PwSearchThread');
			$dataSource = new PwSearchThread($pwforum);
			$dataSource->setOrderby($orderby);
			$dataSource->setType($type, $this->_getSubTopictype($type));
		} elseif ($orderby == 'postdate') {
			Wind::import('SRV:forum.srv.threadList.PwNewForumThread');
			$dataSource = new PwNewForumThread($pwforum);
		} else {
			Wind::import('SRV:forum.srv.threadList.PwCommonThread');
			$dataSource = new PwCommonThread($pwforum);//帖子列表数据接口
		}
//                 var_dump($dataSource);exit;//PwCommonThread对象
		$orderby != $defaultOrderby && $dataSource->setUrlArg('orderby', $orderby);
		$threadList->execute($dataSource);
                //需要合并移动端扩展表数据以及内容数据
                $tids = array();
                foreach($threadList->threaddb as $v){
                    $tids[] = $v['tid'];
                }
                $threads_list = Wekit::load('native.srv.PwDynamicService')->fetchThreadsList($tids,"NUM");
                
//                var_dump($this);exit;
//                var_dump(get_class($pwforum),get_class_methods($pwforum));exit;
//                var_dump($pwforum->foruminfo);exit;//获得版块数据$pwforum->isJoin($loginUser->uid)
                var_dump($tids,$threads_list);exit;//置顶帖子包含在通用帖子当中
 		var_dump($threadList->threaddb);exit;//获得帖子数据
                
		
		$this->setOutput($threadList, 'threadList');
		$this->setOutput($threadList->getList(), 'threaddb');
		$this->setOutput($fid, 'fid');
		$this->setOutput($type ? $type : null, 'type');
		$this->setOutput($tab, 'tab');
		$this->setOutput($pwforum, 'pwforum');
		$this->setOutput($pwforum->headguide(), 'headguide');
		$this->setOutput($threadList->icon, 'icon');
		$this->setOutput($threadList->uploadIcon, 'uploadIcon');
		$this->setOutput($operateThread, 'operateThread');
		$this->setOutput($pwforum->forumset['numofthreadtitle'] ? $pwforum->forumset['numofthreadtitle'] : 26, 'numofthreadtitle');
		$this->setOutput((!$this->loginUser->uid && !$this->allowPost($pwforum)) ? ' J_qlogin_trigger' : '', 'postNeedLogin');

		$this->setOutput($threadList->page, 'page');
		$this->setOutput($threadList->perpage, 'perpage');
		$this->setOutput($threadList->total, 'count');
		$this->setOutput($threadList->maxPage, 'totalpage');
		$this->setOutput($defaultOrderby, 'defaultOrderby');
		$this->setOutput($orderby, 'orderby');
		$this->setOutput($threadList->getUrlArgs(), 'urlargs');
		$this->setOutput($this->_formatTopictype($type), 'topictypes');
		
		//版块风格
		if ($pwforum->foruminfo['style']) {
			$this->setTheme('forum', $pwforum->foruminfo['style']);
			//$this->addCompileDir($pwforum->foruminfo['style']);
		}
		
		//seo设置
		Wind::import('SRV:seo.bo.PwSeoBo');
		$seoBo = PwSeoBo::getInstance();
		$lang = Wind::getComponent('i18n');
		if ($threadList->page <=1) {
			if ($type)
				$seoBo->setDefaultSeo($lang->getMessage('SEO:bbs.thread.run.type.title'), '', $lang->getMessage('SEO:bbs.thread.run.type.description'));
			else 
				$seoBo->setDefaultSeo($lang->getMessage('SEO:bbs.thread.run.title'), '', $lang->getMessage('SEO:bbs.thread.run.description'));
		}
		$seoBo->init('bbs', 'thread', $fid);
		$seoBo->set(array(
			'{forumname}' => $pwforum->foruminfo['name'],
			'{forumdescription}' => Pw::substrs($pwforum->foruminfo['descrip'], 100, 0, false),
			'{classification}' => $this->_getSubTopictypeName($type),
			'{page}' => $threadList->page
		));
		Wekit::setV('seo', $seoBo);
		Pw::setCookie('visit_referer', 'fid_' . $fid . '_page_' . $threadList->page, 300);
	}

	private function _initTopictypes($fid, &$type) {
		$this->topictypes = $this->_getTopictypeService()->getTopicTypesByFid($fid);
		if (!isset($this->topictypes['all_types'][$type])) $type = 0;
	}

	private function _getSubTopictype($type) {
		if (isset($this->topictypes['sub_topic_types']) && isset($this->topictypes['sub_topic_types'][$type])) {
			return array_keys($this->topictypes['sub_topic_types'][$type]);
		}
		return array();
	}

	private function _getSubTopictypeName($type) {
		return isset($this->topictypes['all_types'][$type]) ? $this->topictypes['all_types'][$type]['name'] : '';
	}
	
	private function _formatTopictype($type) {
		$topictypes = $this->topictypes;
		if (isset($topictypes['all_types'][$type]) && $topictypes['all_types'][$type]['parentid']) {
			$topictypeService = Wekit::load('forum.srv.PwTopicTypeService');
			$topictypes = $topictypeService->sortTopictype($type, $topictypes);
		}
		return $topictypes;
	}
	
	private function _getTopictypeService(){
		return Wekit::load('forum.PwTopicType');
	}

	private function allowPost(PwForumBo $forum) {
		return $forum->foruminfo['allow_post'] ? $forum->allowPost($this->loginUser) : $this->loginUser->getPermission('allow_post');
	}
}