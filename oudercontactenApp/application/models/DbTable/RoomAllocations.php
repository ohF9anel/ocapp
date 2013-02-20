<?php
/**
 * Db class for roomallocations table 
 */
class Application_Model_DbTable_RoomAllocations extends Zend_Db_Table_Abstract
{
    protected $roomId, $staffId, $conferenceDayId;
    protected $_name = 'roomallocations';


    public function __construct($roomId = null, $staffId = null, $conferenceDayId = null, $config = array()) {
        parent::__construct($config);
        $this->roomId = $roomId;
        $this->staffId = $staffId;
        $this->conferenceDayId = $conferenceDayId;
    }
    
    
    /**
     * Function deletes roomallocation object from database
     * @throws Exception Er150 Null in non optional field
     */
    public function deleteRoomAllocation() {
        if ($this->conferenceDayId == null || $this->staffId == null) {
            throw new Exception('Er150 Null in non optional field');
        }
        
        $this->delete('conferenceDayId = ' . $this->_db->quote($this->conferenceDayId, 'INT') . ' AND staffId = ' . $this->_db->quote($this->staffId, 'INT'));
    }
    
    
    /**
     * Function updates/saves roomallocation object to database if all required fields have a value
     * @throws Exception Er150 Null in non optional field
     */
    public function save() {
        if ($this->roomId == null || $this->staffId == null || $this->conferenceDayId == null) {
            throw new Exception('Er150 Null in non optional field');
        }
        
        $select = $this->select();
        $select->from('roomallocations', 'count(*) as amount')
                ->where('staffId = ? AND conferenceDayId = ?')
                ->bind(array($this->staffId, $this->conferenceDayId));
        $result = $this->fetchRow($select);

        if ($result['amount'] == 0) {
            $this->insert(array('roomId' => $this->roomId, 'conferenceDayId' => $this->conferenceDayId, 'staffId' => $this->staffId));
        } else {
            $this->update(array('roomId' => $this->roomId, 'conferenceDayId' => $this->conferenceDayId, 'staffId' => $this->staffId), 'staffId = ' . $this->_db->quote($this->staffId, 'INT') . ' AND conferenceDayId = ' . $this->_db->quote($this->conferenceDayId, 'INT'));
        }
    }
    
    
    
    /**
     * Function deletes all data from roomallocations table
     */
    public static function deleteAll() {
        $roomallocation = new Application_Model_DbTable_RoomAllocations();
        $roomallocation->delete('');
    }
    
    
    /**
     * Function deletes all data from roomallocations table for a specified conference
     * @param int $conferenceId conferenceId to identify conference
     */
    public static function deleteConference($conferenceId) {
        $conferenceId = (int) $conferenceId;
        $roomallocation = new Application_Model_DbTable_RoomAllocations();
        
        $days = Application_Model_DbTable_ConferenceDays::getConferenceDaysOfConference($conferenceId);
        foreach($days as $day) {
            $roomallocation->delete('conferenceDayId = ' . $roomallocation->_db->quote($day->getConferenceDayId(), 'INT'));
        }
    }
}