<?php
class Slick_App_API_V1_Blog_Model extends Slick_Core_Model
{
	public function getAllPosts($data, $getExtra = 0, $start = 0, $filled = array())
	{
		$meta = new Slick_App_Meta_Model;
		$blogApp = $this->get('apps', 'blog', array('appId'), 'slug');
		$blogMeta = $meta->appMeta($blogApp['appId']);
		$limit = 15;
		if(isset($blogMeta['postsPerPage'])){
			$limit = intval($blogMeta['postsPerPage']);
		}
		if(isset($data['limit'])){
			$limit = intval($data['limit']);
		}
		

		$origLimit = $limit;
		//$limit += $getExtra;


		if(isset($data['page'])){
			$page = intval($data['page']);
			if($page > 1){
				$start = ($page * $limit) - $limit;
				$start++;
			}
			if($start < 0){
				$start = 0;
			}
		}
	
		if($getExtra > 0){
		//	$start = $start + $limit;
		}
		if(count($filled) >= $limit){
			return $filled;
		}

		$getExtra = 0;
		
		$andCats = '';
		if(isset($data['categories'])){
			$expCats = explode(',', $data['categories']);
			$catList= array();
			foreach($expCats as $expCat){
				$getCat = $this->get('blog_categories', $expCat);
				if(!$getCat){
					continue;
				}
				$catList[] = $getCat['categoryId'];
			}
			if(count($catList) > 0){
				$andCats = ' AND p.postId IN((SELECT postId FROM blog_postCategories WHERE categoryId IN('.join(',', $catList).'))) '; 
				//$andCats = ' AND c.categoryId IN('.join(',', $catList).') ';
			}
			else{
				throw new Exception('Categories not found');
			}
		}
		
		$siteList = array($data['site']['siteId']);
		if(isset($data['sites'])){
			$expSites = explode(',', $data['sites']);
			if(count($expSites) > 0){
				$newSites = array();
				foreach($expSites as $site){
					$getSite = $this->get('sites', $site);
					if(!$getSite){
						continue;
					}
					$newSites[] = $getSite['siteId'];
					
				}
				if(count($newSites) > 0){
					$siteList = $newSites;
				}
				else{
					throw new Exception('Sites not found');
				}
			}
		}
		
		$andUsers = '';
		if(isset($data['users'])){
			$expUsers = explode(',', $data['users']);
			if(count($expUsers) > 0){
				$userList = array();
				foreach($expUsers as $user){
					$getUser = $this->get('users', $user, array('userId'), 'slug');
					if(!$getUser){
						continue;
					}
					$userList[] = $getUser['userId'];
				}
				if(count($userList) > 0){
					$andUsers = ' AND p.userId IN('.join(',', $userList).') ';
				}
				else{
					throw new Exception('Users not found');
				}
			}
		}
		
		
		$metaFields = $this->getAll('blog_postMetaTypes', array('siteId' => $data['site']['siteId']));
		$metaFilters = array('true' => array(), 'false' => array());
		$andMeta = '';
		foreach($data as $key => $val){
			foreach($metaFields as $field){
				if($key == $field['slug']){
					$val = strtolower($val);
					if($val == 'true' || $val === true){
						$metaFilters['true'][$field['slug']] = $field['metaTypeId'];
					}
					else{
						$metaFilters['false'][$field['slug']] = $field['metaTypeId'];
					}
					
					
					continue 2;
				}
			}
		}
		
		$getPostFields = 'p.postId, p.siteId, p.title, p.url, p.content, p.userId, p.publishDate, p.image, p.excerpt, p.views, p.featured, p.coverImage, p.commentCount, p.commentCheck, p.formatType ';
		$minimizeData = false;
		if(isset($data['minimize']) AND intval($data['minimize']) === 1){
			$getPostFields = 'p.postId, p.siteId, p.title, p.url, p.excerpt, p.userId, p.publishDate, p.featured, p.coverImage, p.formatType';
			$minimizeData = true;
		}
		
		$andWhen = '';
		$modifiedSince = false;
		$modTime = false;
		if(isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])){
			if(is_int($_SERVER['HTTP_IF_MODIFIED_SINCE'])){
				$modTime = intval($_SERVER['HTTP_IF_MODIFIED_SINCE']);
			}
			else{
				$modTime = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
			}
		}
		elseif(isset($data['modified-since']) AND trim($data['modified-since']) != ''){
			if(is_int($data['modified-since'])){
				$modTime = intval($data['modified-since']);
			}
			else{
				$modTime = strtotime($data['modified-since']);
			}
		}
		if($modTime !== false){
			$modifiedSince = date('Y-m-d H:i:s', $modTime);
		}
		if($modifiedSince !== false){
			$andWhen .= ' AND editTime >= "'.$modifiedSince.'" ';
		}
		
		$postedBefore = false;
		$beforeTime = false;
		if(isset($_SERVER['HTTP_IF_POSTED_BEFORE'])){
			if(is_int($_SERVER['HTTP_IF_POSTED_BEFORE'])){
				$beforeTime = intval($_SERVER['HTTP_IF_POSTED_BEFORE']);
			}
			else{
				$beforeTime = strtotime($_SERVER['HTTP_IF_POSTED_BEFORE']);
			}
		}
		elseif(isset($data['posted-before']) AND trim($data['posted-before']) != ''){
			if(is_int($data['posted-before'])){
				$beforeTime = intval($data['posted-before']);
			}
			else{
				$beforeTime = strtotime($data['posted-before']);
			}
		}
		if($beforeTime !== false){
			$postedBefore = date('Y-m-d H:i:s', $beforeTime);
		}
		if($postedBefore !== false){
			$andWhen .= ' AND publishDate <= "'.$postedBefore.'" ';
		}
		
		
		if(count($metaFilters['true']) > 0 || count($metaFilters['false']) > 0){
			
			if(count($metaFilters['true']) > 0){
				$andMeta .= ' AND p.postId IN((
									SELECT mv2.postId as metaPostId
								    FROM blog_postMetaTypes mt2
								    LEFT JOIN blog_postMeta mv2 ON mt2.metaTypeId = mv2.metaTypeId
								    WHERE mv2.value != "" AND mt2.metaTypeId IN('.join(',',$metaFilters['true']).') GROUP BY mv2.postId)) ';
			}
			if(count($metaFilters['false']) > 0){
				$andMeta .= ' AND p.postId NOT IN((
									SELECT mv2.postId as metaPostId
								    FROM blog_postMetaTypes mt2
								    LEFT JOIN blog_postMeta mv2 ON mt2.metaTypeId = mv2.metaTypeId
								    WHERE mv2.value != "" AND mt2.metaTypeId IN('.join(',',$metaFilters['false']).') GROUP BY mv2.postId)) ';
				
			}
			
			$sql = 'SELECT '.$getPostFields.'
					 FROM blog_posts p
					 LEFT JOIN blog_postMeta mv ON mv.postId = p.postId
					 LEFT JOIN blog_postMetaTypes mt ON mt.metaTypeId = mv.metaTypeId
					 WHERE p.siteId IN('.join(',', $siteList).')
					 AND p.published = 1
					 AND p.publishDate <= "'.timestamp().'"
					 '.$andCats.'
					 '.$andUsers.'
					 '.$andMeta.'
					 '.$andWhen.'
					 GROUP BY postId
					 ORDER BY publishDate DESC
					 LIMIT '.$start.', '.$limit;
		}
		else{
			$sql = 'SELECT '.$getPostFields.'
					 FROM blog_posts p
					 WHERE p.siteId IN('.join(',', $siteList).')
					 AND p.published = 1
					 AND p.publishDate <= "'.timestamp().'"
					 '.$andCats.'
					 '.$andUsers.'
					 '.$andWhen.'
					 ORDER BY publishDate DESC
					 LIMIT '.$start.', '.$limit;
		}
		
		$getPosts = $this->fetchAll($sql);


		$profModel = new Slick_App_Profile_User_Model;
		$postModel = new Slick_App_Blog_Post_Model;
		if(!isset($data['isRSS'])){
			$disqus = new Slick_API_Disqus;
		}
		$origExtra = $getExtra;
		foreach($getPosts as $key => $post){
			if(isset($filled[$post['postId']])){
				continue;
			}
			
			if(!$minimizeData){
				if(!isset($data['noProfiles']) OR (isset($data['noProfiles']) AND !$data['noProfiles'])){
					$getPosts[$key]['author'] = $profModel->getUserProfile($post['userId'], $data['site']['siteId']);
					unset($getPosts[$key]['author']['lastActive']);
					unset($getPosts[$key]['author']['lastAuth']);
				}
			}
			
			
			if(!isset($data['noCategories']) OR (isset($data['noCategories']) AND !$data['noCategories'])){
				$getCats = $this->getAll('blog_postCategories', array('postId' => $post['postId']));
				$cats = array();
				foreach($getCats as $cat){
					$getCat = $this->get('blog_categories', $cat['categoryId']);
					$cats[] = $getCat;
				}
				$getPosts[$key]['categories'] = $cats;
			}
			
			$pageIndex = Slick_App_Controller::$pageIndex;
			$getIndex = extract_row($pageIndex, array('itemId' => $post['postId'], 'moduleId' => 28));
			$postURL = $data['site']['url'].'/blog/post/'.$post['url'];
			if($getIndex AND count($getIndex) > 0){
				$postURL = $data['site']['url'].'/'.$getIndex[count($getIndex) - 1]['url'];
			}
			
			
			if(!$minimizeData){
				$commentThread = true;
				if(isset($data['isRSS']) OR (isset($data['noComments']) AND $data['noComments'] == true)){
					$commentThread = false;
				}
				else{
					$comDiff = time() - strtotime($post['commentCheck']);
					if($comDiff > 300){
						$commentThread = $disqus->getThread($postURL, false);
					}
					
				}
				unset($getPosts[$key]['commentCheck']);
				//$getPosts[$key]['commentCount'] = 0;
				if($commentThread){
					$getPosts[$key]['commentCount'] = $commentThread['thread']['posts'];
					$this->edit('blog_posts', $post['postId'], array('commentCheck' => timestamp(), 'commentCount' => $commentThread['thread']['posts']));
				}				
			}
		
			
			if(trim($post['image']) != ''){
				$getPosts[$key]['image'] = $data['site']['url'].'/files/blogs/'.$post['image'];
			}
			else{
				$getPosts[$key]['image'] = null;
			}
			if(trim($post['coverImage']) != ''){
				$getPosts[$key]['coverImage'] = $data['site']['url'].'/files/blogs/'.$post['coverImage'];
			}
			else{
				$getPosts[$key]['coverImage'] = null;
			}
			$getMeta = $postModel->getPostMeta($post['postId']);
			foreach($getMeta as $mkey => $val){
				if(!isset($getPosts[$key][$mkey])){
					$getPosts[$key][$mkey] = $val;
				}
			}
			
			if(!isset($getPosts[$key]['audio-url']) AND isset($getPosts[$key]['soundcloud-id'])){
				$getPosts[$key]['audio-url'] = 'http://api.soundcloud.com/tracks/'.$getPosts[$key]['soundcloud-id'].'/stream?client_id='.SOUNDCLOUD_ID;
			}
			
			if($post['formatType'] == 'markdown'){
				$getPosts[$key]['excerpt'] = markdown($post['excerpt']);
				if(isset($post['content'])){
					$getPosts[$key]['content'] = markdown($post['content']);
				}
			}
			unset($getPosts[$key]['formatType']);
			
			if(isset($data['strip-html']) AND ($data['strip-html'] == 'true' || $data['strip-html'] === true)){
				$getPosts[$key]['excerpt'] = strip_tags($post['excerpt']);
				if(isset($post['content'])){
					$getPosts[$key]['content'] = strip_tags($post['content']);
				}
			}
		

			
		}


		return $getPosts;
	}
	
	public function addComment($data, $appData)
	{
		if(!isset($data['postId'])){
			throw new Exception('postId not set');
		}
		
		if(!isset($data['user'])){
			throw new Exception('Not logged in');
		}
		
		if(!isset($data['message'])){
			throw new Exception('Message required');
		}
		
		$model = new Slick_App_Blog_Post_Model;
		$get = $model->get('blog_posts', $data['postId'], array('postId', 'url'), 'url');
		if(!$get){
			$get = $model->get('blog_posts', $data['postId'], array('postId', 'url'));
			if(!$get){
				throw new Exception('Post not found');
			}
		}
		$data['postId'] = $get['postId'];
		$data['userId'] = $data['user']['userId'];
		
		/* Disqus Comment Code */
		$disqus = new Slick_API_Disqus;
		$profModel = new Slick_App_Profile_User_Model;
		$getIndex = $this->getAll('page_index', array('itemId' => $get['postId'], 'moduleId' => 28));
		$postURL = $appData['site']['url'].'/blog/post/'.$get['url'];
		if($getIndex AND count($getIndex) > 0){
			$postURL = $appData['site']['url'].'/'.$getIndex[count($getIndex) - 1]['url'];
			
		}
		$userProf = $profModel->getUserProfile($data['userId'], $appData['site']['siteId']);
		
		if(!$userProf){
			throw new Exception('Error getting user profile');
		}
		
		$getThread = $disqus->getThread($postURL);
		if(!$getThread){
			throw new Exception('Comment thread not found');
		}
		$threadId = $getThread['thread']['id'];
		
		$userData = array('id' => $data['userId'], 'username' => $userProf['username'], 'email' => $userProf['email'],
						 'avatar' => $appData['site']['url'].'/files/avatars/'.$userProf['avatar'],'url' => $appData['site']['url'].'/profile/user/'.$userProf['slug']);
		$remote = $disqus->genRemoteAuth($userData);
		$comData = array('remote_auth' => $remote, 'threadId' => $threadId, 'message' => $data['message']);
		
		$postComment = $disqus->makePost($comData);
		if(!$postComment){
			throw new Exception('Error posting comment');
		}
		
		if(!is_array($postComment)){
			throw new Exception($postComment);
		}
		
		$com = $postComment;
		
		
		$comment = array();
		$comment['commentId'] = $com['id'];
		$comment['postId'] = $get['postId'];
		
		if(!isset($data['strip_html'])){
			$data['strip_html'] = true;
		}

		if(isset($data['strip_html']) AND ($data['strip_html'] == 'true' || $data['strip_html'] === true)){
			$comment['message'] = strip_tags($com['message']);
		}
		else{
			$comment['message'] = $com['message'];
		}
		
		$comment['commentDate'] = $com['createdAt'];
		$comment['buried'] = 0;
		$comment['editTime'] = null;
		$author = array();
		$author['username'] = $com['author']['name'];
		$author['slug'] = genURL($com['author']['name']);
		$author['regDate'] = $com['author']['joinedAt'];
		$author['profile'] = array();
		$author['avatar'] = $com['author']['avatar']['permalink'];
	
		$getComUser = $model->get('users', $author['username'], array('userId'), 'username');
		if($getComUser){
			$getComProf = $profModel->getUserProfile($getComUser['userId'], $appData['site']['siteId']);
			if($getComProf){
				$author['profile'] = $getComProf['profile'];
				$author['regDate'] = $getComProf['regDate'];
				$author['slug'] = $getComProf['slug'];
			}
		}
		
		$comment['author'] = $author;

		return $comment;
		
		/*
		-- native site comment code --
		$post = $model->postComment($data, $appData);
		
		if($post){
			$output = $model->getComment($post, $data['site']['siteId']);
			unset($output['author']['pubProf']);
			unset($output['author']['showEmail']);
			return $output;
		}
		throw new Exception('Error posting comment..');
		*/
	}
	
}
