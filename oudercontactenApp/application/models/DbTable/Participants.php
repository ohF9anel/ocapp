<?php

class Application_Model_DbTable_Participants extends Zend_Db_Table_Abstract
{
    protected $conferenceId, $groupId;
    protected $_name = 'participants';


    public function __construct($conferenceId = null, $groupId = null, $config = array()) {
        parent::__construct($config);
        $this->conferenceId = $conferenceId;
        $this->groupId = $groupId;
    }
    
    
    
    /*
     * GETTERS & SETTERS
     */
    public function getConferenceId() {
        return $this->conferenceId;
    }
    
    public function getGroupId() {
        return $this->groupId;
    }
    
    
    
    /**
     * Function deletes all data from participants table
     */
    public static function deleteAll() {
        $participant = new Application_Model_DbTable_Participants();
        $participant->delete('');
    }
    
    
    /**
     * Function deletes all data from participants table based on a specified conference
     * @param int $conferenceId conferenceId to identify conference
     */
    public static function deleteConference($conferenceId) {
        $conferenceId = (int) $conferenceId;
        $participant = new Application_Model_DbTable_Participants();
        
        $participant->delete('conferenceId = ' . $participant->_db->quote($conferenceId, 'INT'));
    }
    
    
    /**
     * Function saves/updates participants object to database if all required fields have a value
     */
    public function save() {
        if ($this->conferenceId == null || $this->groupId == null) {
            throw new Exception('Er150 Null in non optional field');
        }
        
        $select = $this->select();
        $select->from('participants', 'count(*) AS amount')
                ->where('conferenceId = ? AND groupId = ?')
                ->bind(array($this->conferenceId, $this->groupId));
        $result = $this->fetchRow($select);
        
        if ($result['amount'] == 0) {
            $this->insert(array('conferenceId' => $this->conferenceId, 'groupId' => $this->groupId));
        }
    }
}