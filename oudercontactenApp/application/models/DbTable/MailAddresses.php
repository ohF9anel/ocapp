<?php

class Application_Model_DbTable_MailAddresses extends Zend_Db_Table_Abstract
{
    protected $mailAddressId, $parentOwnerId, $staffOwnerId, $address, $confirmed, $errors;
    protected $_name = 'mailaddresses';

    
    
    public function __construct($mailAddressId = null, $parentOwnerId = null, $staffOwnerId = null, $address = null, $confirmed = false, $errors = 0, $config = array()) {
        parent::__construct($config);
        $this->mailAddressId = $mailAddressId;
        $this->parentOwnerId = $parentOwnerId;
        $this->staffOwnerId = $staffOwnerId;
        $this->address = $address;
        $this->confirmed = $confirmed;
        $this->errors = $errors;
    }
    
    
    
    public function getMailAddressId() {
        return $this->mailAddressId;
    }
    
    public function getParentOwnerId() {
        return $this->parentOwnerId;
    }
    
    public function getStaffOwnerId() {
        return $this->staffOwnerId;
    }
    
    public function getAddress() {
        return $this->address;
    }
    
    
    
    /**
     * Function returns all mailAddresses of a parent, based on it's id
     * @param type $id
     * @return \Application_Model_DbTable_MailAddresses 
     */
    public static function getMailAddressesByParentId($id) {
        $id = (int) $id;
        
        $mailAddress = new Application_Model_DbTable_MailAddresses();
        $result = $mailAddress->fetchAll(sprintf('parentOwnerId = %d', $id));
        
        $mailAddresses = array();
        foreach ($result as $row) {
            $mailAddresses[] = new Application_Model_DbTable_MailAddresses($row['mailAddressId'], $row['parentOwnerId'], $row['staffOwnerId'], $row['address'], $row['confirmed'], $row['errors']);
        }
        return $mailAddresses;
    }

    
    
    /**
     * Function saves object information to database 
     */
    public function saveToDatabase() {
        $select = $this->select();
        $select->from('mailaddresses', 'count(*) as amount')
                ->where(sprintf('mailAddressId = %d', $this->mailAddressId));
        $result = $this->_fetch($select);
        
        //check on information
        if ($this->address == null) {
            throw new Exception('Er150 Null in non optional field');
        }

        if ($result[0]['amount'] == 0) {           
            $this->insert(array('parentOwnerId' => $this->parentOwnerId, 'staffOwnerId' => $this->staffOwnerId, 'address' => $this->address));
        }
        else {
            $this->update(array('parentOwnerId' => $this->parentId, 'staffOwnerId' => $this->staffOwnerId, 'address' => $this->address, 'confirmed' => false, 'errors' => 0), sprintf('mailAddressId = %d', $this->mailAddressId));
        }
    }
    
    
    
    /**
     * Function to remove object information from database 
     */
    public function deleteFromDatabase() {
        $select = $this->select();
        $select->from('mailaddresses', 'count(*) as amount')
                ->where(sprintf('mailAddressId = %d', $this->mailAddressId));
        $result = $this->_fetch($select);
        
        if ($result[0]['amount'] != 0) {
            $this->delete(sprintf('mailAddressId = %d', $this->mailAddressId));
        }
    }
}

