<?php

class Sidebox_model extends CI_Model
{
    public function getSideboxes()
    {
        $query = $this->db->query("SELECT * FROM sideboxes ORDER BY `order` ASC");

        return $query->getResultArray();
    }

    public function add($data)
    {
        if (isset($data["content"])) {
            unset($data["content"]);
        }
        $data['rank_needed'] = $this->cms_model->getAnyOldRank();

        $this->db->table('sideboxes')->insert($data);

        $query = $this->db->query("SELECT id FROM sideboxes ORDER BY id DESC LIMIT 1");
        $row = $query->getResultArray();

        $this->db->query("UPDATE sideboxes SET `order`=? WHERE id=?", array($row[0]['id'], $row[0]['id']));

        return $row[0]['id'];
    }

    public function edit($id, $data)
    {
        if (isset($data["content"])) {
            unset($data["content"]);
        }

        $this->db->table('sideboxes')->where('id', $id)->update($data);
    }

    public function delete($id)
    {
        $this->deletePermission($id);

        $this->db->query("DELETE FROM sideboxes WHERE id = ?", [$id]);
    }

    public function setPermission($id)
    {
        $this->db->query("UPDATE sideboxes SET `permission` = ? WHERE id = ?", [$id, $id]);
    }

    public function deletePermission($id)
    {
        $this->db->query("UPDATE sideboxes SET `permission` = '' WHERE id = ?", [$id]);
        $this->db->query("DELETE FROM acl_group_roles WHERE module = '--SIDEBOX--' AND role_name = ?", [$id]);
    }

    public function hasPermission($id)
    {
        $query = $this->db->query("SELECT `permission` FROM sideboxes WHERE id = ?", [$id]);

        if ($query->getNumRows() > 0) {
            $result = $query->getResultArray();

            return $result[0]['permission'];
        } else {
            return false;
        }
    }

    public function addCustom($text)
    {
        $query = $this->db->query("SELECT id FROM sideboxes ORDER BY id DESC LIMIT 1");

        if ($query->getNumRows() > 0) {
            $row = $query->getResultArray();

            $data = array(
                'sidebox_id' => $row[0]['id'],
                'content' => $text
            );

            $this->db->table('sideboxes_custom')->insert($data);
        }
    }

    public function editCustom($id, $text)
    {
        if ($this->db->query("SELECT sidebox_id FROM sideboxes_custom WHERE sidebox_id=?", array($id))->getNumRows()) {
            $this->db->table('sideboxes_custom')->where('sidebox_id', $id)->update(['content' => $text]);
        } else {
            $data = array(
                'sidebox_id' => $id,
                'content' => $text
            );

            $this->db->table('sideboxes_custom')->insert( $data);
        }
    }

    public function getSidebox($id)
    {
        $query = $this->db->query("SELECT * FROM sideboxes WHERE id=? LIMIT 1", array($id));

        if ($query->getNumRows() > 0) {
            $row = $query->getResultArray();

            return $row[0];
        } else {
            return false;
        }
    }

    public function getCustomText($id)
    {
        $query = $this->db->query("SELECT content FROM sideboxes_custom WHERE sidebox_id=? LIMIT 1", array($id));

        if ($query->getNumRows() > 0) {
            $row = $query->getResultArray();

            return $row[0]['content'];
        } else {
            return "";
        }
    }

    public function getOrder($id)
    {
        $query = $this->db->query("SELECT `order` FROM sideboxes WHERE `id`=? LIMIT 1", array($id));

        if ($query->getNumRows() > 0) {
            $row = $query->getResultArray();

            return $row[0]['order'];
        } else {
            return false;
        }
    }

    public function getPreviousOrder($order)
    {
        $query = $this->db->query("SELECT `order`, id FROM sideboxes WHERE `order` < ? ORDER BY `order` DESC LIMIT 1", array($order));

        if ($query->getNumRows() > 0) {
            $row = $query->getResultArray();

            return $row[0];
        } else {
            return false;
        }
    }

    public function getNextOrder($order)
    {
        $query = $this->db->query("SELECT `order`, id FROM sideboxes WHERE `order` > ? ORDER BY `order` ASC LIMIT 1", array($order));

        if ($query->getNumRows() > 0) {
            $row = $query->getResultArray();

            return $row[0];
        } else {
            return false;
        }
    }

    public function setOrder($id, $order)
    {
        $this->db->query("UPDATE sideboxes SET `order`=? WHERE `id`=? LIMIT 1", array($order, $id));
    }
}
