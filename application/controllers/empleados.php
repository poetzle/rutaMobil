<?php

require_once ("persona_controller.php");

class Empleados extends Persona_controller {

    function __construct() {
        parent::__construct('empleados');
    }

    function index() {
//        $data['controller_name'] = strtolower($this->uri->segment(1));
//        $data['form_width'] = $this->get_form_width();
//        $data['manage_table'] = get_people_manage_table($this->Empleado->get_all(), $this);
//        $this->load->view('people/manage', $data);

        $data['controller_name'] = strtolower($this->uri->segment(1));
        $data['admin_table'] = get_persona_admin_table();
        $data['form_width'] = $this->get_form_width();
        $data['form_height'] = $this->get_form_height();
        $this->output->enable_profiler(TRUE);
        $this->load->view('personas/manage', $data);
    }

    /*
      Returns employee table data rows. This will be called with AJAX.
     */

    function search() {
        $search = $this->input->post('search');
        $data_rows = get_people_manage_table_data_rows($this->Employee->search($search), $this);
        echo $data_rows;
    }

    /*
      Gives search suggestions based on what is being searched for
     */

    function suggest() {
        $suggestions = $this->Employee->get_search_suggestions($this->input->post('q'), $this->input->post('limit'));
        echo implode("\n", $suggestions);
    }

    /*
      Loads the employee edit form
     */

    function view($empleado_id = -1) {
        $data['persona_info'] = $this->Empleado->get_info($empleado_id);
        $data['all_modulos'] = $this->Modulo->get_all_modules();
        $this->load->view("empleados/form", $data);
    }

    function mis_datos() {
//		$data['controller_name'] = strtolower($this->uri->segment(1));
//		$data['form_width'] = $this->get_form_width();
//		$data['form_height'] = 150;
//		$aColumns = array('id','nombre', 'descripcion', 'nombre_enlace');
        $aColumns = array(
            'persona_id' => array('checked' => true, 'es_mas' => true),
            'nombre' => array('limit' => 13),
            'apellido' => array('limit' => 30),
            'email' => array('limit' => 15),
            'telefono' => array('limit' => 15)
            );
        //Eventos Tabla
        $cllAccion = array(
            '1' => array(
                'function' => "view",
                'comun_language' => "comun_edit",
                'language' => "_update",
                'width' => $this->get_form_width(),
                'height' => $this->get_form_height(),
        'class'  => 'thickbox'));
        echo getData('Empleado', $aColumns, $cllAccion);
    }
    /*
      Inserts/updates an employee
     */
    function save($id = -1) {
        $persona_data = array(
            'nombre' => $this->input->post('nombre'),
            'apellido' => $this->input->post('apellido'),
            'email' => $this->input->post('email'),
            'telefono' => $this->input->post('telefono'),
            'direccion' => $this->input->post('direccion'),
            'ciudad' => $this->input->post('ciudad'),
            'pais' => $this->input->post('pais'),
            'comentarios' => $this->input->post('comentarios')
        );
        $permiso_data = $this->input->post("permisos") != false ? $this->input->post("permisos") : array();

        //Password has been changed OR first time password set
        if ($this->input->post('clave') != '') {
            $empleado_data = array(
                'usuario' => $this->input->post('usuario'),
                'clave' => md5($this->input->post('clave'))
            );
        } else { //Password not changed
            $empleado_data = array('usuario' => $this->input->post('usuario'));
        }

        if ($_SERVER['HTTP_HOST'] == 'rutasmobiles.demo.com' && $id == 1) {
            //failure
            echo json_encode(array('success' => false, 'message' => $this->lang->line('empleados_error_updating_demo_admin') . ' ' .
                $persona_data['nombre'] . ' ' . $persona_data['apellido'], 'persona_id' => -1));
        } elseif ($this->Empleado->save($persona_data, $empleado_data, $permiso_data, $id)) {
            //New employee
            if ($id == -1) {
                echo json_encode(array('success' => true, 'message' => $this->lang->line('empelados_successful_adding') . ' ' .
                    $persona_data['nombre'] . ' ' . $persona_data['apellido'], 'persona_id' => $empleado_data['persona_id']));
            } else { //previous employee
                echo json_encode(array('success' => true, 'message' => $this->lang->line('empelados_successful_updating') . ' ' .
                    $persona_data['nombre'] . ' ' . $persona_data['apellido'], 'persona_id' => $id));
            }
        } else {//failure
            echo json_encode(array('success' => false, 'message' => $this->lang->line('empelados_error_adding_updating') . ' ' .
                $persona_data['nombre'] . ' ' . $persona_data['apellido'], 'persona_id' => -1));
        }
    }

    /*
      This deletes empelados from the empelados table
     */

    function delete() {
        $empelados_to_delete = $this->input->post('ids');

        if ($_SERVER['HTTP_HOST'] == 'demo.phppointofsale.com' && in_array(1, $empelados_to_delete)) {
            //failure
            echo json_encode(array('success' => false, 'message' => $this->lang->line('empelados_error_deleting_demo_admin')));
        } elseif ($this->Empleado->delete_list($empelados_to_delete)) {
            echo json_encode(array('success' => true, 'message' => $this->lang->line('empelados_successful_deleted') . ' ' .
                count($empelados_to_delete) . ' ' . $this->lang->line('empelados_one_or_multiple')));
        } else {
            echo json_encode(array('success' => false, 'message' => $this->lang->line('empelados_cannot_be_deleted')));
        }
    }

    /**
     * Display form: Import data from an excel file
     * @author: Nguyen OJB, Mario T.
     * @since: 10.1
     */
    function excel_import() {
        $this->load->view("empelados/excel_import", null);
    }

    /**
     * Read data from excel file -> save it to databse
     * @author: Nguyen OJB, Mario T.
     * @since: 10.1
     */
    function do_excel_import() {
        $msg = "do_excel_import";
        $failCodes = null;
        $successCode = null;
        if ($_FILES['file_path']['error'] != UPLOAD_ERR_OK) {
            $msg = $this->lang->line('items_excel_import_failed');
            echo json_encode(array('success' => false, 'message' => $msg));
            return;
        } else {
            try {
                $this->load->library('spreadsheetexcelreader');
                $this->spreadsheetexcelreader->store_extended_info = false;
                $success = $this->spreadsheetexcelreader->read($_FILES['file_path']['tmp_name']);

                $rowCount = $this->spreadsheetexcelreader->rowcount(0);
                if ($rowCount > 2) {
                    for ($i = 3; $i <= $rowCount; $i++) {
                        $user_name = $this->spreadsheetexcelreader->val($i, 'A');
                        $id = $this->Employee->get_employee_id($user_name);
                        if ($id <> false) {
                            $failCodes[] = $this->spreadsheetexcelreader->val($i, 'A');
                            continue;
                        }
                        $employee_data = array(
                            'username' => $user_name,
                            'password' => md5($this->spreadsheetexcelreader->val($i, 'B'))
                        );
                        $person_data = array(
                            'first_name' => $this->spreadsheetexcelreader->val($i, 'C'),
                            'last_name' => $this->spreadsheetexcelreader->val($i, 'D'),
                            'phone_number' => $this->spreadsheetexcelreader->val($i, 'E'),
                            'email' => $this->spreadsheetexcelreader->val($i, 'F'),
                            'address_1' => $this->spreadsheetexcelreader->val($i, 'G'),
                            'address_2' => $this->spreadsheetexcelreader->val($i, 'H'),
                            'city' => $this->spreadsheetexcelreader->val($i, 'I'),
                            'state' => $this->spreadsheetexcelreader->val($i, 'J'),
                            'zip' => $this->spreadsheetexcelreader->val($i, 'K'),
                            'country' => $this->spreadsheetexcelreader->val($i, 'L'),
                            'comments' => $this->spreadsheetexcelreader->val($i, 'M')
                        );
                        $permissions = null;
                        if ($this->Employee->save($person_data, $employee_data, $permissions, false)) {
                            
                        } else {//insert or update item failure
                            $failCodes[] = $this->spreadsheetexcelreader->val($i, 'A');
                            //echo json_encode( array('success'=>true,'message'=>'Your upload file has no data or not in supported format.') );
                        }
                    }
                } else {
                    // rowCount < 2
                    echo json_encode(array('success' => true, 'message' => 'Your upload file has no data or not in supported format.'));
                    return;
                }
            } catch (Exception $e) {
                //echo 'Caught exception: ',  $e->getMessage(), "\n";
                // echo json_encode( array('success'=>false,'message'=>$e->getMessage()) );
                echo json_encode(array('success' => false, 'message' => 'vamos'));
            }
        }

        $success = true;
        if (count($failCodes) > 1) {
            $msg = "Most suppliers imported. But some were not, here is list of their CODE (" . count($failCodes) . "): " . implode(", ", $failCodes);
            $success = false;
        } else {
            $msg = "Import suppliers successful";
        }

        echo json_encode(array('success' => $success, 'message' => $msg));
    }

    /*
      get the width for the add/edit form
     */

    function get_form_width() {
        return 650;
    }
    function get_form_height() {
        return 400;
    }

}

?>