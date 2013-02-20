<?php
/**
 * Db class for rooms table 
 */
class Application_Model_DbTable_Rooms extends Zend_Db_Table_Abstract
{
    protected $roomId, $name;
    protected $_name = 'rooms';

    public function __construct($roomId = null, $name = null, $config = array()) {
        parent::__construct($config);
        $this->roomId = $roomId;
        $this->name = $name;
    }
    
    
    
    /*
     * GETTERS & SETTERS
     */
    public function getName() {
        return $this->name;
    }
    
    public function getRoomId() {
        return $this->roomId;
    }
    
    
    
    /**
     * Function counts the amount of records in this table
     * @return int amount of records
     */
    public static function countEntries() {
        $room = new Application_Model_DbTable_Rooms();
        $select = $room->select();
        $select->from('rooms', 'count(*) As amount')
                ->setIntegrityCheck(false);
        $result = $room->fetchRow($select);
        
        return $result['amount'];
    }
    
    
    /**
     * Function deletes all data from rooms table
     */
    public static function deleteAll() {
        $room = new Application_Model_DbTable_Rooms();
        $room->delete('');
    }
    
    
    /**
     * Function returns array of all available room for a specified conferenceDay
     * @param int $conferenceDayId conferenceDayId to identify conferenceDay
     * @param int $staffId staffId to identify staffmember
     * @return array of \Application_Model_DbTable_Rooms 
     */
    public static function getAllAvailableRooms($conferenceDayId, $staffId) {
        $conferenceDayId = (int) $conferenceDayId;
        $staffId = (int) $staffId;
        
        $room = new Application_Model_DbTable_Rooms();
        $select = $room->select();
        $select->from('rooms', array('roomId', 'name'))
                ->joinLeft('roomallocations', 'rooms.roomId = roomallocations.roomId AND conferenceDayId = ' . $room->_db->quote($conferenceDayId, 'INT'), array())
                ->where('conferenceDayId IS NULL OR staffId = ?')
                ->bind(array($staffId))
                ->setIntegrityCheck(false);
        $results = $room->fetchAll($select);
        
        $rooms = array();
        foreach($results as $result) {
            $rooms[] = new Application_Model_DbTable_Rooms($result['roomId'], $result['name']);
        }

        return $rooms;
    }
    
    
    /**
     * Function returns room name based on it's id
     * @param int $roomId roomId to identify room
     * @return string room name 
     */
    public static function getNameByRoomId($roomId) {
        $roomId = (int) $roomId;
        $room = new Application_Model_DbTable_Rooms();
        
        $select = $room->select();
        $select->from('rooms', 'name')
                ->where('roomId = ?')
                ->bind(array($roomId));
        $result = $room->fetchRow($select);
        
        return (sizeof($result) == 0 ? '' : $result['name']);
    }
    
    
    /**
     * Function renews rooms table based on a data array
     * @param array $rooms array of arrays with following structure array(('name'))
     * @throws Exception Er151 Update failed
     */
    public static function UpdateRooms($rooms) {
        $room = new Application_Model_DbTable_Rooms();
        
        $succes = true;
        $information = '';
        $room->_db->beginTransaction();
        try {
            foreach ($rooms as $roomData) {
                $information = $roomData;
                
                $select = $room->select();
                $select->from('rooms', 'count(*) As amount')
                        ->where('name = ?')
                        ->bind(array($roomData));
                $result = $room->fetchRow($select);
                
                if ($result['amount'] == 0) {
                    $room->insert(array('name' => $roomData));
                }
            }
        } catch (Exception $e) {
            $succes = false;
        }
        
        if ($succes) {
            $room->_db->commit();
        } else {
            $room->_db->rollBack();
            throw new Exception('update of ' . $information . ' failed', 151);
        }
    }
}