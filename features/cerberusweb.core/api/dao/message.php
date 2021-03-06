<?php
/***********************************************************************
| Cerb(tm) developed by Webgroup Media, LLC.
|-----------------------------------------------------------------------
| All source code & content (c) Copyright 2013, Webgroup Media LLC
|   unless specifically noted otherwise.
|
| This source code is released under the Devblocks Public License.
| The latest version of this license can be found here:
| http://cerberusweb.com/license
|
| By using this software, you acknowledge having read this license
| and agree to be bound thereby.
| ______________________________________________________________________
|	http://www.cerberusweb.com	  http://www.webgroupmedia.com/
***********************************************************************/

class DAO_Message extends Cerb_ORMHelper {
	const ID = 'id';
	const TICKET_ID = 'ticket_id';
	const CREATED_DATE = 'created_date';
	const ADDRESS_ID = 'address_id';
	const IS_BROADCAST = 'is_broadcast';
	const IS_OUTGOING = 'is_outgoing';
	const WORKER_ID = 'worker_id';
	const STORAGE_EXTENSION = 'storage_extension';
	const STORAGE_KEY = 'storage_key';
	const STORAGE_PROFILE_ID = 'storage_profile_id';
	const STORAGE_SIZE = 'storage_size';
	const RESPONSE_TIME = 'response_time';

	static function create($fields) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = "INSERT INTO message () VALUES ()";
		$db->Execute($sql) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg());
		$id = $db->LastInsertId();

		self::update($id, $fields);
		
		if(isset($fields[self::TICKET_ID])) {
			DAO_Ticket::updateMessageCount($fields[self::TICKET_ID]);
		}
		
		return $id;
	}

	static function update($id, $fields) {
		parent::_update($id, 'message', $fields);
	}

	/**
	 * @param string $where
	 * @return Model_Message[]
	 */
	static function getWhere($where=null, $sortBy='created_date', $sortAsc=true, $limit=null) {
		$db = DevblocksPlatform::getDatabaseService();

		list($where_sql, $sort_sql, $limit_sql) = self::_getWhereSQL($where, $sortBy, $sortAsc, $limit);
		
		// SQL
		$sql = "SELECT id, ticket_id, created_date, is_outgoing, worker_id, address_id, storage_extension, storage_key, storage_profile_id, storage_size, response_time, is_broadcast ".
			"FROM message ".
			$where_sql.
			$sort_sql.
			$limit_sql
		;
		$rs = $db->Execute($sql);
		
		return self::_getObjectsFromResult($rs);
	}

	/**
	 * @param integer $id
	 * @return Model_Message
	 */
	static function get($id) {
		$objects = self::getWhere(sprintf("%s = %d",
			self::ID,
			$id
		));
		
		if(isset($objects[$id]))
			return $objects[$id];
		
		return null;
	}
	
	/**
	 * @param resource $rs
	 * @return Model_Message[]
	 */
	static private function _getObjectsFromResult($rs) {
		$objects = array();
		
		if(empty($rs))
			return $objects;
		
		while($row = mysql_fetch_assoc($rs)) {
			$object = new Model_Message();
			$object->id = $row['id'];
			$object->ticket_id = $row['ticket_id'];
			$object->created_date = $row['created_date'];
			$object->is_outgoing = $row['is_outgoing'];
			$object->worker_id = $row['worker_id'];
			$object->address_id = $row['address_id'];
			$object->storage_extension = $row['storage_extension'];
			$object->storage_key = $row['storage_key'];
			$object->storage_profile_id = $row['storage_profile_id'];
			$object->storage_size = $row['storage_size'];
			$object->response_time = $row['response_time'];
			$object->is_broadcast = intval($row['is_broadcast']);
			$objects[$object->id] = $object;
		}
		
		mysql_free_result($rs);
		
		return $objects;
	}
	
	/**
	 * @return Model_Message[]
	 */
	static function getMessagesByTicket($ticket_id) {
		return self::getWhere(
			sprintf("%s = %d",
				self::TICKET_ID,
				$ticket_id
			),
			DAO_Message::CREATED_DATE,
			true
		);
	}

	static function delete($ids) {
		$db = DevblocksPlatform::getDatabaseService();
		
		if(!is_array($ids))
			$ids = array($ids);
		
		if(empty($ids))
			return array();
		
		$ids_list = implode(',', $ids);

		$messages = DAO_Message::getWhere(sprintf("%s IN (%s)",
			DAO_Message::ID,
			$ids_list
		));

		// Message Headers
		DAO_MessageHeader::deleteById($ids);
		
		// Message Content
		Storage_MessageContent::delete($ids);
		
		// Search indexes
		Search_MessageContent::delete($ids);
		
		// Messages
		$sql = sprintf("DELETE FROM message WHERE id IN (%s)",
				$ids_list
		);
		$db->Execute($sql);
		
		// Remap first/last on ticket
		foreach($messages as $message_id => $message) {
			DAO_Ticket::rebuild($message->ticket_id);
		}
		
		// Fire event
		$eventMgr = DevblocksPlatform::getEventService();
		$eventMgr->trigger(
			new Model_DevblocksEvent(
				'context.delete',
				array(
					'context' => CerberusContexts::CONTEXT_MESSAGE,
					'context_ids' => $ids
				)
			)
		);
	}

	static function maint() {
		$db = DevblocksPlatform::getDatabaseService();
		$logger = DevblocksPlatform::getConsoleLog();
		$tables = $db->metaTables();
		
		// Purge message content (storage)
		$sql = "SELECT message.id FROM message LEFT JOIN ticket ON message.ticket_id = ticket.id WHERE ticket.id IS NULL";
		$rs = $db->Execute($sql) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg());

		$ids_buffer = array();
		$count = 0;
		
		while($row = mysql_fetch_assoc($rs)) {
			$ids_buffer[$count++] = $row['id'];
			
			// Flush buffer every 50
			if(0 == $count % 50) {
				Storage_MessageContent::delete($ids_buffer);
				$ids_buffer = array();
				$count = 0;
			}
		}
		mysql_free_result($rs);

		// Any remainder
		if(!empty($ids_buffer)) {
			Storage_MessageContent::delete($ids_buffer);
			unset($ids_buffer);
			unset($count);
		}

		// Purge messages without linked tickets
		$sql = "DELETE QUICK message FROM message LEFT JOIN ticket ON message.ticket_id = ticket.id WHERE ticket.id IS NULL";
		$db->Execute($sql);
		$logger->info('[Maint] Purged ' . $db->Affected_Rows() . ' message records.');
		
		// Headers
		$sql = "DELETE QUICK message_header FROM message_header LEFT JOIN message ON message_header.message_id = message.id WHERE message.id IS NULL";
		$db->Execute($sql);
		$logger->info('[Maint] Purged ' . $db->Affected_Rows() . ' message_header records.');

		// Attachments
		$sql = "DELETE QUICK attachment_link FROM attachment_link LEFT JOIN message ON (attachment_link.context_id=message.id) WHERE attachment_link.context = 'cerberusweb.contexts.message' AND message.id IS NULL";
		$db->Execute($sql);
		$logger->info('[Maint] Purged ' . $db->Affected_Rows() . ' message attachment_links.');
		
		// Search indexes
		if(isset($tables['fulltext_message_content'])) {
			$sql = "DELETE QUICK fulltext_message_content FROM fulltext_message_content LEFT JOIN message ON fulltext_message_content.id = message.id WHERE message.id IS NULL";
			$db->Execute($sql);
			$logger->info('[Maint] Purged ' . $db->Affected_Rows() . ' fulltext_message_content records.');
		}
		
		// Fire event
		$eventMgr = DevblocksPlatform::getEventService();
		$eventMgr->trigger(
			new Model_DevblocksEvent(
				'context.maint',
				array(
					'context' => CerberusContexts::CONTEXT_MESSAGE,
					'context_table' => 'message',
					'context_key' => 'id',
				)
			)
		);
	}

	public static function random() {
		return self::_getRandom('message');
	}

	public static function getSearchQueryComponents($columns, $params, $sortBy=null, $sortAsc=null) {
		$fields = SearchFields_Message::getFields();
		
		// Sanitize
		if('*'==substr($sortBy,0,1) || !isset($fields[$sortBy]))
			$sortBy=null;

		list($tables,$wheres,$selects) = parent::_parseSearchParams($params, array(),$fields,$sortBy);

		$select_sql = sprintf("SELECT ".
			"m.id as %s, ".
			"m.address_id as %s, ".
			"m.created_date as %s, ".
			"m.is_outgoing as %s, ".
			"m.ticket_id as %s, ".
			"m.worker_id as %s, ".
			"m.storage_extension as %s, ".
			"m.storage_key as %s, ".
			"m.storage_profile_id as %s, ".
			"m.storage_size as %s, ".
			"m.response_time as %s, ".
			"m.is_broadcast as %s, ".
			"t.group_id as %s, ".
			"t.mask as %s, ".
			"t.subject as %s, ".
			"a.email as %s ",
			SearchFields_Message::ID,
			SearchFields_Message::ADDRESS_ID,
			SearchFields_Message::CREATED_DATE,
			SearchFields_Message::IS_OUTGOING,
			SearchFields_Message::TICKET_ID,
			SearchFields_Message::WORKER_ID,
			SearchFields_Message::STORAGE_EXTENSION,
			SearchFields_Message::STORAGE_KEY,
			SearchFields_Message::STORAGE_PROFILE_ID,
			SearchFields_Message::STORAGE_SIZE,
			SearchFields_Message::RESPONSE_TIME,
			SearchFields_Message::IS_BROADCAST,
			SearchFields_Message::TICKET_GROUP_ID,
			SearchFields_Message::TICKET_MASK,
			SearchFields_Message::TICKET_SUBJECT,
			SearchFields_Message::ADDRESS_EMAIL
		);
		
		$join_sql = "FROM message m ".
			"INNER JOIN ticket t ON (m.ticket_id = t.id) ".
			"INNER JOIN address a ON (m.address_id = a.id) ".
			(isset($tables['mh']) ? "INNER JOIN message_header mh ON (mh.message_id=m.id)" : " ").
			(isset($tables['ftmc']) ? "INNER JOIN fulltext_message_content ftmc ON (ftmc.id=m.id)" : " ");
			
		$where_sql = "".
			(!empty($wheres) ? sprintf("WHERE %s ",implode(' AND ',$wheres)) : "WHERE 1 ");
			
		$sort_sql = (!empty($sortBy) ? sprintf("ORDER BY %s %s ",$sortBy,($sortAsc || is_null($sortAsc))?"ASC":"DESC") : " ");
		
		$has_multiple_values = false;
		
		// Translate virtual fields
		
		$args = array(
			'join_sql' => &$join_sql,
			'where_sql' => &$where_sql,
			'tables' => &$tables,
			'has_multiple_values' => &$has_multiple_values
		);
		
		array_walk_recursive(
			$params,
			array('DAO_Message', '_translateVirtualParameters'),
			$args
		);
		
		$result = array(
			'primary_table' => 'm',
			'select' => $select_sql,
			'join' => $join_sql,
			'where' => $where_sql,
			'has_multiple_values' => $has_multiple_values,
			'sort' => $sort_sql,
		);
		
		return $result;
	}

	private static function _translateVirtualParameters($param, $key, &$args) {
		if(!is_a($param, 'DevblocksSearchCriteria'))
			return;
		
		$from_context = 'cerberusweb.contexts.message';
		$from_index = 'm.id';
		
		$param_key = $param->field;
		settype($param_key, 'string');

		switch($param_key) {
			case SearchFields_Message::VIRTUAL_TICKET_STATUS:
				$values = $param->value;
				if(!is_array($values))
					$values = array($values);
					
				$oper_sql = array();
				$status_sql = array();
				
				switch($param->operator) {
					default:
					case DevblocksSearchCriteria::OPER_IN:
					case DevblocksSearchCriteria::OPER_IN_OR_NULL:
						$oper = '';
						break;
					case DevblocksSearchCriteria::OPER_NIN:
					case DevblocksSearchCriteria::OPER_NIN_OR_NULL:
						$oper = 'NOT ';
						break;
				}
				
				foreach($values as $value) {
					switch($value) {
						case 'open':
							$status_sql[] = sprintf('%s(t.is_waiting = 0 AND t.is_closed = 0 AND t.is_deleted = 0)', $oper);
							break;
						case 'waiting':
							$status_sql[] = sprintf('%s(t.is_waiting = 1 AND t.is_closed = 0 AND t.is_deleted = 0)', $oper);
							break;
						case 'closed':
							$status_sql[] = sprintf('%s(t.is_closed = 1 AND t.is_deleted = 0)', $oper);
							break;
						case 'deleted':
							$status_sql[] = sprintf('%s(t.is_deleted = 1)', $oper);
							break;
					}
				}
				
				if(empty($status_sql))
					break;
				
				$args['where_sql'] .= 'AND (' . implode(' OR ', $status_sql) . ') ';
				break;
		}
	}
	
	/**
	 * Enter description here...
	 *
	 * @param DevblocksSearchCriteria[] $params
	 * @param integer $limit
	 * @param integer $page
	 * @param string $sortBy
	 * @param boolean $sortAsc
	 * @param boolean $withCounts
	 * @return array
	 */
	static function search($columns, $params, $limit=10, $page=0, $sortBy=null, $sortAsc=null, $withCounts=true) {
		$db = DevblocksPlatform::getDatabaseService();

		// Build search queries
		$query_parts = self::getSearchQueryComponents($columns,$params,$sortBy,$sortAsc);

		$select_sql = $query_parts['select'];
		$join_sql = $query_parts['join'];
		$where_sql = $query_parts['where'];
		$has_multiple_values = $query_parts['has_multiple_values'];
		$sort_sql = $query_parts['sort'];
		
		$sql =
			$select_sql.
			$join_sql.
			$where_sql.
			($has_multiple_values ? 'GROUP BY m.id ' : '').
			$sort_sql;
		
		$rs = $db->SelectLimit($sql,$limit,$page*$limit) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg());
		
		$results = array();
		
		while($row = mysql_fetch_assoc($rs)) {
			$result = array();
			foreach($row as $f => $v) {
				$result[$f] = $v;
			}
			$ticket_id = intval($row[SearchFields_Message::ID]);
			$results[$ticket_id] = $result;
		}
		
		// [JAS]: Count all
		$total = -1;
		if($withCounts) {
			$count_sql =
				($has_multiple_values ? "SELECT COUNT(DISTINCT m.id) " : "SELECT COUNT(m.id) ").
				$join_sql.
				$where_sql;
			$total = $db->GetOne($count_sql);
		}

		mysql_free_result($rs);
		
		return array($results,$total);
	}
};

class SearchFields_Message implements IDevblocksSearchFields {
	// Message
	const ID = 'm_id';
	const ADDRESS_ID = 'm_address_id';
	const CREATED_DATE = 'm_created_date';
	const IS_OUTGOING = 'm_is_outgoing';
	const TICKET_ID = 'm_ticket_id';
	const WORKER_ID = 'm_worker_id';
	const RESPONSE_TIME = 'm_response_time';
	const IS_BROADCAST = 'm_is_broadcast';
	
	// Storage
	const STORAGE_EXTENSION = 'm_storage_extension';
	const STORAGE_KEY = 'm_storage_key';
	const STORAGE_PROFILE_ID = 'm_storage_profile_id';
	const STORAGE_SIZE = 'm_storage_size';
	
	// Headers
	const MESSAGE_HEADER_NAME = 'mh_header_name';
	const MESSAGE_HEADER_VALUE = 'mh_header_value';

	// Content
	const MESSAGE_CONTENT = 'ftmc_content';
	
	// Address
	const ADDRESS_EMAIL = 'a_email';
	
	// Ticket
	const TICKET_GROUP_ID = 't_group_id';
	const TICKET_IS_DELETED = 't_is_deleted';
	const TICKET_MASK = 't_mask';
	const TICKET_SUBJECT = 't_subject';
	
	// Virtuals
	const VIRTUAL_TICKET_STATUS = '*_ticket_status';

	/**
	 * @return DevblocksSearchField[]
	 */
	static function getFields() {
		$translate = DevblocksPlatform::getTranslationService();
		
		$columns = array(
			SearchFields_Message::ID => new DevblocksSearchField(SearchFields_Message::ID, 'm', 'id', $translate->_('common.id')),
			SearchFields_Message::ADDRESS_ID => new DevblocksSearchField(SearchFields_Message::ADDRESS_ID, 'm', 'address_id'),
			SearchFields_Message::CREATED_DATE => new DevblocksSearchField(SearchFields_Message::CREATED_DATE, 'm', 'created_date', $translate->_('common.created'), Model_CustomField::TYPE_DATE),
			SearchFields_Message::IS_OUTGOING => new DevblocksSearchField(SearchFields_Message::IS_OUTGOING, 'm', 'is_outgoing', $translate->_('message.is_outgoing'), Model_CustomField::TYPE_CHECKBOX),
			SearchFields_Message::TICKET_ID => new DevblocksSearchField(SearchFields_Message::TICKET_ID, 'm', 'ticket_id', 'Ticket ID'),
			SearchFields_Message::WORKER_ID => new DevblocksSearchField(SearchFields_Message::WORKER_ID, 'm', 'worker_id', $translate->_('common.worker'), Model_CustomField::TYPE_WORKER),
			SearchFields_Message::RESPONSE_TIME => new DevblocksSearchField(SearchFields_Message::RESPONSE_TIME, 'm', 'response_time', $translate->_('message.response_time'), Model_CustomField::TYPE_NUMBER),
			SearchFields_Message::IS_BROADCAST => new DevblocksSearchField(SearchFields_Message::IS_BROADCAST, 'm', 'is_broadcast', $translate->_('message.is_broadcast'), Model_CustomField::TYPE_CHECKBOX),
			
			SearchFields_Message::STORAGE_EXTENSION => new DevblocksSearchField(SearchFields_Message::STORAGE_EXTENSION, 'm', 'storage_extension'),
			SearchFields_Message::STORAGE_KEY => new DevblocksSearchField(SearchFields_Message::STORAGE_KEY, 'm', 'storage_key'),
			SearchFields_Message::STORAGE_PROFILE_ID => new DevblocksSearchField(SearchFields_Message::STORAGE_PROFILE_ID, 'm', 'storage_profile_id'),
			SearchFields_Message::STORAGE_SIZE => new DevblocksSearchField(SearchFields_Message::STORAGE_SIZE, 'm', 'storage_size'),
			
			SearchFields_Message::MESSAGE_HEADER_NAME => new DevblocksSearchField(SearchFields_Message::MESSAGE_HEADER_NAME, 'mh', 'header_name'),
			SearchFields_Message::MESSAGE_HEADER_VALUE => new DevblocksSearchField(SearchFields_Message::MESSAGE_HEADER_VALUE, 'mh', 'header_value'),
			
			SearchFields_Message::ADDRESS_EMAIL => new DevblocksSearchField(SearchFields_Message::ADDRESS_EMAIL, 'a', 'email', $translate->_('common.email'), Model_CustomField::TYPE_SINGLE_LINE),
			
			SearchFields_Message::TICKET_GROUP_ID => new DevblocksSearchField(SearchFields_Message::TICKET_GROUP_ID, 't', 'group_id', $translate->_('common.group')),
			SearchFields_Message::TICKET_IS_DELETED => new DevblocksSearchField(SearchFields_Message::TICKET_IS_DELETED, 't', 'is_deleted', $translate->_('status.deleted'), Model_CustomField::TYPE_CHECKBOX),
			SearchFields_Message::TICKET_MASK => new DevblocksSearchField(SearchFields_Message::TICKET_MASK, 't', 'mask', $translate->_('ticket.mask'), Model_CustomField::TYPE_SINGLE_LINE),
			SearchFields_Message::TICKET_SUBJECT => new DevblocksSearchField(SearchFields_Message::TICKET_SUBJECT, 't', 'subject', $translate->_('ticket.subject'), Model_CustomField::TYPE_SINGLE_LINE),
			
			SearchFields_Message::VIRTUAL_TICKET_STATUS => new DevblocksSearchField(SearchFields_Message::VIRTUAL_TICKET_STATUS, '*', 'ticket_status', $translate->_('ticket.status')),
		);
	
		$tables = DevblocksPlatform::getDatabaseTables();
		if(isset($tables['fulltext_message_content'])) {
			$columns[SearchFields_Message::MESSAGE_CONTENT] = new DevblocksSearchField(SearchFields_Message::MESSAGE_CONTENT, 'ftmc', 'content', $translate->_('common.content'), 'FT');
		}
		
		// Sort by label (translation-conscious)
		DevblocksPlatform::sortObjects($columns, 'db_label');

		return $columns;
	}
};

class Model_Message {
	public $id;
	public $ticket_id;
	public $created_date;
	public $address_id;
	public $is_outgoing;
	public $worker_id;
	public $storage_extension;
	public $storage_key;
	public $storage_profile_id;
	public $storage_size;
	public $response_time;
	public $is_broadcast;
	
	private $_sender_object = null;

	function Model_Message() {}

	function getContent(&$fp=null) {
		if(empty($this->storage_extension) || empty($this->storage_key))
			return '';

		return Storage_MessageContent::get($this, $fp);
	}

	function getHeaders() {
		return DAO_MessageHeader::getAll($this->id);
	}

	/**
	 *
	 * Enter description here ...
	 * @return Model_Address
	 */
	function getSender() {
		// Lazy load + cache
		if(null == $this->_sender_object) {
			$this->_sender_object = DAO_Address::get($this->address_id);
		}
		
		return $this->_sender_object;
	}
	
	/**
	 * returns an array of the message's attachments
	 *
	 * @return Model_Attachment[]
	 */
	function getAttachments() {
		return DAO_Attachment::getByContextIds(CerberusContexts::CONTEXT_MESSAGE, $this->id);
	}
	
	function getLinksAndAttachments() {
		return DAO_AttachmentLink::getLinksAndAttachments(CerberusContexts::CONTEXT_MESSAGE, $this->id);
	}
};

class Search_MessageContent {
	const ID = 'cerberusweb.search.schema.message_content';
	
	public static function index($stop_time=null) {
		$logger = DevblocksPlatform::getConsoleLog();
		
		if(false == ($search = DevblocksPlatform::getSearchService())) {
			$logger->error("[Search] The search engine is misconfigured.");
			return;
		}
		
		$ns = 'message_content';
		$id = DAO_DevblocksExtensionPropertyStore::get(self::ID, 'last_indexed_id', 0);
		$done = false;
		
		while(!$done && time() < $stop_time) {
			$where = sprintf("%s > %d", DAO_Message::ID, $id);
			$messages = DAO_Message::getWhere($where, 'id', true, 100);
	
			if(empty($messages)) {
				$done = true;
				continue;
			}
			
			$count = 0;
			
			if(is_array($messages))
			foreach($messages as $message) { /* @var $message Model_Message */
				$id = $message->id;
				
				$logger->info(sprintf("[Search] Indexing %s %d...",
					$ns,
					$id
				));
				
				if(false !== ($content = Storage_MessageContent::get($message))) {
					// Strip reply quotes
					$content = preg_replace("/(^\>(.*)\$)/m", "", $content);
					$content = preg_replace("/[\r\n]+/", "\n", $content);
					
					// Truncate to 10KB
					$content = $search->truncateOnWhitespace($content, 10000);
					
					$search->index($ns, $id, $content, true);
				}

				// Record our progress every 10th index
				if(++$count % 10 == 0) {
					if(!empty($id))
						DAO_DevblocksExtensionPropertyStore::put(self::ID, 'last_indexed_id', $id);
				}
			}
			
			flush();
			
			// Record our index every batch
			if(!empty($id))
				DAO_DevblocksExtensionPropertyStore::put(self::ID, 'last_indexed_id', $id);
		}
	}
	
	public static function delete($ids) {
		if(false == ($search = DevblocksPlatform::getSearchService())) {
			$logger->error("[Search] The search engine is misconfigured.");
			return;
		}
		
		$ns = 'message_content';
		return $search->delete($ns, $ids);
	}
};

class Storage_MessageContent extends Extension_DevblocksStorageSchema {
	const ID = 'cerberusweb.storage.schema.message_content';
	
	public static function getActiveStorageProfile() {
		return DAO_DevblocksExtensionPropertyStore::get(self::ID, 'active_storage_profile', 'devblocks.storage.engine.database');
	}
	
	function render() {
		$tpl = DevblocksPlatform::getTemplateService();
		
		$tpl->assign('active_storage_profile', $this->getParam('active_storage_profile'));
		$tpl->assign('archive_storage_profile', $this->getParam('archive_storage_profile'));
		$tpl->assign('archive_after_days', $this->getParam('archive_after_days'));
		
		$tpl->display("devblocks:cerberusweb.core::configuration/section/storage_profiles/schemas/message_content/render.tpl");
	}
	
	function renderConfig() {
		$tpl = DevblocksPlatform::getTemplateService();
		
		$tpl->assign('active_storage_profile', $this->getParam('active_storage_profile'));
		$tpl->assign('archive_storage_profile', $this->getParam('archive_storage_profile'));
		$tpl->assign('archive_after_days', $this->getParam('archive_after_days'));
		
		$tpl->display("devblocks:cerberusweb.core::configuration/section/storage_profiles/schemas/message_content/config.tpl");
	}
	
	function saveConfig() {
		@$active_storage_profile = DevblocksPlatform::importGPC($_REQUEST['active_storage_profile'],'string','');
		@$archive_storage_profile = DevblocksPlatform::importGPC($_REQUEST['archive_storage_profile'],'string','');
		@$archive_after_days = DevblocksPlatform::importGPC($_REQUEST['archive_after_days'],'integer',0);
		
		if(!empty($active_storage_profile))
			$this->setParam('active_storage_profile', $active_storage_profile);
		
		if(!empty($archive_storage_profile))
			$this->setParam('archive_storage_profile', $archive_storage_profile);

		$this->setParam('archive_after_days', $archive_after_days);
		
		return true;
	}
	
	/**
	 * @param Model_Message | $message_id
	 * @return unknown_type
	 */
	public static function get($object, &$fp=null) {
		if($object instanceof Model_Message) {
			// Do nothing
		} elseif(is_numeric($object)) {
			$object = DAO_Message::get($object);
		} else {
			$object = null;
		}
		
		if(empty($object))
			return false;
		
		$key = $object->storage_key;
		$profile = !empty($object->storage_profile_id) ? $object->storage_profile_id : $object->storage_extension;
		
		if(false === ($storage = DevblocksPlatform::getStorageService($profile)))
			return false;
			
		$contents = $storage->get('message_content', $key, $fp);
		
		// Convert the appropriate bytes
		if(is_string($contents) && !mb_check_encoding($contents, LANG_CHARSET_CODE))
			$contents = mb_convert_encoding($contents, LANG_CHARSET_CODE);
			
		return $contents;
	}
	
	public static function put($id, $contents, $profile=null) {
		if(empty($profile)) {
			$profile = self::getActiveStorageProfile();
		}
		
		if($profile instanceof Model_DevblocksStorageProfile) {
			$profile_id = $profile->id;
		} elseif(is_numeric($profile)) {
			$profile_id = intval($profile_id);
		} elseif(is_string($profile)) {
			$profile_id = 0;
		}
		
		$storage = DevblocksPlatform::getStorageService($profile);

		if(is_resource($contents)) {
			$stats = fstat($contents);
			$storage_size = $stats['size'];
			
		} else {
			// Store the appropriate bytes
			if(!mb_check_encoding($contents, LANG_CHARSET_CODE))
				$contents = mb_convert_encoding($contents, LANG_CHARSET_CODE);
			
			$storage_size = strlen($contents);
		}
		
		// Save to storage
		if(false === ($storage_key = $storage->put('message_content', $id, $contents)))
			return false;
			
		// Update storage key
		DAO_Message::update($id, array(
			DAO_Message::STORAGE_EXTENSION => $storage->manifest->id,
			DAO_Message::STORAGE_KEY => $storage_key,
			DAO_Message::STORAGE_PROFILE_ID => $profile_id,
			DAO_Message::STORAGE_SIZE => $storage_size,
		));
	
		return $storage_key;
	}

	public static function delete($ids) {
		if(!is_array($ids)) $ids = array($ids);
		
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = sprintf("SELECT storage_extension, storage_key, storage_profile_id FROM message WHERE id IN (%s)", implode(',',$ids));
		$rs = $db->Execute($sql) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg());
		
		// Delete the physical files
		
		while($row = mysql_fetch_assoc($rs)) {
			$profile = !empty($row['storage_profile_id']) ? $row['storage_profile_id'] : $row['storage_extension'];
			if(null != ($storage = DevblocksPlatform::getStorageService($profile)))
				$storage->delete('message_content', $row['storage_key']);
		}
		
		mysql_free_result($rs);
		
		return true;
	}
	
	public function getStats() {
		return $this->_stats('message');
	}
		
	public static function archive($stop_time=null) {
		$db = DevblocksPlatform::getDatabaseService();
		
		// Params
		$src_profile = DAO_DevblocksStorageProfile::get(DAO_DevblocksExtensionPropertyStore::get(self::ID, 'active_storage_profile'));
		$dst_profile = DAO_DevblocksStorageProfile::get(DAO_DevblocksExtensionPropertyStore::get(self::ID, 'archive_storage_profile'));
		$archive_after_days = DAO_DevblocksExtensionPropertyStore::get(self::ID, 'archive_after_days');
				
		if(empty($src_profile) || empty($dst_profile))
			return;

		if(json_encode($src_profile) == json_encode($dst_profile))
			return;
		
		// Find inactive attachments
		$sql = sprintf("SELECT message.id, message.storage_extension, message.storage_key, message.storage_profile_id, message.storage_size ".
			"FROM message ".
			"INNER JOIN ticket ON (ticket.id=message.ticket_id) ".
			"WHERE ticket.is_deleted = 0 ".
			"AND ticket.updated_date < %d ".
			"AND (message.storage_extension = %s AND message.storage_profile_id = %d) ".
			"ORDER BY message.id ASC ",
				time()-(86400*$archive_after_days),
				$db->qstr($src_profile->extension_id),
				$src_profile->id
		);
		$rs = $db->Execute($sql);
		
		while($row = mysql_fetch_assoc($rs)) {
			self::_migrate($dst_profile, $row);

			if(time() > $stop_time)
				return;
		}
	}
	
	public static function unarchive($stop_time=null) {
		// We don't want to unarchive message content under any condition
		/*
		$db = DevblocksPlatform::getDatabaseService();
		
		// Params
		$dst_profile = DAO_DevblocksStorageProfile::get(DAO_DevblocksExtensionPropertyStore::get(self::ID, 'active_storage_profile'));
		$archive_after_days = DAO_DevblocksExtensionPropertyStore::get(self::ID, 'archive_after_days');
				
		if(empty($dst_profile))
			return;
		
		// Find active attachments
		$sql = sprintf("SELECT message.id, message.storage_extension, message.storage_key, message.storage_profile_id, message.storage_size ".
			"FROM message ".
			"INNER JOIN ticket ON (ticket.id=message.ticket_id) ".
			"WHERE ticket.is_deleted = 0 ".
			"AND ticket.updated_date >= %d ".
			"AND NOT (message.storage_extension = %s AND message.storage_profile_id = %d) ".
			"ORDER BY message.id DESC ",
				time()-(86400*$archive_after_days),
				$db->qstr($dst_profile->extension_id),
				$dst_profile->id
		);
		$rs = $db->Execute($sql);
		
		while($row = mysql_fetch_assoc($rs)) {
			self::_migrate($dst_profile, $row, true);
			
			if(time() > $stop_time)
				return;
		}
		*/
	}
	
	private static function _migrate($dst_profile, $row, $is_unarchive=false) {
		$logger = DevblocksPlatform::getConsoleLog();
		
		$ns = 'message_content';
		
		$src_key = $row['storage_key'];
		$src_id = $row['id'];
		$src_size = $row['storage_size'];
		
		$src_profile = new Model_DevblocksStorageProfile();
		$src_profile->id = $row['storage_profile_id'];
		$src_profile->extension_id = $row['storage_extension'];
		
		if(empty($src_key) || empty($src_id)
			|| !$src_profile instanceof Model_DevblocksStorageProfile
			|| !$dst_profile instanceof Model_DevblocksStorageProfile
			)
			return;
		
		$src_engine = DevblocksPlatform::getStorageService(!empty($src_profile->id) ? $src_profile->id : $src_profile->extension_id);
		
		$logger->info(sprintf("[Storage] %s %s %d (%d bytes) from (%s) to (%s)...",
			(($is_unarchive) ? 'Unarchiving' : 'Archiving'),
			$ns,
			$src_id,
			$src_size,
			$src_profile->extension_id,
			$dst_profile->extension_id
		));

		// Do as quicker strings if under 1MB?
		$is_small = ($src_size < (1024 * 1000)) ? true : false;
		
		// Allocate a temporary file for retrieving content
		if($is_small) {
			if(false === ($data = $src_engine->get($ns, $src_key))) {
				$logger->error(sprintf("[Storage] Error reading %s key (%s) from (%s)",
					$ns,
					$src_key,
					$src_profile->extension_id
				));
				return;
			}
		} else {
			$fp_in = DevblocksPlatform::getTempFile();
			if(false === $src_engine->get($ns, $src_key, $fp_in)) {
				$logger->error(sprintf("[Storage] Error reading %s key (%s) from (%s)",
					$ns,
					$src_key,
					$src_profile->extension_id
				));
				return;
			}
		}

		if($is_small) {
			$loaded_size = strlen($data);
		} else {
			$stats_in = fstat($fp_in);
			$loaded_size = $stats_in['size'];
		}
		
		$logger->info(sprintf("[Storage] Loaded %d bytes of data from (%s)...",
			$loaded_size,
			$src_profile->extension_id
		));
		
		if($is_small) {
			if(false === ($dst_key = self::put($src_id, $data, $dst_profile))) {
				$logger->error(sprintf("[Storage] Error saving %s %d to (%s)",
					$ns,
					$src_id,
					$dst_profile->extension_id
				));
				unset($data);
				return;
			}
		} else {
			if(false === ($dst_key = self::put($src_id, $fp_in, $dst_profile))) {
				$logger->error(sprintf("[Storage] Error saving %s %d to (%s)",
					$ns,
					$src_id,
					$dst_profile->extension_id
				));
				fclose($fp_in);
				return;
			}
		}
		
		$logger->info(sprintf("[Storage] Saved %s %d to destination (%s) as key (%s)...",
			$ns,
			$src_id,
			$dst_profile->extension_id,
			$dst_key
		));
		
		// Free resources
		if($is_small) {
			unset($data);
		} else {
			@unlink(DevblocksPlatform::getTempFileInfo($fp_in));
			fclose($fp_in);
		}
		
		$src_engine->delete($ns, $src_key);
		$logger->info(sprintf("[Storage] Deleted %s %d from source (%s)...",
			$ns,
			$src_id,
			$src_profile->extension_id
		));
		
		$logger->info(''); // blank
	}
};

class DAO_MessageHeader {
	const MESSAGE_ID = 'message_id';
	const HEADER_NAME = 'header_name';
	const HEADER_VALUE = 'header_value';

	static function create($message_id, $header, $value) {
		$db = DevblocksPlatform::getDatabaseService();
		
		if(empty($header) || empty($value) || empty($message_id))
			return;
		
		$header = strtolower($header);

		// Handle stacked headers
		if(is_array($value)) {
			$value = implode("\r\n",$value);
		}

		$db->Execute(sprintf("INSERT INTO message_header (message_id, header_name, header_value) ".
				"VALUES (%d, %s, %s)",
				$message_id,
				$db->qstr($header),
				$db->qstr($value)
		));
	}

	static function getAll($message_id) {
		$db = DevblocksPlatform::getDatabaseService();

		$sql = sprintf("SELECT header_name, header_value ".
			"FROM message_header ".
			"WHERE message_id = %d",
			$message_id
		);

		$rs = $db->Execute($sql) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg());

		$headers = array();

		while($row = mysql_fetch_assoc($rs)) {
			$headers[$row['header_name']] = $row['header_value'];
		}

		mysql_free_result($rs);

		return $headers;
	}

	static function getOne($message_id, $header_name) {
		$db = DevblocksPlatform::getDatabaseService();

		$sql = sprintf("SELECT header_value ".
			"FROM message_header ".
			"WHERE message_id = %d ".
			"AND header_name = %s ",
			$message_id,
			$db->qstr($header_name)
		);
		return $db->GetOne($sql);
	}

	static function getUnique() {
		$db = DevblocksPlatform::getDatabaseService();
		$headers = array();

		$sql = "SELECT header_name FROM message_header GROUP BY header_name";
		$rs = $db->Execute($sql) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg());

		while($row = mysql_fetch_assoc($rs)) {
			$headers[] = $row['header_name'];
		}

		mysql_free_result($rs);

		sort($headers);

		return $headers;
	}

	static function deleteById($ids) {
		if(!is_array($ids))
			$ids = array($ids);
		
		if(empty($ids))
			return;

		$db = DevblocksPlatform::getDatabaseService();
		 
		$sql = sprintf("DELETE FROM message_header WHERE message_id IN (%s)",
			implode(',', $ids)
		);
		$db->Execute($sql);
	}
};

class View_Message extends C4_AbstractView implements IAbstractView_Subtotals, IAbstractView_QuickSearch {
	const DEFAULT_ID = 'messages';

	function __construct() {
		$this->id = self::DEFAULT_ID;
		$this->name = 'Messages';
		$this->renderLimit = 25;
		$this->renderSortBy = SearchFields_Message::CREATED_DATE;
		$this->renderSortAsc = true;

		$this->view_columns = array(
			SearchFields_Message::ADDRESS_EMAIL,
			SearchFields_Message::TICKET_GROUP_ID,
			SearchFields_Message::WORKER_ID,
			SearchFields_Message::CREATED_DATE,
		);
		
		$this->addColumnsHidden(array(
			SearchFields_Message::ID,
			SearchFields_Message::MESSAGE_CONTENT,
			SearchFields_Message::MESSAGE_HEADER_NAME,
			SearchFields_Message::MESSAGE_HEADER_VALUE,
			SearchFields_Message::STORAGE_EXTENSION,
			SearchFields_Message::STORAGE_KEY,
			SearchFields_Message::STORAGE_PROFILE_ID,
			SearchFields_Message::STORAGE_SIZE,
			SearchFields_Message::VIRTUAL_TICKET_STATUS,
		));
		$this->addParamsHidden(array(
			SearchFields_Message::ID,
		));
		
		$this->doResetCriteria();
	}

	function getData() {
		return DAO_Message::search(
			$this->view_columns,
			$this->getParams(),
			$this->renderLimit,
			$this->renderPage,
			$this->renderSortBy,
			$this->renderSortAsc,
			$this->renderTotal
		);
	}

	function getDataAsObjects($ids=null) {
		return $this->_getDataAsObjects('DAO_Message', $ids);
	}
	
	function getSubtotalFields() {
		$all_fields = $this->getParamsAvailable(true);
		
		$fields = array();

		if(is_array($all_fields))
		foreach($all_fields as $field_key => $field_model) {
			$pass = false;
			
			switch($field_key) {
				case SearchFields_Message::ADDRESS_EMAIL:
				case SearchFields_Message::IS_BROADCAST:
				case SearchFields_Message::IS_OUTGOING:
				case SearchFields_Message::TICKET_GROUP_ID:
				case SearchFields_Message::TICKET_IS_DELETED:
				case SearchFields_Message::WORKER_ID:
				case SearchFields_Message::VIRTUAL_TICKET_STATUS:
					$pass = true;
					break;
					
				// Valid custom fields
				default:
					if('cf_' == substr($field_key,0,3))
						$pass = $this->_canSubtotalCustomField($field_key);
					break;
			}
			
			if($pass)
				$fields[$field_key] = $field_model;
		}
		
		return $fields;
	}
	
	function getSubtotalCounts($column) {
		$counts = array();
		$fields = $this->getFields();

		if(!isset($fields[$column]))
			return array();
		
		switch($column) {
			case SearchFields_Message::ADDRESS_EMAIL:
				$counts = $this->_getSubtotalCountForStringColumn('DAO_Message', $column);
				break;
				
			case SearchFields_Message::TICKET_GROUP_ID:
				$groups = DAO_Group::getAll();
				$label_map = array();
				foreach($groups as $group_id => $group)
					$label_map[$group_id] = $group->name;
				$counts = $this->_getSubtotalCountForStringColumn('DAO_Message', $column, $label_map, 'in', 'group_id[]');
				break;
				
			case SearchFields_Message::WORKER_ID:
				$workers = DAO_Worker::getAll();
				$label_map = array();
				foreach($workers as $worker_id => $worker)
					$label_map[$worker_id] = $worker->getName();
				$counts = $this->_getSubtotalCountForNumberColumn('DAO_Message', $column, $label_map, 'in', 'worker_id[]');
				break;

			case SearchFields_Message::IS_BROADCAST:
			case SearchFields_Message::IS_OUTGOING:
			case SearchFields_Message::TICKET_IS_DELETED:
				$counts = $this->_getSubtotalCountForBooleanColumn('DAO_Message', $column);
				break;
			
			case SearchFields_Message::VIRTUAL_TICKET_STATUS:
				$counts = $this->_getSubtotalCountForStatus();
				break;
			
			default:
				// Custom fields
				if('cf_' == substr($column,0,3)) {
					$counts = $this->_getSubtotalCountForCustomColumn('DAO_Message', $column, 'm.id');
				}
				
				break;
		}
		
		return $counts;
	}
	
	protected function _getSubtotalDataForStatus($dao_class, $field_key) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$fields = $this->getFields();
		$columns = $this->view_columns;
		$params = $this->getParams();
		
		// We want counts for all statuses even though we're filtering
		if(
			isset($params[SearchFields_Message::VIRTUAL_TICKET_STATUS])
			&& is_array($params[SearchFields_Message::VIRTUAL_TICKET_STATUS]->value)
			&& count($params[SearchFields_Message::VIRTUAL_TICKET_STATUS]->value) < 2
			)
			unset($params[SearchFields_Message::VIRTUAL_TICKET_STATUS]);
			
		if(!method_exists($dao_class,'getSearchQueryComponents'))
			return array();
		
		$query_parts = call_user_func_array(
			array($dao_class,'getSearchQueryComponents'),
			array(
				$columns,
				$params,
				$this->renderSortBy,
				$this->renderSortAsc
			)
		);
		
		$join_sql = $query_parts['join'];
		$where_sql = $query_parts['where'];
		
		$sql = "SELECT COUNT(IF(t.is_closed=0 AND t.is_waiting=0 AND t.is_deleted=0,1,NULL)) AS open_hits, COUNT(IF(t.is_waiting=1 AND t.is_closed=0 AND t.is_deleted=0,1,NULL)) AS waiting_hits, COUNT(IF(t.is_closed=1 AND t.is_deleted=0,1,NULL)) AS closed_hits, COUNT(IF(t.is_deleted=1,1,NULL)) AS deleted_hits ".
			$join_sql.
			$where_sql
		;
		
		$results = $db->GetArray($sql);

		return $results;
	}
	
	protected function _getSubtotalCountForStatus() {
		$workers = DAO_Worker::getAll();
		$translate = DevblocksPlatform::getTranslationService();
		
		$counts = array();
		$results = $this->_getSubtotalDataForStatus('DAO_Message', SearchFields_Message::VIRTUAL_TICKET_STATUS);

		$result = array_shift($results);
		$oper = DevblocksSearchCriteria::OPER_IN;
		
		foreach($result as $key => $hits) {
			if(empty($hits))
				continue;
			
			switch($key) {
				case 'open_hits':
					$label = $translate->_('status.open');
					$values = array('options[]' => 'open');
					break;
				case 'waiting_hits':
					$label = $translate->_('status.waiting');
					$values = array('options[]' => 'waiting');
					break;
				case 'closed_hits':
					$label = $translate->_('status.closed');
					$values = array('options[]' => 'closed');
					break;
				case 'deleted_hits':
					$label = $translate->_('status.deleted');
					$values = array('options[]' => 'deleted');
					break;
				default:
					$label = '';
					break;
			}
			
			if(!isset($counts[$label]))
				$counts[$label] = array(
					'hits' => $hits,
					'label' => $label,
					'filter' =>
						array(
							'field' => SearchFields_Message::VIRTUAL_TICKET_STATUS,
							'oper' => $oper,
							'values' => $values,
						),
					'children' => array()
				);
		}
		
		return $counts;
	}
	
	function isQuickSearchField($token) {
		switch($token) {
			case SearchFields_Message::TICKET_GROUP_ID:
			case SearchFields_Message::VIRTUAL_TICKET_STATUS:
				return true;
			break;
		}
		
		return false;
	}
	
	function quickSearch($token, $query, &$oper, &$value) {
		switch($token) {
			case SearchFields_Message::TICKET_GROUP_ID:
				$search_ids = array();
				$oper = DevblocksSearchCriteria::OPER_IN;
				
				if(preg_match('#([\!\=]+)(.*)#', $query, $matches)) {
					$oper_hint = trim($matches[1]);
					$query = trim($matches[2]);
					
					switch($oper_hint) {
						case '!':
						case '!=':
							$oper = DevblocksSearchCriteria::OPER_NIN;
							break;
					}
				}
				
				$groups = DAO_Group::getAll();
				$inputs = DevblocksPlatform::parseCsvString($query);

				if(is_array($inputs))
				foreach($inputs as $input) {
					foreach($groups as $group_id => $group) {
						if(0 == strcasecmp($input, substr($group->name,0,strlen($input))))
							$search_ids[$group_id] = true;
					}
				}
				
				if(!empty($search_ids)) {
					$value = array_keys($search_ids);
				} else {
					$value = null;
				}
				
				return true;
				break;
				
			case SearchFields_Message::VIRTUAL_TICKET_STATUS:
				$statuses = array();
				$oper = DevblocksSearchCriteria::OPER_IN;
				
				if(preg_match('#([\!\=]+)(.*)#', $query, $matches)) {
					$oper_hint = trim($matches[1]);
					$query = trim($matches[2]);
					
					switch($oper_hint) {
						case '!':
						case '!=':
							$oper = DevblocksSearchCriteria::OPER_NIN;
							break;
					}
				}
				
				$inputs = DevblocksPlatform::parseCsvString($query);
				
				if(is_array($inputs))
				foreach($inputs as $v) {
					switch(strtolower(substr($v,0,1))) {
						case 'o':
							$statuses['open'] = true;
							break;
						case 'w':
							$statuses['waiting'] = true;
							break;
						case 'c':
							$statuses['closed'] = true;
							break;
						case 'd':
							$statuses['deleted'] = true;
							break;
					}
				}
				
				if(empty($statuses)) {
					$value = null;
					
				} else {
					$value = array_keys($statuses);
				}
				
				return true;
				break;
				
		}
		
		return false;
	}
	
	function render() {
		$this->_sanitize();
		
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);
		$tpl->assign('view', $this);

//		$custom_fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_WORKER);
//		$tpl->assign('custom_fields', $custom_fields);

		switch($this->renderTemplate) {
			default:
				$tpl->assign('view_template', 'devblocks:cerberusweb.core::messages/view.tpl');
				$tpl->display('devblocks:cerberusweb.core::internal/views/subtotals_and_view.tpl');
				break;
		}
	}

	function renderVirtualCriteria($param) {
		$key = $param->field;
		$translate = DevblocksPlatform::getTranslationService();
		
		switch($key) {
			case SearchFields_Message::VIRTUAL_TICKET_STATUS:
				if(!is_array($param->value))
					$param->value = array($param->value);
					
				$strings = array();
				
				foreach($param->value as $value) {
					switch($value) {
						case 'open':
							$strings[] = '<b>' . $translate->_('status.open') . '</b>';
							break;
						case 'waiting':
							$strings[] = '<b>' . $translate->_('status.waiting') . '</b>';
							break;
						case 'closed':
							$strings[] = '<b>' . $translate->_('status.closed') . '</b>';
							break;
						case 'deleted':
							$strings[] = '<b>' . $translate->_('status.deleted') . '</b>';
							break;
					}
				}
				
				switch($param->operator) {
					case DevblocksSearchCriteria::OPER_IN:
						$oper = 'is';
						break;
					case DevblocksSearchCriteria::OPER_IN_OR_NULL:
						$oper = 'is blank or';
						break;
					case DevblocksSearchCriteria::OPER_NIN:
						$oper = 'is not';
						break;
					case DevblocksSearchCriteria::OPER_NIN_OR_NULL:
						$oper = 'is blank or not';
						break;
				}
				echo sprintf("Status %s %s", $oper, implode(' or ', $strings));
				break;
		}
	}
	
	function renderCriteria($field) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);
		$tpl->assign('view', $this);

		switch($field) {
			case SearchFields_Message::ADDRESS_EMAIL:
			case SearchFields_Message::TICKET_MASK:
			case SearchFields_Message::TICKET_SUBJECT:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__string.tpl');
				break;
				
			case '_placeholder_number':
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__number.tpl');
				break;
				
			case SearchFields_Message::RESPONSE_TIME:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__time_elapsed.tpl');
				break;
				
			case SearchFields_Message::IS_BROADCAST:
			case SearchFields_Message::IS_OUTGOING:
			case SearchFields_Message::TICKET_IS_DELETED:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__bool.tpl');
				break;
				
			case SearchFields_Message::CREATED_DATE:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__date.tpl');
				break;
				
			case SearchFields_Message::TICKET_GROUP_ID:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__context_group.tpl');
				break;
				
			case SearchFields_Message::WORKER_ID:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__context_worker.tpl');
				break;
				
			case SearchFields_Message::MESSAGE_CONTENT:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__fulltext.tpl');
				break;
				
			case SearchFields_Message::VIRTUAL_TICKET_STATUS:
				$translate = DevblocksPlatform::getTranslationService();
				
				$options = array(
					'open' => $translate->_('status.open'),
					'waiting' => $translate->_('status.waiting'),
					'closed' => $translate->_('status.closed'),
					'deleted' => $translate->_('status.deleted'),
				);
				
				$tpl->assign('options', $options);
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__list.tpl');
				break;
				
			default:
				// Custom Fields
//				if('cf_' == substr($field,0,3)) {
//					$this->_renderCriteriaCustomField($tpl, substr($field,3));
//				} else {
//					echo ' ';
//				}
				break;
		}
	}

	function renderCriteriaParam($param) {
		$field = $param->field;
		$values = !is_array($param->value) ? array($param->value) : $param->value;

		switch($field) {
			case SearchFields_Message::IS_BROADCAST:
			case SearchFields_Message::IS_OUTGOING:
			case SearchFields_Message::TICKET_IS_DELETED:
				$this->_renderCriteriaParamBoolean($param);
				break;
				
			case SearchFields_Message::TICKET_GROUP_ID:
				$groups = DAO_Group::getAll();
				$strings = array();

				foreach($values as $val) {
					if(!isset($groups[$val]))
					continue;

					$strings[] = $groups[$val]->name;
				}
				echo implode(" or ", $strings);
				break;
				
			case SearchFields_Message::WORKER_ID:
				$this->_renderCriteriaParamWorker($param);
				break;
				
			case SearchFields_Message::RESPONSE_TIME:
				$value = array_shift($values);
				echo DevblocksPlatform::strSecsToString($value);
				break;
				
			default:
				parent::renderCriteriaParam($param);
				break;
		}
	}

	function getFields() {
		return SearchFields_Message::getFields();
	}

	function doSetCriteria($field, $oper, $value) {
		$criteria = null;

		switch($field) {
			case SearchFields_Message::ADDRESS_EMAIL:
			case SearchFields_Message::TICKET_MASK:
			case SearchFields_Message::TICKET_SUBJECT:
				$criteria = $this->_doSetCriteriaString($field, $oper, $value);
				break;
				
			case SearchFields_Message::RESPONSE_TIME:
				$now = time();
				@$then = intval(strtotime($value, $now));
				$value = $then - $now;
				
				$criteria = new DevblocksSearchCriteria($field,$oper,$value);
				break;
				
			case SearchFields_Message::CREATED_DATE:
				$criteria = $this->_doSetCriteriaDate($field, $oper);
				break;
				
			case SearchFields_Message::IS_BROADCAST:
			case SearchFields_Message::IS_OUTGOING:
			case SearchFields_Message::TICKET_IS_DELETED:
				@$bool = DevblocksPlatform::importGPC($_REQUEST['bool'],'integer',1);
				$criteria = new DevblocksSearchCriteria($field,$oper,$bool);
				break;

			case SearchFields_Message::TICKET_GROUP_ID:
				@$group_ids = DevblocksPlatform::importGPC($_REQUEST['group_id'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_IN,$group_ids);
				break;
				
			case SearchFields_Message::WORKER_ID:
				@$worker_ids = DevblocksPlatform::importGPC($_REQUEST['worker_id'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,$oper,$worker_ids);
				break;
				
			case SearchFields_Message::MESSAGE_CONTENT:
				@$scope = DevblocksPlatform::importGPC($_REQUEST['scope'],'string','expert');
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_FULLTEXT,array($value,$scope));
				break;
				
			case SearchFields_Message::VIRTUAL_TICKET_STATUS:
				@$options = DevblocksPlatform::importGPC($_REQUEST['options'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,$oper,$options);
				break;
				
			default:
				// Custom Fields
//				if(substr($field,0,3)=='cf_') {
//					$criteria = $this->_doSetCriteriaCustomField($field, substr($field,3));
//				}
				break;
		}

		if(!empty($criteria)) {
			$this->addParam($criteria);
			$this->renderPage = 0;
		}
	}

	function doBulkUpdate($filter, $do, $ids=array()) {
		@set_time_limit(600); // 10m
		
		$change_fields = array();
		$custom_fields = array();

		if(empty($do))
			return;

		// Make sure we have checked items if we want a checked list
		if(0 == strcasecmp($filter,"checks") && empty($ids))
			return;
			
		if(is_array($do))
		foreach($do as $k => $v) {
			switch($k) {
//				case 'is_disabled':
//					$change_fields[DAO_Worker::IS_DISABLED] = intval($v);
//					break;
//				default:
//					// Custom fields
//					if(substr($k,0,3)=="cf_") {
//						$custom_fields[substr($k,3)] = $v;
//					}
//					break;
			}
		}

		$pg = 0;

		if(empty($ids))
		do {
			list($objects,$null) = DAO_Message::search(
			array(),
			$this->getParams(),
			100,
			$pg++,
			SearchFields_Message::ID,
			true,
			false
			);
			 
			$ids = array_merge($ids, array_keys($objects));
			 
		} while(!empty($objects));

		$batch_total = count($ids);
		for($x=0;$x<=$batch_total;$x+=100) {
			$batch_ids = array_slice($ids,$x,100);
			DAO_Message::update($batch_ids, $change_fields);
			
			// Custom Fields
			//self::_doBulkSetCustomFields(CerberusContexts::CONTEXT_WORKER, $custom_fields, $batch_ids);
			
			unset($batch_ids);
		}

		unset($ids);
	}
};

class Context_Message extends Extension_DevblocksContext {
	function authorize($context_id, Model_Worker $worker) {
		// Security
		try {
			if(empty($worker))
				throw new Exception();
			
			if($worker->is_superuser)
				return TRUE;
				
			if(null == ($message = DAO_Message::get($context_id)))
				throw new Exception();
			
			if(null == ($ticket = DAO_Ticket::get($message->ticket_id)))
				throw new Exception();
			
			return $worker->isGroupMember($ticket->group_id);
				
		} catch (Exception $e) {
			// Fail
		}
		
		return FALSE;
	}
	
	function getRandom() {
		return DAO_Message::random();
	}
	
	function getMeta($context_id) {
		$url_writer = DevblocksPlatform::getUrlService();

		if(null == ($message = DAO_Message::get($context_id)))
			return FALSE;
			
		if(null == ($ticket = DAO_Ticket::get($message->ticket_id)))
			return FALSE;
			
		return array(
			'id' => $context_id,
			'name' => sprintf("[%s] %s", $ticket->mask, $ticket->subject),
			'permalink' => $url_writer->writeNoProxy(sprintf('c=profiles&type=ticket&mask=%s&focus=message&focusid=%d', $ticket->mask, $message->id), true),
		);
	}
	
	function getPropertyLabels(DevblocksDictionaryDelegate $dict) {
		$labels = $dict->_labels;
		$prefix = $labels['_label'];
		
		if(!empty($prefix)) {
			array_walk($labels, function(&$label, $key) use ($prefix) {
				// [TODO] Translate
				$label = preg_replace(sprintf("#^%s #i", preg_quote($prefix)), '', $label);
				$label = preg_replace(sprintf("#^%s #i", preg_quote('Ticket org')), 'Org', $label);
				
				switch($key) {
					case 'ticket_org__label':
						$label = 'Org';
						break;
						
					case 'worker__label':
						$label = 'Worker';
						break;
						
					case 'ticket_status':
						$label = 'Status';
						break;
				}
				
				$label = mb_convert_case($label, MB_CASE_LOWER);
				$label[0] = mb_convert_case($label[0], MB_CASE_UPPER);
			});
		}
		
		asort($labels);
		
		return $labels;
	}
	
	// [TODO] Interface
	function getDefaultProperties() {
		return array(
			'ticket__label',
			'ticket_status',
			'sender__label',
			'is_outgoing',
			'worker__label',
			'ticket_org__label',
			'created',
		);
	}
	
	function getContext($message, &$token_labels, &$token_values, $prefix=null) {
		$is_nested = $prefix ? true : false;
		
		if(is_null($prefix))
			$prefix = 'Message:';
		
		$translate = DevblocksPlatform::getTranslationService();

		// Polymorph
		if(is_numeric($message)) {
			$message = DAO_Message::get($message);
		} elseif($message instanceof Model_Message) {
			// It's what we want already.
		} else {
			$message = null;
		}
		/* @var $message Model_Message */
		
		// Token labels
		$token_labels = array(
			'_label' => $prefix,
			'content' => $prefix.$translate->_('common.content'),
			'created' => $prefix.$translate->_('common.created'),
			'is_broadcast' => $prefix.$translate->_('message.is_broadcast'),
			'is_outgoing' => $prefix.$translate->_('message.is_outgoing'),
			'response_time' => $prefix.$translate->_('message.response_time'),
			'storage_size' => $prefix.$translate->_('message.storage_size'),
			'record_url' => $prefix.$translate->_('common.url.record'),
		);
		
		// Token types
		$token_types = array(
			'_label' => 'context_url',
			'content' => Model_CustomField::TYPE_MULTI_LINE,
			'created' => Model_CustomField::TYPE_DATE,
			'is_broadcast' => Model_CustomField::TYPE_CHECKBOX,
			'is_outgoing' => Model_CustomField::TYPE_CHECKBOX,
			'response_time' => 'time_secs',
			'storage_size' => 'size_bytes',
			'record_url' => Model_CustomField::TYPE_URL,
		);
		
		// Token values
		$token_values = array();
		
		$token_values['_context'] = CerberusContexts::CONTEXT_MESSAGE;
		$token_values['_types'] = $token_types;
		
		// Message token values
		if($message) {
			// [TODO] Cache these in a request registry
			$sender = DAO_Address::get($message->address_id);
			$ticket = DAO_Ticket::get($message->ticket_id);
			
			$token_values['_loaded'] = true;
			$token_values['_label'] = sprintf("%s wrote on [%s] %s", $sender->email, $ticket->mask, $ticket->subject);
			$token_values['created'] = $message->created_date;
			$token_values['id'] = $message->id;
			$token_values['is_broadcast'] = $message->is_broadcast;
			$token_values['is_outgoing'] = $message->is_outgoing;
			$token_values['response_time'] = $message->response_time;
			$token_values['storage_size'] = $message->storage_size;
			$token_values['ticket_id'] = $message->ticket_id;
			$token_values['worker_id'] = $message->worker_id;
			
			// Sender
			@$address_id = $message->address_id;
			$token_values['sender_id'] = $address_id;
			
			// URL
			$url_writer = DevblocksPlatform::getUrlService();
			$token_values['record_url'] = $url_writer->writeNoProxy(sprintf("c=profiles&type=ticket&id=%d/message/%d", $message->ticket_id, $message->id), true);
		}

		// Ticket (only if message is the top of the context chain)
		if(!$is_nested) {
			$merge_token_labels = array();
			$merge_token_values = array();
			CerberusContexts::getContext(CerberusContexts::CONTEXT_TICKET, null, $merge_token_labels, $merge_token_values, '', true);
	
			CerberusContexts::merge(
				'ticket_',
				'Ticket:',
				$merge_token_labels,
				$merge_token_values,
				$token_labels,
				$token_values
			);
		}
		
		// Sender
		$merge_token_labels = array();
		$merge_token_values = array();
		CerberusContexts::getContext(CerberusContexts::CONTEXT_ADDRESS, null, $merge_token_labels, $merge_token_values, '', true);

		CerberusContexts::merge(
			'sender_',
			'Message:Sender:',
			$merge_token_labels,
			$merge_token_values,
			$token_labels,
			$token_values
		);
		
		// Sender Worker
		$merge_token_labels = array();
		$merge_token_values = array();
		CerberusContexts::getContext(CerberusContexts::CONTEXT_WORKER, null, $merge_token_labels, $merge_token_values, '', true);

		CerberusContexts::merge(
			'worker_',
			'Message:Sender:Worker',
			$merge_token_labels,
			$merge_token_values,
			$token_labels,
			$token_values
		);
		
		return true;
	}
	
	function lazyLoadContextValues($token, $dictionary) {
		if(!isset($dictionary['id']))
			return;
		
		$context = CerberusContexts::CONTEXT_MESSAGE;
		$context_id = $dictionary['id'];
		
		@$is_loaded = $dictionary['_loaded'];
		$values = array();
		
		if(!$is_loaded) {
			$labels = array();
			CerberusContexts::getContext($context, $context_id, $labels, $values, null, true);
		}
		
		switch($token) {
			case 'content':
				$values['content'] = Storage_MessageContent::get($context_id);
				break;
				
			default:
				if(substr($token,0,7) == 'custom_') {
					$fields = $this->_lazyLoadCustomFields($context, $context_id);
					$values = array_merge($values, $fields);
				}
				break;
		}
		
		return $values;
	}

	function getChooserView($view_id=null) {
		$active_worker = CerberusApplication::getActiveWorker();

		if(empty($view_id))
			$view_id = 'chooser_'.str_replace('.','_',$this->id).time().mt_rand(0,9999);
		
		// View
		$defaults = new C4_AbstractViewModel();
		$defaults->id = $view_id;
		$defaults->is_ephemeral = true;
		$defaults->class_name = $this->getViewClass();
		$view = C4_AbstractViewLoader::getView($view_id, $defaults);
		$view->name = 'Messages';
//		$view->view_columns = array(
//			SearchFields_Message::UPDATED_DATE,
//		);
		$view->addParams(array(
//			SearchFields_Task::IS_COMPLETED => new DevblocksSearchCriteria(SearchFields_Task::IS_COMPLETED,'=',0),
			//SearchFields_Task::VIRTUAL_WATCHERS => new DevblocksSearchCriteria(SearchFields_Task::VIRTUAL_WATCHERS,'in',array($active_worker->id)),
		), true);
		
		$view->addParamsRequired(array(
			SearchFields_Message::TICKET_GROUP_ID => new DevblocksSearchCriteria(SearchFields_Message::TICKET_GROUP_ID,'in',array_keys($active_worker->getMemberships())),
		), true);
		
		$view->renderSortBy = SearchFields_Message::CREATED_DATE;
		$view->renderSortAsc = false;
		$view->renderLimit = 10;
		$view->renderTemplate = 'contextlinks_chooser';
		C4_AbstractViewLoader::setView($view_id, $view);
		return $view;
	}
	
	function getView($context=null, $context_id=null, $options=array()) {
		$view_id = str_replace('.','_',$this->id);
		
		$defaults = new C4_AbstractViewModel();
		$defaults->id = $view_id;
		$defaults->class_name = $this->getViewClass();
		$view = C4_AbstractViewLoader::getView($view_id, $defaults);
		$view->name = 'Messages';
		
		$params_req = array();
		
		if(!empty($context) && !empty($context_id)) {
			$params_req = array(
				new DevblocksSearchCriteria(SearchFields_Message::CONTEXT_LINK,'=',$context),
				new DevblocksSearchCriteria(SearchFields_Message::CONTEXT_LINK_ID,'=',$context_id),
			);
		}
		
		$view->addParamsRequired($params_req, true);
		
		$view->renderTemplate = 'context';
		C4_AbstractViewLoader::setView($view_id, $view);
		return $view;
	}
	
};