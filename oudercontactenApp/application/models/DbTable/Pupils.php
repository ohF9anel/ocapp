<?php
/**
 * Db class for pupils table
 */
class Application_Model_DbTable_Pupils extends Zend_Db_Table_Abstract
{
    protected $pupilId, $name, $firstname, $groupId;
    protected $_name = 'pupils';

    
    
    public function __construct($pupilId = null, $name = null, $firstname = null, $groupId = null, $config = array()) {
        parent::__construct($config);
        
        $this->pupilId = $pupilId;
        $this->name = $name;
        $this->firstname = $firstname;
        $this->groupId = $groupId;
    }
    


    /*
     * GETTERS & SETTERS
     */
    public function getFirstname() {
        return $this->firstname;
    }
    
    public function getGroupId() {
        return $this->groupId;
    }
    
    public function getName() {
        return $this->name;
    }
    
    public function getPupilId() {
        return $this->pupilId;
    }
    
    
    
    /**
     * Function returns all appointments from this pupil
     * @return \Application_Model_DbTable_Appointments
     */
    public function getAppointments() {
        $select = $this->select();
        $select->from('appointments', '*')
                ->where('pupilId = ? AND temporary IS NULL')
                ->bind(array($this->pupilId))
                ->setIntegrityCheck(false);
        $result = $this->fetchAll($select);
        
        $appointments = array();
        foreach ($result as $row) {
            $appointments[] = new Application_Model_DbTable_Appointments($row['appointmentId'], $row['staffId'], $row['pupilId'], $row['parentId'], $row['courseId'], $row['appointment'], $row['conferenceDayId'], $row['conferenceId'], $row['selfPlanned'], $row['confirmed']);
        }
        return $appointments;
    }
    
    
    
    /**
     * Function counts the amount of records in this table
     * @return int amount of records
     */
    public static function countEntries() {
        $pupil = new Application_Model_DbTable_Pupils();
        $select = $pupil->select();
        $select->from('pupils', 'count(*) As amount')
                ->setIntegrityCheck(false);
        $result = $pupil->fetchRow($select);
        
        return $result['amount'];
    }
    
    
    /**
     * Function deletes all data from pupils table
     */
    public static function deleteAll() {
        $pupil = new Application_Model_DbTable_Pupils();
        $pupil->delete('');
    }
    
    
    /**
     * Function returns pupil object based on it's id
     * @param int $id pupilId to identify pupil
     * @return \Application_Model_DbTable_Pupils
     * @throws Exception Er103 Unexisting pupil
     */
    public static function getPupilById ($id) {
        $id = (int) $id;
        
        $pupil = new Application_Model_DbTable_Pupils();
        $select = $pupil->select();
        $select->from('pupils', array('pupilId', 'name', 'firstname', 'groupId'))
                ->where('pupilId = ?')
                ->bind(array($id));
        $result = $pupil->_fetch($select);
        
        //check if pupil exists
        if (sizeof($result) != 1) {
            throw new Exception('Er103 Unexisting pupil');
        }
        
        $pupil->pupilId = $result[0]['pupilId'];
        $pupil->name = $result[0]['name'];
        $pupil->firstname = $result[0]['firstname'];
        $pupil->groupId = $result[0]['groupId'];
        return $pupil;
    }
    
    
    /**
     * Function updates pupils table based on a data array
     * @param array $data array of arrays with following structure array(('uuid', 'name', 'firstname', 'groupName))
     * @throws Exception Er151 Update failed
     */
    public static function updatePupils($data) {
        $pupils = new Application_Model_DbTable_Pupils();
        
        $succes = true;
        $information = '';
        $pupils->_db->beginTransaction();
        try {
            foreach ($data as $pupil) {
                $information = $pupil['uuid'];
                
                //determine groupId, based on group name
                $select1 = $pupils->select();
                $select1->from('groups', 'groupId')
                        ->where('name = ?')
                        ->bind(array($pupil['groupName']))
                        ->setIntegrityCheck(false);
                $result1 = $pupils->fetchRow($select1);
                $groupId = $result1['groupId'];
                
                //determine if update or insert has to be executed
                $select2 = $pupils->select();
                $select2->from('pupils', 'count(*) AS amount')
                        ->where('uuid = ?')
                        ->bind(array($pupil['uuid']));
                $result2 = $pupils->fetchRow($select2);
                if ($result2['amount'] == 0) {      //insert
                    $pupils->insert(array('uuid' => $pupil['uuid'], 'name' => $pupil['name'], 'firstname' => $pupil['firstname'], 'groupId' => $groupId));
                } else {        //update
                    $pupils->update(array('name' => $pupil['name'], 'firstname' => $pupil['firstname'], 'groupId' => $groupId), 'uuid = ' . $pupils->_db->quote($pupil['uuid'], 'VARCHAR'));
                }
            }
        } catch (Exception $e) {
            $succes = false;
        }
        
        if ($succes) {
            $pupils->_db->commit();
        } else {
            $pupils->_db->rollBack();
            throw new Exception('update of ' . $information . ' failed', 151);
        }
    }
}