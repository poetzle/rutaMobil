<?php

class Empleado extends Persona {
    /*
      Determines if a given person_id is an employee
     */

    function exists($persona_id) {
        $this->db->from('empleado');
        $this->db->join('persona', 'persona.persona_id = empleado.persona_id');
        $this->db->where('empleado.persona_id', $persona_id);
        $this->db->where('deleted', 0);
        $query = $this->db->get();

        return ($query->num_rows() == 1);
    }

    /*
      Returns all the employees
     */

    function getall() {
        $this->db->from('empleado');
        $this->db->where('deleted', 0);
        $this->db->join('persona', 'persona.persona_id=empleado.persona_id');
        $this->db->order_by("apellido", "asc");
        return $this->db->get();
    }

    function get_all($num = 0, $offset = 0, $where, $order = '') {
        $this->db->select('persona.*, persona.persona_id as empleado_id FROM empleado, persona where persona.persona_id = empleado.persona_id and empleado.deleted = 0 ' .
                $where);
        $this->db->order_by($order);
        $this->db->limit($offset + $num, $offset);

        return $this->db->get();
    }
//    function get_all($num = 0, $offset = 0, $where, $order = null) {
//        if ($order == null)
//            $order = "id";
//        //$this->db->select('id','nombre');
//        $this->db->from('categoria');
//        if ($where != "")
//            $this->db->where($where);
//        $this->db->where('deleted', 0);
//        $this->db->order_by($order);
//        $this->db->limit($offset + $num, $offset);
//
//        return $this->db->get();
//    }

    function get_total() {
        $this->db->select("count(*) as total");
        $this->db->from("empleado");
        $this->db->join('persona', 'persona.persona_id=empleado.persona_id');
        $this->db->where('empleado.deleted', 0);
        //$q = $this->db->query($sql);
        // return $this->db->get();
        $query = $this->db->get();
        return $query->row();
    }

    /*
      Gets information about a particular employee
     */

    function get_info($empleado_id) {
        $this->db->from('empleado');
        $this->db->join('persona', 'persona.persona_id = empleado.persona_id');
        $this->db->where('empleado.persona_id', $empleado_id);
        $this->db->where('deleted', 0);
        $query = $this->db->get();

        if ($query->num_rows() == 1) {
            return $query->row();
        } else {
            //Get empty base parent object, as $employee_id is NOT an employee
            $persona_obj = parent::get_info(-1);

            //Get all the fields from employee table
            $fields = $this->db->list_fields('empleado');

            //append those fields to base parent object, we we have a complete empty object
            foreach ($fields as $field) {
                $persona_obj->$field = '';
            }

            return $persona_obj;
        }
    }

    /*
      Gets information about multiple employees
     */

    function get_multiple_info($empleado_ids) {
        $this->db->from('empleado');
        $this->db->join('persona', 'persona.id = empleado.persona_id');
        $this->db->where_in('empleado.persona_id', $empleado_ids);
        $this->db->where('deleted', 0);
        $this->db->order_by("apellido", "asc");
        return $this->db->get();
    }

    function save(&$persona_data, &$empleado_data, &$permiso_data, $empleado_id = false) {
        $success = false;

        //Run these queries as a transaction, we want to make sure we do all or nothing
        $this->db->trans_start();
        try {
            if (parent::save($persona_data, $empleado_id)) {
                if (!$empleado_id or !$this->exists($empleado_id)) {
                    $empleado_data['persona_id'] = $empleado_id = $persona_data['persona_id'];
                    $success = $this->db->insert('empleado', $empleado_data);
                } else {
                    $this->db->where('persona_id', $empleado_id);
                    $success = $this->db->update('empleado', $empleado_data);
                }

                //We have either inserted or updated a new employee, now lets set permissions. 
                if ($success) {
                    //First lets clear out any permissions the employee currently has.
                    $success = $this->db->delete('permiso', array('persona_id' => $empleado_id));

                    //Now insert the new permissions
                    if ($success) {
                        if ($permiso_data <> null) {
                            foreach ($permiso_data as $allowed_module) {
                                $success = $this->db->insert('permiso', array(
                                    'modulo_id' => $allowed_module,
                                    'persona_id' => $empleado_id));
                            }
                        }
                    }
                }
            }

            $this->db->trans_complete();
            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
            } else {
                $this->db->trans_commit();
            }
        } catch (Exception $e) {
            $this->db->trans_off();
        }
        return $success;
    }

    /*
      Deletes one empleado, not implemented yet!
     */

    function delete($employee_id) {
        $success = false;

        //Don't let employee delete their self
        if ($employee_id == $this->get_logged_in_empleado_info()->persona_id)
            return false;

        //Run these queries as a transaction, we want to make sure we do all or nothing
        $this->db->trans_start();
        try {
            //Delete permissions
            if ($this->db->delete('permiso', array('persona_id' => $employee_id))) {
                $this->db->where('persona_id', $employee_id);
                $success = $this->db->update('empleado', array('deleted' => 1));
            }
            $this->db->trans_complete();
            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
            } else {
                $this->db->trans_commit();
            }
        } catch (Exception $e) {
            $this->db->trans_off();
        }
        return $success;
    }

    /*
      Deletes a list of employees
     */

    function delete_list($empleado_ids) {
        $success = false;

        //Don't let employee delete their self
        if (in_array($this->get_logged_in_empleado_info()->persona_id, $empleado_ids))
            return false;

        //Run these queries as a transaction, we want to make sure we do all or nothing
        $this->db->trans_start();
        $this->db->where_in('persona_id', $empleado_ids);
        try {
            //Delete permissions
            if ($this->db->delete('permiso')) {
                //delete from employee table
                $this->db->where_in('persona_id', $empleado_ids);
                $success = $this->db->update('empleado', array('deleted' => 1));
            }
            $this->db->trans_complete();
            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
            } else {
                $this->db->trans_commit();
            }
        } catch (Exception $e) {
            $this->db->trans_off();
        }
        return $success;
    }

    /*
      Get search suggestions to find employees
     */

    function get_search_suggestions($search, $limit = 5) {
        $suggestions = array();

        $this->db->from('empleado');
        $this->db->join('persona', 'empleado.persona_id=persona.persona_id');
        $this->db->where("(nombre LIKE '%" . $this->db->escape_like_str($search) . "%' or 
		apellido LIKE '%" . $this->db->escape_like_str($search) .
                // "%' or 
                // CONCAT('nombre',' ','apellido') LIKE '%".
                "%' or 
		nombre + ' ' + apellido LIKE '%" .
                $this->db->escape_like_str($search) . "%') and deleted=0");

        $this->db->order_by("apellido", "asc");
        $by_name = $this->db->get();
        foreach ($by_name->result() as $row) {
            $suggestions[] = $row->nombre . ' ' . $row->apellido;
        }

        $this->db->from('empleado');
        $this->db->join('persona', 'empleado.persona_id=persona.persona_id');
        $this->db->where('deleted', 0);
        $this->db->like("email", $search);
        $this->db->order_by("email", "asc");
        $by_email = $this->db->get();
        foreach ($by_email->result() as $row) {
            $suggestions[] = $row->email;
        }

        $this->db->from('empleado');
        $this->db->join('persona', 'empleado.persona_id=persona.persona_id');
        $this->db->where('deleted', 0);
        $this->db->like("username", $search);
        $this->db->order_by("username", "asc");
        $by_username = $this->db->get();
        foreach ($by_username->result() as $row) {
            $suggestions[] = $row->username;
        }


        $this->db->from('empleado');
        $this->db->join('persona', 'empleado.persona_id=persona.persona_id');
        $this->db->where('deleted', 0);
        $this->db->like("telefono", $search);
        $this->db->order_by("telefono", "asc");
        $by_phone = $this->db->get();
        foreach ($by_phone->result() as $row) {
            $suggestions[] = $row->telefono;
        }


        //only return $limit suggestions
        if (count($suggestions > $limit)) {
            $suggestions = array_slice($suggestions, 0, $limit);
        }
        return $suggestions;
    }

    /*
      Preform a search on employees
     */

    function search($search) {
        $this->db->from('empleado');
        $this->db->join('persona', 'empleado.persona_id=persona.persona_id');
        $this->db->where("(nombre LIKE '%" . $this->db->escape_like_str($search) . "%' or 
		apellido LIKE '%" . $this->db->escape_like_str($search) . "%' or 
		email LIKE '%" . $this->db->escape_like_str($search) . "%' or 
		telefono LIKE '%" . $this->db->escape_like_str($search) . "%' or 
		username LIKE '%" . $this->db->escape_like_str($search) .
                //"%' or 
                //CONCAT(`nombre`,' ',`apellido`) LIKE '%"
                "%' or 
		nombre + ' ' + apellido LIKE '%"
                . $this->db->escape_like_str($search) . "%') and deleted=0");
        $this->db->order_by("apellido", "asc");

        return $this->db->get();
    }

    /*
      Attempts to login employee and set session. Returns boolean based on outcome.
     */

    function login($username, $password) {
//        $query = $this->db->get_where('empleado', array('usuario' => $username, 'clave' => $password, 'deleted' => 0), 1);
        $query = $this->db->get_where('empleado', array('usuario' => $username, 'clave' => md5($password), 'deleted' => 0), 1);
        if ($query->num_rows() == 1) {
            $row = $query->row();
            $this->session->set_userdata('persona_id', $row->persona_id);
            return true;
        }
        return false;
    }

    /*
      Logs out a user by destorying all session data and redirect to login
     */

    function logout() {
        $this->session->sess_destroy();
        redirect('home');
    }

    /*
      Determins if a employee is logged in
     */

    function is_logged_in() {
        //echo $this->session->userdata('person_id');
        return $this->session->userdata('persona_id') != false;
    }

    /*
      Gets information about the currently logged in employee.
     */

    function get_logged_in_empleado_info() {
        if ($this->is_logged_in()) {
            return $this->get_info($this->session->userdata('persona_id'));
        }

        return false;
    }

    /*
      Determins whether the employee specified employee has access the specific module.
     */

    function has_permission($modulo_id, $persona_id) {
        //if no modulo_id is null, allow access
        if ($modulo_id == null) {
            return true;
        }

        $query = $this->db->get_where('permiso', array('persona_id' => $persona_id, 'modulo_id' => $modulo_id), 1);
        return $query->num_rows() == 1;


        return false;
    }

}

?>