<?php

class sabrecube_carddav
    extends Sabre\CardDAV\Backend\AbstractBackend
    implements Sabre\CardDAV\Backend\SyncSupport {

    protected $adb_uri = 'contacts.vcf';
    protected $empty_sync = '0000-00-00 00:00:00/0';

    protected function getSynctoken($addressBookId) {
        $changed = $this->db->query(
            'SELECT c.`changed` FROM ' .
            $this->db->table_name('users', true) . ' AS u ' .
            'LEFT JOIN ' .
            $this->db->table_name('contacts', true) . ' AS c '.
            'ON u.`user_id` = c.`user_id` ' .
            'WHERE u.`user_id` = ? ' .
            'ORDER BY c.`changed` DESC '.
            'LIMIT 1',
            $addressBookId)->fetch();

        $id = $this->db->query(
            'SELECT c.`contact_id` FROM ' .
            $this->db->table_name('users', true) . ' AS u ' .
            'LEFT JOIN ' .
            $this->db->table_name('contacts', true) . ' AS c '.
            'ON u.`user_id` = c.`user_id` ' .
            'WHERE u.`user_id` = ? ' .
            'ORDER BY c.`contact_id` DESC '.
            'LIMIT 1',
            $addressBookId)->fetch();


        if($changed === FALSE || $id == FALSE)
            return $this->empty_sync;

        return $changed[0] . '/' . $id[0];
    }

    protected function cardUri2Id($cardUri) {
        $result = $this->db->query(
            'SELECT m.`contact_id` FROM ' .
            $this->db->table_name('sabrecube_contacts_map', true) . ' AS m ' .
            'WHERE m.`uri` = ?',
            $cardUri)->fetch();

        if($result)
            return $result[0];

        $id = explode('.vcf', $cardUri, 2);
        if($id[1] !== '')
            return null;

        return $id[0];
    }

    protected function cardId2Uri($cardId) {
        $result = $this->db->query(
            'SELECT m.`uri` FROM ' .
            $this->db->table_name('sabrecube_contacts_map', true) . ' AS m ' .
            'WHERE m.`contact_id` = ?',
            $cardId)->fetch();

        if($result)
            return $result[0];
        else
            return $cardId . '.vcf';
    }

    protected function initAddressBook() {
        if(!$this->ab)
            $this->ab = $this->rc->get_address_book('sql');
    }

    public function __construct($rcmail) {
        $this->rc = $rcmail;

        if(strtolower($this->rc->config->get('address_book_type')) != 'sql')
            throw new Exception('Only supported with SQL addressbook.');

        $this->db = $this->rc->get_dbh();
        $this->ab = null;
    }

    public function getAddressBooksForUser($principalUri) {
        if($principalUri != 'principals/' . $this->rc->get_user_name())
            return array();

        $uid = $this->rc->get_user_id();
        $synctoken = $this->getSynctoken($uid);

        return array(
            array(
                'id' => $uid,
                'uri' => $this->adb_uri,
                'principaluri' => $principalUri,
                '{DAV:}displayname' => $this->rc->gettext('personaladrbook'),
                '{http://calendarserver.org/ns/}getctag' => $synctoken,
                '{http://sabredav.org/ns}sync-token' => $synctoken,
            ),
        );
    }

    public function getChangesForAddressBook($addressBookId, $syncToken,
                                             $syncLevel, $limit = null) {
        $changes = array(
            'syncToken' => $this->getSynctoken($addressBookId),
            'added' => array(),
            'modified' => array(),
            'deleted' => array(),
        );

        if(!$syncToken)
            $token = $this->empty_sync;
        else
            $token = $syncToken;

        $token = explode('/', $token, 2);

        function uri($uri, $id) {
            return $uri ? $uri : $id . '.vcf';
        }

        // ADDED
        $result = $this->db->query(
            'SELECT c.`contact_id`, m.`uri` FROM ' .
            $this->db->table_name('users', true) . ' AS u ' .
            'LEFT JOIN ' .
            $this->db->table_name('contacts', true) . ' AS c '.
            'ON u.`user_id` = c.`user_id` ' .
            'LEFT JOIN ' .
            $this->db->table_name('sabrecube_contacts_map', true) . ' AS m ' .
            'ON c.`contact_id` = m.`contact_id` ' .
            'WHERE u.`user_id` = ? AND c.`del` <> 1 AND c.`contact_id` > ?',
            $addressBookId, $token[1]
        );

        while($row = $result->fetch()) {
            $changes['added'][] = uri($row['uri'], $row['contact_id']);
        }

        if($syncToken) {
            // DELETED
            $result = $this->db->query(
                'SELECT c.`contact_id`, m.`uri` FROM ' .
                $this->db->table_name('users', true) . ' AS u ' .
                'LEFT JOIN ' .
                $this->db->table_name('contacts', true) . ' AS c '.
                'ON u.`user_id` = c.`user_id` ' .
                'LEFT JOIN ' .
                $this->db->table_name('sabrecube_contacts_map', true) . ' AS m ' .
                'ON c.`contact_id` = m.`contact_id` ' .
                'WHERE u.`user_id` = ? AND c.`del` = 1 AND c.`changed` > ?',
                $addressBookId, $token[0]
            );

            while($row = $result->fetch()) {
                $changes['deleted'][] = uri($row['uri'], $row['contact_id']);
            }

            // MODIFIED
            $result = $this->db->query(
                'SELECT c.`contact_id`, m.`uri` FROM ' .
                $this->db->table_name('users', true) . ' AS u ' .
                'LEFT JOIN ' .
                $this->db->table_name('contacts', true) . ' AS c '.
                'ON u.`user_id` = c.`user_id` ' .
                'LEFT JOIN ' .
                $this->db->table_name('sabrecube_contacts_map', true) . ' AS m ' .
                'ON c.`contact_id` = m.`contact_id` ' .
                'WHERE u.`user_id` = ? AND c.`del` <> 1 ' .
                'AND c.`changed` > ? AND c.`contact_id` <= ?',
                $addressBookId, $token[0], $token[1]
            );

            while($row = $result->fetch()) {
                $changes['modified'][] = uri($row['uri'], $row['contact_id']);
            }
        }

        return $changes;
    }

    public function updateAddressBook($addressBookId,
                                      \Sabre\DAV\PropPatch $propPatch) {
        /* empty */
    }

    public function createAddressBook($principalUri, $url, array $properties) {
        /* empty */
    }

    public function deleteAddressBook($addressBookId) {
        /* empty */
    }

    public function getCards($addressBookId) {
        $this->initAddressBook();
        $cards = array();

        foreach($this->ab->list_records(array('changed', 'vcard'), 0, true)
                as $contact) {
            $cards[] = array(
                'id' => $contact['ID'],
                'uri' => $this->cardId2Uri($contact['ID']),
                'lastmodified' => new DateTime($contact['changed']),
                'etag' => '"' . $contact['changed'] . '"',
                'size' => strlen($contact['vcard']),
            );
        }

        return $cards;
    }

    public function getCard($addressBookId, $cardUri) {
        $this->initAddressBook();
        $card = $this->ab->get_record($this->cardUri2Id($cardUri), true);

        if(!$card)
            return;

        return array(
            'id' => $card['ID'],
            'uri' => $cardUri,
            'carddata' => $card['vcard'],
            'lastmodified' => new DateTime($card['changed']),
            'etag' => '"' . $card['changed'] . '"',
            'size' => strlen($card['vcard']),
        );
    }

    public function createCard($addressBookId, $cardUri, $cardData) {
        $this->initAddressBook();
        $vcard = new rcube_vcard($cardData);
        $id = $this->ab->insert($vcard->get_assoc());

        $this->db->query(
            'INSERT INTO ' .
            $this->db->table_name('sabrecube_contacts_map', true) . ' ' .
            'VALUES (?, ?)',
            $id, $cardUri);
    }

    public function updateCard($addressBookId, $cardUri, $cardData) {
        $this->initAddressBook();
        $vcard = new rcube_vcard($cardData);
        $this->ab->update($this->cardUri2Id($cardUri), $vcard->get_assoc());
    }

    function deleteCard($addressBookId, $cardUri) {
        $this->initAddressBook();
        $this->ab->delete($this->cardUri2Id($cardUri));
    }
}

?>
