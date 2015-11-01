<?php

class sabrecube_principals
    extends Sabre\DAVACL\PrincipalBackend\AbstractBackend {

    protected function queryPrincipals($username = null) {
        $query = ('SELECT u.`username`, i.`name`, i.`email` FROM ' .
                  $this->db->table_name('users', true) . ' AS u ' .
                  'LEFT JOIN ' .
                  $this->db->table_name('identities', true) . ' AS i ' .
                  'ON (u.`user_id` = i.`user_id`)');

        if($username !== null)
            $query = $query . ' WHERE (u.`username` = ?)';

        return $this->db->query($query, $username);
    }

    protected function constructPrincipal($result) {
        return array(
            'uri' => 'principals/' . $result['username'],
            '{DAV:}displayname' => $result['name'],
            '{http://sabredav.org/ns}email-address' => $result['email'],
            '{http://sabredav.org/ns}vcard-url' =>
            'addressbooks/' . $result['username'] . '/vcard.vcf',
        );
    }

    public function __construct($rcmail) {
        $this->rc = $rcmail;
        $this->db = $this->rc->get_dbh();
    }

    public function getPrincipalsByPrefix($prefix) {
        if($prefix != 'principals')
            return array();

        $principals = [];
        $result = $this->queryPrincipals();

        while($row = $result->fetch()) {
            $principals[] = $this->constructPrincipal($row);
        }

        return $principals;
    }

    public function getPrincipalByPath($path) {
        if(strpos($path, 'principals/') !== 0)
            return;

        $result = $this->queryPrincipals(substr($path, 11))->fetch();
        if($result !== FALSE)
            return $this->constructPrincipal($result);
    }

    public function searchPrincipals($prefixPath, array $searchProperties,
                                     $test = 'allof') {
        throw new Exception('searchPrincipals not implemented');
    }

    public function updatePrincipal($path, \Sabre\DAV\PropPatch $propPatch) {
        /* empty */
    }

    /* Roundcube has no notion of user group membership. So all these
       three methods just return empty and do nothing. */
    public function getGroupMemberSet($path) {
        return array();
    }

    public function getGroupMembership($path) {
        return array();
    }

    public function setGroupMemberSet($path, array $members) {
        /* empty */
    }
}

?>
